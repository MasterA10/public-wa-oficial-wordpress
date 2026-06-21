<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp SaaS Core</title>
    <?php 
    if ( ! function_exists( 'get_current_screen' ) ) {
        require_once ABSPATH . 'wp-admin/includes/screen.php';
    }
    wp_head(); 
    ?>
</head>
<body class="was-app-shell">
    <div class="was-sidebar">
        <div class="was-logo">
            <a href="<?php echo \WAS\Core\URLService::get_page_url('dashboard'); ?>" style="text-decoration: none;">
                <h2>WABA SaaS</h2>
            </a>
        </div>
        <nav class="was-nav">
            <ul>
                <li><a href="<?php echo \WAS\Core\URLService::get_page_url('dashboard'); ?>" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>"><span class="dashicons dashicons-dashboard"></span> Dashboard</a></li>
                <?php if (current_user_can('was_view_inbox')): ?>
                    <li><a href="<?php echo \WAS\Core\URLService::get_page_url('inbox'); ?>" class="<?php echo $page === 'inbox' ? 'active' : ''; ?>"><span class="dashicons dashicons-whatsapp"></span> Inbox</a></li>
                <?php endif; ?>
                <?php if (current_user_can('was_manage_templates')): ?>
                    <li><a href="<?php echo \WAS\Core\URLService::get_page_url('templates'); ?>" class="<?php echo $page === 'templates' ? 'active' : ''; ?>"><span class="dashicons dashicons-layout"></span> Modelos</a></li>
                <?php endif; ?>

                <li class="nav-divider" style="margin: 15px 16px 5px; font-size: 0.7rem; text-transform: uppercase; color: var(--slate-600); font-weight: 700; letter-spacing: 0.05em;">Configurações</li>
                
                <?php if (current_user_can('manage_options')): ?>
                    <li><a href="<?php echo \WAS\Core\URLService::get_page_url('settings-meta'); ?>" class="<?php echo $page === 'settings-meta' ? 'active' : ''; ?>"><span class="dashicons dashicons-networking"></span> Configuração Meta</a></li>
                <?php endif; ?>

                <?php if (current_user_can('was_manage_whatsapp')): ?>
                    <li><a href="<?php echo \WAS\Core\URLService::get_page_url('settings-whatsapp'); ?>" class="<?php echo $page === 'settings-whatsapp' ? 'active' : ''; ?>"><span class="dashicons dashicons-admin-settings"></span> Conexão WhatsApp</a></li>
                <?php endif; ?>
                <?php if (current_user_can('was_view_logs')): ?>
                    <li><a href="<?php echo \WAS\Core\URLService::get_page_url('logs'); ?>" class="<?php echo $page === 'logs' ? 'active' : ''; ?>"><span class="dashicons dashicons-list-view"></span> Auditoria</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <div class="was-main">
        <header class="was-header">
            <div class="was-tenant-info">
                <?php 
                $tenant_id = \WAS\Auth\TenantContext::get_current_tenant_id();
                $tenant = (new \WAS\Tenants\TenantRepository())->find($tenant_id);
                echo esc_html($tenant ? $tenant->name : 'Nenhuma empresa');
                ?>
            </div>
            <div class="was-user-info">
                <?php $user = wp_get_current_user(); ?>
                <span><?php echo esc_html($user->display_name); ?></span>
                <a href="<?php echo wp_logout_url(home_url('/app/login')); ?>">Sair</a>
            </div>
        </header>
        <div class="was-content page-<?php echo esc_attr($page); ?>">
            <?php 
            $page_file = WAS_PLUGIN_DIR . "templates/{$page}.php";
            if (file_exists($page_file)) {
                include $page_file;
            } else {
                echo "<h2>Página não encontrada: " . esc_html($page) . "</h2>";
            }
            ?>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
