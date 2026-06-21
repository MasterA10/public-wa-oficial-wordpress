<?php
/**
 * Template for Data Deletion page
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitação de Exclusão de Dados - WABA SaaS</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #334155; max-width: 800px; margin: 0 auto; padding: 40px 20px; background: #f8fafc; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0; }
        h1 { color: #0f172a; margin-top: 0; font-size: 2rem; letter-spacing: -0.025em; }
        h2 { color: #1e293b; margin-top: 32px; font-size: 1.25rem; }
        ul { padding-left: 20px; }
        li { margin-bottom: 8px; }
        .footer { margin-top: 40px; text-align: center; font-size: 0.875rem; color: #64748b; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-top: 20px; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Solicitação de Exclusão de Dados</h1>
        <p>A plataforma <strong>Plataforma</strong> permite que usuários e empresas solicitem a exclusão dos dados associados à integração com a Meta/Facebook/WhatsApp.</p>

        <h2>Dados que podem ser excluídos:</h2>
        <ul>
            <li>Dados de conexão OAuth e tokens armazenados;</li>
            <li>Identificadores Meta associados à integração;</li>
            <li>Registros de onboarding e configuração de WABA;</li>
            <li>Dados de contato importados pela integração;</li>
            <li>Logs técnicos vinculados à conta (respeitando obrigações legais).</li>
        </ul>

        <h2>Como solicitar:</h2>
        <ol>
            <?php $support_email = get_option( 'admin_email', 'support@example.com' ); ?>
            <li>Envie um e-mail para <strong><?php echo esc_html( $support_email ); ?></strong></li>
            <li>Informe o e-mail da conta, nome da empresa e o número de telefone WhatsApp conectado.</li>
            <li>Nossa equipe processará a solicitação e retornará um protocolo de confirmação em até 48 horas.</li>
        </ol>

        <p>Se você já realizou uma solicitação através do painel do Facebook/Meta, você pode acompanhar o status da sua solicitação utilizando o link de status fornecido pela plataforma.</p>
        
        <a href="<?php echo home_url('/support'); ?>" class="btn">Entrar em contato</a>
    </div>
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Plataforma. Todos os direitos reservados.
    </div>
</body>
</html>
