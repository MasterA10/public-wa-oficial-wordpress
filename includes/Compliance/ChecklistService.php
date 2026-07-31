<?php

namespace WAS\Compliance;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Catalog of the two checklist HTML files kept at the plugin root. */
class ChecklistService {
	public function get_catalog() {
		return [
			'onboarding-pt' => [ 'slug' => 'onboarding-pt', 'title' => 'Checklist de Permissões WhatsApp (Português)', 'description' => 'Checklist de onboarding e permissões da Meta em português.', 'file' => WAS_PLUGIN_DIR . 'onboarding-whatsapp-permissions-pt.html' ],
			'onboarding-en' => [ 'slug' => 'onboarding-en', 'title' => 'WhatsApp Permissions Checklist (English)', 'description' => 'Meta onboarding and permissions checklist in English.', 'file' => WAS_PLUGIN_DIR . 'onboarding-whatsapp-permissions-en.html' ],
		];
	}

	public function get( $slug ) {
		$catalog = $this->get_catalog();
		if ( 'app-review' === $slug ) { $slug = 'onboarding-pt'; }
		return $catalog[ $slug ] ?? null;
	}

	public function get_source( $slug ) {
		$checklist = $this->get( $slug );
		return $checklist && is_readable( $checklist['file'] ) ? file_get_contents( $checklist['file'] ) : null;
	}

	public function get_items( $slug ) {
		$source = $this->get_source( $slug );
		if ( null === $source ) { return null; }
		preg_match_all( '/<input\s+type=["\']checkbox["\'][^>]*\sid=["\']([^"\']+)["\'][^>]*>/i', $source, $matches );
		$canonical = $this->get( $slug )['slug'];
		$statuses = (array) get_option( 'was_checklist_status_' . $canonical, [] );
		$items = [];
		foreach ( $matches[1] as $item_key ) {
			$key = sanitize_key( $item_key );
			$items[] = [ 'item_key' => $key, 'status' => ( isset( $statuses[ $key ] ) && 'done' === $statuses[ $key ] ) ? 'done' : 'pending' ];
		}
		return $items;
	}

	public function update_item( $slug, $item_key, $status ) {
		$valid_keys = wp_list_pluck( (array) $this->get_items( $slug ), 'item_key' );
		if ( ! in_array( sanitize_key( $item_key ), $valid_keys, true ) ) { return false; }
		$canonical = $this->get( $slug )['slug'];
		$statuses = (array) get_option( 'was_checklist_status_' . $canonical, [] );
		$statuses[ sanitize_key( $item_key ) ] = 'done' === $status ? 'done' : 'pending';
		update_option( 'was_checklist_status_' . $canonical, $statuses, false );
		return true;
	}
}
