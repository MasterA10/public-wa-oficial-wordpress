<?php
namespace WAS\Templates;

use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateDuplicationService {

    private $repository;

    public function __construct() {
        $this->repository = new TemplateRepository();
    }

    public function duplicate(int $templateId, string $newName): array {
        $tenantId = TenantContext::get_tenant_id();

        $original = $this->repository->findForTenant($templateId, $tenantId);
        if (!$original) {
            return ['success' => false, 'error' => 'Template original não encontrado.'];
        }

        $existing = $this->repository->findByWabaNameLanguage(
            $tenantId, 
            $original->waba_id, 
            $newName, 
            $original->language
        );

        if ($existing) {
            return ['success' => false, 'error' => 'Já existe um template com esse nome.'];
        }

        $newData = [
            'tenant_id'           => $tenantId,
            'whatsapp_account_id' => $original->whatsapp_account_id,
            'waba_id'             => $original->waba_id,
            'name'                => $newName,
            'category'            => $original->category,
            'language'            => $original->language,
            'status'              => 'draft',
            'friendly_payload'    => $original->friendly_payload,
            'variable_map'        => $original->variable_map,
            'header_type'         => $original->header_type,
            'body_text'           => $original->body_text,
            'footer_text'         => $original->footer_text,
            'buttons_json'        => $original->buttons_json,
            'created_at'          => current_time('mysql', 1),
            'updated_at'          => current_time('mysql', 1),
            // meta_template_id and synced_at deliberately left null
        ];

        $newId = $this->repository->create($newData);

        if ($newId) {
            \WAS\Compliance\AuditLogger::log('template_duplicated', 'template', $newId, [
                'original_id' => $templateId,
                'new_name'    => $newName
            ]);
            return ['success' => true, 'new_id' => $newId];
        }

        return ['success' => false, 'error' => 'Erro ao salvar o novo template.'];
    }
}
