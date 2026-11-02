<?php
/**
 * Uninstall: only removes data when "Delete all data" is enabled in Settings.
 *
 * @package SubaruPartsInventory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sps_settings = get_option( 'sps_settings', array() );
if ( empty( $sps_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$sps_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'sps_part' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $sps_ids as $sps_id ) {
	wp_delete_post( (int) $sps_id, true );
}

foreach ( array( 'sps_category', 'sps_fitment' ) as $sps_tax ) {
	$sps_terms = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, tt.term_taxonomy_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s", $sps_tax ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $sps_terms as $sps_term ) {
		$wpdb->delete( $wpdb->termmeta, array( 'term_id' => $sps_term->term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $sps_term->term_taxonomy_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $sps_term->term_taxonomy_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->terms, array( 'term_id' => $sps_term->term_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

foreach ( array( 'sps_settings', 'sps_version', 'sps_sku_counter' ) as $sps_opt ) {
	delete_option( $sps_opt );
}
foreach ( array( 'sps_category_counts', 'sps_stats', 'sps_public_counts' ) as $sps_t ) {
	delete_transient( $sps_t );
}

$sps_caps = array( 'edit_sps_part', 'read_sps_part', 'delete_sps_part', 'edit_sps_parts', 'edit_others_sps_parts', 'publish_sps_parts', 'read_private_sps_parts', 'delete_sps_parts', 'delete_private_sps_parts', 'delete_published_sps_parts', 'delete_others_sps_parts', 'edit_private_sps_parts', 'edit_published_sps_parts', 'create_sps_parts', 'manage_sps_parts' );
$sps_admin = get_role( 'administrator' );
if ( $sps_admin ) {
	foreach ( $sps_caps as $sps_cap ) {
		$sps_admin->remove_cap( $sps_cap );
	}
}
remove_role( 'sps_parts_manager' );
