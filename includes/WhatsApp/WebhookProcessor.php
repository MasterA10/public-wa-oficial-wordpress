<?php
namespace WAS\WhatsApp;

use WAS\Core\TableNameResolver;
use WAS\Inbox\InboundMessageService;
use WAS\Inbox\ContactRepository;
use WAS\Inbox\ConversationRepository;
use WAS\Inbox\MessageRepository;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookProcessor {
    /**
     * Processa o payload bruto do webhook.
     */
    public function process($payload) {
        global $wpdb;

        // 1. Identificar IDs básicos
        $phone_number_id = $this->extract_phone_number_id($payload);
        $waba_id = $payload['entry'][0]['id'] ?? null;

        // 2. Resolver Tenant pelo phone_number_id ou waba_id
        $tenant_id = $this->resolve_tenant($phone_number_id, $waba_id);

        if (!$tenant_id) {
            \WAS\Core\SystemLogger::logError('Falha ao resolver tenant no webhook', ['waba_id' => $waba_id, 'phone' => $phone_number_id]);
            return false;
        }

        // 3. Salvar evento bruto
        $event_table = TableNameResolver::get_table_name('webhook_events');
        $wpdb->insert($event_table, [
            'tenant_id'       => $tenant_id,
            'waba_id'         => $waba_id,
            'phone_number_id' => $phone_number_id,
            'payload'         => json_encode($payload),
            'processing_status' => 'pending',
            'received_at'     => current_time('mysql', 1)
        ]);
        $event_id = $wpdb->insert_id;

        // 4. Se for mensagem, rotear para InboundMessageService
        if ($this->is_message_event($payload)) {
            try {
                $success = $this->handle_message_event($payload, $tenant_id, $phone_number_id, $event_id);
                if (!$success) {
                    \WAS\Core\SystemLogger::logError('InboundMessageService retornou false ao processar mensagem.', [
                        'payload_summary' => ['waba_id' => $waba_id, 'phone' => $phone_number_id]
                    ]);
                }
            } catch (\Throwable $e) {
                \WAS\Core\SystemLogger::logException($e, [
                    'context'         => 'WebhookProcessor::process',
                    'tenant_id'       => $tenant_id,
                    'phone_number_id' => $phone_number_id,
                    'event_id'        => $event_id
                ]);
            }
        } elseif ($this->is_status_event($payload)) {
            try {
                $this->handle_status_event($payload, $tenant_id, $event_id);
            } catch (\Throwable $e) {
                \WAS\Core\SystemLogger::logException($e, [
                    'context'         => 'WebhookProcessor::status_update',
                    'tenant_id'       => $tenant_id,
                    'event_id'        => $event_id
                ]);
            }
        } elseif ($this->is_template_status_event($payload)) {
            try {
                $this->handle_template_status_event($payload, $tenant_id, $waba_id);
            } catch (\Throwable $e) {
                \WAS\Core\SystemLogger::logException($e, [
                    'context'         => 'WebhookProcessor::template_status',
                    'tenant_id'       => $tenant_id,
                    'event_id'        => $event_id
                ]);
            }
        }

        // Marcar como processado
        $wpdb->update($event_table, 
            ['processing_status' => 'processed', 'processed_at' => current_time('mysql', 1)],
            ['id' => $event_id]
        );

        return true;
    }

    private function extract_phone_number_id($payload) {
        return $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;
    }

    private function resolve_tenant($phone_number_id, $waba_id) {
        global $wpdb;
        
        if ($phone_number_id) {
            $table = TableNameResolver::get_table_name('whatsapp_phone_numbers');
            $tenant = $wpdb->get_var($wpdb->prepare("SELECT tenant_id FROM $table WHERE phone_number_id = %s LIMIT 1", $phone_number_id));
            if ($tenant) return $tenant;
        }

        if ($waba_id) {
            $table = TableNameResolver::get_table_name('whatsapp_accounts');
            $tenant = $wpdb->get_var($wpdb->prepare("SELECT tenant_id FROM $table WHERE waba_id = %s LIMIT 1", $waba_id));
            if ($tenant) return $tenant;
        }

        return null;
    }

    private function is_message_event($payload) {
        return isset($payload['entry'][0]['changes'][0]['value']['messages']);
    }

    private function is_status_event($payload) {
        return isset($payload['entry'][0]['changes'][0]['value']['statuses']);
    }

    private function is_template_status_event($payload) {
        return isset($payload['entry'][0]['changes'][0]['field']) &&
               $payload['entry'][0]['changes'][0]['field'] === 'message_template_status_update';
    }

    private function handle_template_status_event($payload, $tenant_id, $waba_id) {
        $value = $payload['entry'][0]['changes'][0]['value'];
        $meta_template_id = $value['message_template_id'] ?? null;
        $name = $value['message_template_name'] ?? null;
        $language = $value['message_template_language'] ?? null;
        $status = strtoupper($value['event'] ?? '');
        $reason = $value['reason'] ?? null;

        if (!$name || !$language) return false;

        $repository = new \WAS\Templates\TemplateRepository();
        $existing = $repository->findByWabaNameLanguage($tenant_id, $waba_id, $name, $language);

        if ($existing) {
            $update_data = [
                'status' => $status,
                'updated_at' => current_time('mysql', 1)
            ];
            if ($meta_template_id) {
                $update_data['meta_template_id'] = $meta_template_id;
            }
            if ($reason) {
                $update_data['rejection_reason'] = $reason;
            }

            $repository->update($existing->id, $update_data);

            \WAS\Compliance\AuditLogger::log('template_status_webhook', 'template', $existing->id, [
                'new_status' => $status,
                'reason' => $reason
            ]);
        }
        
        return true;
    }

    private function handle_message_event($payload, $tenant_id, $phone_number_id, $event_id) {
        $value = $payload['entry'][0]['changes'][0]['value'];
        $message = $value['messages'][0];
        $contact = $value['contacts'][0] ?? [];

        // Normalizar mensagem usando o novo serviço centralizado
        $normalizer = new \WAS\WhatsApp\WhatsAppInboundMessageNormalizer();
        $normalized = $normalizer->normalize($message);

        // Mesclar com dados fixos do evento
        $dto = array_merge($normalized, [
            'tenant_id'       => $tenant_id,
            'phone_number_id' => $phone_number_id,
            'wa_message_id'   => $message['id'],
            'from'            => $message['from'],
            'profile_name'    => $contact['profile']['name'] ?? '',
            'timestamp'       => $message['timestamp'],
            'raw_event_id'    => $event_id,
            'raw_message'     => $message,
        ]);

        // Injetar dependências manualmente para o serviço
        $service = new InboundMessageService(
            new ContactRepository(),
            new ConversationRepository(),
            new MessageRepository()
        );

        // 1. Processar identidade do contato (Nome de Perfil vs Nome de Exibição)
        if (!empty($value['contacts'][0])) {
            $contact_service = new \WAS\Inbox\ContactService();
            $contact_service->upsertNameFromWebhook($tenant_id, $value['contacts'][0]);
        }

        return $service->handle($dto);
    }

    private function handle_status_event($payload, $tenant_id, $event_id) {
        $value = $payload['entry'][0]['changes'][0]['value'];
        $status_item = $value['statuses'][0];
        $wa_message_id = $status_item['id'];
        $status = $status_item['status']; // sent, delivered, read, failed, deleted

        $message_repo = new MessageRepository();
        return $message_repo->update_status($wa_message_id, $status);
    }
}
