<?php
/**
 * Template for Terms of Service Page - Modern SaaS Style
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
    <title><?php _e('Termos e Condições', 'whatsapp-saas-core'); ?></title>
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

        .important-notice {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 24px;
            border-radius: 8px;
            margin: 32px 0;
            font-weight: 500;
            color: #92400e;
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
            margin-bottom: 8px;
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
            <span class="badge">Oficial</span>
            <h1>Termos e Condições Gerais de Uso</h1>
            <div class="meta-data">
                Publicado em: <strong>22/05/2024</strong> • Atualizado em: <strong>28/04/2026</strong>
            </div>
        </header>

        <p>Estes Termos e Condições de Uso (“Termos”) regulam a relação comercial e o licenciamento de uso entre <strong>Equipe do Produto (Fornecedor)</strong>, doravante denominada “Plataforma” ou “LICENCIANTE”, e a pessoa física ou jurídica que adquire a licença, doravante denominada “CLIENTE” ou “LICENCIADO”.</p>

        <p>O objeto deste instrumento é o regramento da utilização do software de gestão e automação de atendimentos denominado “SISTEMA PLATAFORMA”, fornecido na modalidade auto-hospedada (selfhosted).</p>

        <div class="important-notice">
            AO CONTRATAR E UTILIZAR O SISTEMA PLATAFORMA, O CLIENTE DECLARA TER LIDO, COMPREENDIDO E ACEITO INTEGRALMENTE ESTES TERMOS.
        </div>

        <ul class="toc-list">
            <li>• CONCEITOS IMPORTANTES</li>
            <li>• NATUREZA E EFICÁCIA</li>
            <li>• REQUISITOS TÉCNICOS</li>
            <li>• RESPONSABILIDADES</li>
            <li>• OBJETO E LICENÇA</li>
            <li>• PAGAMENTO E CANCELAMENTO</li>
            <li>• SUPORTE TÉCNICO E SLA</li>
            <li>• OBRIGAÇÕES E LIMITAÇÕES</li>
            <li>• PROPRIEDADE INTELECTUAL</li>
            <li>• PRIVACIDADE (LGPD)</li>
            <li>• DISPOSIÇÕES GERAIS</li>
            <li>• FORO E LEGISLAÇÃO</li>
        </ul>

        <h2>1. CONCEITOS IMPORTANTES NESTES TERMOS</h2>
        <p>Para facilitar a leitura e interpretação deste documento, adotamos as seguintes definições:</p>
        <p><strong>Cliente (ou Licenciado):</strong> Pessoa física ou jurídica que adquire a Licença de Uso do Software Plataforma, responsável pelo pagamento, pela contratação da infraestrutura (VPS) e pela gestão dos Usuários e Clientes Finais (Tenants).</p>
        <p><strong>Sistema Plataforma:</strong> Software desenvolvido e de propriedade exclusiva da Plataforma, fornecido sob regime de licenciamento self-hosted.</p>
        <p><strong>Licença de Uso Anual:</strong> Modalidade de contratação que concede ao Cliente o direito de uso do Sistema Plataforma pelo período de 12 (doze) meses.</p>
        <p><strong>Modelo White-Label:</strong> Característica que permite ao Cliente personalizar a identidade visual e revender o acesso ao software para terceiros sob sua própria marca.</p>
        <p><strong>Infraestrutura (VPS):</strong> Servidor Virtual Privado (Virtual Private Server) contratado e custeado diretamente pelo Cliente.</p>
        <p><strong>Usuário:</strong> Pessoa autorizada pelo Cliente a acessar o painel do sistema.</p>
        <p><strong>Tenant (Cliente Final):</strong> Conta ou instância criada pelo Cliente para revender serviços a terceiros.</p>

        <h2>2. NATUREZA E EFICÁCIA DOS TERMOS</h2>
        <p>2.1. Ao adquirir a Licença de Uso, o Cliente concorda integralmente com estes Termos. A aceitação destas regras é condição indispensável para a liberação do acesso.</p>
        <p>2.2. A realização do pagamento ou o simples início da utilização do sistema implica na aceitação plena e irrevogável de todas as condições estabelecidas.</p>
        <p>2.3. O Cliente reconhece que estes Termos possuem força de contrato vinculante, substituindo quaisquer acordos verbais anteriores.</p>
        <p>2.5. A Plataforma poderá alterar estes Termos a qualquer momento. O uso continuado confirma a aceitação dos novos Termos.</p>

        <h2>3. REQUISITOS TÉCNICOS E CONDIÇÕES DE OPERAÇÃO</h2>
        <p>Para garantir a performance e segurança, o Cliente deve observar rigorosamente os seguintes requisitos:</p>
        
        <p><strong>3.1. Requisitos da Estação de Trabalho:</strong></p>
        <ul>
            <li>Memória (RAM): 8GB ou mais.</li>
            <li>Processador: Intel i5 ou superior (ou equivalente).</li>
            <li>Conexão: Internet rápida e estável.</li>
        </ul>
        
        <p><strong>3.2. Requisitos do Servidor (VPS):</strong></p>
        <ul>
            <li>Memória (RAM): 16GB ou mais.</li>
            <li>Processamento: 4 vCPUs ou mais.</li>
            <li>Sistema Operacional: Ubuntu 20.04 ou 22.04 LTS.</li>
            <li>Armazenamento: SSD ou NVMe a partir de 200GB.</li>
        </ul>

        <p><strong>3.6. Protocolos de Conexão:</strong></p>
        <p><strong>3.6.1. Padrão Recomendado:</strong> A API Oficial do WhatsApp (WABA) é o padrão de estabilidade e segurança.</p>
        <p><strong>3.6.2. APIs Não Oficiais (Riscos):</strong> O uso de APIs não oficiais é de total responsabilidade do Cliente, assumindo riscos de desconexões e banimentos.</p>

        <h2>4. DELIMITAÇÃO DE RESPONSABILIDADES</h2>
        <p>4.1. Estes Termos disciplinam exclusivamente a relação entre a Plataforma e o Cliente.</p>
        <p>4.2. O Cliente é o único responsável pela sua equipe e funcionários.</p>
        <p>4.3. No modelo White-Label, o Cliente é o único responsável pelo suporte e cobrança de seus próprios clientes finais (Tenants).</p>

        <h2>5. OBJETO E LICENÇA DE USO</h2>
        <p>5.1. Concessão de Licença de Uso não exclusiva, intransferível e temporária.</p>
        <p>5.3. Restrições: É vedado copiar, vender o código-fonte ou realizar engenharia reversa sob pena de cancelamento imediato.</p>

        <h2>6. PLANOS, PAGAMENTO E CANCELAMENTO</h2>
        <p>6.1. A aquisição é realizada através de Licença de Uso Anual (12 meses).</p>
        <p>6.1.2. Parcelamento via cartão de crédito não é uma assinatura mensal cancelável, mas uma compra única parcelada.</p>
        <p>6.4. Política de Reembolso: Direito de arrependimento de 7 dias conforme o Art. 49 do CDC.</p>

        <h2>7. POLÍTICA DE SUPORTE TÉCNICO E SLA</h2>
        <p>7.1. Canal Oficial: Portal de tickets. Horário: Seg a Sex, 08h às 18h.</p>
        <p>7.2. SLA de Resposta: Até 03 (três) dias úteis.</p>

        <h2>8. OBRIGAÇÕES E LIMITAÇÕES</h2>
        <p>8.1. Plataforma: Fornecer software, setup inicial e atualizações de segurança.</p>
        <p>8.2. Cliente: Manter servidor, realizar backups e administrar seus usuários.</p>

        <h2>9. PROPRIEDADE INTELECTUAL</h2>
        <p>9.1. O código-fonte, arquitetura e logos são de propriedade intelectual exclusiva da Plataforma.</p>

        <h2>10. PRIVACIDADE E DADOS (LGPD)</h2>
        <p>10.1. A Plataforma é Controladora apenas dos dados cadastrais do licenciante. O Cliente é o Controlador dos dados de seus próprios usuários e leads.</p>

        <h2>11. DISPOSIÇÕES GERAIS</h2>
        <p>11.1. A tolerância de uma das partes quanto ao descumprimento de qualquer obrigação pela outra não implicará em renúncia ao direito de exigir o cumprimento da obrigação.</p>

        <h2>12. FORO E LEGISLAÇÃO APLICÁVEL</h2>
        <p>12.1. Regido pelas leis da República Federativa do Brasil. Foro eleito: Comarca do domicílio do LICENCIANTE para dirimir quaisquer dúvidas oriundas deste instrumento.</p>

        <div class="footer-note">
            &copy; <?php echo date('Y'); ?> Plataforma. Todos os direitos reservados.<br>
            A tecnologia por trás do seu atendimento oficial.
        </div>
    </div>
</div>

</body>
</html>
