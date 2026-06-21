<?php
namespace WAS\REST;

use WAS\Inbox\ConversationRepository;
use WAS\Inbox\MessageRepository;
use WAS\Auth\TenantContext;

if (!defined('ABSPATH')) {
    exit;
}

class InboxApiController {
    private $conversation_repo;
    private $message_repo;

    public function __construct() {
        $this->conversation_repo = new ConversationRepository();
        $this->message_repo = new MessageRepository();
    }

    public function register_routes() {
        register_rest_route(WAS_REST_NAMESPACE, '/conversations', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_conversations'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_conversation_detail'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/messages', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'send_message'],
                'permission_callback' => [$this, 'check_send_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/assign', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'assign_conversation'],
                'permission_callback' => [$this, 'check_assignment_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/messages/text', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'send_text_message'],
                'permission_callback' => [$this, 'check_send_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/messages/(?P<media_type>image|audio|document|video)', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'send_media_message'],
                'permission_callback' => [$this, 'check_send_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/poll', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'poll_new_messages'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/typing', [
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'send_typing_indicator'],
                'permission_callback' => [$this, 'check_send_permission'],
            ],
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/conversations/(?P<id>\d+)/window', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_window_status'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    /**
     * Retorna o status da janela de atendimento de 24 horas.
     */
    public function get_window_status($request) {
        $id = $request['id'];
        $conversation = $this->conversation_repo->get_by_id($id);

        if (!$conversation) {
            return new \WP_REST_Response(['message' => 'Conversa não encontrada'], 404);
        }

        $window_service = new \WAS\Inbox\ConversationWindowService();
        $state = $window_service->getWindowState($conversation);

        return new \WP_REST_Response($state, 200);
    }

    public function check_permission() {
        return current_user_can('was_view_inbox');
    }

    /**
     * Envia uma mensagem de texto em uma conversa.
     */
    public function send_text_message($request) {
        $id   = $request['id'];
        $text = $request->get_param('text');
        $reply_to = $request->get_param('reply_to_message_id');

        \WAS\Core\SystemLogger::logInfo('InboxAPI: Requisição de envio de texto recebida.', [
            'conversation_id'      => $id,
            'text_length'          => strlen($text ?: ''),
            'reply_to_message_id'  => $reply_to,
            'user_id'              => get_current_user_id(),
        ]);

        if (empty($text)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'O texto da mensagem é obrigatório'], 400);
        }

