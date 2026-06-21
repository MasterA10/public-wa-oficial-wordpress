<?php

namespace WAS\REST;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrador de rotas REST
 */
class Routes {
    public static function register() {
        $templateController = new TemplateApiController();
        $complianceController = new ComplianceApiController();
        $metaController = new MetaApiController();
        $whatsAppController = new WhatsAppApiController();
        $webhookController = new WebhookController();
        $authController = new AuthApiController();
        $dashboardController = new DashboardApiController();
        $inboxController = new InboxApiController();
        $embeddedSignupController = new EmbeddedSignupController();
        $adminMasterController = new AdminMasterApiController();

        // Inbox (Conversations)
        $inboxController->register_routes();

        // Onboarding
        $embeddedSignupController->register_routes();

        // Admin Master
        $adminMasterController->register_routes();

        // Auth
        register_rest_route(WAS_REST_NAMESPACE, '/me', [
            [
                'methods'             => 'GET',
                'callback'            => [$authController, 'get_me'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        // Dashboard
        register_rest_route(WAS_REST_NAMESPACE, '/dashboard', [
            [
                'methods'             => 'GET',
                'callback'            => [$dashboardController, 'get_summary'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        // Webhook (Público)
        register_rest_route(WAS_REST_NAMESPACE, '/meta/webhook', [
            [
                'methods'             => 'GET',
                'callback'            => [$webhookController, 'verify_webhook'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$webhookController, 'receive_event'],
                'permission_callback' => '__return_true',
            ]
        ]);

        // Meta Callbacks (Públicos para a Meta)
        $metaCallbackController = new MetaCallbackController();

        register_rest_route(WAS_REST_NAMESPACE, '/meta/oauth/callback', [
            [
                'methods'             => ['GET', 'POST'],
                'callback'            => [$metaCallbackController, 'oauth_callback'],
                'permission_callback' => '__return_true',
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/meta/deauthorize', [
            [
                'methods'             => ['GET', 'POST'],
                'callback'            => [$metaCallbackController, 'deauthorize_callback'],
                'permission_callback' => '__return_true',
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/meta/data-deletion', [
            [
                'methods'             => ['GET', 'POST'],
                'callback'            => [$metaCallbackController, 'data_deletion_callback'],
                'permission_callback' => '__return_true',
            ]
        ]);

        // Meta Config
        register_rest_route(WAS_REST_NAMESPACE, '/meta/config', [
            [
                'methods'             => 'GET',
                'callback'            => [$metaController, 'get_config'],
                'permission_callback' => [$metaController, 'permissions_check'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$metaController, 'save_config'],
                'permission_callback' => [$metaController, 'permissions_check'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/meta/config/reveal', [
            [
                'methods'             => 'POST',
                'callback'            => [$metaController, 'reveal_config'],
                'permission_callback' => [$metaController, 'permissions_check'],
            ]
        ]);

        // WhatsApp Accounts
        $whatsAppController->register_routes();

        // Outras rotas já existentes
        register_rest_route(WAS_REST_NAMESPACE, '/templates', [
            [
                'methods'             => 'GET',
                'callback'            => [$templateController, 'get_items'],
                'permission_callback' => [self::class, 'check_auth'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$templateController, 'create_item'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/templates/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$templateController, 'get_item'],
                'permission_callback' => [self::class, 'check_auth'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$templateController, 'update_item'],
                'permission_callback' => [self::class, 'check_auth'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$templateController, 'delete_item'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        // Template Duplicate
        register_rest_route(WAS_REST_NAMESPACE, '/templates/(?P<id>\d+)/duplicate', [
            [
                'methods'             => 'POST',
                'callback'            => [$templateController, 'duplicate_item'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        // Template Sync
        register_rest_route(WAS_REST_NAMESPACE, '/templates/sync', [
            [
                'methods'             => 'POST',
                'callback'            => [$templateController, 'sync_templates'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/templates/(?P<id>\d+)/send', [
            [
                'methods'             => 'POST',
                'callback'            => [$templateController, 'send_template'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/webhook-events', [
            [
                'methods'             => 'GET',
                'callback'            => [$complianceController, 'get_webhook_events'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/audit-logs', [
            [
                'methods'             => 'GET',
                'callback'            => [$complianceController, 'get_audit_logs'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/meta-api-logs', [
            [
                'methods'             => 'GET',
                'callback'            => [$complianceController, 'get_meta_api_logs'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/contacts/(?P<id>\d+)/export', [
            [
                'methods'             => 'POST',
                'callback'            => [$complianceController, 'export_contact'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);

        register_rest_route(WAS_REST_NAMESPACE, '/contacts/(?P<id>\d+)/delete', [
            [
                'methods'             => 'POST',
                'callback'            => [$complianceController, 'delete_contact'],
                'permission_callback' => [self::class, 'check_auth'],
            ]
        ]);
    }

    /**
     * Standard permission callback.
     *
     * @return bool|\WP_Error
     */
    public static function check_auth() {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_unauthorized', 'User not logged in.', [ 'status' => 401 ] );
        }

        if ( ! \WAS\Auth\TenantGuard::check_access() ) {
            return new \WP_Error( 'rest_forbidden', 'User does not have access to any tenant.', [ 'status' => 403 ] );
        }

        return true;
    }
}
