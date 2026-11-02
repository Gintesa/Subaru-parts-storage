<?php
/**
 * Plugin Name:       Subaru Parts Inventory
 * Description:       Inventory system for new and used Subaru parts: Subaru-catalog categories mapped to storage boxes, condition grading and inspection notes, printable part and box labels with QR codes and barcodes, scan lookup, CSV import/export, and a public catalogue block, shortcode and widget.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            gintesa
 * License:           GPL-2.0-or-later
 * Text Domain:       subaru-parts-inventory
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

define( 'SPS_VERSION', '1.2.0' );
define( 'SPS_FILE', __FILE__ );
define( 'SPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPS_URL', plugin_dir_url( __FILE__ ) );

require_once SPS_DIR . 'includes/helpers.php';
require_once SPS_DIR . 'includes/class-sps-post-types.php';
require_once SPS_DIR . 'includes/class-sps-categories.php';
require_once SPS_DIR . 'includes/class-sps-admin.php';
require_once SPS_DIR . 'includes/class-sps-labels.php';
require_once SPS_DIR . 'includes/class-sps-catalog.php';
require_once SPS_DIR . 'includes/class-sps-rest.php';
require_once SPS_DIR . 'includes/class-sps-frontend.php';
require_once SPS_DIR . 'includes/class-sps-app.php';
require_once SPS_DIR . 'includes/class-sps-widget.php';
require_once SPS_DIR . 'includes/class-sps-import-export.php';
require_once SPS_DIR . 'includes/class-sps-settings.php';

/**
 * Activation: register types, seed the Subaru category tree, grant capabilities.
 */
function sps_activate() {
	SPS_Post_Types::register();
	SPS_Post_Types::add_capabilities();
	SPS_Categories::seed();
	SPS_Catalog::install();
	sps_run_upgrades( (string) get_option( 'sps_version', '' ) );
	update_option( 'sps_version', SPS_VERSION );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sps_activate' );

function sps_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sps_deactivate' );

add_action(
	'plugins_loaded',
	static function () {
		SPS_Post_Types::init();
		SPS_Categories::init();
		SPS_Labels::init();
		SPS_Catalog::init();
		SPS_REST::init();
		SPS_Frontend::init();
		SPS_App::init();
		SPS_Widget::init();
		SPS_Import_Export::init();
		SPS_Settings::init();
		if ( is_admin() ) {
			SPS_Admin::init();
		}
	}
);

// Re-run setup steps after an update (activation hook does not fire on updates).
add_action(
	'init',
	static function () {
		if ( get_option( 'sps_version' ) !== SPS_VERSION ) {
			SPS_Post_Types::add_capabilities();
			SPS_Categories::seed();
			SPS_Catalog::install();
			sps_run_upgrades( (string) get_option( 'sps_version', '' ) );
			update_option( 'sps_version', SPS_VERSION );
			flush_rewrite_rules( false );
		}
	},
	99
);
