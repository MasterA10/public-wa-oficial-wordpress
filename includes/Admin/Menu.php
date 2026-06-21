<?php

namespace WAS\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerenciador de Menus Admin
 */
class Menu {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'was-') === false) {
            return;
        }

        // Map menu slugs to our page identifiers
        $page_mapping = [
            'toplevel_page_was-dashboard' => 'dashboard',
            'whatsapp-saas_page_was-inbox' => 'inbox',
            'whatsapp-saas_page_was-templates' => 'templates',
            'whatsapp-saas_page_was-settings-meta' => 'settings-meta',
            'whatsapp-saas_page_was-settings-whatsapp' => 'settings-whatsapp',
            'whatsapp-saas_page_was-logs' => 'logs',
            'toplevel_page_was-master-dashboard' => 'master-dashboard',
            'was-master_page_was-master-meta-apps' => 'master-meta-apps',
            'was-master_page_was-master-tenants' => 'master-tenants',
            'was-master_page_was-master-wabas' => 'master-wabas',
            'was-master_page_was-master-phones' => 'master-phones',
            'was-master_page_was-master-onboardings' => 'master-onboardings',
            'was-master_page_was-master-templates' => 'master-templates',
            'was-master_page_was-master-webhooks' => 'master-webhooks',
            'was-master_page_was-master-tokens' => 'master-tokens',
            'was-master_page_was-master-review' => 'master-review',
            'was-master_page_was-master-audit' => 'master-audit',
            'was-master_page_was-master-settings' => 'master-settings',
        ];

        $current_page = $page_mapping[$hook] ?? 'dashboard';

        \WAS\Core\AssetService::enqueue_assets($current_page);
    }

    public function register_menus() {
        $capability = 'was_access_app'; 
        $master_cap = 'was_view_master_dashboard';

        // Master Admin Menu
        if (current_user_can($master_cap)) {
            add_menu_page(
                'WAS Master',
                'WAS Master',
                $master_cap,
                'was-master-dashboard',
                [$this, 'render_master_dashboard'],
                'dashicons-shield',
                2
            );

            add_submenu_page('was-master-dashboard', 'Visão Geral', 'Visão Geral', $master_cap, 'was-master-dashboard', [$this, 'render_master_dashboard']);
            add_submenu_page('was-master-dashboard', 'Apps Meta', 'Apps Meta', 'was_platform_admin', 'was-master-meta-apps', [$this, 'render_master_meta_apps']);
            add_submenu_page('was-master-dashboard', 'Clientes / Tenants', 'Clientes', $master_cap, 'was-master-tenants', [$this, 'render_master_tenants']);
            add_submenu_page('was-master-dashboard', 'WABAs', 'WABAs', $master_cap, 'was-master-wabas', [$this, 'render_master_wabas']);
            add_submenu_page('was-master-dashboard', 'Números WhatsApp', 'Números', $master_cap, 'was-master-phones', [$this, 'render_master_phones']);
            add_submenu_page('was-master-dashboard', 'Onboardings', 'Onboardings', $master_cap, 'was-master-onboardings', [$this, 'render_master_onboardings']);
            add_submenu_page('was-master-dashboard', 'Templates Globais', 'Templates', $master_cap, 'was-master-templates', [$this, 'render_master_templates']);
            add_submenu_page('was-master-dashboard', 'Webhooks', 'Webhooks', 'was_platform_admin', 'was-master-webhooks', [$this, 'render_master_webhooks']);
            add_submenu_page('was-master-dashboard', 'Tokens e Permissões', 'Tokens', 'was_platform_admin', 'was-master-tokens', [$this, 'render_master_tokens']);
            add_submenu_page('was-master-dashboard', 'App Review / Compliance', 'Review', 'was_manage_compliance', 'was-master-review', [$this, 'render_master_review']);
            add_submenu_page('was-master-dashboard', 'Logs Master', 'Logs', 'was_view_logs', 'was-master-audit', [$this, 'render_master_audit']);
            add_submenu_page('was-master-dashboard', 'Configurações Globais', 'Config. Globais', 'was_platform_admin', 'was-master-settings', [$this, 'render_master_settings']);
        }

        // Customer App Menu
        add_menu_page(
            'WhatsApp SaaS',
            'WhatsApp SaaS',
            $capability,
            'was-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-whatsapp',
            30
        );

        add_submenu_page(
            'was-dashboard',
            'Dashboard',
            'Dashboard',
            $capability,
            'was-dashboard',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'was-dashboard',
            'Inbox',
            'Inbox',
            'was_view_inbox',
            'was-inbox',
            [$this, 'render_inbox']
        );

        add_submenu_page(
            'was-dashboard',
            'Templates',
            'Templates',
            'was_manage_templates',
            'was-templates',
            [$this, 'render_templates_page']
        );

        add_submenu_page(
            'was-dashboard',
            'Configurações Meta',
            'Configurações Meta',
            'manage_options',
            'was-settings-meta',
            [$this, 'render_settings_meta']
        );

        add_submenu_page(
            'was-dashboard',
            'WhatsApp Setup',
            'WhatsApp Setup',
            'was_manage_whatsapp',
            'was-settings-whatsapp',
            [$this, 'render_settings_whatsapp']
        );

        add_submenu_page(
            'was-dashboard',
            'Logs',
            'Logs',
            'was_view_logs',
            'was-logs',
            [$this, 'render_logs_page']
        );
    }

    public function render_dashboard() {
        include WAS_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public function render_inbox() {
        include WAS_PLUGIN_DIR . 'templates/inbox.php';
    }

    public function render_templates_page() {
        include WAS_PLUGIN_DIR . 'templates/templates.php';
    }

    public function render_logs_page() {
        include WAS_PLUGIN_DIR . 'templates/logs.php';
    }

    public function render_settings_meta() {
        include WAS_PLUGIN_DIR . 'templates/settings-meta.php';
    }

    public function render_settings_whatsapp() {
        include WAS_PLUGIN_DIR . 'templates/settings-whatsapp.php';
    }

    public function render_master_dashboard() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/dashboard.php';
    }

    public function render_master_meta_apps() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/meta-apps.php';
    }

    public function render_master_tenants() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/tenants.php';
    }

    public function render_master_wabas() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/wabas.php';
    }

    public function render_master_phones() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/phones.php';
    }

    public function render_master_onboardings() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/onboardings.php';
    }

    public function render_master_templates() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/templates.php';
    }

    public function render_master_webhooks() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/webhooks.php';
    }

    public function render_master_tokens() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/tokens.php';
    }

    public function render_master_review() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/review.php';
    }

    public function render_master_audit() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/audit.php';
    }

    public function render_master_settings() {
        include WAS_PLUGIN_DIR . 'templates/admin-master/settings.php';
    }
}
