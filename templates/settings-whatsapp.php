<?php
/**
 * Template para WhatsApp Setup e Embedded Signup
 */
if (!defined('ABSPATH')) {
    exit;
}

$repository = new \WAS\Meta\MetaAppRepository();
$app = $repository->get_active_app();
$app_id = $app ? $app->app_id : '';
?>
<div class="wrap">
    <h1>WhatsApp Business Setup</h1>
    <hr>

    <?php if (!$app_id): ?>
        <div class="notice notice-warning">
            <p>Você precisa configurar o <strong>Meta App ID</strong> antes de conectar uma conta WhatsApp. Vá em <a href="<?php echo \WAS\Core\URLService::get_meta_settings_url(); ?>">Configurações Meta</a>.</p>
        </div>
    <?php else: ?>
        <div id="was-whatsapp-setup-app">
            <div id="was-whatsapp-setup-app">
                <!-- Nova seção de Verificação de Conexão -->
                <div class="was-verify-connection-box" style="margin: 20px 0; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div>
                            <h2 style="margin:0; font-size: 1.25rem;">Diagnóstico de Conexão Oficial</h2>
                            <p class="description">Valide se sua integração com a Meta Cloud API está 100% operacional.</p>
                        </div>
                        <button id="was-btn-check-connection" class="button button-primary">Verificar conexão oficial</button>
                    </div>

                    <div id="was-verify-results" style="display:none;">
                        <div id="was-verify-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                            <!-- Preenchido via JS -->
                        </div>
                    </div>
                </div>

                <div class="card" style="max-width: 800px; margin-top: 20px;">
                    <strong>URL do Webhook:</strong><br>
                    <code><?php echo esc_url_raw(rest_url(WAS_REST_NAMESPACE . '/meta/webhook')); ?></code>
                    <p class="description">Use esta URL na configuração do produto "WhatsApp" no seu painel da Meta.</p>
                </div>

                <div id="was-connect-actions">
                    <button id="was-launch-signup" class="button button-primary button-large" style="background-color: #1877f2; border-color: #1877f2;">
                        Embedded Signup
                    </button>
                    <button id="was-sdk-login" type="button" class="button button-secondary button-large" style="margin-left: 10px;">
                        Login with Facebook (SDK)
                    </button>
                    <button id="was-disconnect-waba" class="button button-link-delete" style="display: none;">Desconectar Conta</button>
                </div>

                <div id="was-fb-debug-box" style="margin-top: 20px; display: none;">
                    <h3>Facebook Login Result (Debug)</h3>
                    <pre id="was-fb-result" style="background: #f1f5f9; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; border: 1px solid #cbd5e1;"></pre>
                </div>
            </div>
        </div>

        <script>
        // Carregamento do SDK do Facebook
        window.fbAsyncInit = function() {
            FB.init({
                appId      : '<?php echo esc_js($app_id); ?>',
                cookie     : true,
                xfbml      : true,
                version    : 'v25.0'
            });

            FB.AppEvents.logPageView();
        };

        (function(d, s, id){
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {return;}
            js = d.createElement(s); js.id = id;
            js.src = "https://connect.facebook.net/en_US/sdk.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));

        // Função de Login via SDK (conforme solicitado pelo usuário)
        function wasLoginWithFacebookSDK() {
            console.log('WAS: SDK Login Button Clicked');
            
            if (typeof FB === 'undefined') {
                console.error('WAS Error: Facebook SDK (FB) is not defined. Check ad-blockers or connection.');
                alert('O SDK do Facebook ainda não foi carregado. Verifique se há algum bloqueador de anúncios ativo ou se você está em um ambiente seguro (HTTPS).');
                return;
            }

            FB.login(function(response) {
                console.log('Login response:', response);
                const resultBox = document.getElementById('was-fb-debug-box');
                const resultPre = document.getElementById('was-fb-result');

                if (response.authResponse) {
                    const accessToken = response.authResponse.accessToken;
                    const userID = response.authResponse.userID;

                    // Mascarar o token para exibição segura (como sugerido)
                    const maskedToken = accessToken.substring(0, 10) + '...' + accessToken.substring(accessToken.length - 5);

                    // Busca dados básicos do usuário
                    FB.api('/me', { fields: 'id,name,email' }, function(userInfo) {
                        console.log('User info:', userInfo);

                        if (resultBox && resultPre) {
                            resultBox.style.display = 'block';
                            resultPre.textContent = JSON.stringify({
                                status: 'Connected',
                                userID: userID,
                                accessToken: maskedToken,
                                userInfo: userInfo,
                                grantedScopes: response.authResponse.grantedScopes
                            }, null, 2);
                        }

                        // Opcional: Enviar para o backend se houver rota específica
                        // wasApiFetch('/meta/sdk-login', 'POST', { access_token: accessToken, user: userInfo });
                    });

                } else {
                    alert('Usuário cancelou o login ou não autorizou.');
                }
            }, {
                scope: 'public_profile,email,business_management,whatsapp_business_management,whatsapp_business_messaging',
                return_scopes: true
            });
        }

        document.getElementById('was-sdk-login')?.addEventListener('click', wasLoginWithFacebookSDK);

        // Nota: A lógica de estado (checkStatus) e o Embedded Signup são gerenciados pelo app.js
        </script>
    <?php endif; ?>
</div>
