<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_success = 'success' === (string) ( $status ?? '' );
$is_pending = 'pending' === (string) ( $status ?? '' );
$title = $is_success ? 'WhatsApp conectado' : ( $is_pending ? 'Cadastro em processamento' : 'Cadastro não concluído' );
$color = $is_success ? '#15803d' : ( $is_pending ? '#a16207' : '#b91c1c' );
$origin = esc_url_raw( home_url() );
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $title ); ?></title>
    <style>
        body { margin: 0; padding: 32px; background: #f8fafc; color: #0f172a; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        main { max-width: 560px; margin: 8vh auto; padding: 32px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 8px 30px rgba(15,23,42,.08); }
        h1 { margin-top: 0; color: <?php echo esc_attr( $color ); ?>; }
        .detail { margin: 20px 0; padding: 16px; background: #f8fafc; border-radius: 10px; }
        .detail div { margin: 6px 0; }
        button { cursor: pointer; border: 0; border-radius: 8px; padding: 11px 16px; background: #1877f2; color: #fff; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <h1><?php echo esc_html( $title ); ?></h1>
    <p><?php echo esc_html( $message ?? '' ); ?></p>
    <?php if ( $is_success ) : ?>
        <div class="detail">
            <?php if ( ! empty( $connection['waba_id'] ) || ! empty( $connection['meta_waba_id'] ) ) : ?>
                <div><strong>WABA:</strong> <?php echo esc_html( $connection['waba_id'] ?? $connection['meta_waba_id'] ); ?></div>
            <?php endif; ?>
            <?php if ( ! empty( $connection['phone_number_id'] ) || ! empty( $connection['meta_phone_number_id'] ) ) : ?>
                <div><strong>Phone number ID:</strong> <?php echo esc_html( $connection['phone_number_id'] ?? $connection['meta_phone_number_id'] ); ?></div>
            <?php endif; ?>
            <?php if ( ! empty( $connection['display_phone_number'] ) ) : ?>
                <div><strong>Número:</strong> <?php echo esc_html( $connection['display_phone_number'] ); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ( ! empty( $attempt_id ) ) : ?>
        <small>Tentativa: <?php echo esc_html( $attempt_id ); ?></small>
    <?php endif; ?>
    <p><button type="button" onclick="window.close();">Voltar para o painel</button></p>
</main>
<script>
(function () {
    if (!window.opener || !window.opener.postMessage) return;
    window.opener.postMessage({
        type: 'WAS_ONBOARDING_RESULT',
        status: <?php echo wp_json_encode( $status ); ?>,
        attempt_id: <?php echo wp_json_encode( $attempt_id ); ?>
    }, <?php echo wp_json_encode( $origin ); ?>);
}());
</script>
</body>
</html>
