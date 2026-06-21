<div class="wrap">
    <h1>Logs e Auditoria</h1>
    
    <div id="was-logs-app">
        <h2>Auditoria de Ações</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Entidade</th>
                    <th>Metadata</th>
                </tr>
            </thead>
            <tbody id="log-list-body">
                <!-- Preenchido via JS -->
            </tbody>
        </table>

        <h2 style="margin-top:40px;">Logs Técnicos (Meta API)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Operação</th>
                    <th>Método</th>
                    <th>Status</th>
                    <th>Sucesso</th>
                    <th>Duração</th>
                </tr>
            </thead>
            <tbody id="meta-log-list-body">
                <!-- Preenchido via JS -->
            </tbody>
        </table>
    </div>
</div>
