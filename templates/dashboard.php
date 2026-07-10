<?php
/**
 * Template para Dashboard / Tela de Onboarding Inicial
 */
if (!defined('ABSPATH')) {
    exit;
}

$tenant_id = \WAS\Auth\TenantContext::get_current_tenant_id();
$phone_service = new \WAS\WhatsApp\PhoneNumberService();
$primary_phone_id = $phone_service->get_primary_id($tenant_id);

$account_repo = new \WAS\WhatsApp\WhatsAppAccountRepository();
$waba_account = $account_repo->findForTenant($tenant_id);
$saved_waba_id = $waba_account ? $waba_account->waba_id : '';

// Flag para mostrar onboarding apenas no momento do login
$show_onboarding = isset($_GET['onboarding']) && '1' === $_GET['onboarding'];

if ($show_onboarding):
?>
<div class="was-onboarding-screen">
    <!-- View de Onboarding (Ações) -->
    <div id="was-onboarding-content" class="was-onboarding-hero">
        <span class="was-status-badge was-status-approved" style="margin-bottom: 20px;">Oficial Meta Cloud API</span>
        <h1>Cadastrar e Conectar</h1>
        <p class="subtitle">Conecte sua Conta WhatsApp Business (WABA) para começar a gerenciar seus templates e enviar mensagens oficiais com total escala e segurança.</p>
        
        <div class="was-onboarding-actions" id="was-connect-actions">
            <button id="was-launch-signup" class="button button-primary" style="background-color: #1877f2; border-color: #1877f2; padding: 12px 24px; height: auto;">
                <span class="dashicons dashicons-whatsapp" style="margin-right: 8px; vertical-align: middle;"></span> Embedded Signup
            </button>
        </div>

        <div class="was-meta-notice">
            <h4><span class="dashicons dashicons-info" style="color: var(--primary);"></span> Nota Importante sobre Permissões</h4>
            <p>
                A permissão <strong>whatsapp_business_management</strong> é obrigatória para que nossa plataforma possa gerenciar seus <strong>modelos de mensagem (templates)</strong>. Sem ela, não conseguimos sincronizar, criar ou validar suas mensagens junto à Meta.
            </p>
        </div>

        <div class="was-feature-mini-grid">
            <div class="was-feature-mini-card">
                <span class="icon">📑</span>
                <h5>Gestão de Templates</h5>
                <p>Sincronização bidirecional com o painel da Meta.</p>
            </div>
            <div class="was-feature-mini-card">
                <span class="icon">⚡</span>
                <h5>Envio em Massa</h5>
                <p>Notificações oficiais com alta taxa de entrega.</p>
            </div>
            <div class="was-feature-mini-card">
                <span class="icon">💬</span>
                <h5>Inbox Centralizada</h5>
                <p>Todas as conversas da sua WABA em um só lugar.</p>
            </div>
        </div>
    </div>

    <!-- View de Sucesso (Após Conexão) -->
    <div id="was-onboarding-success" class="was-onboarding-hero" style="display: none;">
        <div style="background: #f0fdf4; border: 2px solid #bbf7d0; border-radius: 24px; padding: 40px; text-align: center;">
            <div style="background: #22c55e; color: white; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 40px;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 48px; width: 48px; height: 48px;"></span>
            </div>
            <h1 style="color: #166534; margin-bottom: 8px;">Parabéns! Conexão Realizada</h1>
            <p style="color: #15803d; font-size: 1.1rem; margin-bottom: 32px;">Sua conta WhatsApp Business foi integrada com sucesso à nossa plataforma.</p>
            
            <div class="was-feature-mini-grid" style="grid-template-columns: repeat(2, 1fr); text-align: left;">
                <div class="was-feature-mini-card" style="background: white; border: 1px solid #dcfce7;">
                    <h5 id="success-waba-id" style="font-family: monospace; font-size: 0.9rem; margin-bottom: 5px;">WABA ID: ---</h5>
                    <p style="margin: 0; font-size: 0.8rem; color: #166534;">Conta Business Verificada</p>
                </div>
                <div class="was-feature-mini-card" style="background: white; border: 1px solid #dcfce7;">
                    <h5 id="success-phone-id" style="font-family: monospace; font-size: 0.9rem; margin-bottom: 5px;">Phone ID: ---</h5>
                    <p style="margin: 0; font-size: 0.8rem; color: #166534;">Número Operacional</p>
                </div>
            </div>

            <div style="margin-top: 32px; display: flex; gap: 12px; justify-content: center;">
                <button onclick="window.location.reload();" class="button button-primary" style="padding: 12px 24px; height: auto;">
                    Ir para o Dashboard
                </button>
                <a href="<?php echo \WAS\Core\URLService::get_page_url('templates'); ?>" class="button" style="padding: 12px 24px; height: auto;">
                    Ver Meus Templates
                </a>
            </div>
        </div>
    </div>

    <div class="was-footer-note">
        <p>
            <strong>Nota de Coexistência:</strong> Ao conectar sua conta via Plataforma, garantimos a possibilidade de <strong>coexistência no aplicativo</strong>. Isso significa que você pode continuar utilizando o aplicativo móvel oficial do WhatsApp Business enquanto aproveita todos os recursos avançados da nossa plataforma SaaS.
        </p>
    </div>
