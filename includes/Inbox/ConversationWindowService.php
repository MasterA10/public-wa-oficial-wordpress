<?php
namespace WAS\Inbox;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço responsável por gerenciar a Janela de Atendimento de 24 horas (Customer Service Window).
 * Conforme regras da Meta, mensagens livres só podem ser enviadas dentro dessa janela.
 */
class ConversationWindowService {
    private $repository;

    public function __construct() {
        $this->repository = new ConversationRepository();
    }

    /**
     * Atualiza/Renova a janela de atendimento a partir de uma mensagem inbound do cliente.
     */
    public function refreshFromInboundMessage(int $tenantId, int $conversationId, string $waMessageId, int $timestamp): void {
        // Se o timestamp não for fornecido ou for inválido, usa o atual
        $messageTime = $timestamp > 0 ? (int) $timestamp : time();

        $lastCustomerMessageAt = gmdate('Y-m-d H:i:s', $messageTime);
        $expiresAt = gmdate('Y-m-d H:i:s', $messageTime + (24 * 60 * 60));

        // 1. Validar se a mensagem é realmente mais recente que a última (evita webhooks fora de ordem encurtarem a janela)
        $conversation = $this->repository->findForTenant($conversationId, $tenantId);
        if ($conversation && !empty($conversation->last_customer_message_at)) {
            $currentLast = strtotime($conversation->last_customer_message_at . ' UTC');
            if ($messageTime <= $currentLast) {
                \WAS\Core\SystemLogger::logInfo('WindowService: Renovação ignorada (mensagem duplicada ou antiga).', [
                    'conversation_id' => $conversationId,
                    'current_last'    => $conversation->last_customer_message_at,
                    'new_timestamp'   => $lastCustomerMessageAt
                ]);
                return;
            }
        }

        // 2. Atualizar banco de dados
        \WAS\Auth\TenantContext::set_tenant_id($tenantId);
        
        $result = $this->repository->update($conversationId, [
            'last_customer_message_at'           => $lastCustomerMessageAt,
            'customer_service_window_expires_at' => $expiresAt,
            'customer_service_window_status'     => 'open',
            'last_inbound_wa_message_id'         => $waMessageId,
            'updated_at'                         => current_time('mysql', true)
        ]);

        if (false === $result) {
            \WAS\Core\SystemLogger::logError('WindowService: Falha ao atualizar janela no banco de dados.', [
                'tenant_id'       => $tenantId,
                'conversation_id' => $conversationId,
                'data'            => ['expires_at' => $expiresAt]
            ]);
            return;
        }

        \WAS\Core\SystemLogger::logInfo('WindowService: Janela de atendimento renovada com sucesso.', [
            'tenant_id'       => $tenantId,
            'conversation_id' => $conversationId,
            'message_time'    => $lastCustomerMessageAt,
            'expires_at'      => $expiresAt,
            'wa_message_id'   => $waMessageId
        ]);
    }

    /**
     * Retorna o estado detalhado da janela de uma conversa com auto-correção.
     */
    public function getWindowState(object $conversation, bool $debugLog = false): array {
        $expiresAtStr = $conversation->customer_service_window_expires_at ?? null;
        $lastCustomerAtStr = $conversation->last_customer_message_at ?? null;

        // 1. Auto-correção: Se não temos expiração mas temos última mensagem, calculamos agora
        if (empty($expiresAtStr) && !empty($lastCustomerAtStr)) {
            $lastTime = strtotime($lastCustomerAtStr . ' UTC');
            $expiresAtStr = gmdate('Y-m-d H:i:s', $lastTime + (24 * 60 * 60));
            
            // Tenta atualizar o banco para persistir a correção
            $this->repository->update($conversation->id, [
                'customer_service_window_expires_at' => $expiresAtStr,
                'customer_service_window_status'     => (time() < ($lastTime + 86400)) ? 'open' : 'closed'
            ]);

            if ($debugLog) {
                \WAS\Core\SystemLogger::logInfo('WindowService: Auto-correção aplicada via getWindowState.', [
                    'conversation_id' => $conversation->id,
                    'calculated_expires' => $expiresAtStr
                ]);
            }
        }

        if (empty($expiresAtStr)) {
            if ($debugLog) {
                \WAS\Core\SystemLogger::logInfo('WindowService_Debug: Conversa sem data de expiração e sem histórico de cliente.', [
                    'conversation_id' => $conversation->id ?? 'unknown'
                ]);
            }
            return [
                'status'            => 'closed',
                'is_open'           => false,
                'expires_at'        => null,
                'seconds_remaining' => 0,
                'human_remaining'   => 'expirada',
                'can_send_freeform' => false,
                'must_use_template' => true,
            ];
        }

        $now = time();
        $expiresAt = strtotime($expiresAtStr . ' UTC');
        $remaining = max(0, $expiresAt - $now);

        if ($remaining <= 0) {
            return [
                'status'            => 'closed',
                'is_open'           => false,
                'expires_at'        => $expiresAtStr,
                'seconds_remaining' => 0,
                'human_remaining'   => 'expirada',
                'can_send_freeform' => false,
                'must_use_template' => true,
            ];
        }

        // Se faltar menos de 1 hora, status muda para closing_soon
        $status = $remaining <= (60 * 60) ? 'closing_soon' : 'open';

        $state = [
            'status'            => $status,
            'is_open'           => true,
            'expires_at'        => $expiresAtStr,
            'seconds_remaining' => $remaining,
            'human_remaining'   => $this->formatHumanRemaining($remaining),
            'can_send_freeform' => true,
            'must_use_template' => false,
        ];

        if ($debugLog) {
            \WAS\Core\SystemLogger::logInfo('WindowService_Debug: Cálculo de janela realizado.', [
                'conversation_id' => $conversation->id ?? 'unknown',
                'raw_expires_at'  => $expiresAtStr,
                'time_now_utc'    => gmdate('Y-m-d H:i:s', $now),
                'remaining'       => $remaining,
                'final_status'    => $status
            ]);
        }

        return $state;
    }

    /**
     * Verifica se uma mensagem livre (texto/mídia) pode ser enviada.
     * @throws \RuntimeException
     */
    public function assertCanSendFreeform(int $tenantId, int $conversationId): void {
        $conversation = $this->repository->findForTenant($conversationId, $tenantId);
        if (!$conversation) {
            \WAS\Core\SystemLogger::logError('WindowService: Falha na validação - Conversa não encontrada.', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId
            ]);
            throw new \RuntimeException('Conversa não encontrada.');
        }

        $window = $this->getWindowState($conversation, true);
        
        if (!$window['can_send_freeform']) {
            \WAS\Core\SystemLogger::logWarning('WindowService: Bloqueio de envio - Janela fechada.', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'window_state' => $window
            ]);
            throw new \RuntimeException('A janela de atendimento de 24 horas está fechada. Use um template para retomar o contato.');
        }

        \WAS\Core\SystemLogger::logInfo('WindowService: Validação de envio permitida.', [
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'expires_at' => $window['expires_at'],
            'seconds_remaining' => $window['seconds_remaining']
        ]);
    }

    /**
     * Formata o tempo restante de forma amigável.
     */
    private function formatHumanRemaining(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        }
        return "{$minutes}min";
    }
}
