<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$service = new \WAS\Compliance\ChecklistService();
$slug = $checklist_slug ?? sanitize_key( get_query_var( 'was_checklist' ) );
$checklist = $service->get( $slug );
$source = $service->get_source( $slug );
$editable = ! empty( $checklist_editable );
if ( ! $checklist || null === $source ) { status_header( 404 ); echo 'Checklist não encontrado.'; return; }
$script = '<script>document.querySelectorAll("input[type=checkbox][id]").forEach(function(input){var key="was-checklist-' . esc_js( $checklist['slug'] ) . '-"+input.id;var saved=localStorage.getItem(key);if(saved!==null){input.checked=saved==="1";}input.addEventListener("change",function(){localStorage.setItem(key,input.checked?"1":"0");' . ( $editable ? 'fetch(' . wp_json_encode( rest_url( 'was/v1/admin/checklists/' . $checklist['slug'] ) ) . ',{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . '},body:JSON.stringify({item_key:input.id,status:input.checked?"done":"pending")});' : '' ) . '});});</script>';
if ( $editable ) {
	preg_match( '/<style[^>]*>.*?<\/style>/is', $source, $style_match );
	preg_match( '/<body[^>]*>(.*?)<\/body>/is', $source, $body_match );
	echo ( $style_match[0] ?? '' ) . ( $body_match[1] ?? $source ) . $script;
} else {
	echo str_replace( '</body>', $script . '</body>', $source );
}
