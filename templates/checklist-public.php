<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$service = new \WAS\Compliance\ChecklistService();
$checklist = $service->get( get_query_var( 'was_checklist' ) );
$items = $service->get_items( $checklist['slug'] );
status_header( 200 );
nocache_headers();
?><!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html( $checklist['title'] ); ?></title><?php wp_head(); ?><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f3f4f6;color:#172033;margin:0}.was-public-checklist{max-width:760px;margin:60px auto;padding:32px;background:#fff;border-radius:12px;box-shadow:0 8px 30px #17203314}.was-public-checklist h1{margin-top:0}.was-public-checklist li{list-style:none;border-bottom:1px solid #e5e7eb;padding:15px 0}.was-public-checklist ul{padding:0}.was-public-checklist input{width:18px;height:18px;vertical-align:middle;margin-right:10px}</style></head><body><main class="was-public-checklist"><h1><?php echo esc_html( $checklist['title'] ); ?></h1><p><?php echo esc_html( $checklist['description'] ); ?></p><ul><?php foreach ( $items as $item ) : ?><li><input type="checkbox" disabled <?php checked( 'done', $item['status'] ); ?>><?php echo esc_html( $item['label'] ); ?></li><?php endforeach; ?></ul><p><small>Visualização pública. As marcações são gerenciadas no painel do WordPress.</small></p></main><?php wp_footer(); ?></body></html>
