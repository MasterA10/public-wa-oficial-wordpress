<?php
/**
 * Template for Data Deletion Status page
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status da Exclusão de Dados - Plataforma</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #334155; max-width: 600px; margin: 0 auto; padding: 40px 20px; background: #f8fafc; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0; text-align: center; }
        h1 { color: #0f172a; margin-top: 0; font-size: 1.5rem; margin-bottom: 24px; }
        .status-box { background: #f1f5f9; padding: 24px; border-radius: 12px; margin: 24px 0; text-align: left; }
        .status-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.95rem; }
        .status-label { font-weight: 600; color: #64748b; }
        .status-value { font-weight: 700; color: #0f172a; }
        .badge { padding: 4px 12px; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-processed { background: #dcfce7; color: #166534; }
        .footer { margin-top: 40px; text-align: center; font-size: 0.875rem; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Status da Solicitação</h1>
        
        <?php
        $request_id = isset($_GET['request']) ? sanitize_text_field($_GET['request']) : '';
        $record = null;
        
        if ($request_id) {
            $record = \WAS\Compliance\DataDeletionRepository::find_by_uuid($request_id);
        }
        
        if ($record): ?>
            <div class="status-box">
                <div class="status-row">
                    <span class="status-label">Protocolo:</span>
                    <span class="status-value"><?php echo esc_html($record->confirmation_code); ?></span>
                </div>
                <div class="status-row">
                    <span class="status-label">Data da Solicitação:</span>
                    <span class="status-value"><?php echo date_i18n(get_option('date_format'), strtotime($record->created_at)); ?></span>
                </div>
                <div class="status-row">
                    <span class="status-label">Status Atual:</span>
                    <span class="status-value">
                        <span class="badge badge-<?php echo esc_attr($record->status); ?>">
                            <?php echo $record->status === 'pending' ? 'Pendente' : 'Processado'; ?>
                        </span>
                    </span>
                </div>
                <?php if ($record->processed_at): ?>
                <div class="status-row">
                    <span class="status-label">Finalizado em:</span>
                    <span class="status-value"><?php echo date_i18n(get_option('date_format'), strtotime($record->processed_at)); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <p style="font-size: 0.9rem; color: #64748b;">Sua solicitação está sendo processada conforme as políticas da Meta e nossa política de privacidade.</p>
        <?php else: ?>
            <div class="status-box" style="text-align: center; color: #ef4444;">
                <p><strong>Solicitação não encontrada.</strong></p>
                <p style="font-size: 0.9rem; margin-bottom: 0;">Verifique se o link está correto ou entre em contato com o suporte.</p>
            </div>
        <?php endif; ?>
        
        <a href="<?php echo home_url('/'); ?>" style="color: #2563eb; text-decoration: none; font-size: 0.9rem; font-weight: 600;">Voltar ao início</a>
    </div>
    <div class="footer">
        &copy; <?php echo date('Y'); ?> Plataforma.
    </div>
</body>
</html>
