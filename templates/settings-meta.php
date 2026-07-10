<?php
/**
 * Template para Configuração do Meta App
 */
if (!defined('ABSPATH')) {
    exit;
}

?>
<style>
    .was-security-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }
    .was-security-modal-content {
        background-color: #fff;
        margin: 15% auto;
        padding: 30px;
        border-radius: 12px;
        width: 400px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .was-security-field-wrapper {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .was-unlock-btn {
        padding: 5px 12px !important;
        height: 30px !important;
        line-height: 1 !important;
    }
</style>

<div class="wrap">
    <h1>Configurações do Meta App</h1>
    <hr>

    <div id="was-meta-settings-app">
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Credenciais do Aplicativo</h2>
            <p class="description">Configure aqui as credenciais do seu aplicativo Meta (Facebook) para habilitar a integração com o WhatsApp.</p>
            
            <form id="was-meta-config-form" onsubmit="return false;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="app_id">App ID</label></th>
                        <td>
                            <input name="app_id" type="text" id="app_id" value="" class="regular-text" required>
                            <p class="description">O ID do seu aplicativo no <a href="https://developers.facebook.com/apps/" target="_blank">Meta for Developers</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="app_secret">App Secret</label></th>
                        <td>
                            <div class="was-security-field-wrapper">
                                <input name="app_secret" type="password" id="app_secret" value="" class="regular-text" autocomplete="new-password" placeholder="Digite para substituir o valor atual">
                                <button type="button" class="button was-unlock-btn" data-target="app_secret">🔓 Desbloquear</button>
                            </div>
                            <p class="description">O segredo do seu aplicativo. Necessário para validar a assinatura dos webhooks.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="waba_id">WABA ID</label></th>
                        <td>
                            <input name="waba_id" type="text" id="waba_id" value="" class="regular-text">
                            <p class="description">Identificador da Conta WhatsApp Business fornecido pela Meta.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="primary_phone_number_id">Phone Number ID Principal</label></th>
                        <td>
                            <input name="primary_phone_number_id" type="text" id="primary_phone_number_id" value="" class="regular-text">
                            <p class="description">ID do número de telefone encontrado no painel da Meta.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="meta_access_token">Meta Access Token</label></th>
                        <td>
                            <div class="was-security-field-wrapper" style="align-items: flex-start;">
                                <textarea name="meta_access_token" id="meta_access_token" class="large-text" rows="4" autocomplete="new-password" placeholder="Digite para substituir o token atual"></textarea>
                                <button type="button" class="button was-unlock-btn" data-target="meta_access_token">🔓 Desbloquear</button>
                            </div>
                            <p class="description">Token de Acesso (Access Token) gerado na Meta Developers. Será salvo criptografado.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="graph_version">Graph API Version</label></th>
                        <td>
                            <input name="graph_version" type="text" id="graph_version" value="v25.0" class="small-text">
                            <p class="description">Versão da Graph API (ex: v25.0).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="webhook_url">Webhook Callback URL</label></th>
                        <td>
                            <input type="text" id="webhook_url" value="Carregando..." class="large-text" readonly onclick="this.select();">
                            <p class="description">URL para configuração do Webhook (mensagens e status).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="oauth_callback_url">OAuth Redirect URI</label></th>
                        <td>
                            <input type="text" id="oauth_callback_url" value="Carregando..." class="large-text" readonly onclick="this.select();">
                            <p class="description">URL para configuração do "Redirect URI" no login do Facebook.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="deauthorize_url">Deauthorize Callback</label></th>
                        <td>
                            <input type="text" id="deauthorize_url" value="Carregando..." class="large-text" readonly onclick="this.select();">
                            <p class="description">URL de desautorização do aplicativo.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="data_deletion_url">Data Deletion Callback</label></th>
                        <td>
                            <input type="text" id="data_deletion_url" value="Carregando..." class="large-text" readonly onclick="this.select();">
                            <p class="description">URL para solicitações de exclusão de dados.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">URLs de Compliance</th>
                        <td>
                            <p><strong>Privacidade:</strong> <code id="privacy_policy_url">...</code></p>
                            <p><strong>Termos:</strong> <code id="terms_of_service_url">...</code></p>
                            <p><strong>Suporte:</strong> <code id="support_url">...</code></p>
                            <p class="description">Use estas URLs nos campos correspondentes da aba "Básico" e "App Review" na Meta.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="verify_token">Webhook Verify Token</label></th>
                        <td>
                            <div class="was-security-field-wrapper">
                                <input name="verify_token" type="password" id="verify_token" value="" class="regular-text" autocomplete="new-password" placeholder="Digite para substituir o valor atual">
                                <button type="button" class="button was-unlock-btn" data-target="verify_token">🔓 Desbloquear</button>
                                <button type="button" id="was-generate-token" class="button">Gerar Novo</button>
                            </div>
                            <script>
                                document.getElementById('was-generate-token').addEventListener('click', function() {
                                    const randomToken = Array.from(crypto.getRandomValues(new Uint8Array(16)))
                                        .map(b => b.toString(16).padStart(2, '0'))
                                        .join('');
                                    document.getElementById('verify_token').value = randomToken;
                                    document.getElementById('verify_token').readOnly = false;
                                });
                            </script>
                            <p class="description">Token usado para validar o webhook. Cole este valor no campo "Verificar token" na Meta.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="was-btn-save-meta" class="button button-primary">Salvar Configurações</button>
                    <span id="was-save-status" style="margin-left: 10px;"></span>
                </p>
            </form>
        </div>

        <!-- Modal de Segurança -->
        <div id="was-security-unlock-modal" class="was-security-modal">
            <div class="was-security-modal-content">
                <h3 style="margin-top:0;">🔐 Verificação de Segurança</h3>
                <p>Por favor, insira sua <strong>senha do WordPress</strong> para revelar as informações sensíveis.</p>
                <div style="margin-bottom:20px;">
                    <input type="password" id="was-unlock-password" class="regular-text" style="width:100%;" placeholder="Sua senha de administrador">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" id="was-cancel-unlock" class="button">Cancelar</button>
                    <button type="button" id="was-confirm-unlock" class="button button-primary">Confirmar e Revelar</button>
                </div>
            </div>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Cadastro Incorporado (Embedded Signup)</h2>
            <p class="description">Utilize o fluxo oficial da Meta para conectar sua conta WhatsApp Business de forma simplificada.</p>
            
            <form id="was-embedded-signup-config-form" onsubmit="event.preventDefault(); return false;">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="config_id">Embedded Signup Configuration ID</label></th>
                        <td>
                            <input name="config_id" type="text" id="config_id" value="" class="regular-text">
                            <p class="description">O ID da configuração de login obtido no painel da Meta Developers.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="embedded_signup_url">Link do Cadastro Incorporado</label></th>
                        <td>
                            <input name="embedded_signup_url" type="url" id="embedded_signup_url" value="" class="large-text" placeholder="https://...">
                            <p class="description">Insira aqui o link correto de acordo com o seu plano para iniciar o cadastro.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="was-btn-save-embedded" class="button button-primary">Salvar Configuração de Cadastro</button>
                    <a id="was-start-embedded-signup" href="#" class="button button-secondary" style="margin-left: 10px;" target="_blank">Iniciar Cadastro Incorporado</a>
                </p>
            </form>
        </div>
    </div>
</div>
