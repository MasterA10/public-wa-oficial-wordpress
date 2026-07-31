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
    <?php else : ?>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=was-master-checklists' ) ); ?>">← Voltar para os checklists</a></p>
        <div class="was-checklist-detail">
            <p><a href="<?php echo esc_url( home_url( '/checklists/' . $selected . '/' ) ); ?>" target="_blank" rel="noopener">Ver URL pública</a></p>
            <?php $checklist_slug = $selected; $checklist_editable = true; include WAS_PLUGIN_DIR . 'templates/checklist-public.php'; ?>
        </div>
    <?php endif; ?>
</div>
<style>
.was-checklist-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;margin-top:24px}.was-checklist-card{padding:20px}.was-checklist-card h2{margin-top:0}.was-public-link{display:block;margin-top:12px}.was-checklist-detail{max-width:980px;margin-top:18px;padding:24px;background:#fff}
</style>
