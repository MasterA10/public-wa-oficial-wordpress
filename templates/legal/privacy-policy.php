<?php
/**
 * Template for Privacy Policy Page - Modern SaaS Style
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
    <title><?php _e('Política de Privacidade', 'whatsapp-saas-core'); ?></title>
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

        header {
            text-align: center;
            margin-bottom: 50px;
        }

        header h1 {
            font-size: 2.25rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 16px 0;
            line-height: 1.2;
        }

        .meta-data {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 48px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        h2::before {
            content: "";
            display: inline-block;
            width: 8px;
            height: 24px;
            background: var(--primary);
            border-radius: 4px;
        }

        p {
            margin-bottom: 20px;
        }

        ul {
            margin-bottom: 24px;
            padding-left: 20px;
        }

        li {
            margin-bottom: 12px;
        }

        strong {
            color: #0f172a;
            font-weight: 600;
        }

        .toc-list {
            background: var(--bg-app);
            padding: 30px;
            border-radius: 12px;
            list-style: none;
            padding-left: 0;
            columns: 2;
            column-gap: 30px;
        }

        .toc-list li {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .footer-note {
            text-align: center;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .section-highlight {
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            background: #fafafa;
        }

        @media (max-width: 768px) {
            .legal-wrapper { margin: 20px auto; }
            .legal-card { padding: 30px; }
            .toc-list { columns: 1; }
            header h1 { font-size: 1.75rem; }
        }
    </style>
</head>
<body>

<div class="legal-wrapper">
    <div class="legal-card">
        <header>
            <span class="badge">Privacidade</span>
            <h1>Política de Privacidade</h1>
            <div class="meta-data">
                Data de disponibilização: <strong>28/04/2026</strong>
            </div>
        </header>

        <p>Oi, Titular!</p>

        <p>A <strong>Plataforma</strong> tem um compromisso sério com a privacidade e a proteção dos dados pessoais das pessoas impactadas pelas suas atividades. Por isso, criamos este Aviso de Privacidade (o “Aviso”), para informar você como coletamos, utilizamos, processamos e compartilhamos dados pessoais ao utilizar o nosso ecossistema.</p>

        <div class="section-highlight">
            Como você vai perceber, nós coletamos o mínimo possível de Dados Pessoais na prestação dos nossos serviços e optamos por não ter acesso a nenhum Dado Pessoal a que não precisamos ter acesso para cumprimento de nossas atividades.
        </div>

        <ul class="toc-list">
            <li>• CONCEITOS IMPORTANTES</li>
            <li>• A QUEM SE APLICA</li>
            <li>• PAPÉIS E RESPONSABILIDADES</li>
            <li>• TRATAMENTO DE DADOS</li>
            <li>• COMPARTILHAMENTO</li>
            <li>• LINKS DE TERCEIROS</li>
            <li>• PROTEÇÃO DE DADOS</li>
            <li>• SEUS DIREITOS</li>
            <li>• ARMAZENAMENTO</li>
            <li>• CANAL DE COMUNICAÇÃO</li>
        </ul>

        <h2>1. CONCEITOS IMPORTANTES</h2>
        <p>Alguns conceitos para auxiliar na compreensão deste Aviso:</p>
        <ul>
            <li><strong>Controlador:</strong> O responsável pelas decisões referentes ao tratamento de dados.</li>
            <li><strong>Dados Pessoais:</strong> Informações que identificam uma pessoa natural (e-mail, documento, telefone).</li>
            <li><strong>LGPD:</strong> Lei Geral de Proteção de Dados Pessoais (Lei nº 13.709/2018).</li>
            <li><strong>Tratamento:</strong> Toda operação realizada com dados pessoais (coleta, armazenamento, eliminação).</li>
        </ul>

        <h2>2. A QUEM SE APLICA ESTE AVISO?</h2>
        <p>Este Aviso de Privacidade se aplica aos Usuários do Plataforma e do Site.</p>

        <h2>3. PAPÉIS E RESPONSABILIDADES</h2>
        <p>A Plataforma é a responsável pelo tratamento dos dados pessoais dos seus Usuários, agindo como <strong>Controladora</strong> desses dados.</p>
        <p>Todavia, quando um Cliente do Plataforma usa a sua licença, ele também age como <strong>Controlador Conjunto</strong> sobre esses dados, sendo indispensável que mantenha suas próprias políticas de proteção.</p>

        <h2>4. COMO OS DADOS SÃO TRATADOS</h2>
        <p><strong>Dados de Cadastro:</strong> Nome, Telefone, E-mail, IP e Senha.</p>
        <p><strong>Logs de Aplicação:</strong> Registros de acesso (data, hora e IP) conforme o Marco Civil da Internet.</p>
        <p><strong>Cookies:</strong> Utilizamos cookies necessários (essenciais), de performance, funcionais e de marketing para aprimorar sua experiência.</p>

        <div class="section-highlight" style="background: #eff6ff; border-color: #dbeafe; color: #1e40af;">
            <strong>Importante:</strong> O Plataforma não tem acesso às conversas dos Usuários nem aos seus contatos telefônicos operados na plataforma.
        </div>

        <h2>5. COMPARTILHAMENTO DE DADOS</h2>
        <p>Poderemos compartilhar dados com parceiros essenciais como:</p>
        <ul>
            <li>Hetzner (Servidores Cloud)</li>
            <li>Plataforma WhatsApp (Meta)</li>
            <li>Plataformas de Pagamento (Hotmart/Greenn)</li>
        </ul>

        <h2>6. COMO PROTEGEMOS SEUS DADOS</h2>
        <p>Adotamos procedimentos físicos, eletrônicos e administrativos que garantem a privacidade e segurança dos seus dados, com medidas técnicas compatíveis com as boas práticas de segurança da informação.</p>

        <h2>7. SEUS DIREITOS E COMO EXERCÊ-LOS</h2>
        <p>Como titular, você tem direito a:</p>
        <ul>
            <li>Confirmação e Acesso aos dados.</li>
            <li>Correção de dados incompletos ou inexatos.</li>
            <li>Anonimização ou bloqueio de dados desnecessários.</li>
            <li>Portabilidade e Eliminação de dados.</li>
            <li>Retirada de consentimento.</li>
        </ul>

        <h2>8. POR QUANTO TEMPO ARMAZENAMOS?</h2>
        <p>Os dados serão armazenados enquanto seu cadastro estiver ativo. Após a solicitação de exclusão, os dados são deletados permanentemente, exceto quando a manutenção é obrigatória por lei.</p>

        <h2>9. CANAL DE COMUNICAÇÃO</h2>
        <p>Para exercer seus direitos ou tirar dúvidas, utilize nosso canal oficial:</p>
        <?php $support_email = get_option( 'admin_email', 'support@example.com' ); ?>
        <p><strong>E-mail:</strong> <?php echo esc_html( $support_email ); ?><br>
        <strong>Site:</strong> https://example.com/contato</p>

        <div class="footer-note">
            &copy; <?php echo date('Y'); ?> Plataforma. Todos os direitos reservados.<br>
            Privacidade em primeiro lugar.
        </div>
    </div>
</div>

</body>
</html>
