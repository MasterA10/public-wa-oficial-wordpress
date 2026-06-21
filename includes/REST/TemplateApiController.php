<?php
namespace WAS\REST;

use WAS\Templates\TemplateRepository;
use WAS\Templates\TemplatePayloadBuilder;
use WAS\Templates\TemplateMetaService;
use WAS\Auth\TenantContext;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateApiController {
    private $repository;
    private $builder;

    public function __construct() {
        $this->repository = new TemplateRepository();
        $this->builder = new TemplatePayloadBuilder();
    }

    /**
     * Lista templates do tenant.
     */
    public function get_items(WP_REST_Request $request) {
        $templates = $this->repository->list_templates();
        return new WP_REST_Response($templates, 200);
    }

    /**
     * Cria um novo template na Meta e salva localmente.
     */
    public function create_item(WP_REST_Request $request) {
        $params = $request->get_json_params();

        \WAS\Core\SystemLogger::logInfo('TemplateAPI: Requisição de criação recebida.', [
            'name'     => $params['name'] ?? null,
            'category' => $params['category'] ?? null,
            'language' => $params['language'] ?? null,
            'has_body' => !empty($params['body']['text']),
            'has_header' => !empty($params['header']),
            'has_footer' => !empty($params['footer']),
            'buttons_count' => count($params['buttons'] ?? []),
            'user_id'  => get_current_user_id(),
        ]);

        $is_auth = ($params['category'] ?? '') === 'AUTHENTICATION';
        if (empty($params['name']) || (!$is_auth && empty($params['body']['text']))) {
            \WAS\Core\SystemLogger::logWarning('TemplateAPI: Criação rejeitada — campos obrigatórios ausentes.', [
                'has_name' => !empty($params['name']),
                'has_body' => !empty($params['body']['text']),
                'is_auth'  => $is_auth
            ]);
            return new WP_REST_Response(['message' => 'Nome e conteúdo do corpo são obrigatórios'], 400);
        }

        try {
            $build_result = $this->builder->build($params);
            $meta_payload = $build_result['meta_payload'];
            $variable_map = $build_result['variable_map'];
            \WAS\Core\SystemLogger::logInfo('TemplateAPI: Payload Meta construído com sucesso.', [
                'payload_name'   => $meta_payload['name'] ?? null,
                'components'     => count($meta_payload['components'] ?? []),
                'variables_count' => count($variable_map),
            ]);
        } catch (\Exception $e) {
            \WAS\Core\SystemLogger::logError('TemplateAPI: Falha ao construir payload Meta.', [
                'error'   => $e->getMessage(),
                'name'    => $params['name'] ?? null,
            ]);
            return new WP_REST_Response(['message' => $e->getMessage()], 400);
        }

        $tenant_id = TenantContext::get_tenant_id();
        $account_repo = new \WAS\WhatsApp\WhatsAppAccountRepository();
        $account = $account_repo->getByTenant($tenant_id)[0] ?? null;

        if (!$account || empty($account->waba_id)) {
            \WAS\Core\SystemLogger::logError('TemplateAPI: WABA ID não configurado.', ['tenant_id' => $tenant_id]);
            return new WP_REST_Response(['message' => 'WABA ID não configurado para o tenant.'], 400);
        }

        $token_service = new \WAS\Meta\TokenService();
        $token = $token_service->get_active_token($tenant_id);

        if (!$token) {
            \WAS\Core\SystemLogger::logError('TemplateAPI: Token não encontrado.', ['tenant_id' => $tenant_id]);
            return new WP_REST_Response(['message' => 'Token não configurado para o tenant.'], 400);
        }

        $is_auth = strtoupper($params['category'] ?? '') === 'AUTHENTICATION';
        $body_text = $params['body']['text'] ?? 'Template';

        if ($is_auth) {
            $body_text = 'Seu código é {{1}}.';
            if (!empty($params['authentication']['add_security_recommendation'])) {
                $body_text .= ' Por segurança, não compartilhe este código.';
            }
        }

        $create_data = [
            'waba_id'             => $account->waba_id,
            'whatsapp_account_id' => $account->id,
            'name'                => $params['name'],
            'category'            => $params['category'],
            'language'            => $params['language'],
            'body_text'           => $body_text,
            'status'              => 'submitting',
            'friendly_payload'    => json_encode($params),
            'variable_map'        => json_encode($variable_map)
        ];

        // Se for autenticação, salvar campos específicos no banco
        if ($is_auth && !empty($params['authentication'])) {
            $auth = $params['authentication'];
            $create_data['template_family'] = 'authentication';
            $create_data['authentication_type'] = strtolower($auth['type'] ?? 'copy_code');
            $create_data['otp_type'] = strtoupper($auth['type'] ?? 'COPY_CODE');
            $create_data['code_expiration_minutes'] = $auth['code_expiration_minutes'] ?? 10;
            $create_data['add_security_recommendation'] = isset($auth['add_security_recommendation']) ? (int)$auth['add_security_recommendation'] : 1;
            
            if ($create_data['otp_type'] !== 'COPY_CODE') {
                $create_data['package_name'] = $auth['package_name'] ?? null;
                $create_data['signature_hash'] = $auth['signature_hash'] ?? null;
                $create_data['autofill_text'] = $auth['autofill_text'] ?? null;
                $create_data['zero_tap_terms_accepted'] = isset($auth['zero_tap_terms_accepted']) ? (int)$auth['zero_tap_terms_accepted'] : 0;
            }
        }

        $local_id = $this->repository->create($create_data);

        \WAS\Core\SystemLogger::logInfo('TemplateAPI: Template salvo localmente (submitting).', [
            'local_id' => $local_id,
            'waba_id'  => $account->waba_id,
        ]);

        try {
            $meta_service = new TemplateMetaService();
            \WAS\Core\SystemLogger::logInfo('TemplateAPI: Enviando payload para Meta Graph API...', [
                'local_id' => $local_id,
                'waba_id'  => $account->waba_id,
                'payload'  => $meta_payload,
            ]);
            $response = $meta_service->create($account->waba_id, $meta_payload, $token);

            if ($response['success'] ?? false) {
                $this->repository->update($local_id, [
                    'meta_template_id' => $response['id'] ?? null,
                    'status'           => 'PENDING',
                    'meta_payload'     => json_encode($meta_payload)
                ]);

                \WAS\Core\SystemLogger::logInfo('TemplateAPI: Template criado na Meta com sucesso.', [
                    'local_id' => $local_id,
                    'meta_id'  => $response['id'] ?? null,
                    'status'   => 'PENDING',
                ]);

                \WAS\Compliance\AuditLogger::log('template_create_success', 'template', $local_id, [
                    'name'      => $meta_payload['name'],
                    'waba_id'   => $account->waba_id,
                    'meta_id'   => $response['id'] ?? null
                ]);

                return new WP_REST_Response(['success' => true, 'id' => $local_id, 'meta_id' => $response['id'] ?? null], 201);
            }

            $meta_error = $response['error'] ?? 'Erro desconhecido';
            $meta_error_code = $response['code'] ?? null;
            $meta_error_subcode = $response['error_subcode'] ?? null;

            \WAS\Core\SystemLogger::logError('TemplateAPI: Meta REJEITOU a criação do template.', [
                'local_id'       => $local_id,
                'meta_error'     => $meta_error,
                'meta_error_code' => $meta_error_code,
                'meta_error_subcode' => $meta_error_subcode,
                'full_response'  => $response,
                'sent_payload'   => $meta_payload,
            ]);

            \WAS\Compliance\AuditLogger::log('template_create_failed', 'template', $local_id, [
                'name'       => $meta_payload['name'],
                'waba_id'    => $account->waba_id,
                'meta_error' => $meta_error,
                'error_code' => $meta_error_code,
            ]);

            $this->repository->update($local_id, [
                'status' => 'FAILED',
                'last_meta_error' => $meta_error
            ]);

            return new WP_REST_Response(['message' => 'Erro na Meta: ' . $meta_error], 400);

        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'  => 'TemplateApiController::create_item',
                'local_id' => $local_id,
                'payload'  => $meta_payload ?? null,
            ]);
            return new WP_REST_Response(['message' => 'Erro interno ao criar template.'], 500);
        }
    }

    public function get_item(WP_REST_Request $request) {
        $id = $request['id'];
        $template = $this->repository->get_by_id($id);
        if (!$template) {
            return new WP_REST_Error('not_found', 'Template não encontrado', ['status' => 404]);
        }
        return new WP_REST_Response($template, 200);
    }

    public function update_item(WP_REST_Request $request) {
        $id = $request['id'];
        $params = $request->get_json_params();

        \WAS\Core\SystemLogger::logInfo('TemplateAPI: Requisição de atualização recebida.', [
            'local_id' => $id,
            'name'     => $params['name'] ?? null,
        ]);
        
        try {
            $template = $this->repository->get_by_id($id);
            if (!$template) {
                return new WP_REST_Error('not_found', 'Template não encontrado', ['status' => 404]);
            }

            $policyService = new \WAS\Templates\TemplatePolicyService();
            if ($policyService->shouldBlockEdit($template)) {
                \WAS\Core\SystemLogger::logWarning('TemplateAPI: Edição bloqueada por política.', [
                    'local_id' => $id,
                    'status'   => $template->status,
                ]);
                return new WP_REST_Response(['message' => 'Este template não pode ser editado no estado atual. Considere duplicá-lo.'], 403);
            }

            $build_result = $this->builder->build($params);
            $meta_payload = $build_result['meta_payload'];
            $variable_map = $build_result['variable_map'];

            $tenant_id = TenantContext::get_tenant_id();
            $token_service = new \WAS\Meta\TokenService();
            $token = $token_service->get_active_token($tenant_id);

            if ($template->meta_template_id && $token) {
                $meta_service = new TemplateMetaService();
                \WAS\Core\SystemLogger::logInfo('TemplateAPI: Enviando atualização para Meta...', [
                    'local_id'    => $id,
                    'meta_tpl_id' => $template->meta_template_id,
                ]);
                $response = $meta_service->update($template->meta_template_id, $meta_payload, $token);
                if (!($response['success'] ?? false)) {
                    $meta_error = $response['error'] ?? 'Desconhecido';
                    \WAS\Core\SystemLogger::logError('TemplateAPI: Meta REJEITOU atualização.', [
                        'local_id'      => $id,
                        'meta_error'    => $meta_error,
                        'full_response' => $response,
                        'sent_payload'  => $meta_payload,
                    ]);
                    \WAS\Compliance\AuditLogger::log('template_update_failed', 'template', $id, [
                        'meta_error' => $meta_error,
                    ]);
                    return new WP_REST_Response(['message' => 'Erro na Meta ao atualizar: ' . $meta_error], 400);
                }
            }

            $update_data = [
                'name' => $params['name'],
                'category' => $params['category'],
                'language' => $params['language'],
                'body_text' => $params['body']['text'],
                'friendly_payload' => json_encode($params),
                'variable_map' => json_encode($variable_map),
                'meta_payload' => json_encode($meta_payload)
            ];

            $this->repository->update($id, $update_data);
            
            \WAS\Core\SystemLogger::logInfo('TemplateAPI: Template atualizado com sucesso.', ['local_id' => $id]);
            \WAS\Compliance\AuditLogger::log('template_update_success', 'template', $id, [
                'name' => $meta_payload['name'],
                'meta_id' => $template->meta_template_id
            ]);

            return new WP_REST_Response(['success' => true], 200);

        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::update_item', 'local_id' => $id, 'payload' => $meta_payload ?? null]);
            return new WP_REST_Response(['message' => 'Erro interno ao atualizar template.'], 500);
        }
    }

    public function delete_item(WP_REST_Request $request) {
        $id = (int) $request['id'];
        
        try {
            $deletionService = new \WAS\Templates\TemplateDeletionService();
            $result = $deletionService->deleteTemplate($id);

            if ($result['success'] ?? false) {
                return new WP_REST_Response(['success' => true], 200);
            }
            return new WP_REST_Response(['message' => $result['error'] ?? 'Erro ao excluir.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::delete_item', 'local_id' => $id]);
            return new WP_REST_Response(['message' => 'Erro interno ao excluir template.'], 500);
        }
    }

    public function duplicate_item(WP_REST_Request $request) {
        $id = (int) $request['id'];
        $params = $request->get_json_params();
        $newName = $params['new_name'] ?? '';

        if (!$newName || !preg_match('/^[a-z0-9_]+$/', $newName)) {
            return new WP_REST_Response(['message' => 'Nome inválido. Use apenas minúsculas e underscores.'], 400);
        }

        try {
            $duplicateService = new \WAS\Templates\TemplateDuplicationService();
            $result = $duplicateService->duplicate($id, $newName);

            if ($result['success'] ?? false) {
                return new WP_REST_Response($result, 201);
            }
            return new WP_REST_Response(['message' => $result['error'] ?? 'Erro ao duplicar.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::duplicate_item', 'local_id' => $id]);
            return new WP_REST_Response(['message' => 'Erro interno ao duplicar template.'], 500);
        }
    }

    public function sync_templates(WP_REST_Request $request) {
        $tenant_id = TenantContext::get_tenant_id();
        $waba_id = $request->get_param('waba_id');
        try {
            $sync_service = new \WAS\Templates\TemplateSyncService();
            $result = $sync_service->syncWaba($tenant_id, $waba_id);

            if ($result['success'] ?? false) {
                return new WP_REST_Response($result, 200);
            }

            return new WP_REST_Response(['message' => $result['error'] ?? 'Erro ao sincronizar da Meta.'], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'TemplateApiController::sync_templates']);
            return new WP_REST_Response(['message' => 'Erro interno do sistema ao sincronizar templates.'], 500);
        }
    }

    public function send_template(WP_REST_Request $request) {
        $id = $request['id'];
        $conversation_id = $request->get_param('conversation_id');
        $to_phone = $request->get_param('to_phone');
        $variables = $request->get_param('variables') ?? [];
        $button_variables = $request->get_param('button_variables') ?? [];

        \WAS\Core\SystemLogger::logInfo('TemplateAPI: Requisição de envio de template recebida.', [
            'template_id'     => $id,
            'conversation_id' => $conversation_id,
            'to_phone'        => $to_phone,
            'variables_count' => count($variables),
            'user_id'         => get_current_user_id(),
        ]);

        try {
            $send_service = new \WAS\Templates\TemplateSendService();
            $result = $send_service->send($conversation_id, $id, $variables, $button_variables, $to_phone);

            if ($result['success'] ?? false) {
                \WAS\Core\SystemLogger::logInfo('TemplateAPI: Template enviado com sucesso.', [
                    'template_id'     => $id,
                    'wa_message_id'   => $result['wa_message_id'] ?? null,
                    'conversation_id' => $result['conversation_id'] ?? $conversation_id,
                ]);
                \WAS\Compliance\AuditLogger::log('template_send_success', 'template', $id, [
                    'conversation_id' => $conversation_id,
                    'to_phone'        => $to_phone,
                    'wa_message_id'   => $result['wa_message_id'] ?? null
                ]);
                return new WP_REST_Response([
                    'success'         => true, 
                    'wa_message_id'   => $result['wa_message_id'] ?? null,
                    'conversation_id' => $result['conversation_id'] ?? $conversation_id,
                    'rendered_header' => $result['rendered_header'] ?? '',
                    'rendered_body'   => $result['rendered_body'] ?? '',
                    'rendered_footer' => $result['rendered_footer'] ?? '',
                    'buttons'         => $result['buttons'] ?? []
                ], 200);
            }

            $send_error = $result['error'] ?? 'Erro ao enviar template.';
            \WAS\Core\SystemLogger::logError('TemplateAPI: Falha ao enviar template.', [
                'template_id'     => $id,
                'error'           => $send_error,
                'full_result'     => $result,
                'conversation_id' => $conversation_id,
                'to_phone'        => $to_phone,
            ]);
            \WAS\Compliance\AuditLogger::log('template_send_failed', 'template', $id, [
                'error'    => $send_error,
                'to_phone' => $to_phone,
            ]);
            return new WP_REST_Response(['message' => $send_error], 400);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'         => 'TemplateApiController::send_template',
                'local_id'        => $id,
                'conversation_id' => $conversation_id,
                'to_phone'        => $to_phone,
            ]);
            return new WP_REST_Response(['message' => 'Erro interno do sistema ao enviar template.'], 500);
        }
    }

    public function permissions_check() {
        return current_user_can('manage_options');
    }
}
