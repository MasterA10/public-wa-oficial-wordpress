<?php
/**
 * Master Admin App Review Checklist Template
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1>App Review / Compliance Center</h1>
    <p class="description">Prepare sua plataforma para a aprovação oficial da Meta (Tech Provider).</p>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        <div class="card">
            <h2>Checklist de Prontidão</h2>
            <ul id="master-review-checklist" style="list-style: none; padding: 0;">
                <!-- Carregado via JS -->
                <li>Carregando checklist...</li>
            </ul>
        </div>

        <div class="card">
            <h2>Demos para Review</h2>
            <p>A Meta exige gravações de tela demonstrando o uso das permissões.</p>
            <div style="padding: 15px; background: #f0f2f5; border-radius: 8px;">
                <strong>whatsapp_business_messaging</strong><br>
                <small>Vídeo enviando uma mensagem para um cliente.</small><br>
                <button class="button" style="margin-top: 5px;">Abrir Demo de Envio</button>
            </div>
            <div style="padding: 15px; background: #f0f2f5; border-radius: 8px; margin-top: 10px;">
                <strong>whatsapp_business_management</strong><br>
                <small>Vídeo criando um modelo de mensagem.</small><br>
                <button class="button" style="margin-top: 5px;">Abrir Demo de Template</button>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h2>Monitoramento de Páginas Legais</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Página</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Obrigatória (Meta)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Política de Privacidade</td>
                    <td><code>/privacy-policy</code></td>
                    <td><span class="status-indicator status-done">OK (200)</span></td>
                    <td>✅ SIM</td>
                </tr>
                <tr>
                    <td>Termos de Serviço</td>
                    <td><code>/terms-of-service</code></td>
                    <td><span class="status-indicator status-done">OK (200)</span></td>
                    <td>✅ SIM</td>
                </tr>
                <tr>
                    <td>Exclusão de Dados</td>
                    <td><code>/data-deletion</code></td>
                    <td><span class="status-indicator status-done">OK (200)</span></td>
                    <td>✅ SIM</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
#master-review-checklist li {
    padding: 12px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
#master-review-checklist li:last-child { border-bottom: none; }
.status-indicator {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: bold;
}
.status-pending { background: #fee2e2; color: #991b1b; }
.status-done { background: #dcfce7; color: #166534; }
</style>
