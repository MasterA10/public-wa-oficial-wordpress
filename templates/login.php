<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Plataforma</title>
    <link rel="stylesheet" href="<?php echo WAS_PLUGIN_URL; ?>assets/css/login.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body class="was-login-body">
    <div class="was-login-container">
        <div class="was-login-box">
            <img src="<?php echo WAS_PLUGIN_URL; ?>assets/images/logo.png" alt="Plataforma Logo" class="was-login-logo">
            <h1>Bem-vindo de volta</h1>
            <p>Acesse sua conta para gerenciar seu WhatsApp</p>

            <?php if (!empty($errors)): ?>
                <div class="was-errors">
                    <?php foreach($errors as $error): ?>
                        <p><?php echo esc_html($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="" method="post" onsubmit="this.querySelector('button').innerHTML = 'Entrando...'; this.querySelector('button').disabled = true;">
                <?php wp_nonce_field('was_login_action', 'was_login_nonce'); ?>
                
                <div class="was-form-group">
                    <label for="log">E-mail ou Usuário</label>
                    <input type="text" name="log" id="log" placeholder="seu@email.com" required autocomplete="username">
                </div>
                
                <div class="was-form-group">
                    <label for="pwd">Senha</label>
                    <input type="password" name="pwd" id="pwd" placeholder="••••••••" required autocomplete="current-password">
                </div>
                
                <div class="was-form-group checkbox">
                    <input type="checkbox" name="rememberme" id="rememberme">
                    <label for="rememberme">Mantenha-me conectado</label>
                </div>
                
                <button type="submit" class="was-btn">Entrar na Plataforma</button>
            </form>
        </div>
        
        <div class="was-login-footer">
            <a href="<?php echo home_url('/privacy-policy'); ?>">Privacidade</a>
            <a href="<?php echo home_url('/terms-of-service'); ?>">Termos de Uso</a>
            <a href="<?php echo home_url('/data-deletion'); ?>">Exclusão de Dados</a>
            <a href="<?php echo home_url('/support'); ?>">Suporte</a>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