</div>

<script>
    // Configurações salvas para fallback
    const wasSavedConfig = {
        waba_id: '<?php echo esc_js($saved_waba_id); ?>',
        phone_number_id: '<?php echo esc_js($primary_phone_id); ?>'
    };

    // Limpar o parâmetro da URL após carregar, para que não apareça no refresh
    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('onboarding');
        window.history.replaceState({path: url.href}, '', url.href);
    }

    // Função para mostrar sucesso na tela
    function wasResolveOnboardingId(value, savedValue) {
        const normalizedValue = String(value || '').trim();
        const normalizedSavedValue = String(savedValue || '').trim();

        if (normalizedValue && normalizedValue.toLowerCase() !== 'sincronizando...') {
            return normalizedValue;
        }

        return normalizedSavedValue || 'ID Pendente';
    }

    async function wasShowOnboardingSuccess(data = {}) {
        const content = document.getElementById('was-onboarding-content');
        const success = document.getElementById('was-onboarding-success');
        const wabaEl = document.getElementById('success-waba-id');
        const phoneEl = document.getElementById('success-phone-id');
        
        if (content) content.style.display = 'none';
        if (success) {
            success.style.display = 'block';
            
            // Mostrar IMEDIATAMENTE o que veio da Meta ou o que já está salvo nas configurações.
            const initialWabaId = wasResolveOnboardingId(data.waba_id, wasSavedConfig.waba_id);
            const initialPhoneId = wasResolveOnboardingId(data.phone_number_id, wasSavedConfig.phone_number_id);

            if (wabaEl) wabaEl.textContent = 'WABA ID: ' + initialWabaId;
            if (phoneEl) phoneEl.textContent = 'Phone ID: ' + initialPhoneId;

            // Fazer o fetch em segundo plano para atualizar se houver algo novo
            try {
                if (typeof wasApiFetch === 'function') {
                    const accounts = await wasApiFetch('/whatsapp/accounts');
                    if (accounts && accounts.length > 0) {
                        const account = accounts[0];
                        if (wabaEl) wabaEl.textContent = 'WABA ID: ' + account.waba_id;
                        if (account.phone_number_id && phoneEl) {
                            phoneEl.textContent = 'Phone ID: ' + account.phone_number_id;
                        }
                    }
                }
            } catch (e) {
                console.error('Erro ao buscar dados atualizados:', e);
            }
        }
    }
</script>





<?php else: ?>
<div class="wrap">
    <h1>Dashboard Operacional</h1>
    <p style="color: var(--slate-600); margin-bottom: 30px;">Bem-vindo ao centro de controle do seu WhatsApp Business.</p>
    
    <div class="was-dashboard">
        <div class="was-stats-grid">
            <div class="was-stat-card">
                <h3>Contas WhatsApp</h3>
                <div class="was-stat-value" id="stat-wa-accounts">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Números Ativos</h3>
                <div class="was-stat-value" id="stat-active-numbers">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Mensagens Hoje</h3>
                <div class="was-stat-value" id="stat-messages-today">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Conversas Abertas</h3>
                <div class="was-stat-value" id="stat-open-conversations">0</div>
            </div>
            <div class="was-stat-card">
                <h3>Templates</h3>
                <div class="was-stat-value" id="stat-templates">0</div>
            </div>
        </div>
        
        <?php if ($primary_phone_id): ?>
        <div class="was-meta-notice" style="margin-top: 24px; display: flex; justify-content: space-between; align-items: center; background: #f0fdf4; border-color: #bbf7d0;">
            <div>
                <h4 style="margin: 0; color: #166534;"><span class="dashicons dashicons-yes-alt" style="color: #166534;"></span> Conexão Ativa</h4>
                <p style="margin: 5px 0 0; font-size: 0.85rem; color: #166534;">Seu número <strong><?php echo esc_html($primary_phone_id); ?></strong> está conectado e operacional.</p>

            </div>
            <a href="<?php echo \WAS\Core\URLService::get_page_url('settings-whatsapp'); ?>" class="button">Gerenciar Conexão</a>
        </div>
        <?php else: ?>
        <div class="was-meta-notice" style="margin-top: 24px; display: flex; justify-content: space-between; align-items: center; background: #fffbeb; border-color: #fef3c7;">
            <div>
                <h4 style="margin: 0; color: #92400e;"><span class="dashicons dashicons-warning" style="color: #92400e;"></span> Aguardando Conexão</h4>
                <p style="margin: 5px 0 0; font-size: 0.85rem; color: #92400e;">Você ainda não conectou um número oficial. <a href="<?php echo \WAS\Core\URLService::get_page_url('settings-whatsapp'); ?>">Conecte agora</a> para começar.</p>
            </div>
            <a href="<?php echo \WAS\Core\URLService::get_page_url('settings-whatsapp'); ?>" class="button button-primary">Conectar WhatsApp</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>


