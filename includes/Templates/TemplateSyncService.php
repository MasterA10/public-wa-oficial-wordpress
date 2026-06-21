<?php
namespace WAS\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateSyncService {
    private $repository;
    private $meta_service;

    public function __construct() {
        $this->repository = new TemplateRepository();
        $this->meta_service = new TemplateMetaService();
    }

    /**
     * Sincroniza templates da Meta para o banco local.
     */
    public function syncWaba(int $tenantId, ?string $wabaId = null): array {
        $accountRepo = new \WAS\WhatsApp\WhatsAppAccountRepository();
        $account = $accountRepo->findForTenant($tenantId, $wabaId);

        if (!$account || empty($account->waba_id)) {
            return ['success' => false, 'error' => 'WABA ID não configurado para este tenant.'];
        }

        $tokenService = new \WAS\Meta\TokenService();
        $token = $tokenService->get_active_token($tenantId);
        if (!$token) {
            return ['success' => false, 'error' => 'Token não configurado para este tenant.'];
        }

        $response = $this->meta_service->list($account->waba_id, $token);

        if (!isset($response['data'])) {
            \WAS\Core\SystemLogger::logError('Falha ao listar templates da Meta para sincronização.', [
                'tenant_id' => $tenantId,
                'error'     => $response['error'] ?? 'Unknown error'
            ]);
            return ['success' => false, 'error' => $response['error'] ?? 'Unknown error'];
        }

        $summary = [
            'created_local' => 0,
            'updated_local' => 0,
            'unchanged' => 0,
            'errors' => 0,
        ];

        $syncedNames = [];
        foreach (($response['data'] ?? []) as $metaTemplate) {
            try {
                $result = $this->upsertFromMeta($tenantId, $account->id, $account->waba_id, $metaTemplate);
                $summary[$result]++;
                if ($metaTemplate['name']) $syncedNames[] = $metaTemplate['name'];
            } catch (\Throwable $e) {
                $summary['errors']++;
                \WAS\Core\SystemLogger::logException($e, [
                    'tenant_id' => $tenantId,
                    'waba_id' => $account->waba_id,
                    'template' => $metaTemplate['name'] ?? null
                ]);
            }
        }

        // Soft-delete local templates that were not in the Meta response
        $this->cleanupDeletedTemplates($tenantId, $account->waba_id, $syncedNames);

        \WAS\Compliance\AuditLogger::log('template_sync_completed', 'template', 0, [
            'tenant_id'       => $tenantId,
            'waba_id'         => $account->waba_id,
            'summary'         => $summary
        ]);

        return ['success' => true, 'summary' => $summary];
    }

    private function upsertFromMeta(int $tenantId, int $accountId, string $wabaId, array $metaTemplate): string {
        $name = $metaTemplate['name'] ?? '';
        $language = $metaTemplate['language'] ?? '';

        if (!$name || !$language) {
            return 'unchanged';
        }

        // Tenta encontrar o registro ignorando soft-delete para evitar Duplicate Entry
        $existing = $this->repository->find_any_by_name_lang($tenantId, $wabaId, $name, $language);

        $data = [
            'tenant_id' => $tenantId,
            'whatsapp_account_id' => $accountId,
            'waba_id' => $wabaId,
            'meta_template_id' => $metaTemplate['id'] ?? null,
            'name' => $name,
            'language' => $language,
            'category' => $metaTemplate['category'] ?? 'UNKNOWN',
            'status' => $metaTemplate['status'] ?? 'UNKNOWN',
            'components_json' => wp_json_encode($metaTemplate['components'] ?? []),
            'rejection_reason' => $metaTemplate['rejected_reason'] ?? null,
            'meta_payload' => wp_json_encode($metaTemplate),
            'synced_at' => current_time('mysql', 1),
            'updated_at' => current_time('mysql', 1),
            'deleted_at' => null, // Restaura se estava deletado
            'body_text' => ''
        ];

        if (!empty($metaTemplate['components']) && is_array($metaTemplate['components'])) {
            foreach ($metaTemplate['components'] as $comp) {
                if ($comp['type'] === 'BODY') {
                    $data['body_text'] = $comp['text'] ?? '';
                } elseif ($comp['type'] === 'HEADER') {
                    $data['header_type'] = $comp['format'] ?? null;
                } elseif ($comp['type'] === 'FOOTER') {
                    $data['footer_text'] = $comp['text'] ?? '';
                } elseif ($comp['type'] === 'BUTTONS') {
                    $data['buttons_json'] = json_encode($comp['buttons']);
                }
            }
        }

        if (!$existing) {
            $data['created_at'] = current_time('mysql', 1);
            $res = $this->repository->create($data);
            if (!$res) {
                global $wpdb;
                \WAS\Core\SystemLogger::logError('Template insert failed', ['error' => $wpdb->last_error, 'data' => $data]);
            }
            return $res ? 'created_local' : 'errors';
        }

        // Se existir, atualiza (limpa o deleted_at automaticamente se houver)
        $res = $this->repository->update($existing->id, $data);
        if ($res === false) {
            global $wpdb;
            \WAS\Core\SystemLogger::logError('Template update failed', ['error' => $wpdb->last_error, 'data' => $data, 'id' => $existing->id]);
        }
        return $res !== false ? 'updated_local' : 'errors';
    }

    private function cleanupDeletedTemplates(int $tenantId, string $wabaId, array $syncedNames): void {
        global $wpdb;
        $table = $this->repository->getTable();
        
        // Templates locais deste tenant/waba que NÃO estão na lista de sincronizados e NÃO estão deletados ainda
        if (empty($syncedNames)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'DELETED', deleted_at = %s WHERE tenant_id = %d AND waba_id = %s AND deleted_at IS NULL",
                current_time('mysql', 1), $tenantId, $wabaId
            ));
        } else {
            $placeholders = implode(',', array_fill(0, count($syncedNames), '%s'));
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'DELETED', deleted_at = %s WHERE tenant_id = %d AND waba_id = %s AND deleted_at IS NULL AND name NOT IN ($placeholders)",
                array_merge([current_time('mysql', 1), $tenantId, $wabaId], $syncedNames)
            ));
        }
    }
}