        try {
            $service = new \WAS\Inbox\OutboundMessageService();
            $result = $service->send_text($id, $text, $reply_to);

            if ($result['success']) {
                \WAS\Core\SystemLogger::logInfo('InboxAPI: Texto enviado com sucesso.', [
                    'conversation_id' => $id,
                    'wa_message_id'   => $result['wa_message_id'] ?? null,
                    'is_reply'        => !!$reply_to,
                ]);

                // Formatar dados para o frontend renderizar imediatamente
                $msg = $this->message_repo->find_by_id($result['id']);
                if ($msg && $msg->reply_to_message_id) {
                    $original = $this->message_repo->find_by_id($msg->reply_to_message_id);
                    if ($original) {
                        $msg->reply_preview = [
                            'id'        => $original->id,
                            'text'      => $original->text_body ?: $original->body ?: '[' . $original->message_type . ']',
                            'direction' => $original->direction,
                            'type'      => $original->message_type
                        ];
                    }
                }

                return new \WP_REST_Response([
                    'success' => true,
                    'wa_message_id' => $result['wa_message_id'] ?? null,
                    'data' => $msg
                ], 200);
            }

            \WAS\Core\SystemLogger::logWarning('InboxAPI: Falha no envio de texto.', [
                'conversation_id' => $id,
                'error'           => $result['error'] ?? 'Desconhecido',
                'is_reply'        => !!$reply_to,
            ]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao enviar mensagem'
            ], 500);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'              => 'InboxApiController::send_text_message',
                'conversation_id'      => $id,
                'reply_to_message_id'  => $reply_to,
            ]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno do sistema ao enviar mensagem.'
            ], 500);
        }
    }

    public function check_send_permission() {
        $auth = Routes::check_auth();
        if ( is_wp_error( $auth ) ) {
            return $auth;
        }
        return current_user_can('was_send_messages');
    }

    public function check_assignment_permission() {
        $auth = Routes::check_auth();
        if ( is_wp_error( $auth ) ) {
            return $auth;
        }
        return current_user_can('was_assign_conversations');
    }

    /**
     * Envia uma mensagem de mídia (imagem, áudio, etc).
     */
    public function send_media_message($request) {
        $id = $request['id'];
        $mediaType = $request['media_type'];
        $caption = $request->get_param('caption') ?: '';
        $filename = $request->get_param('filename') ?: '';
        $reply_to = $request->get_param('reply_to_message_id');

        \WAS\Core\SystemLogger::logInfo('Tentativa de envio de mídia iniciada.', [
            'conversation_id'      => $id,
            'media_type'           => $mediaType,
            'reply_to_message_id'  => $reply_to,
            'has_file'             => !empty($_FILES['file']),
            'file_name'            => $_FILES['file']['name'] ?? 'N/A',
            'file_size'            => $_FILES['file']['size'] ?? 0,
            'file_type'            => $_FILES['file']['type'] ?? 'N/A',
            'file_error'           => $_FILES['file']['error'] ?? 'N/A',
        ]);

        if (empty($_FILES['file'])) {
            \WAS\Core\SystemLogger::logWarning('Envio de mídia falhou: nenhum arquivo recebido.', [
                'conversation_id' => $id,
                'media_type'      => $mediaType,
                'files_keys'      => array_keys($_FILES),
            ]);
            return new \WP_REST_Response(['success' => false, 'message' => 'Nenhum arquivo enviado. Verifique o limite de upload do servidor.'], 400);
        }

        $file = $_FILES['file'];

        // Verificar erros de upload do PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o limite do servidor (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o limite do formulário.',
                UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
                UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada no servidor.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo em disco.',
                UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão PHP.',
            ];
            $errorMsg = $uploadErrors[$file['error']] ?? 'Erro desconhecido no upload (código ' . $file['error'] . ').';
            \WAS\Core\SystemLogger::logError('Erro de upload PHP detectado.', [
                'conversation_id' => $id,
                'media_type'      => $mediaType,
                'php_error_code'  => $file['error'],
                'error_message'   => $errorMsg,
            ]);
            return new \WP_REST_Response(['success' => false, 'message' => $errorMsg], 400);
        }
        
        try {
            $service = new \WAS\WhatsApp\OutboundMediaService();
            $result = $service->send_media(
                $id, 
                $file['tmp_name'], 
                $file['type'], 
                $mediaType, 
                $caption, 
                $filename ?: $file['name'],
                $reply_to
            );

            if ($result['success']) {
                \WAS\Core\SystemLogger::logInfo('Mídia enviada com sucesso.', [
                    'conversation_id' => $id,
                    'media_type'      => $mediaType,
                    'wa_message_id'   => $result['wa_message_id'] ?? null,
                ]);
                return new \WP_REST_Response($result, 200);
            }

            \WAS\Core\SystemLogger::logError('Falha ao enviar mídia (retorno do serviço).', [
                'conversation_id' => $id,
                'media_type'      => $mediaType,
                'result'          => $result,
            ]);

            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao enviar mídia'
            ], 500);

        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'         => 'InboxApiController::send_media_message',
                'conversation_id' => $id,
                'media_type'      => $mediaType,
                'file_name'       => $file['name'],
                'file_size'       => $file['size'],
            ]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atribui uma conversa a um atendente.
     */
    public function assign_conversation($request) {
        $id      = $request['id'];
        $user_id = $request->get_param('user_id');

        try {
            $result = $this->conversation_repo->assign($id, $user_id);

            if ($result !== false) {
                return new \WP_REST_Response([
                    'success' => true,
                    'message' => 'Conversa atribuída com sucesso'
                ], 200);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro ao atribuir conversa'
            ], 500);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'InboxApiController::assign_conversation', 'conversation_id' => $id]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno do sistema ao atribuir conversa.'
            ], 500);
        }
    }

    /**
     * Envia uma mensagem em uma conversa.
     */
    public function send_message($request) {
        $id      = $request['id'];
        $type    = $request->get_param('type') ?: 'text';
        $body    = $request->get_param('body');
        
        try {
            $conversation = $this->conversation_repo->get_by_id($id);
            if (!$conversation) {
                return new \WP_REST_Error('not_found', 'Conversa não encontrada', ['status' => 404]);
            }

            // Buscar dados do contato para saber o "to"
            $contact_repo = new \WAS\Inbox\ContactRepository();
            $contact = $contact_repo->get_by_id($conversation->contact_id);

            if (!$contact) {
                return new \WP_REST_Error('contact_not_found', 'Contato não encontrado', ['status' => 404]);
            }

            $tenant_id = \WAS\Auth\TenantContext::get_tenant_id();

            // Chamar serviço de envio (Dev 02)
            $dispatch_service = new \WAS\WhatsApp\MessageDispatchService();
            $result = $dispatch_service->send_message($contact->wa_id, $type, $body, $tenant_id);

            if ($result['success']) {
                // Salvar no repositório local
                $this->message_repo->create_outbound([
                    'conversation_id' => $conversation->id,
                    'wa_message_id'   => $result['wa_message_id'],
                    'message_type'    => $type,
                    'text_body'       => $body,
                    'status'          => 'sent'
                ]);

                $this->conversation_repo->update_last_message_at($conversation->id);

                return new \WP_REST_Response([
                    'success' => true,
                    'wa_message_id' => $result['wa_message_id']
                ], 200);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao enviar mensagem'
            ], 500);
        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, ['context' => 'InboxApiController::send_message', 'conversation_id' => $id]);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Erro interno do sistema ao enviar mensagem de template/mídia.'
            ], 500);
        }
    }

    /**
     * Lista conversas do tenant atual.
     */
    public function get_conversations($request) {
        $limit  = $request->get_param('limit') ?: 20;
        $offset = $request->get_param('offset') ?: 0;

        $conversations = $this->conversation_repo->list_conversations($limit, $offset);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $conversations
        ], 200);
    }

    /**
     * Busca detalhes e mensagens de uma conversa (WAS-075).
     */
    public function get_conversation_detail($request) {
        $id = $request['id'];
        
        $conversation = $this->conversation_repo->get_by_id($id);
        
        if (!$conversation) {
            return new \WP_REST_Error('not_found', 'Conversa não encontrada', ['status' => 404]);
        }

        $messages = $this->message_repo->list_by_conversation($id);

        // Formatar reply_preview e referral
        $reply_count = 0;
        foreach ($messages as $msg) {
            if ($msg->reply_to_message_id) {
                $reply_count++;
                $msg->reply_preview = [
                    'id'        => $msg->reply_to_message_id,
                    'text'      => $msg->reply_text,
                    'direction' => $msg->reply_direction,
                    'type'      => $msg->reply_type
                ];
            } else {
                $msg->reply_preview = null;
            }

            if ($msg->referral_id) {
                $msg->referral = [
                    'headline'   => $msg->referral_headline,
                    'body'       => $msg->referral_body,
                    'source_url' => $msg->referral_url,
                    'media_type' => $msg->referral_media_type,
                    'image_url'  => $msg->referral_image,
                    'video_url'  => $msg->referral_video,
                ];
            } else {
                $msg->referral = null;
            }

            // Limpar campos de join para o JSON ficar limpo
            unset($msg->reply_text, $msg->reply_direction, $msg->reply_type);
            unset($msg->referral_headline, $msg->referral_body, $msg->referral_url, $msg->referral_media_type, $msg->referral_image, $msg->referral_video);
        }

        if ($reply_count > 0) {
            \WAS\Core\SystemLogger::logInfo('InboxAPI: Conversa carregada com mensagens de reply.', [
                'conversation_id' => $id,
                'total_messages'  => count($messages),
                'reply_messages'  => $reply_count,
            ]);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'conversation' => $conversation,
                'messages'     => $messages
            ]
        ], 200);
    }

    /**
     * Polling: retorna apenas mensagens novas (após um determinado ID).
     */
    public function poll_new_messages($request) {
        $id = $request['id'];
        $after_id = (int) $request->get_param('after_id');

        if (!$after_id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Parâmetro after_id é obrigatório'], 400);
        }

        $messages = $this->message_repo->list_new_messages($id, $after_id);

        if ($messages) {
            foreach ($messages as $msg) {
                if ($msg->reply_to_message_id) {
                    $msg->reply_preview = [
                        'id'        => $msg->reply_to_message_id,
                        'text'      => $msg->reply_text,
                        'direction' => $msg->reply_direction,
                        'type'      => $msg->reply_type
                    ];
                } else {
                    $msg->reply_preview = null;
                }

                if ($msg->referral_id) {
                    $msg->referral = [
                        'headline'   => $msg->referral_headline,
                        'body'       => $msg->referral_body,
                        'source_url' => $msg->referral_url,
                        'media_type' => $msg->referral_media_type,
                        'image_url'  => $msg->referral_image,
                        'video_url'  => $msg->referral_video,
                    ];
                } else {
                    $msg->referral = null;
                }

                unset($msg->reply_text, $msg->reply_direction, $msg->reply_type);
                unset($msg->referral_headline, $msg->referral_body, $msg->referral_url, $msg->referral_media_type, $msg->referral_image, $msg->referral_video);
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $messages ?: []
        ], 200);
    }

    /**
     * Aciona o indicador de "digitando..." (WAS-090).
     */
    public function send_typing_indicator($request) {
        $conversation_id = (int) $request['id'];
        $message_id      = $request->get_param('message_id') ? (int) $request->get_param('message_id') : null;

        try {
            $service = new \WAS\WhatsApp\TypingIndicatorService();
            $result = $service->show_typing($conversation_id, $message_id);

            if ($result['success']) {
                return new \WP_REST_Response(['success' => true], 200);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Erro ao exibir indicador de digitação'
            ], 400);

        } catch (\Throwable $e) {
            \WAS\Core\SystemLogger::logException($e, [
                'context'         => 'InboxApiController::send_typing_indicator',
                'conversation_id' => $conversation_id,
                'message_id'      => $message_id
            ]);
            return new \WP_REST_Response(['success' => false, 'message' => 'Erro interno ao processar indicador.'], 500);
        }
    }
}
