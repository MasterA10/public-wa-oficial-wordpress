<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$service = new \WAS\Compliance\ChecklistService();
$catalog = $service->get_catalog();
$selected = sanitize_key( $_GET['checklist'] ?? '' );
?>
<div class="wrap was-checklists-admin">
    <h1>Checklists</h1>
    <p class="description">Escolha um checklist para acompanhar a documentação e a verificação da feature.</p>

    <?php if ( ! $selected || ! isset( $catalog[ $selected ] ) ) : ?>
        <div class="was-checklist-grid">
            <?php foreach ( $catalog as $checklist ) : ?>
                <div class="card was-checklist-card">
                    <h2><?php echo esc_html( $checklist['title'] ); ?></h2>
                    <p><?php echo esc_html( $checklist['description'] ); ?></p>
                    <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=was-master-checklists&checklist=' . $checklist['slug'] ) ); ?>">Abrir checklist</a>
                    <a class="was-public-link" href="<?php echo esc_url( home_url( '/checklists/' . $checklist['slug'] . '/' ) ); ?>" target="_blank" rel="noopener">Abrir URL pública ↗</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : $items = $service->get_items( $selected ); ?>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=was-master-checklists' ) ); ?>">← Voltar para os checklists</a></p>
        <div class="card was-checklist-detail">
            <h2><?php echo esc_html( $catalog[ $selected ]['title'] ); ?></h2>
            <p><?php echo esc_html( $catalog[ $selected ]['description'] ); ?></p>
            <p><a href="<?php echo esc_url( home_url( '/checklists/' . $selected . '/' ) ); ?>" target="_blank" rel="noopener">Ver URL pública</a></p>
            <ul id="was-checklist-items" class="was-checklist-items" data-slug="<?php echo esc_attr( $selected ); ?>">
                <?php foreach ( $items as $item ) : ?>
                    <li><label><input type="checkbox" class="was-checklist-toggle" data-key="<?php echo esc_attr( $item['item_key'] ); ?>" <?php checked( 'done', $item['status'] ); ?>> <span><?php echo esc_html( $item['label'] ); ?></span></label></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<style>
.was-checklist-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;margin-top:24px}.was-checklist-card{padding:20px}.was-checklist-card h2{margin-top:0}.was-public-link{display:block;margin-top:12px}.was-checklist-detail{max-width:820px;padding:24px}.was-checklist-items{list-style:none;margin:24px 0 0;padding:0}.was-checklist-items li{padding:14px 8px;border-bottom:1px solid #e5e7eb}.was-checklist-items label{display:flex;gap:10px;align-items:center;font-size:14px}.was-checklist-items input{width:18px;height:18px}
</style>
<script>
document.querySelectorAll('.was-checklist-toggle').forEach(function (input) {
    input.addEventListener('change', function () {
        var slug = document.getElementById('was-checklist-items').dataset.slug;
        fetch('<?php echo esc_url( rest_url( 'was/v1/admin/checklists/' ) ); ?>' + slug, {method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':'<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'}, body:JSON.stringify({item_key:input.dataset.key,status:input.checked?'done':'pending'})}).then(function(r){if(!r.ok){input.checked=!input.checked; alert('Não foi possível salvar o checklist.');}}).catch(function(){input.checked=!input.checked; alert('Não foi possível salvar o checklist.');});
    });
});
</script>
