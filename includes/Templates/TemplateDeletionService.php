<?php
namespace WAS\Templates;

use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateDeletionService {

    private $repository;
    private $metaService;
    private $tokenService;
    private $accountRepo;

    public function __construct() {
        $this->repository = new TemplateRepository();
        $this->metaService = new TemplateMetaService();
        $this->tokenService = new \WAS\Meta\TokenService();
        $this->accountRepo = new \WAS\WhatsApp\WhatsAppAccountRepository();
    }

    public function deleteTemplate(int $templateId): array {
        $tenantId = TenantContext::get_tenant_id();
        $template = $this->repository->findForTenant($templateId, $tenantId);

        if (!$template) {
            \WAS\Core\SystemLogger::logWarning('TemplateDeletion: Template não encontrado.', [
                'template_id' => $templateId, 'tenant_id' => $tenantId,
            ]);
            return ['success' => false, 'error' => 'Template não encontrado.'];
        }

        \WAS\Core\SystemLogger::logInfo('TemplateDeletion: Iniciando exclusão.', [
            'template_id' => $templateId,
            'name'        => $template->name,
            'meta_id'     => $template->meta_template_id,
            'status'      => $template->status,
        ]);

        $token = $this->tokenService->get_active_token($tenantId);
        if (!$token) {
            \WAS\Core\SystemLogger::logError('TemplateDeletion: Token não encontrado.', ['tenant_id' => $tenantId]);
            return ['success' => false, 'error' => 'Token não configurado para este tenant.'];
        }

        // Tenta deletar na Meta apenas se o template chegou a existir lá
        $status = strtoupper($template->status);
        $existsOnMeta = !empty($template->meta_template_id) || !in_array($status, ['DRAFT', 'FAILED', 'CREATE_FAILED']);

        if ($existsOnMeta) {
            $metaResponse = null;
            if ($template->meta_template_id) {
                $metaResponse = $this->metaService->deleteById($template->waba_id, $template->name, $template->meta_template_id, $token);
            } else {
                $metaResponse = $this->metaService->deleteByName($template->waba_id, $template->name, $token);
            }

            if (!($metaResponse['success'] ?? false) && !str_contains(strtolower($metaResponse['error'] ?? ''), 'does not exist')) {
                $meta_error = $metaResponse['error'] ?? 'Desconhecido';
                \WAS\Core\SystemLogger::logError('TemplateDeletion: Meta rejeitou exclusão.', [
                    'template_id'   => $templateId,
                    'name'          => $template->name,
                    'meta_error'    => $meta_error,
                    'full_response' => $metaResponse,
                ]);
                \WAS\Compliance\AuditLogger::log('template_delete_failed', 'template', $templateId, [
                    'name'       => $template->name,
                    'meta_error' => $meta_error,
                ]);
                return ['success' => false, 'error' => 'Erro na Meta ao excluir: ' . $meta_error];
            }
        }

        // Executa o soft delete localmente
        $this->repository->softDelete($templateId);

        \WAS\Core\SystemLogger::logInfo('TemplateDeletion: Template excluído com sucesso.', [
            'template_id' => $templateId, 'name' => $template->name,
        ]);
        \WAS\Compliance\AuditLogger::log('template_delete_success', 'template', $templateId, [
            'name' => $template->name,
            'waba_id' => $template->waba_id
        ]);

        return ['success' => true];
    }
}
