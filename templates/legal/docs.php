<?php
/**
 * Template for Technical Documentation Page - Plataforma CRM
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
    <title>Documentação Técnica | Plataforma CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-soft: #f5f3ff;
            --accent: #f59e0b;
            --accent-soft: #fff7ed;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-app: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --code-bg: #1e293b;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, system-ui, sans-serif;
            line-height: 1.7;
            color: var(--text-main);
            background-color: var(--bg-app);
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        .docs-wrapper {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 24px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 40px;
        }

        /* Sidebar Navigation */
        .docs-sidebar {
            height: fit-content;
            position: sticky;
            top: 40px;
        }

        .sidebar-nav {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .sidebar-nav h4 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            margin: 24px 0 12px;
        }

        .sidebar-nav h4:first-child { margin-top: 0; }

        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav li { margin-bottom: 4px; }

        .sidebar-nav a {
            text-decoration: none;
            color: var(--text-main);
            font-size: 0.9375rem;
            font-weight: 500;
            display: block;
            padding: 10px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .sidebar-nav a:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }

        /* Content Area */
        .docs-content {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 80px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
        }

        header { margin-bottom: 60px; }

        .badge {
            display: inline-block;
            background: var(--accent-soft);
            color: var(--accent);
            padding: 6px 16px;
            border-radius: 99px;
            font-size: 0.8125rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        h1 { font-size: 3rem; font-weight: 800; color: #0f172a; margin: 0 0 16px 0; letter-spacing: -0.02em; }
        h2 { font-size: 2rem; margin: 60px 0 24px; color: #0f172a; border-bottom: 2px solid var(--bg-app); padding-bottom: 12px; }
        h3 { font-size: 1.5rem; margin: 40px 0 16px; color: #1e293b; }

        p { margin-bottom: 20px; color: var(--text-muted); font-size: 1.0625rem; }

        .highlight-box {
            background: var(--primary-soft);
            border-left: 4px solid var(--primary);
            padding: 30px;
            border-radius: 12px;
            margin: 40px 0;
        }

        .highlight-box h4 { color: var(--primary); margin-bottom: 8px; font-size: 1.125rem; }

        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Fira Code', monospace;
            font-size: 0.9em;
            color: #ef4444;
        }

        pre {
            background: var(--code-bg);
            color: #e2e8f0;
            padding: 30px;
            border-radius: 16px;
            overflow-x: auto;
            margin: 30px 0;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .step-list { list-style: none; padding: 0; counter-reset: step-counter; }
        .step-list li {
            position: relative;
            padding-left: 60px;
            margin-bottom: 32px;
        }
        .step-list li::before {
            counter-increment: step-counter;
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        @media (max-width: 1024px) {
            .docs-wrapper { grid-template-columns: 1fr; }
            .docs-sidebar { display: none; }
            .docs-content { padding: 40px; }
        }
    </style>
</head>
<body>

<div class="docs-wrapper">
    <aside class="docs-sidebar">
        <nav class="sidebar-nav">
            <a href="<?php echo home_url('/'); ?>" style="font-weight: 800; color: var(--primary); font-size: 1.25rem; margin-bottom: 20px;">Plataforma CRM</a>
            
            <h4>Arquitetura</h4>
            <ul>
                <li><a href="#intro">Introdução</a></li>
                <li><a href="#core">Estrutura Core</a></li>
                <li><a href="#multi-tenant">Multi-Tenancy</a></li>
            </ul>

            <h4>Integração Meta</h4>
            <ul>
                <li><a href="#api-client">Meta API Client</a></li>
                <li><a href="#tokens">Gestão de Tokens</a></li>
                <li><a href="#webhooks">Webhooks</a></li>
            </ul>

            <h4>Funcionalidades</h4>
            <ul>
                <li><a href="#messaging">Mensageria</a></li>
                <li><a href="#templates">Templates</a></li>
                <li><a href="#compliance">Compliance</a></li>
            </ul>
        </nav>
    </aside>

    <main class="docs-content">
        <header>
            <span class="badge">Documentação Técnica</span>
            <h1>Como o Plataforma CRM funciona?</h1>
            <p>Um guia completo sobre a arquitetura, segurança e fluxos operacionais da plataforma.</p>
        </header>

        <section id="intro">
            <h2>1. Filosofia do Projeto</h2>
            <p>O Plataforma CRM foi concebido como uma camada de abstração robusta sobre a **WhatsApp Business Platform (Cloud API)** da Meta. Ao contrário de integrações informais, ele utiliza protocolos oficiais para garantir estabilidade, escala e segurança jurídica.</p>
            <div class="highlight-box">
                <h4>Regra de Ouro</h4>
                <p>O sistema segue a separação estrita de responsabilidades: Controllers não contêm lógica de negócio, Repositories não tomam decisões e toda regra reside em Services ou Orchestrators.</p>
            </div>
        </section>

        <section id="core">
            <h2>2. Arquitetura de Software</h2>
            <p>O projeto utiliza um padrão de namespaces moderno (PSR-4) dentro do WordPress:</p>
            <ul>
                <li><strong>WAS\Core:</strong> Responsável pelo boot do plugin, migrações de banco de dados (`dbDelta`) e registro de rotas.</li>
                <li><strong>WAS\Auth:</strong> Gerencia o contexto multi-empresa e as permissões (RBAC).</li>
                <li><strong>WAS\Meta:</strong> O motor de comunicação com a Graph API.</li>
                <li><strong>WAS\WhatsApp:</strong> Lógica específica de mensagens, telefones e contas WABA.</li>
                <li><strong>WAS\REST:</strong> Camada de interface API para o frontend React/Vue ou integrações externas.</li>
            </ul>
        </section>

        <section id="multi-tenant">
            <h2>3. Multi-Tenancy (Multi-Empresa)</h2>
            <p>O isolamento de dados é a prioridade. Cada registro nas tabelas operacionais possui um `tenant_id`.</p>
            <pre>
// Exemplo de aplicação do TenantContext
$tenant_id = TenantContext::get_current_tenant_id();
$messages = $messageRepository->find_by_tenant($tenant_id);
            </pre>
            <p>O `TenantGuard` intercepta todas as requisições para garantir que um usuário nunca acesse dados de outra empresa, mesmo que tente manipular IDs no frontend.</p>
        </section>

        <section id="api-client">
            <h2>4. Meta API Client</h2>
            <p>Não existem URLs da Meta "hardcoded" no sistema. Tudo é centralizado no `MetaEndpointRegistry`, que resolve operações amigáveis para endpoints reais:</p>
            <pre>
// Registro de Endpoints
'messages.send' => '/{phone_number_id}/messages',
'waba.subscribe' => '/{waba_id}/subscribed_apps'
            </pre>
            <p>O `MetaApiClient` gerencia automaticamente os headers de autenticação, o logging de requisições e a sanitização de payloads.</p>
        </section>

        <section id="tokens">
            <h2>5. Segurança & Token Vault</h2>
            <p>Credenciais da Meta (App Secret e Access Tokens) nunca são salvas em texto puro no banco de dados. Elas passam pelo `TokenVault`, que utiliza criptografia simétrica baseada em uma chave mestre definida no servidor.</p>
            <p>Além disso, implementamos o **Security Reveal Pattern**: no painel administrativo, tokens são mascarados e só podem ser revelados após a re-autenticação com a senha do WordPress.</p>
        </section>

        <section id="webhooks">
            <h2>6. O Ciclo do Webhook</h2>
            <p>Para garantir que nenhuma mensagem seja perdida, seguimos o padrão de **Gravação Imediata**:</p>
            <ol class="step-list">
                <li>O endpoint público recebe o JSON da Meta.</li>
                <li>O payload bruto é salvo instantaneamente na tabela `was_webhook_events`.</li>
                <li>O sistema responde `200 OK` para a Meta em milissegundos.</li>
                <li>Um processo em background (ou trigger pós-save) aciona o `WebhookProcessor`.</li>
                <li>O processador identifica o tipo de evento (Mensagem Inbound, Status de Entrega, etc.) e atualiza o CRM.</li>
            </ol>
        </section>

        <section id="messaging">
            <h2>7. Fluxo de Mensagens</h2>
            <p>Toda mensagem enviada passa pelo `MessageDispatchService`. Ele valida:</p>
            <ul>
                <li>Se a janela de 24 horas está aberta (para mensagens de texto livre).</li>
                <li>Se o número remetente está registrado e ativo.</li>
                <li>Se o destinatário deu opt-in (quando aplicável).</li>
            </ul>
        </section>

        <section id="compliance">
            <h2>8. Compliance Meta</h2>
            <p>Para aprovação no App Review, o sistema inclui nativamente:</p>
            <ul>
                <li><strong>Privacy Policy & TOS:</strong> Gerados dinamicamente com dados do licenciante.</li>
                <li><strong>Data Deletion Callback:</strong> Endpoint obrigatório para que usuários solicitem a exclusão de seus dados via Facebook App Dashboard.</li>
                <li><strong>Audit Logs:</strong> Registro inalterável de quem enviou o quê e quando.</li>
            </ul>
        </section>

        <footer style="margin-top: 100px; border-top: 1px solid var(--border-color); padding-top: 40px; text-align: center;">
            <p>&copy; <?php echo date('Y'); ?> Plataforma CRM - Documentação Interna de Engenharia.</p>
            <a href="<?php echo home_url('/support'); ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;">Precisa de suporte técnico?</a>
        </footer>
    </main>
</div>

</body>
</html>
