<?php
/**
 * Template for Support Page - Modern SaaS Style
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Central de Suporte', 'whatsapp-saas-core'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-soft: #eff6ff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-app: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }
        
        body {
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
            line-height: 1.7;
            color: var(--text-main);
            background-color: var(--bg-app);
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        .legal-wrapper {
            max-width: 850px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .legal-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 60px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            text-align: center;
        }

        .badge {
            display: inline-block;
            background: var(--primary-soft);
            color: var(--primary);
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        header h1 {
            font-size: 2.25rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 16px 0;
            line-height: 1.2;
        }

        .support-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-top: 40px;
            text-align: left;
        }

        .support-item {
            padding: 24px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .support-item:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.05);
        }

        .support-icon {
            font-size: 24px;
            margin-bottom: 16px;
        }

        .support-item h3 {
            margin: 0 0 12px 0;
            font-size: 1.125rem;
            color: #0f172a;
        }

        .support-item p {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .footer-note {
            text-align: center;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
            font-size: 0.875rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

<div class="legal-wrapper">
    <div class="legal-card">
        <header>
            <span class="badge">Ajuda & Suporte</span>
            <h1>Como podemos ajudar?</h1>
            <p>Escolha o canal que melhor atende à sua necessidade técnica ou comercial.</p>
        </header>

        <div class="support-grid">
            <div class="support-item">
                <div class="support-icon">✉️</div>
                <h3>E-mail de Suporte</h3>
                <p>Ideal para questões técnicas detalhadas e solicitações formais.</p>
                <?php $support_email = get_option( 'admin_email', 'support@example.com' ); ?>
                <a href="mailto:<?php echo esc_attr( $support_email ); ?>" class="btn">Enviar E-mail</a>
            </div>

            <div class="support-item">
                <div class="support-icon">💬</div>
                <h3>WhatsApp Oficial</h3>
                <p>Atendimento rápido para dúvidas sobre a plataforma Plataforma.</p>
                <a href="<?php echo esc_url( home_url('/app/login') ); ?>" class="btn">Acessar Plataforma</a>
            </div>

            <div class="support-item">
                <div class="support-icon">📚</div>
                <h3>Documentação</h3>
                <p>Aprenda a configurar sua WABA, Webhooks e Embedded Signup.</p>
                <a href="<?php echo home_url('/docs'); ?>" class="btn">Ver Docs</a>
            </div>
        </div>

        <div class="footer-note">
            &copy; <?php echo date('Y'); ?> Plataforma. Nosso suporte funciona de Seg a Sex, das 08h às 18h.
        </div>
    </div>
</div>

</body>
</html>
