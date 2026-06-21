<?php

namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serviço de Templates (Orquestrador)
 */
class TemplateService {
    public $repository;

    public function __construct(TemplateRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Lista templates locais do tenant
     */
    public function listTemplates($status = null) {
        return $this->repository->listByTenant($status);
    }

    /**
     * Cria um novo template (Local + Meta)
     */
    public function createTemplate(array $data) {
        // 1. Validar e Sanitizar Nome
        $data['name'] = strtolower(preg_replace('/[^a-z0-9_]/', '', $data['name']));
        
        // 2. Converte Friendly para Meta
        $metaPayload = TemplatePayloadBuilder::build($data);

        // 3. Salvar Localmente
        $status = \WAS\Core\Plugin::is_demo_mode() ? 'APPROVED' : 'PENDING';

        $template_id = $this->repository->createOrUpdate([
            'name'             => $data['name'],
            'language'         => $data['language'],
            'category'         => $data['category'],
            'status'           => $status,
            'friendly_payload' => json_encode($data),
            'meta_payload'     => json_encode($metaPayload),
            'body_text'        => $data['body']['text'],
            'header_type'      => $data['header']['type'] ?? 'NONE',
            'footer_text'      => $data['footer']['text'] ?? '',
            'buttons_json'     => json_encode($data['buttons'] ?? [])
        ]);

        // 4. Auditoria
        \WAS\Compliance\AuditLogger::log('create_template', 'template', $template_id, ['name' => $data['name']]);

        // 5. Aqui seria a chamada real para MetaApiClient (Dev 02)
        // MetaApiClient::request('WA_CREATE_TEMPLATE', ['waba_id' => ...], $metaPayload);
        
        return $template_id;
    }

    /**
     * Envia mensagem usando template
     */
    public function sendTemplateMessage($to, $template_id, $components = []) {
        // 0. Check Demo Mode First
        if (\WAS\Core\Plugin::is_demo_mode()) {
            $template = $this->repository->find($template_id);
            if ($template) {
                \WAS\Compliance\AuditLogger::log('send_template', 'contact', 0, ['template_name' => $template->name, 'to' => $to, 'mode' => 'demo']);
            }
            return true;
        }

        $template = $this->repository->find($template_id);
        if (!$template || ($template->status !== 'APPROVED' && $template->status !== 'approved')) {
            throw new \Exception("Template não encontrado ou não aprovado.");
        }

        $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();
        
        // 1. Buscar Número Padrão e Token
        $phone_repo = new \WAS\WhatsApp\PhoneNumberRepository();
        $token_vault = new \WAS\Meta\TokenVault();
        
        $phone = $phone_repo->getDefaultByTenant($tenant_id);
        if (!$phone) throw new \Exception("Nenhum número WhatsApp configurado.");

        $token = $token_vault->get_valid_token($tenant_id, $phone->whatsapp_account_id);
        if (!$token) throw new \Exception("Falha na autenticação com Meta.");

        // 2. Despachar via serviço oficial
        $dispatch = new \WAS\WhatsApp\MessageDispatchService();
        $response = $dispatch->send_template(
            $phone->phone_number_id,
            $to,
            $template->name,
            $template->language,
            $components,
            $token
        );

        if (!$response->success) {
            throw new \Exception("Meta API Error: " . ($response->error['message'] ?? 'Desconhecido'));
        }

        // Auditoria
        \WAS\Compliance\AuditLogger::log('send_template', 'contact', 0, ['template_name' => $template->name, 'to' => $to]);

        return true;
    }
}
