<?php
namespace WAS\Templates;

use WAS\Meta\MetaApiClient;
use WAS\Meta\TokenService;
use WAS\WhatsApp\PhoneNumberService;
use WAS\Inbox\MessageRepository;
use WAS\Inbox\ContactRepository;
use WAS\Inbox\ConversationRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateSendService {
    private $api_client;
    private $token_service;
    private $message_repo;
    private $contact_repo;
    private $conversation_repo;

    public function __construct() {
        $this->api_client = new MetaApiClient();
        $this->token_service = new TokenService();
        $this->message_repo = new MessageRepository();
        $this->contact_repo = new ContactRepository();
        $this->conversation_repo = new ConversationRepository();
    }

    /**
     * Envia um template aprovado.
     */
    public function send($conversation_id, $template_id, $variables = [], $button_variables = [], $to_phone = null) {
        $tenant_id = TenantContext::get_tenant_id();
        
        $repository = new TemplateRepository();
        $template = $repository->get_by_id($template_id);
        if (!$template) return ['success' => false, 'error' => 'Template não encontrado'];

        $to = $to_phone;
        
        // Resolve conversa se necessário
        if (!$to && $conversation_id) {
            $conversation = $this->conversation_repo->get_by_id($conversation_id);
            if ($conversation) {
                $contact = $this->contact_repo->get_by_id($conversation->contact_id);
                if ($contact) $to = $contact->wa_id;
            }
        }

        if (!$to) return ['success' => false, 'error' => 'Destinatário não informado'];
        $to = preg_replace('/\D/', '', $to);

        $phone_service = new PhoneNumberService();
        $phone_number_id = $phone_service->get_primary_id($tenant_id);
        $token = $this->token_service->get_active_token($tenant_id);

        if (!$phone_number_id || !$token) return ['success' => false, 'error' => 'Configuração de envio incompleta.'];

        if (!$conversation_id && $to) {
            $contact = $this->contact_repo->find_or_create_by_wa_id($to, 'Novo Contato', $to);
            if (!$contact) {
                \WAS\Core\SystemLogger::logError('TemplateSendService: Falha ao encontrar/criar contato.', [
                    'to' => $to,
                    'tenant_id' => $tenant_id,
                ]);
                return ['success' => false, 'error' => 'Não foi possível encontrar ou criar o contato.'];
            }
            
            $conversation = $this->conversation_repo->find_or_create_open_conversation($contact->id, $phone_number_id);
            if (!$conversation) {
                return ['success' => false, 'error' => 'Não foi possível iniciar uma conversa para o envio.'];
            }
            $conversation_id = $conversation->id;
        }

        // Mapa de variáveis
        $variable_map = [];
        try { if ($template->variable_map) $variable_map = json_decode($template->variable_map, true); } catch (\Exception $e) {}

        // Montar componentes e renderizar prévia
        $components = [];
        $rendered_body = $template->body_text;
        $rendered_header = $template->header_type === 'TEXT' ? $template->header_text : '';
        $rendered_footer = $template->footer_text;

        // 1. Processa variáveis do BODY
        if (!empty($variable_map)) {
            $body_params = [];
            ksort($variable_map);
            foreach ($variable_map as $pos => $friendlyName) {
                // Tenta pegar pelo nome amigável ou pela posição
                $val = $variables[$friendlyName] ?? $variables[$pos] ?? ''; 
                $body_params[] = ['type' => 'text', 'text' => (string)$val];
                $rendered_body = str_replace("{{{$friendlyName}}}", (string)$val, $rendered_body);
            }
            if (!empty($body_params)) {
                $components[] = ['type' => 'body', 'parameters' => $body_params];
            }
        }

        // 2. Processa variáveis de BOTÕES (URL Dinâmica)
        $buttons = [];
        try { if ($template->buttons_json) $buttons = json_decode($template->buttons_json, true); } catch (\Exception $e) {}
        
        if (!empty($buttons)) {
            $button_index = 0;
            foreach ($buttons as $btn) {
                if (($btn['type'] ?? '') === 'URL' && strpos($btn['url'] ?? '', '{{') !== false) {
                    // Identifica o nome da variável na URL do botão. Ex: domain.com/{{var}}
                    preg_match('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $btn['url'], $matches);
                    $varName = $matches[1] ?? null;
                    
                    if ($varName) {
                        $val = $variables[$varName] ?? $button_variables[$button_index] ?? '';
                        $components[] = [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => (string)$button_index,
                            'parameters' => [
                                ['type' => 'text', 'text' => (string)$val]
                            ]
                        ];
                    }
                }
                // Apenas botões do tipo URL podem ter variáveis na Meta API estável atual
                $button_index++;
            }
        }

        // 3. Processa variáveis do HEADER (Se houver mídia)
        if (in_array($template->header_type, ['IMAGE', 'VIDEO', 'DOCUMENT'])) {
            // Se o template exige mídia no cabeçalho, ela deve ser passada como parâmetro
            // Para simplificar, se houver um header_handle ou media_id nas variáveis:
            $media_id = $variables['header_media_id'] ?? $variables['media_id'] ?? null;
            if ($media_id) {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => strtolower($template->header_type),
                            strtolower($template->header_type) => ['id' => $media_id]
                        ]
                    ]
                ];
            }
        }

        // 4. Decisão de Payload (Regular vs Autenticação)
        if ($template->category === 'AUTHENTICATION') {
            $auth_builder = new AuthenticationTemplateSendPayloadBuilder();
            
            // Em autenticação, esperamos um código. 
            // O frontend pode mandar como 'code', 'código' ou no primeiro índice.
            $code = $variables['código'] ?? $variables['code'] ?? null;
            if (!$code && !empty($variables)) {
                $code = reset($variables);
            }

            if (!$code) {
                return ['success' => false, 'error' => 'Código de autenticação não fornecido.'];
            }

            // Atualmente suportamos COPY_CODE
            if ($template->otp_type === 'COPY_CODE') {
                $payload = $auth_builder->build_copy_code_payload($to, $template->name, $template->language, $code);
                $rendered_body = str_replace('{{1}}', $code, $template->body_text);
                $components = $payload['template']['components'];
            } else {
                return ['success' => false, 'error' => 'Tipo de OTP não suportado para envio automático: ' . $template->otp_type];
            }
        } else {
            // Fluxo Regular (MARKETING/UTILITY)
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template->name,
                    'language' => ['code' => $template->language],
                    'components' => $components
                ]
            ];
        }

        \WAS\Core\SystemLogger::logInfo('TemplateSendService: Enviando payload para Meta.', [
            'template' => $template->name,
            'category' => $template->category,
            'to' => $to,
            'components_count' => count($components)
        ]);

        $response = $this->api_client->postJson('messages.send', ['phone_number_id' => $phone_number_id], $payload, $token);

        if ($response['success']) {
            $wa_message_id = $response['messages'][0]['id'] ?? null;

            // Aprendizado: Se a Meta devolveu um wa_id (canônico), atualizamos o contato
            if (!empty($response['contacts'][0]['wa_id'])) {
                $confirmed_wa_id = $response['contacts'][0]['wa_id'];
                $contact = $this->contact_repo->get_by_id($this->conversation_repo->get_by_id($conversation_id)->contact_id);
                if ($contact && $confirmed_wa_id !== $contact->wa_id) {
                    $this->contact_repo->confirm_wa_id($contact->id, $confirmed_wa_id, 'confirmed_by_meta_response');
                }
            }

            // Salva um snapshot renderizado para o Inbox
            $history_snapshot = [
                'name' => $template->name,
                'header' => $rendered_header,
                'body' => $rendered_body,
                'footer' => $rendered_footer,
                'buttons' => json_decode($template->buttons_json, true) ?: []
            ];

            $this->message_repo->create_outbound([
                'conversation_id' => $conversation_id,
                'wa_message_id'   => $response['messages'][0]['id'] ?? null,
                'message_type'    => 'template',
                'text_body'       => $rendered_body, 
                'status'          => 'sent',
                'raw_payload'     => json_encode($history_snapshot), // Salva o snapshot amigável
                'tenant_id'       => $tenant_id
            ]);

            $this->conversation_repo->update_last_message_at($conversation_id);

            \WAS\Core\SystemLogger::logInfo('WindowService: Template enviado. A janela de 24h permanece inalterada (Regra da Meta: apenas mensagens do cliente abrem a janela).', [
                'conversation_id' => $conversation_id,
                'template_name' => $template->name
            ]);

            return [
                'success' => true, 
                'wa_message_id' => $response['messages'][0]['id'],
                'conversation_id' => $conversation_id,
                'rendered_body' => $rendered_body,
                'rendered_header' => $rendered_header,
                'rendered_footer' => $rendered_footer,
                'buttons' => $history_snapshot['buttons']
            ];
        }

        return $response;
    }
}
