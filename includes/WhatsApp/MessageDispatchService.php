<?php

namespace WAS\WhatsApp;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenVault;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço Central de Despacho de Mensagens
 * 
 * Único lugar responsável por enviar mensagens para a Meta.
 */
class MessageDispatchService {

    /**
     * Método principal para envio (usado pela Inbox).
     */
    public function send_message(string $to, string $type, string $content, int $tenant_id) {
        // 0. Verificar Modo Demo Automático
        if (\WAS\Core\Plugin::is_demo_mode()) {
            return [
                'success' => true,
                'wa_message_id' => 'demo_' . time() . '_' . rand(100, 999)
            ];
        }

        // 1. Buscar Token e Phone ID do Tenant
        $phone_repo = new PhoneNumberRepository();
        $token_vault = new TokenVault();
        
        $phone = $phone_repo->getDefaultByTenant($tenant_id);
        if (!$phone) {
            return ['success' => false, 'error' => 'Nenhum número configurado para este tenant.'];
        }

        $token = $token_vault->get_valid_token($tenant_id, $phone->whatsapp_account_id);
        if (!$token) {
            return ['success' => false, 'error' => 'Falha na autenticação com a Meta.'];
        }

        // 2. Despachar conforme tipo
        if ($type === 'text') {
            $response = $this->send_text($phone->phone_number_id, $to, $content, $token);
        } else {
            // No caso de template, 'content' seria o nome do template no fluxo simplificado da inbox
            // Mas no fluxo oficial, usamos send_template_full abaixo
            return ['success' => false, 'error' => 'Tipo de mensagem não suportado via send_message direta.'];
        }

        if ($response->success) {
            return [
                'success' => true,
                'wa_message_id' => $response->data['messages'][0]['id'] ?? 'sent_' . time()
            ];
        }

        return ['success' => false, 'error' => $response->error['message'] ?? 'Erro desconhecido na Meta API.'];
    }

    /**
     * Envia uma mensagem de texto simples.
     * 
     * @param string $phone_number_id ID do número remetente.
     * @param string $to Número de destino (com DDI).
     * @param string $text Conteúdo da mensagem.
     * @param string $token Access Token.
     * @return \WAS\Meta\MetaApiResponse
     */
    public function send_text(string $phone_number_id, string $to, string $text, string $token) {
        if (\WAS\Core\Plugin::is_demo_mode()) {
            return \WAS\Meta\MetaApiResponse::success('WA_SEND_MESSAGE', ['messages' => [['id' => 'demo_text_' . time()]]]);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $text
            ]
        ];

        return MetaApiClient::request('WA_SEND_MESSAGE', ['PHONE_NUMBER_ID' => $phone_number_id], $payload, $token);
    }

    /**
     * Envia um template.
     */
    public function send_template(string $phone_number_id, string $to, string $template_name, string $language_code, array $components, string $token) {
        if (\WAS\Core\Plugin::is_demo_mode()) {
            return \WAS\Meta\MetaApiResponse::success('WA_SEND_MESSAGE', ['messages' => [['id' => 'demo_tpl_' . time()]]]);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $template_name,
                'language' => ['code' => $language_code],
                'components' => $components
            ]
        ];

        return MetaApiClient::request('WA_SEND_MESSAGE', ['PHONE_NUMBER_ID' => $phone_number_id], $payload, $token);
    }
}
