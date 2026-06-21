<?php
/**
 * Template for Plataforma CRM Landing Page
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
    <title>Plataforma CRM | WhatsApp Business Official SaaS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1; /* Purple/Indigo */
            --primary-dark: #4f46e5;
            --primary-soft: #f5f3ff;
            --accent: #f59e0b; /* Orange */
            --accent-soft: #fff7ed;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --bg-body: #ffffff;
            --bg-soft: #f8fafc;
            --bg-dark: #0f172a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            background-color: var(--bg-body);
            line-height: 1.6;
            overflow-x: hidden;
        }

        h1, h2, h3, h4 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navbar */
        nav {
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid #f1f5f9;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo span {
            color: var(--accent);
        }

        .nav-links {
            display: flex;
            gap: 32px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .btn-login {
            background: var(--primary);
            color: white !important;
            padding: 12px 28px;
            border-radius: 99px;
            transition: all 0.2s ease;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4);
        }

        /* Hero Section */
        .hero {
            padding: 220px 0 140px;
            text-align: center;
            background: radial-gradient(circle at top right, var(--primary-soft) 0%, transparent 50%),
                        radial-gradient(circle at bottom left, var(--accent-soft) 0%, transparent 50%);
            position: relative;
        }

        .badge-hero {
            display: inline-block;
            background: var(--accent-soft);
            color: var(--accent);
            padding: 8px 20px;
            border-radius: 99px;
            font-size: 0.875rem;
            font-weight: 800;
            margin-bottom: 32px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border: 1px solid var(--accent);
        }

        .hero h1 {
            font-size: clamp(2.5rem, 8vw, 5.5rem);
            line-height: 1;
            margin-bottom: 32px;
            letter-spacing: -0.04em;
            color: var(--text-main);
        }

        .hero h1 span.purple {
            color: var(--primary);
        }

        .hero h1 span.orange {
            color: var(--accent);
        }

        .hero p {
            font-size: 1.5rem;
            color: var(--text-muted);
            max-width: 800px;
            margin: 0 auto 50px;
        }

        .hero-btns {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 20px 40px;
            border-radius: 16px;
            font-weight: 700;
            text-decoration: none;
            font-size: 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.2);
        }

        .btn-primary:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: white;
            color: var(--text-main);
            padding: 20px 40px;
            border-radius: 16px;
            font-weight: 700;
            text-decoration: none;
            font-size: 1.25rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Stats Section */
        .stats {
            padding: 60px 0;
            background: var(--bg-dark);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            text-align: center;
            gap: 40px;
        }

        .stat-item h3 {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 8px;
        }

        .stat-item p {
            color: #94a3b8;
            font-weight: 500;
        }

        /* Features */
        .features {
            padding: 140px 0;
        }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 80px;
        }

        .section-header h2 {
            font-size: 3.5rem;
            margin-bottom: 24px;
            letter-spacing: -0.03em;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .feature-card {
            padding: 50px;
            border-radius: 32px;
            border: 1px solid #f1f5f9;
            background: white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            border-color: var(--primary);
            box-shadow: 0 40px 80px -20px rgba(99, 102, 241, 0.1);
            transform: translateY(-10px);
        }

        .feature-icon {
            width: 72px;
            height: 72px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            font-size: 32px;
            margin-bottom: 32px;
        }

        .feature-card.highlight .feature-icon {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .feature-card h3 {
            font-size: 1.75rem;
            margin-bottom: 20px;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 1.125rem;
        }

        /* How it Works */
        .how-it-works {
            padding: 140px 0;
            background: var(--bg-soft);
        }

        .step-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 48px;
            margin-top: 60px;
        }

        .step-item {
            position: relative;
            text-align: center;
        }

        .step-number {
            width: 48px;
            height: 48px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            margin: 0 auto 24px;
            font-size: 1.25rem;
            box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
        }

        /* CTA Section */
        .cta-section {
            padding: 140px 0;
            background: linear-gradient(135deg, var(--primary) 0%, #4338ca 100%);
            color: white;
            text-align: center;
            overflow: hidden;
            position: relative;
        }

        .cta-section h2 {
            font-size: 4rem;
            margin-bottom: 32px;
            letter-spacing: -0.04em;
        }

        .btn-cta {
            background: var(--accent);
            color: white;
            padding: 24px 60px;
            border-radius: 20px;
            font-weight: 800;
            text-decoration: none;
            font-size: 1.5rem;
            display: inline-block;
            transition: all 0.3s;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .btn-cta:hover {
            transform: scale(1.1);
            background: #d97706;
        }

        /* Footer */
        footer {
            padding: 100px 0 60px;
            background: var(--bg-dark);
            color: white;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 60px;
            margin-bottom: 80px;
        }

        .footer-logo {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            margin-bottom: 24px;
            display: block;
        }

        .footer-logo span { color: var(--accent); }

        .footer-col h4 {
            margin-bottom: 32px;
            font-size: 1.125rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.15em;
        }

        .footer-col li { margin-bottom: 16px; }

        .footer-col a {
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 1.05rem;
        }

        .footer-col a:hover {
            color: var(--accent);
            padding-left: 8px;
        }

        .footer-bottom {
            border-top: 1px solid #1e293b;
            padding-top: 60px;
            display: flex;
            justify-content: space-between;
            color: #64748b;
            font-size: 1rem;
        }

        @media (max-width: 1024px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .step-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 3.5rem; }
            .cta-section h2 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <nav>
        <div class="container" style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
            <a href="#" class="logo">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="40" height="40" rx="12" fill="#6366F1"/>
                    <path d="M20 10C14.4772 10 10 14.4772 10 20C10 21.8983 10.5299 23.6731 11.4473 25.1846L10 30L14.9459 28.5575C16.4974 29.4819 18.1865 30 20 30C25.5228 30 30 25.5228 30 20C30 14.4772 25.5228 10 20 10Z" fill="white"/>
                    <circle cx="20" cy="20" r="3" fill="#F59E0B"/>
                </svg>
                Fornecedor<span>CRM</span>
            </a>
            <div class="nav-links">
                <a href="#features">Recursos</a>
                <a href="#how-it-works">Como Funciona</a>
                <a href="<?php echo home_url('/support'); ?>">Suporte</a>
                <a href="<?php echo home_url('/app/login'); ?>" class="btn-login">Acessar CRM</a>
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <span class="badge-hero">Oficial WhatsApp Business Platform</span>
            <h1>O <span class="purple">CRM</span> que <span class="orange">Domina</span> seu Atendimento.</h1>
            <p>Conecte-se à API oficial da Meta, automatize processos e multiplique suas vendas com a ferramenta mais robusta do mercado para gestão de WhatsApp.</p>
            <div class="hero-btns">
                <a href="<?php echo home_url('/app/login'); ?>" class="btn-primary">Criar Minha Conta</a>
                <a href="#how-it-works" class="btn-secondary">Descobrir Como</a>
            </div>
        </div>
    </header>

    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>100%</h3>
                    <p>Conformidade Meta</p>
                </div>
                <div class="stat-item">
                    <h3>0%</h3>
                    <p>Risco de Banimento</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Automação Ativa</p>
                </div>
                <div class="stat-item">
                    <h3>1M+</h3>
                    <p>Mensagens/Mês</p>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <h2>Potencialize seu Business</h2>
                <p>O Plataforma CRM não é apenas um plugin, é uma central de comando para sua comunicação empresarial.</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card highlight">
                    <div class="feature-icon">👑</div>
                    <h3>SaaS Multi-Empresa</h3>
                    <p>Arquitetura tenant-ready para gerenciar departamentos ou clientes externos com isolamento total e performance extrema.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🎯</div>
                    <h3>Cloud API Direta</h3>
                    <p>Integração nativa com os servidores da Meta. Latência mínima e entrega garantida para todas as suas notificações.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">✨</div>
                    <h3>Smart Templates</h3>
                    <p>Editor visual de templates com variáveis dinâmicas. Sincronize com a Meta e envie mensagens ricas em segundos.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔔</div>
                    <h3>Status em Tempo Real</h3>
                    <p>Saiba exatamente quando seu cliente recebeu e leu a mensagem através de webhooks de alta fidelidade.</p>
                </div>
                <div class="feature-card highlight">
                    <div class="feature-icon">🛡️</div>
                    <h3>Segurança Militar</h3>
                    <p>Criptografia de ponta a ponta para seus tokens e dados sensíveis. O Plataforma CRM protege o que é mais valioso: sua conexão.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3>Dashboard Analítico</h3>
                    <p>Visualize métricas de envio, taxas de resposta e saúde da sua WABA em um painel intuitivo e poderoso.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>Simples. Rápido. Oficial.</h2>
                <p>Veja como é fácil transformar sua comunicação em 3 passos simples.</p>
            </div>
            
            <div class="step-grid">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <h3>Conecte sua WABA</h3>
                    <p>Utilize nosso fluxo de Embedded Signup para vincular sua conta oficial em menos de 2 minutos.</p>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <h3>Configure o CRM</h3>
                    <p>Ajuste suas preferências, crie seus primeiros templates e defina seus webhooks operacionais.</p>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <h3>Domine o Mercado</h3>
                    <p>Comece a enviar e receber mensagens com a segurança e escala que só a API oficial pode oferecer.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <h2>Pronto para dominar o jogo?</h2>
            <p style="font-size: 1.5rem; margin-bottom: 50px; opacity: 0.9;">Junte-se às empresas que já escalaram seu atendimento com o Plataforma CRM.</p>
            <a href="<?php echo home_url('/app/login'); ?>" class="btn-cta">Acessar Plataforma Agora</a>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <a href="#" class="footer-logo">Fornecedor<span>CRM</span></a>
                    <p style="color: #94a3b8; margin-bottom: 32px;">A solução definitiva para gestão de canais oficiais do WhatsApp Business com inteligência e escala.</p>
                </div>
                <div class="footer-col">
                    <h4>Navegação</h4>
                    <ul>
                        <li><a href="<?php echo home_url('/app/login'); ?>">Acesso Restrito</a></li>
                        <li><a href="<?php echo home_url('/docs'); ?>">Documentação Técnica</a></li>
                        <li><a href="<?php echo home_url('/support'); ?>">Central de Ajuda</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Legal & Compliance</h4>
                    <ul>
                        <li><a href="<?php echo home_url('/privacy-policy'); ?>">Política de Privacidade</a></li>
                        <li><a href="<?php echo home_url('/terms-of-service'); ?>">Termos de Uso</a></li>
                        <li><a href="<?php echo home_url('/data-deletion'); ?>">Exclusão de Dados</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contato Direto</h4>
                    <ul>
                        <?php $support_email = get_option( 'admin_email', 'support@example.com' ); ?>
                        <li><a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a></li>
                        <li><a href="https://wa.me/553171183457">Suporte via WhatsApp</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Plataforma CRM. Desenvolvido por Equipe do Produto.</p>
                <p>Enterprise Ready | Official Meta Partner API Integration</p>
            </div>
        </div>
    </footer>

</body>
</html>
