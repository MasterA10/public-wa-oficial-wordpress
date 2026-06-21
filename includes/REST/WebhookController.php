<?php
namespace WAS\REST;

use WAS\Meta\MetaAppRepository;
use WAS\WhatsApp\WebhookSignatureValidator;
use WAS\WhatsApp\WebhookProcessor;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller REST para Webhooks da Meta
 */
class WebhookController {

    /**
     * Verificação do Webhook (GET) - Meta Challenge
     */
    public function verify_webhook(WP_REST_Request $request) {
        // Suporta tanto REST quanto Raw ($_GET)
        $mode      = $request->get_param('hub_mode') ?: ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
        $token     = $request->get_param('hub_verify_token') ?: ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
        $challenge = $request->get_param('hub_challenge') ?: ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

        $repository = new MetaAppRepository();
        $app = $repository->get_active_app();

        if ($mode === 'subscribe' && !empty($token)) {
            // Comparação segura de token
            if ($app && hash_equals($app->verify_token, (string)$token)) {
                \WAS\WhatsApp\WebhookLogger::log_verification($_GET, 200, (string)$challenge);
                // IMPORTANTE: Deve retornar APENAS o challenge como texto puro
                status_header(200);
                header('Content-Type: text/plain; charset=utf-8');
                echo (string)$challenge;
                exit;
            }
        }

        \WAS\WhatsApp\WebhookLogger::log_verification($_GET, 403, 'Forbidden');
        return new WP_REST_Response(['message' => 'Forbidden'], 403);
    }

    /**
     * Recebimento de Eventos (POST)
     */
    public function receive_event(WP_REST_Request $request) {
        $repository = new MetaAppRepository();
        $app = $repository->get_active_app(true); // Decrypt secret
        
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        if (!$app) {
            \WAS\WhatsApp\WebhookLogger::log_event([], $headers, 500, 'App not configured');
            return new WP_REST_Response(['message' => 'App not configured'], 500);
        }

        // Para Raw requests, precisamos ler o body diretamente se o $request estiver vazio
        $raw_body = $request->get_body();
        if (empty($raw_body)) {
            $raw_body = file_get_contents('php://input');
        }

        $signature = $request->get_header('x-hub-signature-256');
        if (empty($signature)) {
            $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        }

        // 1. Validar assinatura (Segurança)
        if (!\WAS\WhatsApp\WebhookSignatureValidator::is_valid($raw_body, $signature, $app->app_secret)) {
            \WAS\WhatsApp\WebhookLogger::log_event($raw_body, $headers, 403, 'Invalid signature');
            \WAS\Compliance\AuditLogger::log('webhook_auth_error', 'webhook', 0, ['reason' => 'Invalid signature']);
            return new WP_REST_Response(['message' => 'Invalid signature'], 403);
        }

        $payload = json_decode($raw_body, true);
        if (empty($payload)) {
            \WAS\WhatsApp\WebhookLogger::log_event($raw_body, $headers, 400, 'Invalid payload');
            \WAS\Compliance\AuditLogger::log('webhook_payload_error', 'webhook', 0, ['reason' => 'Empty or malformed JSON']);
            return new WP_REST_Response(['message' => 'Invalid payload'], 400);
        }

        // 2. Processar evento (Roteamento e Persistência)
        $processor = new \WAS\WhatsApp\WebhookProcessor();
        $processor->process($payload);

        // Registrar sucesso
        \WAS\WhatsApp\WebhookLogger::log_event($payload, $headers, 200, 'Success');
        \WAS\Compliance\AuditLogger::log('webhook_event_processed', 'webhook', 0, [
            'waba_id' => $payload['entry'][0]['id'] ?? 'unknown'
        ]);

        // 3. Responder rápido para a Meta
        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Rota pública
     */
    public function permissions_check() {
        return true;
    }
}
