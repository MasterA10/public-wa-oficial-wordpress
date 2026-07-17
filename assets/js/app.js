/**
 * WAS App Core - Main JavaScript
 * 
 * Standardized for Meta SaaS Experience.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.wasApp === 'undefined') return;

    const { restUrl, nonce } = window.wasApp;
    let currentConversationId = null;
    let lastMessageId = 0;
    let lastInboundMessageId = null;
    let lastTypingSentAt = 0;
    let windowStatusTimer = null;
    let currentWindowData = null;
    let lastMessageSentAt = 0;
    let typingTimer = null;
    let replyToMessageId = null;
    let chatPollTimer = null;
    let convListPollTimer = null;
    let currentTemplateForSend = null;
    const CHAT_POLL_INTERVAL = window.wasApp.pollingInterval || 3000;
    const CONV_LIST_POLL_INTERVAL = (window.wasApp.pollingInterval * 3) || 10000;
    let wizardStep = 1;
    let wizardButtons = [];
    let editingTemplateId = null;
    let wizardVariables = {}; // Ex: { 'nome': 'Fulano' }
    let activeSendTemplate = null;

    // Safety: Ensure window helpers are defined early
    window.updateVariableExample = (key, val) => {
        wizardVariables[key] = val;
        updatePreview();
        renderChecklist();
    };

    window.updateWizardButton = (i, field, val) => { 
        if (!wizardButtons[i]) return;
        wizardButtons[i][field] = val; 
        if (field === 'url') renderWizardButtons(); // Re-render to show/hide dynamic URL example field
        updatePreview(); 
        renderChecklist();
    };

    window.removeWizardButton = (i) => { 
        wizardButtons.splice(i, 1); 
        renderWizardButtons(); 
        updatePreview(); 
        renderChecklist();
    };

    /**
     * Helper to make authenticated API requests to WP REST API
     */
    async function wasApiFetch(path, method = 'GET', body = null) {
        const url = `${restUrl}${path}`;
        const options = {
            method,
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        if (body) options.body = JSON.stringify(body);

        try {
            const response = await fetch(url, options);
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || `HTTP error! status: ${response.status}`);
            return data;
        } catch (error) {
            console.error(`WAS API Error [${method} ${path}]:`, error);
            throw error;
        }
    }

    /**
     * Dashboard
     */
    if (document.getElementById('stat-wa-accounts')) {
        initDashboard();
    }

    /**
     * Master Admin Dashboard
     */
    if (document.getElementById('master-stat-tenants')) {
        initMasterDashboard();
    }

    if (document.getElementById('master-tenants-list')) {
        initMasterTenants();
    }

    if (document.getElementById('master-apps-list')) {
        initMasterApps();
    }

    if (document.getElementById('master-wabas-list')) {
        initMasterWabas();
    }

    if (document.getElementById('master-phones-list')) {
        initMasterPhones();
    }

    if (document.getElementById('master-onboardings-list')) {
        initMasterOnboardings();
    }

    if (document.getElementById('master-templates-list')) {
        initMasterTemplates();
    }

    if (document.getElementById('master-webhooks-list')) {
        initMasterWebhooks();
    }

    if (document.getElementById('master-tokens-list')) {
        initMasterTokens();
    }

    if (document.getElementById('master-review-checklist')) {
        initMasterReview();
    }

    if (document.getElementById('master-audit-list')) {
        initMasterAudit();
    }

    if (document.getElementById('was-master-settings-form')) {
        initMasterSettings();
    }

    async function initMasterAudit() {
        const tb = document.getElementById('master-audit-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/audit-logs');
            tb.innerHTML = (data || []).map(l => `
                <tr>
                    <td>${l.created_at}</td>
                    <td>${l.user_login || 'Sistema'}</td>
                    <td>${l.tenant_name || '-'}</td>
                    <td>${l.action}</td>
                    <td>${l.entity_type}</td>
                    <td><small>IP: ${l.ip_address || '-'}</small></td>
                    <td><small>${l.metadata}</small></td>
                </tr>
            `).join('') || '<tr><td colspan="7">Nenhum log encontrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar logs.</td></tr>'; }
    }

    async function initMasterSettings() {
        const form = document.getElementById('was-master-settings-form');
        const btn = document.getElementById('was-btn-save-master-settings');
        if (!form || !btn) return;

        try {
            const data = await wasApiFetch('/admin/settings');
            if (data) {
                document.getElementById('master_graph_version').value = data.master_graph_version || 'v25.0';
                document.getElementById('master_msg_rate_limit').value = data.master_msg_rate_limit || 60;
                document.getElementById('master_log_retention').value = data.master_log_retention || 90;
                document.getElementById('master_polling_interval').value = data.master_polling_interval || 3000;
            }
        } catch (err) { console.error('Error loading master settings:', err); }

        btn.addEventListener('click', async () => {
            const payload = {
                master_graph_version: document.getElementById('master_graph_version').value,
                master_msg_rate_limit: document.getElementById('master_msg_rate_limit').value,
                master_log_retention: document.getElementById('master_log_retention').value,
                master_polling_interval: document.getElementById('master_polling_interval').value
            };
            const webhookSecret = document.getElementById('external_send_webhook_secret');
            if (webhookSecret && webhookSecret.value) {
                payload.external_send_webhook_secret = webhookSecret.value;
            }
            try {
                await wasApiFetch('/admin/settings', 'POST', payload);
                alert('Configurações master salvas com sucesso!');
            } catch (err) { alert(err.message); }
        });
    }

    async function initMasterReview() {
        const list = document.getElementById('master-review-checklist');
        if (!list) return;

        const loadChecklist = async () => {
            try {
                const data = await wasApiFetch('/admin/app-review/checklist');
                list.innerHTML = (data || []).map(i => `
                    <li class="review-item" data-key="${i.item_key}" data-label="${i.label}" data-status="${i.status}" style="cursor:pointer;">
                        <span>${i.label}</span>
                        <span class="status-indicator status-${i.status === 'done' ? 'done' : 'pending'}">
                            ${i.status.toUpperCase()}
                        </span>
                    </li>
                `).join('');

                document.querySelectorAll('.review-item').forEach(li => li.addEventListener('click', async (e) => {
                    const item = e.currentTarget.dataset;
                    const newStatus = item.status === 'done' ? 'pending' : 'done';
                    try {
                        await wasApiFetch('/admin/app-review/checklist', 'POST', {
                            item_key: item.key,
                            label: item.label,
                            status: newStatus
                        });
                        loadChecklist();
                    } catch (err) { alert(err.message); }
                }));

            } catch (err) { list.innerHTML = 'Erro ao carregar checklist.'; }
        };
        loadChecklist();
    }

    async function initMasterTokens() {
        const tb = document.getElementById('master-tokens-list');
        if (!tb) return;

        const loadTokens = async () => {
            try {
                const data = await wasApiFetch('/admin/tokens');
                tb.innerHTML = (data || []).map(k => `
                    <tr>
                        <td><strong>${k.tenant_name}</strong></td>
                        <td><code>${k.prefix}</code></td>
                        <td>${k.length} chars</td>
                        <td><span class="was-status-badge" style="background: ${k.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${k.status === 'active' ? '#166534' : '#991b1b'};">
                            ${k.status.toUpperCase()}
                        </span></td>
                        <td>${k.expires_at || 'NEVER'}</td>
                        <td><span style="color: var(--danger); font-size: 0.8rem;">${k.last_error || '-'}</span></td>
                        <td>
                            <button class="button test-token" data-id="${k.id}">Testar Token</button>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="7">Nenhum token encontrado.</td></tr>';

                document.querySelectorAll('.test-token').forEach(b => b.addEventListener('click', async (e) => {
                    const id = e.target.dataset.id;
                    try {
                        const res = await wasApiFetch(`/admin/tokens/${id}/test`, 'POST');
                        alert(res.message);
                        loadTokens();
                    } catch (err) { alert(err.message); }
                }));

            } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar tokens.</td></tr>'; }
        };
        loadTokens();
    }

    async function initMasterWebhooks() {
        const tb = document.getElementById('master-webhooks-list');
        if (!tb) return;
        const modal = document.getElementById('was-master-webhook-payload-modal');
        const payloadPre = document.getElementById('was-master-webhook-payload-json');
        const closeBtn = document.getElementById('was-master-webhook-payload-close');
        const formatPayload = (payload) => {
            if (payload && typeof payload === 'object') return JSON.stringify(payload, null, 2);
            const text = String(payload || '').trim();
            if (!text) return 'Payload não disponível para este evento.';
            try { return JSON.stringify(JSON.parse(text), null, 2); } catch (error) { return text; }
        };
        if (closeBtn) closeBtn.addEventListener('click', () => { modal.style.display = 'none'; });
        try {
            const data = await wasApiFetch('/admin/webhooks');
            tb.innerHTML = (data || []).map(w => `
                <tr>
                    <td>${w.received_at}</td>
                    <td><strong>${w.tenant_name || 'Sistema'}</strong></td>
                    <td><code>${w.event_type || 'UNKNOWN'}</code></td>
                    <td>${w.signature_valid ? '✅ Válida' : '❌ Inválida'}</td>
                    <td><span class="was-status-badge" style="background: ${w.processing_status === 'processed' ? '#dcfce7' : '#fee2e2'}; color: ${w.processing_status === 'processed' ? '#166534' : '#991b1b'};">
                        ${w.processing_status.toUpperCase()}
                    </span></td>
                    <td>
                        <button class="button view-webhook-payload" data-webhook-id="${w.id}">Payload</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhum evento recebido.</td></tr>';

            const payloads = new Map((data || []).map(webhook => [String(webhook.id), webhook.payload || webhook.normalized_payload]));
            tb.querySelectorAll('.view-webhook-payload').forEach(button => button.addEventListener('click', () => {
                if (!modal || !payloadPre) return;
                payloadPre.textContent = formatPayload(payloads.get(String(button.dataset.webhookId)));
                modal.style.display = 'block';
            }));
        } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar webhooks.</td></tr>'; }
    }

    async function initMasterTemplates() {
        const tb = document.getElementById('master-templates-list');
        if (!tb) return;

        const modal = document.getElementById('was-master-tpl-payload-modal');
        const closeBtn = document.getElementById('was-master-tpl-payload-close');

        const loadTemplates = async () => {
            try {
                const data = await wasApiFetch('/admin/templates');
                tb.innerHTML = (data || []).map(m => `
                    <tr>
                        <td><strong>${m.tenant_name}</strong></td>
                        <td>${m.name}<br><small>Meta ID: ${m.meta_template_id || '-'}</small></td>
                        <td>${m.category}</td>
                        <td>${m.language}</td>
                        <td><span class="was-status-badge" style="background: ${m.status === 'APPROVED' ? '#dcfce7' : '#fee2e2'}; color: ${m.status === 'APPROVED' ? '#166534' : '#991b1b'};">
                            ${m.status.toUpperCase()}
                        </span></td>
                        <td>
                            <button class="button view-payload" data-id="${m.id}" data-name="${m.name}">Ver Payload</button>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="6">Nenhum template encontrado.</td></tr>';

                document.querySelectorAll('.view-payload').forEach(b => b.addEventListener('click', async (e) => {
                    const { id, name } = e.currentTarget.dataset;
                    try {
                        const res = await wasApiFetch(`/admin/templates/${id}/payload`);
                        document.getElementById('master-tpl-name-title').textContent = name;
                        document.getElementById('master-tpl-friendly-pre').textContent = JSON.stringify(res.friendly_payload, null, 2);
                        document.getElementById('master-tpl-meta-pre').textContent = JSON.stringify(res.meta_payload, null, 2);
                        modal.style.display = 'block';
                    } catch (err) { alert(err.message); }
                }));

            } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar templates.</td></tr>'; }
        };

        if (closeBtn) closeBtn.addEventListener('click', () => modal.style.display = 'none');
        loadTemplates();
    }

    async function initMasterOnboardings() {
        const tb = document.getElementById('master-onboardings-list');
        if (!tb) return;
        try {
            const data = await wasApiFetch('/admin/onboardings');
            tb.innerHTML = (data || []).map(s => `
                <tr>
                    <td><strong>${s.tenant_name}</strong><br><small>Por: ${s.user_login}</small></td>
                    <td><span class="was-status-badge" style="background: #e2e8f0; color: #475569;">
                        ${s.status.toUpperCase()}
                    </span></td>
                    <td>
                        <small>WABA: ${s.waba_id || '-'}</small><br>
                        <small>Phone: ${s.phone_number_id || '-'}</small>
                    </td>
                    <td><span style="color: var(--danger); font-size: 0.8rem;">${s.error_message || '-'}</span></td>
                    <td>${s.started_at}</td>
                    <td>
                        <button class="button" onclick="alert('Ver payload bruto: ${btoa(s.raw_session_payload || '')}')">Logs</button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="6">Nenhuma sessão encontrada.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar onboardings.</td></tr>'; }
    }

    async function initMasterPhones() {
        const tb = document.getElementById('master-phones-list');
        if (!tb) return;

        const modal = document.getElementById('was-master-test-msg-modal');
        const form = document.getElementById('was-master-test-msg-form');
        const cancelBtn = document.getElementById('was-master-test-msg-cancel');
        const details = document.getElementById('was-master-phone-details');
        const detailTitle = document.getElementById('was-master-phone-title');
        const detailMeta = document.getElementById('was-master-phone-meta');
        const routesList = document.getElementById('was-master-routes-list');
        const templatesList = document.getElementById('was-master-phone-templates-list');
        const routeForm = document.getElementById('was-master-route-form');
        const syncTemplatesBtn = document.getElementById('was-master-sync-phone-templates');
        const onboardingTenant = document.getElementById('master-onboarding-tenant');
        const onboardingPhone = document.getElementById('master-onboarding-phone');
        const onboardingButton = document.getElementById('master-start-onboarding');
        const onboardingStatus = document.getElementById('master-onboarding-status');
        let selectedPhoneId = null;
        let onboardingTimer = null;

        const escape = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));

        const showOnboardingStatus = (message, color = '#475569') => {
            if (!onboardingStatus) return;
            onboardingStatus.style.display = 'block';
            onboardingStatus.style.padding = '12px 14px';
            onboardingStatus.style.borderLeft = `4px solid ${color}`;
            onboardingStatus.style.background = '#f8fafc';
            onboardingStatus.textContent = message;
        };

        const stopOnboardingPolling = () => {
            if (onboardingTimer) {
                clearTimeout(onboardingTimer);
                onboardingTimer = null;
            }
        };

        const pollOnboarding = async (tenantId, attemptId, tries = 0) => {
            if (tries > 45) {
                showOnboardingStatus('Cadastro recebido. A reconciliação ainda está em andamento; atualize a lista em alguns instantes.', '#a16207');
                return;
            }
            try {
                const status = await wasApiFetch(`/admin/phone-numbers/onboarding/attempts/${encodeURIComponent(attemptId)}?tenant_id=${encodeURIComponent(tenantId)}&refresh=1`);
                if (status.status === 'completed') {
                    stopOnboardingPolling();
                    showOnboardingStatus('Número cadastrado com sucesso. Atualizando a lista de números...', '#15803d');
                    await loadPhones();
                    return;
                }
                if (['failed', 'expired', 'cancelled'].includes(status.status)) {
                    stopOnboardingPolling();
                    showOnboardingStatus(status.last_error || 'A Meta não concluiu o cadastro.', '#b91c1c');
                    return;
                }
                onboardingTimer = setTimeout(() => pollOnboarding(tenantId, attemptId, tries + 1), 2000);
            } catch (error) {
                onboardingTimer = setTimeout(() => pollOnboarding(tenantId, attemptId, tries + 1), 2500);
            }
        };

        const renderRoutes = (routes = []) => {
            if (!routesList) return;
            routesList.innerHTML = routes.length ? routes.map(route => `
                <div style="display:flex; justify-content:space-between; gap:16px; align-items:center; padding:12px; margin:8px 0; border:1px solid #dcdcde; border-radius:6px;">
                    <div>
                        <strong>${escape(route.name || 'Rota')}</strong><br>
                        <code>${escape(route.target_url)}</code><br>
                        <small>${route.is_active ? 'Ativa' : 'Desativada'} · ${route.max_retries} tentativas · timeout ${route.timeout_ms}ms</small>
                    </div>
                    <button type="button" class="button was-delete-route" data-id="${route.id}">Desativar</button>
                </div>`).join('') : '<p>Nenhuma rota configurada para este número.</p>';

            routesList.querySelectorAll('.was-delete-route').forEach(button => button.addEventListener('click', async () => {
                if (!confirm('Desativar esta rota?')) return;
                try {
                    await wasApiFetch(`/admin/routes/${button.dataset.id}`, 'DELETE');
                    await loadPhoneDetails(selectedPhoneId);
                } catch (error) { alert(error.message); }
            }));
        };

        const renderTemplates = (templates = []) => {
            if (!templatesList) return;
            templatesList.innerHTML = templates.length ? `
                <table class="wp-list-table widefat fixed striped"><thead><tr><th>Nome</th><th>Categoria</th><th>Idioma</th><th>Status</th><th>Meta ID</th></tr></thead><tbody>
                ${templates.map(template => `<tr><td><strong>${escape(template.name)}</strong></td><td>${escape(template.category)}</td><td>${escape(template.language)}</td><td>${escape(template.status)}</td><td><code>${escape(template.meta_template_id || '-')}</code></td></tr>`).join('')}
                </tbody></table>` : '<p>Nenhum template sincronizado para este número.</p>';
        };

        const loadPhoneDetails = async (phoneId) => {
            if (!phoneId || !details) return;
            selectedPhoneId = phoneId;
            try {
                const data = await wasApiFetch(`/admin/phone-numbers/${phoneId}/details`);
                const phone = data.phone || {};
                detailTitle.textContent = phone.display_phone_number || 'Número WhatsApp';
                detailMeta.innerHTML = `<strong>Tenant:</strong> ${escape(phone.tenant_name || phone.tenant_id)} · <strong>WABA:</strong> ${escape(phone.waba_name || '-') } (${escape(phone.waba_id || '-')}) · <strong>Phone ID:</strong> ${escape(phone.phone_number_id)}`;
                renderRoutes(data.routes || []);
                renderTemplates(data.templates || []);
                details.style.display = 'block';
                details.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) { alert(error.message); }
        };

        const loadPhones = async () => {
            try {
                const data = await wasApiFetch('/admin/phone-numbers');
                tb.innerHTML = (data || []).map(p => `
                    <tr class="was-master-phone-row" data-id="${p.id}" style="cursor:pointer;">
                        <td><strong>${p.tenant_name}</strong><br><small>ID: ${p.tenant_id}</small></td>
                        <td><strong>${p.waba_name || '-'}</strong><br><small>${p.waba_id || '-'}</small></td>
                        <td><code>${p.phone_number_id}</code></td>
                        <td>${p.display_phone_number || '-'}</td>
                        <td>${p.verified_name || '-'}</td>
                        <td><span class="was-status-badge" style="background: ${p.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${p.status === 'active' ? '#166534' : '#991b1b'};">
                            ${p.status.toUpperCase()}
                        </span></td>
                        <td>${p.quality_rating || 'UNKNOWN'}</td>
                        <td>
                            <button class="button test-msg" data-id="${p.id}">Testar Envio</button>
                            <button class="button button-primary open-phone-chat" data-phone-id="${p.phone_number_id}" data-tenant-id="${p.tenant_id}" data-phone-display="${p.display_phone_number || ''}">Abrir chat</button>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="8">Nenhum número encontrado.</td></tr>';

                document.querySelectorAll('.test-msg').forEach(b => b.addEventListener('click', (e) => {
                    e.stopPropagation();
                    document.getElementById('master-test-phone-id').value = e.target.dataset.id;
                    modal.style.display = 'block';
                }));
                document.querySelectorAll('.open-phone-chat').forEach(b => b.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const button = e.currentTarget;
                    const params = new URLSearchParams({
                        was_phone_number_id: button.dataset.phoneId || '',
                        was_tenant_id: button.dataset.tenantId || '',
                        was_phone_display: button.dataset.phoneDisplay || ''
                    });
                    window.location.href = `${wasApp.adminUrl}inbox&${params.toString()}`;
                }));
                document.querySelectorAll('.was-master-phone-row').forEach(row => row.addEventListener('click', () => loadPhoneDetails(row.dataset.id)));

            } catch (err) { tb.innerHTML = '<tr><td colspan="8">Erro ao carregar números.</td></tr>'; }
        };

        const loadOnboardingTenants = async () => {
            if (!onboardingTenant) return;
            try {
                const tenants = await wasApiFetch('/admin/tenants');
                onboardingTenant.innerHTML = '<option value="">Selecione o tenant</option>' + (tenants || []).filter(tenant => tenant.status === 'active').map(tenant => `<option value="${tenant.id}">${escape(tenant.name)} (#${tenant.id})</option>`).join('');
            } catch (error) {
                onboardingTenant.innerHTML = '<option value="">Erro ao carregar tenants</option>';
            }
        };

        if (cancelBtn) cancelBtn.addEventListener('click', () => modal.style.display = 'none');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('master-test-phone-id').value;
            const to = document.getElementById('master-test-to').value;
            try {
                const res = await wasApiFetch(`/admin/phone-numbers/${id}/test-message`, 'POST', { to: to });
                alert(res.message);
                modal.style.display = 'none';
            } catch (err) { alert(err.message); }
        });

        document.querySelectorAll('.was-phone-detail-tab').forEach(tab => tab.addEventListener('click', () => {
            const routes = tab.dataset.tab === 'routes';
            document.getElementById('was-phone-detail-routes').style.display = routes ? 'block' : 'none';
            document.getElementById('was-phone-detail-templates').style.display = routes ? 'none' : 'block';
            document.querySelectorAll('.was-phone-detail-tab').forEach(item => item.classList.toggle('button-primary', item === tab));
        }));

        document.getElementById('was-master-phone-close')?.addEventListener('click', () => {
            details.style.display = 'none';
            selectedPhoneId = null;
        });

        routeForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!selectedPhoneId) return;
            try {
                await wasApiFetch(`/admin/phone-numbers/${selectedPhoneId}/routes`, 'POST', {
                    name: document.getElementById('master-route-name').value,
                    target_url: document.getElementById('master-route-url').value,
                    secret: document.getElementById('master-route-secret').value,
                    is_active: true
                });
                routeForm.reset();
                await loadPhoneDetails(selectedPhoneId);
                alert('Rota criada. Os próximos webhooks deste número serão encaminhados para a URL externa.');
            } catch (error) { alert(error.message); }
        });

        syncTemplatesBtn?.addEventListener('click', async () => {
            if (!selectedPhoneId) return;
            syncTemplatesBtn.disabled = true;
            try {
                const result = await wasApiFetch(`/admin/phone-numbers/${selectedPhoneId}/templates/sync`, 'POST');
                await loadPhoneDetails(selectedPhoneId);
                alert(result.message || 'Templates sincronizados.');
            } catch (error) { alert(error.message); } finally { syncTemplatesBtn.disabled = false; }
        });

        onboardingButton?.addEventListener('click', async () => {
            const tenantId = onboardingTenant?.value;
            const phone = onboardingPhone?.value.trim();
            if (!tenantId || !phone) {
                showOnboardingStatus('Selecione um tenant e informe o número WhatsApp.', '#b91c1c');
                return;
            }

            stopOnboardingPolling();
            onboardingButton.disabled = true;
            onboardingButton.textContent = 'Iniciando...';
            const popup = window.open('about:blank', 'was_meta_signup', 'width=600,height=700');
            try {
                const start = await wasApiFetch('/admin/phone-numbers/onboarding/start', 'POST', {
                    tenant_id: tenantId,
                    phone_number: phone
                });
                if (!start.attempt_id || !start.authorization_url) throw new Error(start.message || 'Cadastro incorporado não configurado.');
                if (!popup) throw new Error('O navegador bloqueou a janela. Permita pop-ups e tente novamente.');
                popup.location = start.authorization_url;
                showOnboardingStatus('Aguardando a conclusão do Cadastro Incorporado da Meta...', '#a16207');
                pollOnboarding(tenantId, start.attempt_id);
            } catch (error) {
                if (popup) popup.close();
                showOnboardingStatus(error.message, '#b91c1c');
            } finally {
                onboardingButton.disabled = false;
                onboardingButton.textContent = 'Cadastro incorporado';
            }
        });

        loadOnboardingTenants();
        loadPhones();
    }

    async function initMasterWabas() {
        const tb = document.getElementById('master-wabas-list');
        if (!tb) return;

        const loadWabas = async () => {
            try {
                const data = await wasApiFetch('/admin/wabas');
                tb.innerHTML = (data || []).map(w => `
                    <tr>
                        <td><strong>${w.tenant_name}</strong><br><small>ID: ${w.tenant_id}</small></td>
                        <td><code>${w.waba_id}</code></td>
                        <td>${w.name || '-'}</td>
                        <td><span class="was-status-badge" style="background: ${w.status === 'connected' ? '#dcfce7' : '#fee2e2'}; color: ${w.status === 'connected' ? '#166534' : '#991b1b'};">
                            ${w.status.toUpperCase()}
                        </span></td>
                        <td>${w.webhook_subscription_status || 'UNKNOWN'}</td>
                        <td>${w.created_at}</td>
                        <td>
                            <button class="button waba-action" data-id="${w.id}" data-action="sync-templates">Sync Templates</button>
                            <button class="button waba-action" data-id="${w.id}" data-action="subscribe-webhooks">Inscrever Webhook</button>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="7">Nenhuma WABA encontrada.</td></tr>';

                document.querySelectorAll('.waba-action').forEach(b => b.addEventListener('click', async (e) => {
                    const { id, action } = e.currentTarget.dataset;
                    const btn = e.currentTarget;
                    const originalText = btn.textContent;
                    btn.textContent = 'Processando...';
                    btn.disabled = true;

                    try {
                        const res = await wasApiFetch(`/admin/wabas/${id}/${action}`, 'POST');
                        alert(res.message);
                        loadWabas();
                    } catch (err) { alert(err.message); } finally {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                }));

            } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar WABAs.</td></tr>'; }
        };
        loadWabas();
    }

    async function initMasterApps() {
        const tb = document.getElementById('master-apps-list');
        if (!tb) return;

        const modal = document.getElementById('was-master-app-modal');
        const form = document.getElementById('was-master-app-form');
        const addBtn = document.getElementById('master-btn-add-app');
        const cancelBtn = document.getElementById('was-master-app-cancel');

        const loadApps = async () => {
            try {
                const data = await wasApiFetch('/admin/meta-apps');
                tb.innerHTML = (data || []).map(a => `
                    <tr>
                        <td><strong>${a.name}</strong></td>
                        <td><code>${a.app_id}</code></td>
                        <td>${a.graph_version}</td>
                        <td>${a.config_id || '-'}</td>
                        <td>${a.environment.toUpperCase()}</td>
                        <td><span class="was-status-badge" style="background: ${a.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${a.status === 'active' ? '#166534' : '#991b1b'};">
                            ${a.status.toUpperCase()}
                        </span></td>
                        <td>
                            <button class="button edit-app" data-app='${JSON.stringify(a)}'>Editar</button>
                            <button class="button test-app" data-id="${a.id}">Testar</button>
                            <button class="button delete-app" data-id="${a.id}" style="color:red;">Apagar</button>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="7">Nenhum app cadastrado.</td></tr>';

                document.querySelectorAll('.edit-app').forEach(b => b.addEventListener('click', (e) => {
                    const app = JSON.parse(e.target.dataset.app);
                    document.getElementById('master-app-modal-title').textContent = 'Editar App Meta';
                    document.getElementById('master-app-id').value = app.id;
                    document.getElementById('master-app-name').value = app.name;
                    document.getElementById('master-app-appid').value = app.app_id;
                    document.getElementById('master-app-secret').value = '';
                    document.getElementById('master-app-env').value = app.environment;
                    modal.style.display = 'block';
                }));

                document.querySelectorAll('.test-app').forEach(b => b.addEventListener('click', async (e) => {
                    try {
                        const res = await wasApiFetch(`/admin/meta-apps/${e.target.dataset.id}`, 'POST', { action: 'test' });
                        alert(res.message);
                    } catch (err) { alert(err.message); }
                }));

                document.querySelectorAll('.delete-app').forEach(b => b.addEventListener('click', async (e) => {
                    if (!confirm('Deseja realmente apagar este app?')) return;
                    try {
                        await wasApiFetch(`/admin/meta-apps/${e.target.dataset.id}`, 'DELETE');
                        loadApps();
                    } catch (err) { alert(err.message); }
                }));

            } catch (err) { tb.innerHTML = '<tr><td colspan="7">Erro ao carregar apps.</td></tr>'; }
        };

        if (addBtn) addBtn.addEventListener('click', () => {
            document.getElementById('master-app-modal-title').textContent = 'Novo App Meta';
            form.reset();
            document.getElementById('master-app-id').value = '';
            modal.style.display = 'block';
        });

        if (cancelBtn) cancelBtn.addEventListener('click', () => modal.style.display = 'none');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                id: document.getElementById('master-app-id').value,
                name: document.getElementById('master-app-name').value,
                app_id: document.getElementById('master-app-appid').value,
                app_secret: document.getElementById('master-app-secret').value,
                environment: document.getElementById('master-app-env').value
            };
            try {
                await wasApiFetch('/admin/meta-apps', 'POST', payload);
                modal.style.display = 'none';
                loadApps();
            } catch (err) { alert(err.message); }
        });

        loadApps();
    }

    async function initMasterTenants() {
        const tb = document.getElementById('master-tenants-list');
        if (!tb) return;

        const modal = document.getElementById('was-master-tenant-modal');
        const form = document.getElementById('was-master-tenant-form');
        const addBtn = document.getElementById('master-btn-add-tenant');
        const cancelBtn = document.getElementById('was-master-tenant-cancel');

        const loadTenants = async () => {
            try {
                const data = await wasApiFetch('/admin/tenants');
                tb.innerHTML = (data || []).map(t => `
                    <tr>
                        <td><strong>${t.name}</strong><br><small>ID: ${t.id}</small></td>
                        <td><code>${t.slug}</code></td>
                        <td>${t.plan.toUpperCase()}</td>
                        <td><span class="was-status-badge" style="background: ${t.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${t.status === 'active' ? '#166534' : '#991b1b'};">
                            ${t.status.toUpperCase()}
                        </span></td>
                        <td>${t.created_at}</td>
                        <td>
                            <button class="button edit-tenant" data-tenant='${JSON.stringify(t)}'>Editar</button>
                            <button class="button toggle-status" data-id="${t.id}" data-status="${t.status}">${t.status === 'active' ? 'Bloquear' : 'Ativar'}</button>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="6">Nenhum cliente encontrado.</td></tr>';

                document.querySelectorAll('.edit-tenant').forEach(b => b.addEventListener('click', (e) => {
                    const t = JSON.parse(e.target.dataset.tenant);
                    document.getElementById('master-tenant-modal-title').textContent = 'Editar Cliente';
                    document.getElementById('master-tenant-id').value = t.id;
                    document.getElementById('master-tenant-name').value = t.name;
                    document.getElementById('master-tenant-slug').value = t.slug;
                    document.getElementById('master-tenant-plan').value = t.plan;
                    modal.style.display = 'block';
                }));

                document.querySelectorAll('.toggle-status').forEach(b => b.addEventListener('click', async (e) => {
                    const newStatus = e.target.dataset.status === 'active' ? 'blocked' : 'active';
                    if (!confirm(`Deseja realmente ${newStatus === 'active' ? 'ativar' : 'bloquear'} este cliente?`)) return;
                    try {
                        await wasApiFetch(`/admin/tenants/${e.target.dataset.id}/status`, 'POST', { status: newStatus });
                        loadTenants();
                    } catch (err) { alert(err.message); }
                }));

            } catch (err) { tb.innerHTML = '<tr><td colspan="6">Erro ao carregar clientes.</td></tr>'; }
        };

        if (addBtn) addBtn.addEventListener('click', () => {
            document.getElementById('master-tenant-modal-title').textContent = 'Novo Cliente';
            form.reset();
            document.getElementById('master-tenant-id').value = '';
            modal.style.display = 'block';
        });

        if (cancelBtn) cancelBtn.addEventListener('click', () => modal.style.display = 'none');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                id: document.getElementById('master-tenant-id').value,
                name: document.getElementById('master-tenant-name').value,
                slug: document.getElementById('master-tenant-slug').value,
                plan: document.getElementById('master-tenant-plan').value
            };
            try {
                await wasApiFetch('/admin/tenants', 'POST', payload);
                modal.style.display = 'none';
                loadTenants();
            } catch (err) { alert(err.message); }
        });

        loadTenants();
    }

    async function initMasterDashboard() {
        try {
            const data = await wasApiFetch('/admin/overview');
            const mapping = {
                'master-stat-tenants': data.tenants,
                'master-stat-wabas': data.wabas,
                'master-stat-phones': data.phones,
                'master-stat-templates': data.templates,
                'master-stat-webhooks': data.webhooks_today,
                'master-stat-onboarding-failures': data.onboarding_failures
            };
            for (const [id, value] of Object.entries(mapping)) {
                const el = document.getElementById(id);
                if (el) el.textContent = value ?? 0;
            }

            // Mocked Alerts for NOC feel
            const alertsList = document.getElementById('master-alerts-list');
            if (alertsList) {
                alertsList.innerHTML = `
                    <tr>
                        <td><span style="color: var(--danger);">CRÍTICO</span></td>
                        <td>Webhooks</td>
                        <td>Nenhum evento recebido nas últimas 2h.</td>
                        <td><button class="button">Verificar</button></td>
                    </tr>
                    <tr>
                        <td><span style="color: var(--warning);">AVISO</span></td>
                        <td>Templates</td>
                        <td>3 templates rejeitados recentemente.</td>
                        <td><button class="button">Ver Logs</button></td>
                    </tr>
                `;
            }

        } catch (err) { console.error('Master Dashboard Error:', err); }
    }

    async function initDashboard() {
        try {
            const data = await wasApiFetch('/dashboard');
            const mapping = {
                'stat-wa-accounts': data.whatsapp_accounts,
                'stat-active-numbers': data.active_numbers,
                'stat-messages-today': data.messages_today,
                'stat-open-conversations': data.open_conversations,
                'stat-templates': data.templates
            };
            for (const [id, value] of Object.entries(mapping)) {
                const el = document.getElementById(id);
                if (el) el.textContent = value ?? 0;
            }
        } catch (err) { console.error('Dashboard Error:', err); }
    }

    /**
     * Inbox
     */
    if (document.getElementById('was-conversations-list')) {
        initInbox();
    }

    function inboxScopeQuery(separator = '?') {
        const scope = window.wasInboxScope || {};
        const params = new URLSearchParams();
        if (scope.tenantId) params.set('tenant_id', scope.tenantId);
        if (scope.phoneNumberId) params.set('phone_number_id', scope.phoneNumberId);
        const query = params.toString();
        return query ? `${separator}${query}` : '';
    }

    function initInbox() {
        const btnRefresh = document.getElementById('was-refresh-conversations');
        const sendBtn = document.getElementById('was-send-message');
        const inputField = document.getElementById('was-message-input');
        const openTplBtn = document.getElementById('was-open-templates-inbox');
        const pageParams = new URLSearchParams(window.location.search);
        window.wasInboxScope = {
            tenantId: pageParams.get('was_tenant_id') || '',
            phoneNumberId: pageParams.get('was_phone_number_id') || ''
        };
        const phoneContext = document.getElementById('was-inbox-phone-context');
        if (phoneContext && window.wasInboxScope.phoneNumberId) {
            phoneContext.textContent = `Número: ${pageParams.get('was_phone_display') || window.wasInboxScope.phoneNumberId}`;
        }

        if (btnRefresh) btnRefresh.addEventListener('click', fetchConversations);
        if (inputField && sendBtn) {
            inputField.addEventListener('input', () => {
                sendBtn.disabled = inputField.value.trim() === '';
                
                // Typing Indicator Inteligente
                if (inputField.value.trim().length > 0) {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => {
                        sendTypingIndicator();
                    }, 800);
                }
            });
            inputField.addEventListener('keypress', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
            sendBtn.addEventListener('click', sendMessage);
        }
        if (openTplBtn) openTplBtn.addEventListener('click', () => { if (!currentConversationId) return alert('Selecione uma conversa.'); openInboxTplModal(); });
        
        const closeTplBtn = document.getElementById('was-close-inbox-tpl-modal');
        if (closeTplBtn) closeTplBtn.addEventListener('click', () => {
            const modal = document.getElementById('was-inbox-tpl-modal');
            if (modal) modal.style.display = 'none';
        });

        const clearReplyBtn = document.getElementById('was-clear-reply');
        if (clearReplyBtn) clearReplyBtn.addEventListener('click', clearReplyContext);

        window.addEventListener('click', (e) => {
            const modal = document.getElementById('was-inbox-tpl-modal');
            if (e.target === modal) modal.style.display = 'none';
        });

        initAttachmentLogic();
        fetchConversations();
        startConvListPolling();
    }

    function initAttachmentLogic() {
        const attachBtn = document.getElementById('was-attach-media');
        const menu = document.getElementById('was-attachment-menu');
        const fileInput = document.getElementById('was-media-input');

        if (!attachBtn || !menu || !fileInput) return;

        attachBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const isHidden = menu.style.display === 'none' || menu.style.display === '';
            menu.style.display = isHidden ? 'block' : 'none';
        });

        document.addEventListener('click', () => menu.style.display = 'none');

        document.querySelectorAll('.was-attach-item').forEach(item => {
            item.addEventListener('click', () => {
                const type = item.dataset.type;
                fileInput.dataset.targetType = type;
                
                // Ajustar accept do input
                if (type === 'image') fileInput.accept = 'image/*';
                else if (type === 'audio') fileInput.accept = 'audio/*';
                else if (type === 'video') fileInput.accept = 'video/*';
                else fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.txt';

                fileInput.click();
            });
        });

        fileInput.addEventListener('change', async (e) => {
            if (!fileInput.files.length || !currentConversationId) return;
            
            const file = fileInput.files[0];
            const mediaType = fileInput.dataset.targetType;
            let caption = '';

            if (mediaType !== 'audio') {
                caption = prompt('Adicionar uma legenda? (Opcional)', '');
            }

            try {
                // Feedback visual temporário
                const history = document.getElementById('was-messages-history');
                const tempId = 'temp-' + Date.now();
                const tempDiv = document.createElement('div');
                tempDiv.id = tempId;
                tempDiv.className = 'was-message was-message-out';
                tempDiv.innerHTML = `<div class="was-message-content"><div class="was-loading-media">Enviando ${mediaType}...</div></div>`;
                history.appendChild(tempDiv);
                scrollToBottom();

                const formData = new FormData();
                formData.append('file', file);
                if (caption) formData.append('caption', caption);
                if (replyToMessageId) formData.append('reply_to_message_id', replyToMessageId);

                console.log('Starting fetch to:', `${wasApp.restUrl}/conversations/${currentConversationId}/messages/${mediaType}${inboxScopeQuery()}`);
                const res = await fetch(`${wasApp.restUrl}/conversations/${currentConversationId}/messages/${mediaType}${inboxScopeQuery()}`, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': wasApp.nonce },
                    body: formData
                });

                console.log('Response status:', res.status, res.statusText);
                
                let data;
                const contentType = res.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    data = await res.json();
                } else {
                    const rawText = await res.text();
                    console.error('Non-JSON response received:', rawText);
                    throw new Error(`Servidor retornou resposta inválida (Status ${res.status}). Verifique os logs do PHP.`);
                }

                tempDiv.remove();
                console.log('API Data received:', data);

                if (data.success) {
                    clearReplyContext();
                    loadConversation(currentConversationId, document.getElementById('was-chat-contact-name').textContent);
                } else {
                    console.error('API reported failure:', data);
                    const errorMsg = data.message || (data.data && data.data.message) || data.error || 'Erro desconhecido no servidor.';
                    alert(`Falha no envio (API): ${errorMsg}`);
                }

            } catch (err) {
                console.error('Detailed connection/process error:', err);
                if (typeof tempDiv !== 'undefined' && tempDiv) tempDiv.remove();
                alert(`Erro Crítico: ${err.message || 'Falha ao processar arquivo.'}`);
            } finally {
                fileInput.value = '';
            }
        });
    }

    async function fetchConversations() {
        const listContainer = document.getElementById('was-conversations-list');
        if (!listContainer) return;
        listContainer.innerHTML = '<div class="was-loading-state">Carregando...</div>';
        try {
            const response = await wasApiFetch(`/conversations${inboxScopeQuery()}`);
            const conversations = response.data || [];
            listContainer.innerHTML = '';
            if (conversations.length === 0) { listContainer.innerHTML = '<div class="was-empty-state">Vazio</div>'; return; }
            conversations.forEach(conv => {
                const item = document.createElement('div');
                item.className = 'was-conversation-item';
                const name = conv.profile_name || conv.wa_id || 'Contato';
                item.innerHTML = `<div class="was-conv-avatar">👤</div><div class="was-conv-details"><strong>${name}</strong><span>${conv.wa_id}</span></div>`;
                item.addEventListener('click', () => loadConversation(conv.id, name));
                listContainer.appendChild(item);
            });
        } catch (err) { listContainer.innerHTML = `<div class="was-error-state">${err.message}</div>`; }
    }

    async function loadConversation(id, contactName) {
        // Parar polling anterior
        stopChatPolling();
        if (windowStatusTimer) clearTimeout(windowStatusTimer);

        currentConversationId = id;
        lastMessageId = 0;
        lastInboundMessageId = null;
        lastTypingSentAt = 0;
        document.getElementById('was-no-conversation-selected').style.display = 'none';
        document.getElementById('was-active-chat').style.display = 'flex';
        document.getElementById('was-chat-contact-name').textContent = contactName;
        const historyContainer = document.getElementById('was-messages-history');
        historyContainer.innerHTML = '<div class="was-loading-state">...</div>';
        try {
            const response = await wasApiFetch(`/conversations/${id}${inboxScopeQuery()}`);
            const messages = response.data?.messages || [];
            historyContainer.innerHTML = '';
            messages.forEach(msg => {
                const text = msg.text_body || msg.body || '';
                const payload = (msg.message_type === 'template' && msg.raw_payload) ? JSON.parse(msg.raw_payload) : null;
                renderMessage(text, msg.direction, msg.message_type, payload, msg.media_url, msg.media_filename, msg.created_at, msg.reply_preview, msg.id, msg.referral);
                // Rastrear último ID para polling
                if (msg.id && parseInt(msg.id) > lastMessageId) lastMessageId = parseInt(msg.id);
                // Rastrear última inbound para typing indicator
                if (msg.direction === 'inbound') lastInboundMessageId = msg.id;
            });
            scrollToBottom();

            // Iniciar polling em tempo real
            startChatPolling();
            updateWindowStatus(id);
        } catch (err) { historyContainer.innerHTML = 'Erro ao carregar histórico.'; }
    }

    async function updateWindowStatus(id) {
        if (!id || id !== currentConversationId) return;
        try {
            const data = await wasApiFetch(`/conversations/${id}/window${inboxScopeQuery()}`);
            currentWindowData = data;
            renderWindowStatus(data);

            // Reagenda atualização (cada 30s se aberta, ou se estiver mudando de estado)
            if (windowStatusTimer) clearTimeout(windowStatusTimer);
            windowStatusTimer = setTimeout(() => updateWindowStatus(currentConversationId), 30000);
        } catch (err) { console.error('Erro ao atualizar janela:', err); }
    }

    function renderWindowStatus(data) {
        const banner = document.getElementById('was-chat-window-status');
        const input = document.getElementById('was-message-input');
        const attachBtn = document.getElementById('was-attach-media');
        if (!banner) return;

        const icon = banner.querySelector('.was-window-icon');
        const desc = banner.querySelector('.was-window-desc');
        const timer = banner.querySelector('.was-window-timer');

        banner.style.display = 'flex';
        banner.className = 'was-window-status-banner ' + (data.status || 'closed');

        if (data.is_open) {
            icon.innerHTML = '✅';
            desc.textContent = 'Janela aberta: Você pode enviar mensagens livres.';
            timer.textContent = `Expira em: ${data.human_remaining}`;
            input.disabled = false;
            input.placeholder = 'Digite uma mensagem...';
            if (attachBtn) attachBtn.disabled = false;
        } else {
            icon.innerHTML = '🔒';
            desc.textContent = 'Janela fechada: Envie um modelo (template) para retomar o contato.';
            timer.textContent = '';
            input.disabled = true;
            input.value = '';
            input.placeholder = 'Janela fechada. Use um template.';
            if (attachBtn) attachBtn.disabled = true;
        }
    }

    function startChatPolling() {
        stopChatPolling();
        chatPollTimer = setInterval(pollNewMessages, CHAT_POLL_INTERVAL);
    }

    function stopChatPolling() {
        if (chatPollTimer) {
            clearInterval(chatPollTimer);
            chatPollTimer = null;
        }
    }

    async function pollNewMessages() {
        if (!currentConversationId || !lastMessageId) return;
        try {
            const res = await wasApiFetch(`/conversations/${currentConversationId}/poll?after_id=${lastMessageId}${inboxScopeQuery('&')}`);
            const newMsgs = res.data || [];
            if (newMsgs.length === 0) return;

            const history = document.getElementById('was-messages-history');
            const isAtBottom = history.scrollTop + history.clientHeight >= history.scrollHeight - 60;

            newMsgs.forEach(msg => {
                const text = msg.text_body || msg.body || '';
                const payload = (msg.message_type === 'template' && msg.raw_payload) ? JSON.parse(msg.raw_payload) : null;
                renderMessage(text, msg.direction, msg.message_type, payload, msg.media_url, msg.media_filename, msg.created_at, msg.reply_preview, msg.id, msg.referral);
                if (msg.id && parseInt(msg.id) > lastMessageId) lastMessageId = parseInt(msg.id);
                if (msg.direction === 'inbound') lastInboundMessageId = msg.id;
            });

            // Auto-scroll só se já estava no fundo
            if (isAtBottom) scrollToBottom();

            // Notificação visual se é mensagem recebida
            const hasInbound = newMsgs.some(m => m.direction === 'inbound');
            if (hasInbound) {
                document.title = '💬 Nova mensagem! — WhatsApp SaaS';
                setTimeout(() => { document.title = 'WhatsApp SaaS'; }, 3000);
                // Renovar janela visualmente
                updateWindowStatus(currentConversationId);
            }
        } catch (err) {
            // Silenciar erros de polling para não poluir a experiência
            console.debug('Poll error:', err.message);
        }
    }

    function startConvListPolling() {
        stopConvListPolling();
        convListPollTimer = setInterval(fetchConversations, CONV_LIST_POLL_INTERVAL);
    }

    function stopConvListPolling() {
        if (convListPollTimer) {
            clearInterval(convListPollTimer);
            convListPollTimer = null;
        }
    }

    async function sendMessage() {
        if (!currentConversationId) return;
        const inputField = document.getElementById('was-message-input');
        const sendBtn = document.getElementById('was-send-message');
        const body = inputField.value.trim();
        if (!body) return;
        inputField.disabled = true; sendBtn.disabled = true;
        try {
            // Reset Typing State
            lastMessageSentAt = Date.now();
            lastTypingSentAt = 0;
            
            const res = await wasApiFetch(`/conversations/${currentConversationId}/messages/text${inboxScopeQuery()}`, 'POST', {
                text: body,
                reply_to_message_id: replyToMessageId
            });
            if (res.success) {
                // Usar o objeto completo retornado pela API (res.data) para renderizar a mensagem com citação correta
                const msgData = res.data || {};
                renderMessage(
                    msgData.text_body || body, 
                    'outbound', 
                    'text', 
                    null, 
                    null, 
                    null, 
                    msgData.created_at || new Date().toISOString(), 
                    msgData.reply_preview || null, 
                    msgData.id
                );
                inputField.value = '';
                if (msgData.id && parseInt(msgData.id) > lastMessageId) {
                    lastMessageId = parseInt(msgData.id);
                }
                clearReplyContext();
                scrollToBottom();
                lastTypingSentAt = 0; // Reset typing indicator throttle after sending
                pollNewMessages();    // Immediate poll after send to keep state synced
            } else {
                const errorMsg = res.message || (res.data && res.data.message) || 'Erro ao enviar.';
                alert(`Falha no envio: ${errorMsg}`);
            }
        } catch (err) { alert(`Erro na conexão: ${err.message || 'Erro desconhecido'}`); }
        finally { inputField.disabled = false; sendBtn.disabled = false; inputField.focus(); }
    }

    function setReplyContext(messageId, text, author) {
        replyToMessageId = messageId;
        const preview = document.getElementById('was-composer-reply');
        const userEl = document.getElementById('was-reply-preview-user');
        const textEl = document.getElementById('was-reply-preview-text');
        
        if (preview && userEl && textEl) {
            userEl.textContent = `Respondendo a ${author}`;
            textEl.textContent = text.length > 60 ? text.substring(0, 60) + '...' : text;
            preview.style.display = 'block';
            document.getElementById('was-message-input')?.focus();
        }
    }

    function clearReplyContext() {
        replyToMessageId = null;
        const preview = document.getElementById('was-composer-reply');
        if (preview) preview.style.display = 'none';
    }

    function jumpToMessage(messageId) {
        const target = document.querySelector(`.was-message[data-id="${messageId}"]`);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('was-message-highlight');
            setTimeout(() => target.classList.remove('was-message-highlight'), 2000);
        }
    }

    function renderMessage(text, direction, type = 'text', payload = null, mediaUrl = null, mediaFilename = null, timestamp = null, replyPreview = null, messageId = null, referral = null) {
        const history = document.getElementById('was-messages-history');
        if (!history) return;

        // Evitar duplicados se já estiver na tela
        if (messageId && document.querySelector(`.was-message[data-id="${messageId}"]`)) {
            return;
        }

        // Formatar hora usando o fuso horário configurado no WordPress
        const tz = wasApp.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
        let timeStr = '';
        if (timestamp) {
            // Timestamps do banco vêm em UTC. Se não termina com Z ou offset, adicionar Z.
            let ts = timestamp;
            if (!ts.endsWith('Z') && !ts.includes('+') && !ts.includes('T')) {
                ts = ts.replace(' ', 'T') + 'Z';
            } else if (!ts.endsWith('Z') && !ts.includes('+')) {
                ts = ts + 'Z';
            }
            const date = new Date(ts);
            timeStr = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: tz });
        } else {
            timeStr = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: tz });
        }

        const msgDiv = document.createElement('div');
        msgDiv.className = `was-message ${direction === 'outbound' || direction === 'sent' ? 'was-message-out' : 'was-message-in'}`;
        if (messageId) msgDiv.dataset.id = messageId;

        const contentDiv = document.createElement('div');
        contentDiv.className = 'was-message-content';

        // 1. Renderizar Referral Card (Se veio de anúncio)
        if (referral) {
            const refCard = document.createElement('div');
            refCard.className = 'was-referral-card';
            refCard.innerHTML = `
                <span class="was-referral-badge">Veio de Anúncio</span>
                ${referral.image_url ? `<img src="${referral.image_url}" class="was-referral-media">` : ''}
                <div class="was-referral-content">
                    <span class="was-referral-headline">${referral.headline || 'Anúncio do Facebook'}</span>
                    <span class="was-referral-body">${referral.body || ''}</span>
                </div>
            `;
            contentDiv.appendChild(refCard);
        }

        // 2. Bloco de Resposta (Citação)
        if (replyPreview) {
            const replyBox = document.createElement('div');
            replyBox.className = 'was-reply-box';
            const author = replyPreview.direction === 'outbound' ? 'Você' : (document.getElementById('was-chat-contact-name').textContent || 'Contato');
            const replyText = replyPreview.text || replyPreview.text_body || replyPreview.body || (replyPreview.type !== 'text' ? `[${replyPreview.type || 'Mídia'}]` : '...');
            
            replyBox.innerHTML = `
                <div class="was-reply-user">${author}</div>
                <div class="was-reply-text">${replyText}</div>
            `;
            
            if (replyPreview.id) {
                replyBox.addEventListener('click', () => jumpToMessage(replyPreview.id));
            }
            contentDiv.appendChild(replyBox);
        }
        
        if (type === 'template') {
            let header = '';
            let footer = '';
            let body = text;
            let buttons = [];

            // Tenta extrair dados do snapshot amigável
            try {
                if (payload) {
                    header = payload.header || '';
                    footer = payload.footer || '';
                    body = payload.body || text;
                    buttons = payload.buttons || [];
                }
            } catch(e) {}

            let buttonsHtml = '';
            if (buttons && buttons.length > 0) {
                buttonsHtml = `<div class="was-tpl-btns">
                    ${buttons.map(btn => `<div class="was-tpl-btn-item">${btn.text}</div>`).join('')}
                </div>`;
            }

            contentDiv.innerHTML += `<div class="was-template-card">
                ${header ? `<div class="was-tpl-header">${header}</div>` : ''}
                <div class="was-tpl-body">${body}</div>
                ${footer ? `<div class="was-tpl-footer">${footer}</div>` : ''}
                ${buttonsHtml}
            </div>`;
        } else if (type === 'image' && mediaUrl) {
            contentDiv.innerHTML += `<img src="${mediaUrl}" alt="Imagem" loading="lazy">
                                    ${text ? `<div style="padding-top:4px;">${text}</div>` : ''}`;
        } else if (type === 'audio' && mediaUrl) {
            contentDiv.innerHTML += `<audio controls src="${mediaUrl}"></audio>`;
        } else if (type === 'video' && mediaUrl) {
            contentDiv.innerHTML += `<video controls src="${mediaUrl}"></video>
                                    ${text ? `<div style="padding-top:4px;">${text}</div>` : ''}`;
        } else if (type === 'document' && mediaUrl) {
            contentDiv.innerHTML += `<a href="${mediaUrl}" target="_blank" class="was-doc-card">
                                        <span class="dashicons dashicons-media-document"></span>
                                        <div class="was-doc-info">
                                            <span class="was-doc-name">${mediaFilename || 'Documento'}</span>
                                        </div>
                                    </a>
                                    ${text ? `<div style="padding-top:4px;">${text}</div>` : ''}`;
        } else if (type === 'button') {
            contentDiv.innerHTML += `<div class="was-interactive-msg">
                                        <div class="was-interactive-label">Resposta rápida</div>
                                        <div class="was-interactive-btn-pill">${text}</div>
                                    </div>`;
        } else if (type === 'interactive') {
            contentDiv.innerHTML += `<div class="was-interactive-msg">
                                        <div class="was-interactive-label">Opção selecionada</div>
                                        <div class="was-interactive-btn-pill">${text}</div>
                                    </div>`;
        } else if (type === 'reaction') {
            contentDiv.innerHTML += `<div class="was-reaction-msg">${text}</div>`;
        } else {
            const bodySpan = document.createElement('span');
            bodySpan.textContent = text;
            contentDiv.appendChild(bodySpan);
        }

        const timeSpan = document.createElement('span');
        timeSpan.className = 'was-message-time';
        timeSpan.textContent = timeStr;
        contentDiv.appendChild(timeSpan);

        // Botão de Responder (Ações)
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'was-message-actions';
        actionsDiv.innerHTML = `<button class="was-action-btn" title="Responder"><span class="dashicons dashicons-undo"></span></button>`;
        actionsDiv.querySelector('button').addEventListener('click', () => {
            const author = (direction === 'outbound' || direction === 'sent') ? 'Você' : (document.getElementById('was-chat-contact-name').textContent || 'Contato');
            setReplyContext(messageId, text, author);
        });

        msgDiv.appendChild(actionsDiv);
        msgDiv.appendChild(contentDiv);
        history.appendChild(msgDiv);
    }

    function scrollToBottom() {
        const el = document.getElementById('was-messages-history');
        if (el) el.scrollTop = el.scrollHeight;
    }

    /**
     * Templates & Wizard
     */
    if (document.getElementById('was-open-create-wizard')) {
        initTemplates();
    }

    function initTemplates() {
        const openBtn = document.getElementById('was-open-create-wizard');
        const wizardForm = document.getElementById('was-complex-template-form');
        const syncBtn = document.getElementById('sync-templates');

        if (openBtn) openBtn.addEventListener('click', () => { 
            editingTemplateId = null; 
            wizardForm?.reset(); 
            wizardButtons = []; 
            wizardVariables = {};
            const nameInput = document.getElementById('wiz-tpl-name');
            if (nameInput) nameInput.disabled = false;
            document.getElementById('was-template-wizard').style.display = 'block'; 
            document.querySelector('#was-template-wizard h2').textContent = 'Criar Modelo';
            
            // Reset visibility
            document.getElementById('wiz-standard-fields').style.display = 'block';
            document.getElementById('wiz-auth-fields').style.display = 'none';
            document.querySelector('.step-item[data-step="4"]').style.display = 'block';

            setWizardStep(1); 
            renderWizardButtons(); 
            renderVariablesList();
            updatePreview();
        });

        // Toggle Auth Fields
        document.querySelectorAll('input[name="category"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                const isAuth = e.target.value === 'AUTHENTICATION';
                document.getElementById('wiz-standard-fields').style.display = isAuth ? 'none' : 'block';
                document.getElementById('wiz-auth-fields').style.display = isAuth ? 'block' : 'none';
                
                // Botões são parte do Passo 3 em Auth, então ocultamos o Passo 4 (Botões)
                document.querySelector('.step-item[data-step="4"]').style.display = isAuth ? 'none' : 'block';
                
                updatePreview();
            });
        });

        // Auth Type Change
        document.getElementById('wiz-auth-type')?.addEventListener('change', (e) => {
            const val = e.target.value;
            const mobileFields = document.getElementById('wiz-auth-mobile-fields');
            const zeroTapWrap = document.getElementById('wiz-auth-zerotap-wrap');
            const btnText = document.getElementById('wiz-auth-button-text');

            if (mobileFields) mobileFields.style.display = (val === 'ONE_TAP' || val === 'ZERO_TAP') ? 'block' : 'none';
            if (zeroTapWrap) zeroTapWrap.style.display = val === 'ZERO_TAP' ? 'block' : 'none';
            
            if (btnText) {
                if (val === 'COPY_CODE') btnText.value = 'Copiar código';
                else if (val === 'ONE_TAP') btnText.value = 'Preencher código';
                else if (val === 'ZERO_TAP') btnText.value = 'Continuar';
            }
            updatePreview();
        });

        ['wiz-auth-button-text', 'wiz-auth-expiration', 'wiz-auth-security'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', updatePreview);
        });

        document.getElementById('wiz-next')?.addEventListener('click', () => {
            const isAuth = document.querySelector('input[name="category"]:checked')?.value === 'AUTHENTICATION';
            // Se for auth, pula o passo 4 (Botões) que já configuramos no passo 3
            if (isAuth && wizardStep === 3) {
                setWizardStep(5);
            } else {
                setWizardStep(wizardStep + 1);
            }
        });
        document.getElementById('wiz-prev')?.addEventListener('click', () => {
            const isAuth = document.querySelector('input[name="category"]:checked')?.value === 'AUTHENTICATION';
            if (isAuth && wizardStep === 5) {
                setWizardStep(3);
            } else {
                setWizardStep(wizardStep - 1);
            }
        });
        document.getElementById('wiz-cancel')?.addEventListener('click', () => document.getElementById('was-template-wizard').style.display = 'none');
        
        // Add Button Listeners
        document.querySelectorAll('.wiz-add-btn-type').forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.dataset.type;
                let newBtn = { type, text: 'Botão' };
                if (type === 'URL') newBtn.url = 'https://';
                if (type === 'PHONE_NUMBER') newBtn.phone_number = '+';
                if (type === 'COPY_CODE') newBtn.example = 'CUPOM20';
                
                wizardButtons.push(newBtn);
                renderWizardButtons();
                updatePreview();
            });
        });

        // Name Normalization
        document.getElementById('wiz-tpl-name')?.addEventListener('input', (e) => {
            if (editingTemplateId) return;
            const original = e.target.value;
            const normalized = original.toLowerCase()
                .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // Remove acentos
                .replace(/[^a-z0-9_]/g, '_') // Apenas minúsculas, números e underline
                .replace(/_+/g, '_'); // Evita múltiplos underscores
            
            if (original !== normalized) e.target.value = normalized;
            updatePreview();
        });

        ['wiz-body-text', 'wiz-header-text', 'wiz-footer-text', 'wiz-tpl-lang'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => {
                if (id === 'wiz-body-text') syncVariablesFromText();
                updatePreview();
            });
        });

        // Toggle Header Input
        document.getElementById('wiz-header-type')?.addEventListener('change', (e) => {
            const container = document.getElementById('wiz-header-text-container');
            if (container) {
                container.style.display = e.target.value === 'TEXT' ? 'block' : 'none';
            }
            updatePreview();
        });

        // Insert Variable Logic
        document.getElementById('wiz-add-var')?.addEventListener('click', () => {
            const textarea = document.getElementById('wiz-body-text');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const varName = prompt('Nome da variável (ex: nome, pedido):', 'var');
            if (!varName) return;

            const newText = text.substring(0, start) + `{{${varName}}}` + text.substring(end);
            textarea.value = newText;
            
            syncVariablesFromText();
            textarea.focus();
            updatePreview();
            renderVariablesList();
        });

        if (wizardForm) wizardForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('wiz-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Enviando...';

            const cat = wizardForm.querySelector('input[name="category"]:checked').value;
            const isAuth = cat === 'AUTHENTICATION';

            const payload = {
                name: document.getElementById('wiz-tpl-name').value,
                category: cat,
                language: document.getElementById('wiz-tpl-lang').value,
                header: { 
                    type: document.getElementById('wiz-header-type').value, 
                    text: document.getElementById('wiz-header-text').value 
                },
                body: { 
                    text: document.getElementById('wiz-body-text').value,
                    variables: Object.entries(wizardVariables).map(([k, v]) => ({ key: k, example: v }))
                },
                footer: { text: document.getElementById('wiz-footer-text').value },
                buttons: wizardButtons
            };

            if (isAuth) {
                payload.authentication = {
                    type: document.getElementById('wiz-auth-type').value,
                    button_text: document.getElementById('wiz-auth-button-text').value,
                    code_expiration_minutes: document.getElementById('wiz-auth-expiration').value,
                    add_security_recommendation: document.getElementById('wiz-auth-security').checked,
                    package_name: document.getElementById('wiz-auth-package').value,
                    signature_hash: document.getElementById('wiz-auth-hash').value,
                    zero_tap_terms_accepted: document.getElementById('wiz-auth-zerotap-terms').checked
                };
            }

            const method = editingTemplateId ? 'PUT' : 'POST';
            const endpoint = editingTemplateId ? `/templates/${editingTemplateId}` : '/templates';

            try {
                const res = await wasApiFetch(endpoint, method, payload);
                if (res.success) {
                    alert(editingTemplateId ? 'Template atualizado!' : 'Template enviado para aprovação!');
                    location.reload();
                }
            } catch (err) { 
                alert(err.message || 'Erro ao salvar template'); 
                submitBtn.disabled = false;
                submitBtn.textContent = 'Enviar para Aprovação';
            }
        });

        if (syncBtn) syncBtn.addEventListener('click', async () => {
            syncBtn.disabled = true;
            syncBtn.textContent = 'Sincronizando...';
            try { 
                await wasApiFetch('/templates/sync', 'POST'); 
                alert('Sincronização concluída com sucesso!');
                fetchTemplates(); 
            } catch (err) {
                alert(err.message || 'Erro ao sincronizar. Verifique se o Token e o WABA ID são válidos.');
            } finally { 
                syncBtn.disabled = false; 
                syncBtn.textContent = 'Sincronizar com Meta';
            }
        });

        fetchTemplates();
    }

    function syncVariablesFromText() {
        const text = document.getElementById('wiz-body-text').value;
        const matches = text.match(/{{\s*([a-zA-Z0-9_]+)\s*}}/g) || [];
        const currentVars = matches.map(m => m.replace(/{{\s*|\s*}}/g, ''));
        
        // Remove variáveis que não existem mais
        Object.keys(wizardVariables).forEach(k => {
            if (!currentVars.includes(k)) delete wizardVariables[k];
        });
        
        // Adiciona novas
        currentVars.forEach(v => {
            if (!wizardVariables[v]) wizardVariables[v] = 'Exemplo';
        });
    }

    async function fetchTemplates() {
        const tbody = document.getElementById('template-list-body');
        if (!tbody) return;
        try {
            const data = await wasApiFetch('/templates');
            tbody.innerHTML = (data || []).map(t => {
                let color = '#555';
                const st = (t.status || 'DRAFT').toUpperCase();
                if (st === 'APPROVED') color = '#25d366';
                else if (st === 'REJECTED') color = '#dc2626';
                else if (st === 'PENDING' || st === 'IN_REVIEW') color = '#f59e0b';
                else if (st === 'DELETED') color = '#999';

                return `<tr>
                    <td><strong>${t.name}</strong><br><small style="color:#888">${t.waba_id}</small></td>
                    <td>${t.category}</td>
                    <td>${t.language}</td>
                    <td><span style="font-weight:600; color:${color}; border:1px solid ${color}33; padding:2px 6px; border-radius:4px; background:${color}11;">${st}</span></td>
                    <td style="display:flex; gap:5px; flex-wrap:wrap;">
                        <button type="button" class="button was-btn-send-tpl" data-id="${t.id}" data-name="${t.name}">Enviar</button>
                        <button type="button" class="button was-btn-dup-tpl" data-id="${t.id}">Duplicar</button>
                        <button type="button" class="button was-btn-edit-tpl" data-id="${t.id}">Editar</button>
                        <button type="button" class="button button-link-delete was-btn-del-tpl" data-id="${t.id}">Excluir</button>
                    </td>
                </tr>`;
            }).join('') || '<tr><td colspan="5">Vazio</td></tr>';
            
            document.querySelectorAll('.was-btn-send-tpl').forEach(btn => btn.addEventListener('click', () => openSendModal(btn.dataset.id, btn.dataset.name)));
            
            document.querySelectorAll('.was-btn-del-tpl').forEach(btn => btn.addEventListener('click', async () => {
                if(confirm('Tem certeza que deseja excluir permanentemente este template?')) {
                    try {
                        btn.disabled = true;
                        btn.textContent = '...';
                        await wasApiFetch(`/templates/${btn.dataset.id}`, 'DELETE');
                        fetchTemplates();
                    } catch(err) {
                        alert(err.message || 'Erro ao excluir');
                        btn.disabled = false;
                        btn.textContent = 'Excluir';
                    }
                }
            }));

            document.querySelectorAll('.was-btn-dup-tpl').forEach(btn => btn.addEventListener('click', async () => {
                const newName = prompt('Digite um nome para a cópia (apenas minúsculas e _):');
                if (newName) {
                    try {
                        btn.disabled = true;
                        btn.textContent = '...';
                        await wasApiFetch(`/templates/${btn.dataset.id}/duplicate`, 'POST', { new_name: newName });
                        alert('Template duplicado!');
                        fetchTemplates();
                    } catch(err) {
                        alert(err.message || 'Erro ao duplicar');
                        btn.disabled = false;
                        btn.textContent = 'Duplicar';
                    }
                }
            }));

            document.querySelectorAll('.was-btn-edit-tpl').forEach(btn => btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                try {
                    btn.disabled = true;
                    btn.textContent = '...';
                    const template = await wasApiFetch(`/templates/${id}`);
                    populateWizard(template);
                } catch (err) {
                    alert('Erro ao carregar template para edição');
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Editar';
                }
            }));

        } catch (err) { 
            tbody.innerHTML = '<tr><td colspan="5">Erro ao carregar templates.</td></tr>'; 
        }
    }

    function setWizardStep(step) {
        wizardStep = step;
        document.querySelectorAll('.was-wizard-step-content').forEach(el => el.style.display = 'none');
        document.getElementById(`step-${step}`).style.display = 'block';
        document.querySelectorAll('.step-item').forEach(el => el.classList.toggle('active', parseInt(el.dataset.step) === step));
        const bP = document.getElementById('wiz-prev'), bN = document.getElementById('wiz-next'), bS = document.getElementById('wiz-submit');
        if (bP) bP.style.display = step > 1 ? 'inline-block' : 'none';
        if (bN) bN.style.display = step < 5 ? 'inline-block' : 'none';
        if (bS) bS.style.display = step === 5 ? 'inline-block' : 'none';
        
        if (step === 5) {
            renderVariablesList();
            renderChecklist();
        }
    }

    function updatePreview() {
        const hType = document.getElementById('wiz-header-type')?.value || 'NONE';
        const hText = document.getElementById('wiz-header-text')?.value || '';
        const b = document.getElementById('wiz-body-text')?.value || '';
        const f = document.getElementById('wiz-footer-text')?.value || '';
        
        const ph = document.getElementById('pre-header');
        const pb = document.getElementById('pre-body');
        const pf = document.getElementById('pre-footer');
        const pbtns = document.getElementById('pre-buttons');

        if (ph) {
            ph.textContent = hText;
            ph.style.display = hType === 'TEXT' && hText ? 'block' : 'none';
        }

        const isAuth = document.querySelector('input[name="category"]:checked')?.value === 'AUTHENTICATION';
        
        if (isAuth) {
            const exp = document.getElementById('wiz-auth-expiration')?.value || '10';
            const security = document.getElementById('wiz-auth-security')?.checked;
            const btnText = document.getElementById('wiz-auth-button-text')?.value || 'Copiar código';
            
            let authBody = 'Seu código de verificação é <strong>123456</strong>.';
            if (security) authBody += '\n\nPor segurança, não compartilhe este código.';
            
            pb.innerHTML = authBody.replace(/\n/g, '<br>');
            pf.textContent = `Este código expira em ${exp} minutos.`;
            pf.style.display = 'block';
            pbtns.innerHTML = `<div class="was-wa-btn-item">${btnText}</div>`;
            pbtns.style.display = 'block';
            ph.style.display = 'none';
            return;
        }

        if (pb) {
            let processedBody = b;
            Object.entries(wizardVariables).forEach(([k, v]) => {
                // Escape para usar no RegExp
                const safeKey = k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                processedBody = processedBody.replace(new RegExp(`{{\\s*${safeKey}\\s*}}`, 'g'), `<span style="color:#2563eb; font-weight:bold;">${v}</span>`);
            });
            pb.innerHTML = processedBody || 'Sua mensagem aparecerá aqui...';
        }

        if (pf) {
            pf.textContent = f;
            pf.style.display = f ? 'block' : 'none';
        }

        if (pbtns) {
            pbtns.innerHTML = wizardButtons.map(btn => `<div class="was-wa-btn-item">${btn.text}</div>`).join('');
            pbtns.style.display = wizardButtons.length > 0 ? 'block' : 'none';
        }
    }

    function populateWizard(t) {
        editingTemplateId = t.id;
        document.getElementById('was-template-wizard').style.display = 'block';
        const title = document.querySelector('#was-template-wizard h2');
        const nameInput = document.getElementById('wiz-tpl-name');
        if (title) title.textContent = 'Editar Modelo';
        if (nameInput) { nameInput.value = t.name || ''; nameInput.disabled = true; }
        document.getElementById('wiz-tpl-lang').value = t.language || 'pt_BR';
        const catInput = document.querySelector(`input[name="category"][value="${t.category}"]`);
        if (catInput) catInput.checked = true;

        let payload = {};
        try { payload = t.friendly_payload ? JSON.parse(t.friendly_payload) : {}; } catch(e) {}

        const hType = payload.header?.type || (t.header_type === 'TEXT' ? 'TEXT' : 'NONE');
        const hText = payload.header?.text || '';
        document.getElementById('wiz-header-type').value = hType;
        document.getElementById('wiz-header-text').value = hText;
        document.getElementById('wiz-header-text-container').style.display = hType === 'TEXT' ? 'block' : 'none';
        const isAuth = t.category === 'AUTHENTICATION';
        document.getElementById('wiz-standard-fields').style.display = isAuth ? 'none' : 'block';
        document.getElementById('wiz-auth-fields').style.display = isAuth ? 'block' : 'none';
        document.querySelector('.step-item[data-step="4"]').style.display = isAuth ? 'none' : 'block';

        if (isAuth) {
            document.getElementById('wiz-auth-type').value = t.otp_type || 'COPY_CODE';
            document.getElementById('wiz-auth-button-text').value = payload.authentication?.button_text || '';
            document.getElementById('wiz-auth-expiration').value = t.code_expiration_minutes || 10;
            document.getElementById('wiz-auth-security').checked = parseInt(t.add_security_recommendation) !== 0;
            
            const authType = t.otp_type || 'COPY_CODE';
            const mobileFields = document.getElementById('wiz-auth-mobile-fields');
            const zeroTapWrap = document.getElementById('wiz-auth-zerotap-wrap');
            if (mobileFields) mobileFields.style.display = (authType === 'ONE_TAP' || authType === 'ZERO_TAP') ? 'block' : 'none';
            if (zeroTapWrap) zeroTapWrap.style.display = authType === 'ZERO_TAP' ? 'block' : 'none';
            
            document.getElementById('wiz-auth-package').value = t.package_name || '';
            document.getElementById('wiz-auth-hash').value = t.signature_hash || '';
            document.getElementById('wiz-auth-zerotap-terms').checked = parseInt(t.zero_tap_terms_accepted) !== 0;
        }

        document.getElementById('wiz-body-text').value = payload.body?.text || t.body_text || '';
        document.getElementById('wiz-footer-text').value = payload.footer?.text || t.footer_text || '';
        wizardButtons = payload.buttons || [];
        try { if (!wizardButtons.length && t.buttons_json) wizardButtons = JSON.parse(t.buttons_json); } catch(e) {}
        
        wizardVariables = {};
        if (payload.body?.variables) {
            payload.body.variables.forEach(v => { wizardVariables[v.key] = v.example; });
        }
        syncVariablesFromText(); // Garante consistência

        setWizardStep(1);
        renderWizardButtons();
        updatePreview();
    }

    function renderVariablesList() {
        const container = document.getElementById('wiz-variables-examples');
        if (!container) return;
        const entries = Object.entries(wizardVariables);
        if (entries.length === 0) {
            container.innerHTML = '<p class="description">Nenhuma variável detectada no corpo da mensagem.</p>';
            return;
        }
        container.innerHTML = '<h4>Exemplos de Variáveis</h4><p class="description">Insira valores reais para que a Meta aprove seu modelo.</p>';
        entries.forEach(([k, v]) => {
            const row = document.createElement('div');
            row.style.marginBottom = '10px';
            row.innerHTML = `<label style="display:block; font-size:11px; font-weight:bold;">{{${k}}}</label>
                             <input type="text" value="${v}" oninput="updateVariableExample('${k}', this.value)" style="width:100%;" placeholder="Valor de exemplo">`;
            container.appendChild(row);
        });
    }

    function renderWizardButtons() {
        const container = document.getElementById('wiz-buttons-list');
        if (!container) return;
        container.innerHTML = wizardButtons.map((btn, i) => {
            let fields = `<input type="text" value="${btn.text}" oninput="updateWizardButton(${i}, 'text', this.value)" style="width:100%;" placeholder="Texto do botão">`;
            
            if (btn.type === 'URL') {
                fields += `<input type="text" value="${btn.url || ''}" oninput="updateWizardButton(${i}, 'url', this.value)" style="width:100%; margin-top:5px;" placeholder="URL (ex: https://...)">`;
                if (btn.url?.includes('{{')) {
                    fields += `<input type="text" value="${btn.example || ''}" oninput="updateWizardButton(${i}, 'example', this.value)" style="width:100%; margin-top:5px;" placeholder="Exemplo para a variável da URL">`;
                }
            } else if (btn.type === 'PHONE_NUMBER') {
                fields += `<input type="text" value="${btn.phone_number || ''}" oninput="updateWizardButton(${i}, 'phone_number', this.value)" style="width:100%; margin-top:5px;" placeholder="Telefone (ex: +55...)">`;
            } else if (btn.type === 'COPY_CODE') {
                fields += `<input type="text" value="${btn.example || ''}" oninput="updateWizardButton(${i}, 'example', this.value)" style="width:100%; margin-top:5px;" placeholder="Código de exemplo">`;
            }

            return `<div class="wiz-btn-edit-item">
                <span class="remove-btn" onclick="removeWizardButton(${i})">×</span>
                <div style="font-size:10px; font-weight:bold; margin-bottom:5px; color:#64748b;">${btn.type}</div>
                ${fields}
            </div>`;
        }).join('');
    }

    function renderChecklist() {
        const list = document.getElementById('was-checklist-items');
        if (!list) return;
        const name = document.getElementById('wiz-tpl-name')?.value || '';
        const body = document.getElementById('wiz-body-text')?.value || '';
        const hType = document.getElementById('wiz-header-type')?.value || 'NONE';
        const hText = document.getElementById('wiz-header-text')?.value || '';
        const category = document.querySelector('input[name="category"]:checked')?.value || 'UTILITY';
        const isAuth = category === 'AUTHENTICATION';

        let checks = [
            { label: 'Nome válido', pass: /^[a-z0-9_]+$/.test(name) }
        ];

        if (isAuth) {
            const authType = document.getElementById('wiz-auth-type')?.value;
            const expiration = document.getElementById('wiz-auth-expiration')?.value;
            const package = document.getElementById('wiz-auth-package')?.value;
            const hash = document.getElementById('wiz-auth-hash')?.value;
            const btnText = document.getElementById('wiz-auth-button-text')?.value || '';

            checks.push({ label: 'Texto do botão preenchido', pass: btnText.trim().length > 0 });
            checks.push({ label: 'Tempo de expiração válido (1-90)', pass: expiration >= 1 && expiration <= 90 });

            if (authType === 'ONE_TAP' || authType === 'ZERO_TAP') {
                checks.push({ label: 'Package Name preenchido', pass: (package || '').trim().length > 0 });
                checks.push({ label: 'Signature Hash preenchido', pass: (hash || '').trim().length > 0 });
            }
            if (authType === 'ZERO_TAP') {
                checks.push({ label: 'Termos do Zero-tap aceitos', pass: document.getElementById('wiz-auth-zerotap-terms')?.checked });
            }
        } else {
            checks.push({ label: 'Mensagem principal preenchida', pass: body.trim().length > 0 });
            checks.push({ label: 'Sem variável no início/fim', pass: !/^{{\s*[a-zA-Z0-9_]+\s*}}/.test(body.trim()) && !/{{\s*[a-zA-Z0-9_]+\s*}}$/.test(body.trim()) });
            checks.push({ label: 'Variáveis possuem exemplos', pass: Object.values(wizardVariables).every(v => v && v.toString().trim().length > 0) });
            checks.push({ label: 'Cabeçalho válido', pass: hType === 'NONE' || hText.trim().length > 0 });
            checks.push({ label: 'Botões válidos', pass: wizardButtons.every(b => {
                const textOk = (b.text || '').trim().length > 0;
                if (!textOk) return false;
                if (b.type === 'URL') return (b.url || '').startsWith('https://');
                if (b.type === 'PHONE_NUMBER') return (b.phone_number || '').startsWith('+');
                return true;
            }) });
        }

        list.innerHTML = checks.map(c => `<li style="margin-bottom:8px; display:flex; align-items:center; gap:10px;">
            <span style="color:${c.pass ? '#25d366' : '#ef4444'}; font-weight:bold;">${c.pass ? '✅' : '❌'}</span>
            <span style="color:${c.pass ? '#1e293b' : '#ef4444'}">${c.label}</span>
        </li>`).join('');
        
        const submitBtn = document.getElementById('wiz-submit');
        if (submitBtn) submitBtn.disabled = !checks.every(c => c.pass);
    }

    // --- Modal de Envio de Template ---
    window.openSendModal = async function(id, name) {
        const modal = document.getElementById('was-send-template-modal');
        if (!modal) return;
        document.getElementById('send-tpl-id').value = id;
        document.getElementById('send-tpl-name-display').textContent = name;
        try {
            const tpl = await wasApiFetch(`/templates/${id}`);
            currentTemplateForSend = tpl;
            
            const varMap = tpl.variable_map ? JSON.parse(tpl.variable_map) : {};
            const buttons = tpl.buttons_json ? JSON.parse(tpl.buttons_json) : [];
            const inputsContainer = document.getElementById('was-tpl-variables-inputs');
            
            let html = '';
            let varKeys = Object.keys(varMap);

            if (varKeys.length === 0 && tpl.body_text && tpl.body_text.includes('{{')) {
                const matches = tpl.body_text.match(/{{\s*(\d+)\s*}}/g);
                if (matches) {
                    const uniquePositions = [...new Set(matches.map(m => m.match(/\d+/)[0]))];
                    uniquePositions.forEach(pos => { varMap[pos] = pos; });
                    varKeys = Object.keys(varMap);
                }
            }
            
            const isAuth = tpl.category === 'AUTHENTICATION';

            if (isAuth) {
                html += '<h4>Autenticação (OTP)</h4>';
                html += `<div style="margin-bottom:10px;">
                    <label><strong>Código de Autenticação (OTP)</strong></label>
                    <input type="text" class="tpl-var-input regular-text" data-key="código" style="width:100%; margin-top:4px;" required placeholder="Ex: 123456" maxlength="15">
                    <p class="description">Este código será enviado no corpo da mensagem e no botão de copiar.</p>
                </div>`;
            } else {
                // 1. Variáveis do Corpo
                if (varKeys.length > 0) {
                    html += '<h4>Variáveis do Corpo</h4>';
                    varKeys.forEach(k => {
                        const label = (k === varMap[k]) ? `{{${k}}}` : `{{${k}}} (${varMap[k]})`;
                        html += `<div style="margin-bottom:10px;"><label><strong>${label}</strong></label><input type="text" class="tpl-var-input regular-text" data-key="${varMap[k]}" style="width:100%; margin-top:4px;" required placeholder="Digite o valor..."></div>`;
                    });
                }

                // 2. Variáveis de Botões
                buttons.forEach((btn, idx) => {
                    if (btn.type === 'URL' && btn.url && btn.url.includes('{{')) {
                        const match = btn.url.match(/{{\s*([a-zA-Z0-9_]+)\s*}}/);
                        const varName = match ? match[1] : `button_${idx}`;
                        html += `<h4>Variável do Botão: ${btn.text}</h4>`;
                        html += `<div style="margin-bottom:10px;"><label><strong>{{${varName}}}</strong> (Link Dinâmico)</label><input type="text" class="tpl-var-input regular-text" data-key="${varName}" style="width:100%; margin-top:4px;" required placeholder="ex: código ou slug"></div>`;
                    }
                });
            }

            if (html === '' && !isAuth) {
                html = '<p class="description">Este modelo não possui variáveis.</p>';
            }

            inputsContainer.innerHTML = html;

            // Inicializa o preview
            updateSendPreview();

            // Adiciona listeners para atualizar preview em tempo real
            inputsContainer.querySelectorAll('.tpl-var-input').forEach(inp => {
                inp.addEventListener('input', updateSendPreview);
            });

            modal.style.display = 'block';
        } catch (err) { alert('Erro ao carregar detalhes do template para envio.'); }
    };

    function updateSendPreview() {
        if (!currentTemplateForSend) return;
        
        const t = currentTemplateForSend;
        const ph = document.getElementById('send-pre-header');
        const pb = document.getElementById('send-pre-body');
        const pf = document.getElementById('send-pre-footer');
        const pbtns = document.getElementById('send-pre-buttons');

        // Coleta valores atuais das variáveis
        const varInputs = document.querySelectorAll('.tpl-var-input');
        const currentVars = Array.from(varInputs).reduce((acc, inp) => {
            acc[inp.dataset.key] = inp.value;
            return acc;
        }, {});

        // Header
        if (ph) {
            ph.textContent = t.header_text || '';
            ph.style.display = (t.header_format === 'TEXT' && t.header_text) ? 'block' : 'none';
        }

        // Body
        if (pb) {
            let processedBody = t.body_text || '';
            const varMap = t.variable_map ? JSON.parse(t.variable_map) : {};

            Object.entries(currentVars).forEach(([friendlyName, v]) => {
                const displayValue = v || `{{${friendlyName}}}`;
                const highlight = `<span style="color:#25d366; font-weight:bold;">${displayValue}</span>`;
                
                // Substitui pelo nome amigável
                const safeFriendly = friendlyName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                processedBody = processedBody.replace(new RegExp(`{{\\s*${safeFriendly}\\s*}}`, 'g'), highlight);
                
                // Substitui pela posição (para templates sincronizados da Meta)
                Object.entries(varMap).forEach(([pos, name]) => {
                    if (name === friendlyName) {
                        processedBody = processedBody.replace(new RegExp(`{{\\s*${pos}\\s*}}`, 'g'), highlight);
                    }
                });
            });
            pb.innerHTML = processedBody || '...';
        }

        // Footer
        if (pf) {
            pf.textContent = t.footer_text || '';
            pf.style.display = t.footer_text ? 'block' : 'none';
        }

        // Buttons
        if (pbtns) {
            const buttons = t.buttons_json ? JSON.parse(t.buttons_json) : [];
            pbtns.innerHTML = buttons.map(btn => {
                // Se for autenticação, o botão é do tipo OTP. 
                // A Meta não permite variáveis no texto do botão de OTP, mas ele exibe o código internamente.
                const btnText = btn.text || 'Copiar código';
                return `<div class="was-wa-btn-item">${btnText}</div>`;
            }).join('');
            pbtns.style.display = buttons.length > 0 ? 'block' : 'none';
        }
    }

    const closeSendModal = document.getElementById('was-close-send-modal');
    if (closeSendModal) closeSendModal.addEventListener('click', () => { document.getElementById('was-send-template-modal').style.display = 'none'; });

    const sendTplForm = document.getElementById('was-send-template-form');
    if (sendTplForm) {
        sendTplForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('send-tpl-id').value;
            const to = document.getElementById('send-tpl-to').value;
            const varInputs = document.querySelectorAll('.tpl-var-input');
            const variables = Array.from(varInputs).reduce((acc, inp) => { acc[inp.dataset.key] = inp.value; return acc; }, {});
            const submitBtn = sendTplForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Enviando...'; submitBtn.disabled = true;
            try {
                const res = await wasApiFetch(`/templates/${id}/send`, 'POST', { to_phone: to, variables: variables });
                if (res.success) {
                    alert('Template enviado com sucesso!');
                    document.getElementById('was-send-template-modal').style.display = 'none';
                    
                    // Se a conversa aberta for a mesma que recebeu o template, renderiza na tela
                    if (res.conversation_id && currentConversationId == res.conversation_id) {
                        const snapshot = {
                            header: res.rendered_header,
                            body: res.rendered_body,
                            footer: res.rendered_footer,
                            buttons: res.buttons
                        };
                        renderMessage(res.rendered_body || 'Modelo enviado', 'outbound', 'template', snapshot);
                        if (res.id && parseInt(res.id) > lastMessageId) {
                            lastMessageId = parseInt(res.id);
                        }
                        scrollToBottom();
                        lastTypingSentAt = 0;
                        pollNewMessages();
                    } else if (!currentConversationId) {
                        location.reload();
                    }
                } else {
                    alert('Erro ao enviar template: ' + (res.message || 'Desconhecido'));
                }
            } catch(err) { alert(err.message || 'Erro ao enviar template'); }
            finally { submitBtn.textContent = originalText; submitBtn.disabled = false; }
        });
    }

    async function sendTypingIndicator() {
        if (!currentConversationId || !lastInboundMessageId) return;

        const now = Date.now();

        // 1. Não enviar se acabou de mandar mensagem (cooldown de 3s)
        if (now - lastMessageSentAt < 3000) return;

        // 2. Throttle: enviar apenas a cada 10 segundos
        if (now - lastTypingSentAt < 10000) return;

        lastTypingSentAt = now;

        try {
            await wasApiFetch(`/conversations/${currentConversationId}/messages/${lastInboundMessageId}/typing${inboxScopeQuery()}`, 'POST');
        } catch (err) {
            console.error('Falha ao enviar typing indicator:', err);
        }
    }

    /**
     * Settings & Logs
     */
    if (document.getElementById('log-list-body')) initLogs();
    if (document.getElementById('was-meta-config-form')) initSettingsMeta();

    async function initLogs() {
        const tb = document.getElementById('log-list-body');
        if (!tb) return;
        try {
            const logs = await wasApiFetch('/audit-logs');
            tb.innerHTML = logs.map(l => `
                <tr>
                    <td>${l.created_at}</td>
                    <td><span style="color:var(--slate-600)">${l.user_login || 'Sistema'}</span></td>
                    <td><strong>${l.action.toUpperCase()}</strong></td>
                    <td>${l.entity_type} (#${l.entity_id})</td>
                    <td><small style="color:var(--slate-500); font-family:monospace; font-size:11px;">${l.metadata}</small></td>
                </tr>
            `).join('') || '<tr><td colspan="5">Nenhum registro encontrado.</td></tr>';
        } catch (err) { tb.innerHTML = '<tr><td colspan="5">Erro ao carregar logs.</td></tr>'; }
    }

    async function initSettingsMeta() {
        const form = document.getElementById('was-meta-config-form');
        if (!form) return;

        const modal = document.getElementById('was-security-unlock-modal');
        const confirmBtn = document.getElementById('was-confirm-unlock');
        const cancelBtn = document.getElementById('was-cancel-unlock');
        const passwordInput = document.getElementById('was-unlock-password');

        try {
            const data = await wasApiFetch('/meta/config');
            if (data) {
                const setValue = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
                const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || ''; };

                setValue('app_id', data.app_id);
                setValue('primary_phone_number_id', data.primary_phone_number_id);
                setValue('waba_id', data.waba_id);
                setValue('webhook_url', data.webhook_url);
                setValue('oauth_callback_url', data.oauth_callback_url);
                setValue('deauthorize_url', data.deauthorize_url);
                setValue('data_deletion_url', data.data_deletion_url);
                setValue('config_id', data.config_id);
                setValue('embedded_signup_url', data.embedded_signup_url);
                
                setText('privacy_policy_url', data.privacy_policy_url);
                setText('terms_of_service_url', data.terms_of_service_url);
                setText('support_url', data.support_url);

                if (document.getElementById('was-start-embedded-signup')) {
                    document.getElementById('was-start-embedded-signup').href = data.embedded_signup_url || '#';
                }
            }
        } catch (err) { console.error('Error fetching meta config:', err); }

        // Lógica de Desbloqueio
        document.querySelectorAll('.was-unlock-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'block';
                passwordInput.value = '';
                passwordInput.focus();
            });
        });

        cancelBtn.addEventListener('click', () => { modal.style.display = 'none'; });

        confirmBtn.addEventListener('click', async () => {
            const password = passwordInput.value;
            if (!password) return alert('Por favor, insira sua senha.');

            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Verificando...';

            try {
                const data = await wasApiFetch('/meta/config/reveal', 'POST', { password });
                if (data) {
                    const appSecretField = document.getElementById('app_secret');
                    const accessTokenField = document.getElementById('meta_access_token');
                    const verifyTokenField = document.getElementById('verify_token');

                    if (appSecretField && data.app_secret) {
                        appSecretField.value = data.app_secret;
                        appSecretField.readOnly = false;
                    }
                    if (accessTokenField && data.meta_access_token) {
                        accessTokenField.value = data.meta_access_token;
                        accessTokenField.readOnly = false;
                    }
                    if (verifyTokenField && data.verify_token) {
                        verifyTokenField.value = data.verify_token;
                        verifyTokenField.readOnly = false;
                    }

                    modal.style.display = 'none';
                    alert('Informações desbloqueadas com sucesso!');
                    
                    // Esconde os botões de desbloqueio após sucesso
                    document.querySelectorAll('.was-unlock-btn').forEach(b => b.style.display = 'none');
                }
            } catch (err) {
                alert('Erro na verificação: ' + (err.message || 'Senha incorreta.'));
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Confirmar e Revelar';
            }
        });

        const saveConfig = async (e) => {
            if (e) e.preventDefault();
            const payload = {
                app_id: document.getElementById('app_id').value,
                app_secret: document.getElementById('app_secret').value,
                verify_token: document.getElementById('verify_token').value,
                primary_phone_number_id: document.getElementById('primary_phone_number_id').value,
                meta_access_token: document.getElementById('meta_access_token').value,
                waba_id: document.getElementById('waba_id').value,
                config_id: document.getElementById('config_id') ? document.getElementById('config_id').value : '',
                embedded_signup_url: document.getElementById('embedded_signup_url') ? document.getElementById('embedded_signup_url').value : ''
            };
            try {
                await wasApiFetch('/meta/config', 'POST', payload);
                alert('Configurações salvas com sucesso!');
                // Recarrega para voltar ao estado bloqueado
                location.reload();
            } catch (err) { alert(err.message || 'Erro ao salvar configurações'); }
        };

        const btnSaveMeta = document.getElementById('was-btn-save-meta');
        if (btnSaveMeta) btnSaveMeta.addEventListener('click', saveConfig);
        const btnSaveEmbedded = document.getElementById('was-btn-save-embedded');
        if (btnSaveEmbedded) btnSaveEmbedded.addEventListener('click', saveConfig);
    }
    async function openInboxTplModal() {
        const modal = document.getElementById('was-inbox-tpl-modal');
        const list = document.getElementById('was-inbox-tpl-list');
        if (!modal) return;
        modal.style.display = 'block'; list.innerHTML = '...';
        try {
            const data = await wasApiFetch('/templates');
            const approved = (data || []).filter(t => t.status === 'APPROVED');
            list.innerHTML = approved.map(t => {
                const body = t.body_text || '';
                const preview = body.length > 50 ? body.substring(0, 50) + '...' : body;
                return `<div class="was-tpl-select-item" onclick="sendTemplateFromInbox(${t.id})">
                    <strong>${t.name}</strong><br>
                    <small>${preview}</small>
                </div>`;
            }).join('') || 'Nenhum template aprovado.';
        } catch (err) { list.innerHTML = 'Erro.'; }
    }

    window.sendTemplateFromInbox = async (id) => {
        if (!confirm('Enviar este template?')) return;
        try {
            const res = await wasApiFetch(`/templates/${id}/send${inboxScopeQuery()}`, 'POST', { conversation_id: currentConversationId });
            if (res.success) {
                alert('Enviado!'); 
                document.getElementById('was-inbox-tpl-modal').style.display = 'none';
                const snapshot = {
                    header: res.rendered_header,
                    body: res.rendered_body,
                    footer: res.rendered_footer,
                    buttons: res.buttons
                };
                renderMessage(res.rendered_body || 'Modelo enviado', 'outbound', 'template', snapshot);
                scrollToBottom();
            }
        } catch (err) { alert(err.message); }
    };

    /**
     * WhatsApp Business Setup (Embedded Signup & Verification)
     */
    if (document.getElementById('was-whatsapp-setup-app') || document.getElementById('was-launch-signup')) {
        initWhatsAppSetup();
    }

    async function initWhatsAppSetup() {
        const btnCheck = document.getElementById('was-btn-check-connection');
        const resultsBox = document.getElementById('was-verify-results');
        const list = document.getElementById('was-verify-list');
        const btnLaunch = document.getElementById('was-launch-signup');
        const phoneInput = document.getElementById('was-signup-phone');
        const onboardingResult = document.getElementById('was-onboarding-result');
        let onboardingPollTimer = null;

        const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));

        const showOnboardingResult = (status, data = {}) => {
            if (!onboardingResult) return;
            const connection = data.connection || data;
            const completed = status === 'completed';
            const pending = status === 'pending' || status === 'queued_for_retry';
            const color = completed ? '#166534' : (pending ? '#854d0e' : '#991b1b');
            const title = completed ? 'Número cadastrado com sucesso' : (pending ? 'Cadastro em processamento' : 'Cadastro não concluído');
            const details = completed ? `
                <div style="margin-top:10px; color:#334155;">
                    ${connection.display_phone_number ? `<div><strong>Número:</strong> ${escapeHtml(connection.display_phone_number)}</div>` : ''}
                    ${connection.waba_id || connection.meta_waba_id ? `<div><strong>WABA:</strong> ${escapeHtml(connection.waba_id || connection.meta_waba_id)}</div>` : ''}
                    ${connection.phone_number_id || connection.meta_phone_number_id ? `<div><strong>Phone ID:</strong> ${escapeHtml(connection.phone_number_id || connection.meta_phone_number_id)}</div>` : ''}
                </div>` : '';
            onboardingResult.style.display = 'block';
            onboardingResult.style.padding = '14px 16px';
            onboardingResult.style.borderLeft = `4px solid ${color}`;
            onboardingResult.style.background = '#f8fafc';
            onboardingResult.innerHTML = `<strong style="color:${color}">${title}</strong><div>${escapeHtml(data.message || (pending ? 'Aguarde enquanto confirmamos os dados na Meta.' : 'Verifique a configuração da Meta e tente novamente.'))}</div>${details}`;
        };

        const stopPolling = () => {
            if (onboardingPollTimer) {
                clearTimeout(onboardingPollTimer);
                onboardingPollTimer = null;
            }
        };

        const pollAttempt = async (attemptId, tries = 0) => {
            if (!attemptId || tries > 45) {
                showOnboardingResult('pending', { message: 'O cadastro foi recebido, mas ainda está sendo processado. Atualize esta tela em alguns instantes.' });
                return;
            }
            try {
                const status = await wasApiFetch(`/whatsapp/onboarding/attempts/${encodeURIComponent(attemptId)}?refresh=1`);
                if (status.status === 'completed') {
                    stopPolling();
                    showOnboardingResult('completed', status.connection || status);
                    btnLaunch.textContent = 'Número cadastrado';
                    btnLaunch.disabled = true;
                    return;
                }
                if (['failed', 'expired', 'cancelled'].includes(status.status)) {
                    stopPolling();
                    showOnboardingResult('error', { message: status.last_error || 'A Meta não concluiu o cadastro.' });
                    return;
                }
                onboardingPollTimer = setTimeout(() => pollAttempt(attemptId, tries + 1), 2000);
            } catch (error) {
                onboardingPollTimer = setTimeout(() => pollAttempt(attemptId, tries + 1), 2500);
            }
        };

        const loadFacebookSdk = (appId, version) => new Promise((resolve, reject) => {
            const init = () => {
                if (!window.FB) return reject(new Error('SDK da Meta não foi carregado.'));
                window.FB.init({ appId, cookie: true, xfbml: true, version: version || 'v25.0' });
                resolve();
            };
            if (window.FB) return init();
            const existing = document.getElementById('facebook-jssdk');
            if (existing) {
                existing.addEventListener('load', init, { once: true });
                setTimeout(() => window.FB ? init() : reject(new Error('Tempo excedido ao carregar o SDK da Meta.')), 10000);
                return;
            }
            const script = document.createElement('script');
            script.id = 'facebook-jssdk';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            script.async = true;
            script.onload = init;
            script.onerror = () => reject(new Error('Não foi possível carregar o SDK da Meta.'));
            document.head.appendChild(script);
        });

        const runSdkSignup = async (startData, attemptId) => {
            await loadFacebookSdk(startData.app_id, startData.graph_version);
            return new Promise((resolve, reject) => {
                let finishData = {};
                const listener = (event) => {
                    const trustedOrigin = ['https://www.facebook.com', 'https://web.facebook.com', 'https://business.facebook.com'].includes(event.origin);
                    if (!trustedOrigin) return;
                    try {
                        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
                        if (!data || data.type !== 'WA_EMBEDDED_SIGNUP') return;
                        if (['FINISH', 'FINISHED', 'SUCCESS'].includes(String(data.event || '').toUpperCase())) {
                            finishData = data.data || {};
                        }
                    } catch (error) {
                        // Meta can send non-JSON postMessage events; ignore those.
                    }
                };
                window.addEventListener('message', listener);
                window.FB.login((response) => {
                    const authResponse = response && response.authResponse ? response.authResponse : response;
                    const code = authResponse && authResponse.code ? authResponse.code : '';
                    window.removeEventListener('message', listener);
                    if (!code) {
                        reject(new Error('O cadastro incorporado foi cancelado ou não retornou um authorization code.'));
                        return;
                    }
                    resolve({
                        attempt_id: attemptId,
                        code,
                        waba_id: finishData.waba_id || finishData.wabaId || '',
                        phone_number_id: finishData.phone_number_id || finishData.phoneNumberId || '',
                        business_id: finishData.business_id || finishData.businessId || ''
                    });
                }, {
                    config_id: startData.config_id,
                    response_type: 'code',
                    override_default_response_type: true,
                    extras: {
                        version: 'v4',
                        featureType: 'whatsapp_business_app_onboarding',
                        sessionInfoVersion: '3',
                        features: [{ name: 'app_only_install' }]
                    }
                });
            });
        };

        if (btnLaunch) {
            btnLaunch.addEventListener('click', async () => {
                const originalText = btnLaunch.textContent;
                btnLaunch.textContent = 'Iniciando...';
                btnLaunch.disabled = true;
                stopPolling();

                try {
                    const phone = phoneInput ? phoneInput.value.trim() : '';
                    if (!phone) throw new Error('Informe o número WhatsApp da empresa antes de iniciar.');

                    const directPopup = window.open('about:blank', 'fb_signup', 'width=600,height=700');
                    const startData = await wasApiFetch('/whatsapp/onboarding/start', 'POST', {
                        phone_number: phone,
                        return_url: window.location.href
                    });
                    if (!startData.attempt_id) {
                        throw new Error(startData.message || 'Falha ao iniciar o cadastro incorporado.');
                    }

                    showOnboardingResult('pending', { message: 'Aguardando a conclusão do cadastro na Meta.' });
                    if (startData.flow_mode === 'sdk') {
                        if (directPopup) directPopup.close();
                        const completeData = await runSdkSignup(startData, startData.attempt_id);
                        const result = await wasApiFetch('/whatsapp/onboarding/complete', 'POST', completeData);
                        if (result.status === 'completed') {
                            showOnboardingResult('completed', result);
                            btnLaunch.textContent = 'Número cadastrado';
                            btnLaunch.disabled = true;
                        } else {
                            pollAttempt(startData.attempt_id);
                        }
                    } else {
                        if (!startData.authorization_url) throw new Error('URL do cadastro incorporado não configurada.');
                        if (!directPopup) throw new Error('O navegador bloqueou a janela do cadastro. Permita pop-ups e tente novamente.');
                        directPopup.location = startData.authorization_url;
                        pollAttempt(startData.attempt_id);
                    }

                } catch (err) {
                    stopPolling();
                    showOnboardingResult('error', { message: err.message });
                }
                if (!onboardingPollTimer) {
                    btnLaunch.textContent = originalText;
                    btnLaunch.disabled = false;
                }
            });
        }

        if (btnCheck) {
            btnCheck.addEventListener('click', async () => {
                btnCheck.textContent = 'Verificando...';
                btnCheck.disabled = true;
                resultsBox.style.display = 'block';
                list.innerHTML = '<li>⚙️ Iniciando testes de conectividade...</li>';

                try {
                    const res = await wasApiFetch('/whatsapp/check-connection', 'POST');
                    if (res.success && res.results) {
                        list.innerHTML = '';
                        Object.entries(res.results).forEach(([key, r]) => {
                            let icon = '✅';
                            let color = '#166534';
                            if (r.status === 'error') { icon = '❌'; color = '#991b1b'; }
                            else if (r.status === 'warning') { icon = '⚠️'; color = '#854d0e'; }
                            
                            // Base Result
                            let html = `<div style="padding:16px; background:#f8fafc; border-radius:8px; border-left:4px solid ${color}; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                                <strong style="display:block; margin-bottom:4px;">${icon} ${r.label}</strong>
                                <span style="font-size:0.85rem; color:#475569;">${r.message || r.details || '-'}</span>`;

                            // Special rendering for Phone Numbers
                            if (key === 'phone_numbers' && r.numbers) {
                                html += `<div style="margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px;">`;
                                r.numbers.forEach(num => {
                                    const profile = num.business_profile || {};
                                    const isProfileError = profile.status === 'error';

                                    html += `<div style="margin-bottom:12px; padding:10px; background:#fff; border-radius:6px; border:1px solid #e2e8f0;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                            <strong>${num.display_phone_number || 'Sem número'}</strong>
                                            <span class="was-status-badge" style="font-size:0.65rem; padding:2px 6px;">${num.status || 'N/A'}</span>
                                        </div>
                                        <div style="font-size:0.75rem; display:grid; grid-template-columns:1fr 1fr; gap:4px;">
                                            <span>ID: <code>${num.phone_number_id}</code></span>
                                            <span>Qualidade: <strong style="color:${num.quality_rating === 'GREEN' ? '#166534' : '#991b1b'}">${num.quality_rating || 'N/A'}</strong></span>
                                            <span>Nome: ${num.verified_name || '-'}</span>
                                            <span>Limite: ${num.messaging_limit_tier || '-'}</span>
                                            <span>Modo: ${num.account_mode || '-'}</span>
                                            <span>Saúde: ${num.health_status?.status || 'OK'}</span>
                                        </div>
                                        <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #e2e8f0; font-size:0.75rem;">
                                            <strong>Perfil Comercial:</strong> ${isProfileError ? `<span style="color:#991b1b">⚠️ Não carregado (${profile.message || 'Erro'})</span>` : '<span style="color:#166534">✅ Carregado</span>'}
                                        </div>
                                    </div>`;
                                });
                                html += `</div>`;
                            }

                            html += `</div>`;
                            list.innerHTML += html;
                        });
                    }
                } catch (err) {
                    list.innerHTML = `<div style="color:red; padding:10px; grid-column: 1 / -1;">❌ Erro crítico ao realizar verificação: ${err.message}</div>`;
                } finally {
                    btnCheck.textContent = 'Verificar conexão oficial';
                    btnCheck.disabled = false;
                }
            });
        }
    }
});
