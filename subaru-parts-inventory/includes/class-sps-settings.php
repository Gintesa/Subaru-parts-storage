<?php
/**
 * Settings page.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_sps_reseed', array( __CLASS__, 'reseed' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SPS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . SPS_PT, __( 'Settings', 'subaru-parts-inventory' ), __( 'Settings', 'subaru-parts-inventory' ), SPS_CAP, 'sps-settings', array( __CLASS__, 'page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-settings' ) ) . '">' . esc_html__( 'Settings', 'subaru-parts-inventory' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			'sps_settings',
			'sps_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => sps_default_settings(),
			)
		);
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$def = sps_default_settings();
		$out = array(
			'sku_prefix'          => isset( $in['sku_prefix'] ) ? preg_replace( '/[^A-Za-z0-9\-_.]/', '', $in['sku_prefix'] ) : $def['sku_prefix'],
			'sku_digits'          => isset( $in['sku_digits'] ) ? max( 3, min( 10, (int) $in['sku_digits'] ) ) : $def['sku_digits'],
			'default_fitment'     => isset( $in['default_fitment'] ) ? sanitize_text_field( $in['default_fitment'] ) : '',
			'default_removed_from' => isset( $in['default_removed_from'] ) ? strtoupper( sanitize_text_field( $in['default_removed_from'] ) ) : '',
			'distance_unit'       => isset( $in['distance_unit'] ) && 'mi' === $in['distance_unit'] ? 'mi' : 'km',
			'currency'            => isset( $in['currency'] ) ? sanitize_text_field( $in['currency'] ) : $def['currency'],
			'label_template'      => isset( $in['label_template'] ) && array_key_exists( $in['label_template'], SPS_Labels::templates() ) ? $in['label_template'] : $def['label_template'],
			'label_fields'        => isset( $in['label_fields'] ) ? array_values( array_intersect( (array) $in['label_fields'], array_keys( SPS_Labels::fields() ) ) ) : array(),
			'public_default'      => empty( $in['public_default'] ) ? 0 : 1,
			'show_prices'         => empty( $in['show_prices'] ) ? 0 : 1,
			'show_quantity'       => empty( $in['show_quantity'] ) ? 0 : 1,
			'show_location'       => empty( $in['show_location'] ) ? 0 : 1,
			'per_page'            => isset( $in['per_page'] ) ? max( 1, min( 100, (int) $in['per_page'] ) ) : $def['per_page'],
			'catalog_page_id'     => isset( $in['catalog_page_id'] ) ? absint( $in['catalog_page_id'] ) : 0,
			'delete_on_uninstall' => empty( $in['delete_on_uninstall'] ) ? 0 : 1,
		);
		if ( '' === $out['sku_prefix'] ) {
			$out['sku_prefix'] = $def['sku_prefix'];
		}
		sps_flush_caches();
		return $out;
	}

	public static function page() {
		$s = sps_setting();
		$n = 'sps_settings';
		?>
		<div class="wrap sps-wrap">
			<h1><?php esc_html_e( 'Subaru Parts – Settings', 'subaru-parts-inventory' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'sps_settings' ); ?>
				<h2><?php esc_html_e( 'Labels & IDs', 'subaru-parts-inventory' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="sku_prefix"><?php esc_html_e( 'Label ID format', 'subaru-parts-inventory' ); ?></label></th>
						<td><input id="sku_prefix" name="<?php echo esc_attr( $n ); ?>[sku_prefix]" value="<?php echo esc_attr( $s['sku_prefix'] ); ?>" class="small-text" />
						<input type="number" min="3" max="10" name="<?php echo esc_attr( $n ); ?>[sku_digits]" value="<?php echo (int) $s['sku_digits']; ?>" class="small-text" /> <?php esc_html_e( 'digits', 'subaru-parts-inventory' ); ?>
						<p class="description"><?php /* translators: %s: example */ echo esc_html( sprintf( __( 'Example: %s. Every part gets a unique label ID; it is never reused.', 'subaru-parts-inventory' ), $s['sku_prefix'] . str_pad( '42', (int) $s['sku_digits'], '0', STR_PAD_LEFT ) ) ); ?></p></td></tr>
					<tr><th scope="row"><label for="label_template"><?php esc_html_e( 'Default label format', 'subaru-parts-inventory' ); ?></label></th>
						<td><select id="label_template" name="<?php echo esc_attr( $n ); ?>[label_template]">
						<?php foreach ( SPS_Labels::templates() as $key => $t ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['label_template'], $key ); ?>><?php echo esc_html( $t['name'] ); ?></option>
						<?php endforeach; ?>
						</select></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Default label content', 'subaru-parts-inventory' ); ?></th>
						<td><p class="description"><?php esc_html_e( 'The OEM part number is always printed.', 'subaru-parts-inventory' ); ?></p>
						<?php foreach ( SPS_Labels::fields() as $key => $label ) : ?>
							<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[label_fields][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $s['label_fields'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?></td></tr>
				</table>

				<h2><?php esc_html_e( 'Inventory', 'subaru-parts-inventory' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="default_fitment"><?php esc_html_e( 'Default vehicle for new parts', 'subaru-parts-inventory' ); ?></label></th>
						<td><input id="default_fitment" class="regular-text" name="<?php echo esc_attr( $n ); ?>[default_fitment]" value="<?php echo esc_attr( $s['default_fitment'] ); ?>" /></td></tr>
					<tr><th scope="row"><label for="default_removed_from"><?php esc_html_e( 'Default "Removed from" (your VIN)', 'subaru-parts-inventory' ); ?></label></th>
						<td><input id="default_removed_from" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[default_removed_from]" value="<?php echo esc_attr( $s['default_removed_from'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Pre-filled on every new used / bad / unusable part. Change it per part for parts from a donor car.', 'subaru-parts-inventory' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Units', 'subaru-parts-inventory' ); ?></th>
						<td><select name="<?php echo esc_attr( $n ); ?>[distance_unit]"><option value="km" <?php selected( $s['distance_unit'], 'km' ); ?>>km</option><option value="mi" <?php selected( $s['distance_unit'], 'mi' ); ?>>miles</option></select>
						<label><?php esc_html_e( 'Currency', 'subaru-parts-inventory' ); ?> <input class="small-text" name="<?php echo esc_attr( $n ); ?>[currency]" value="<?php echo esc_attr( $s['currency'] ); ?>" /></label></td></tr>
				</table>

				<h2><?php esc_html_e( 'Public catalogue', 'subaru-parts-inventory' ); ?></h2>
				<p><?php echo wp_kses_post( __( 'Show your parts on any page with the <strong>Subaru Parts Catalogue</strong> block or the <code>[subaru_parts]</code> shortcode, and in sidebars with the <strong>Subaru Parts Inventory</strong> widget. Sold, installed and scrapped parts are never shown publicly.', 'subaru-parts-inventory' ) ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Visibility', 'subaru-parts-inventory' ); ?></th>
						<td>
							<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[public_default]" value="1" <?php checked( $s['public_default'], 1 ); ?> /> <?php esc_html_e( 'New parts are public by default', 'subaru-parts-inventory' ); ?></label>
							<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_quantity]" value="1" <?php checked( $s['show_quantity'], 1 ); ?> /> <?php esc_html_e( 'Show quantity', 'subaru-parts-inventory' ); ?></label>
							<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_prices]" value="1" <?php checked( $s['show_prices'], 1 ); ?> /> <?php esc_html_e( 'Show asking prices', 'subaru-parts-inventory' ); ?></label>
							<label style="display:block"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[show_location]" value="1" <?php checked( $s['show_location'], 1 ); ?> /> <?php esc_html_e( 'Show storage box / location', 'subaru-parts-inventory' ); ?></label>
						</td></tr>
					<tr><th scope="row"><label for="per_page"><?php esc_html_e( 'Parts per page', 'subaru-parts-inventory' ); ?></label></th>
						<td><input id="per_page" type="number" min="1" max="100" class="small-text" name="<?php echo esc_attr( $n ); ?>[per_page]" value="<?php echo (int) $s['per_page']; ?>" /></td></tr>
					<tr><th scope="row"><label for="catalog_page_id"><?php esc_html_e( 'Catalogue page', 'subaru-parts-inventory' ); ?></label></th>
						<td><?php wp_dropdown_pages( array( 'name' => $n . '[catalog_page_id]', 'id' => 'catalog_page_id', 'selected' => (int) $s['catalog_page_id'], 'show_option_none' => __( '— Use the built-in /parts/ archive —', 'subaru-parts-inventory' ), 'option_none_value' => 0 ) ); ?>
						<p class="description"><?php esc_html_e( 'Page containing the catalogue block/shortcode. The widget search box links there.', 'subaru-parts-inventory' ); ?></p></td></tr>
				</table>

				<h2><?php esc_html_e( 'Uninstall', 'subaru-parts-inventory' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Data', 'subaru-parts-inventory' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[delete_on_uninstall]" value="1" <?php checked( $s['delete_on_uninstall'], 1 ); ?> /> <?php esc_html_e( 'Delete all parts, categories and settings when the plugin is deleted', 'subaru-parts-inventory' ); ?></label></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Subaru catalog categories', 'subaru-parts-inventory' ); ?></h2>
			<p><?php esc_html_e( 'Re-create any of the default Subaru catalog categories you deleted. Existing categories, box codes and your own categories are not changed.', 'subaru-parts-inventory' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sps_reseed" />
				<?php wp_nonce_field( 'sps_reseed' ); ?>
				<button class="button"><?php esc_html_e( 'Restore default categories', 'subaru-parts-inventory' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . SPS_TAX_CAT . '&post_type=' . SPS_PT ) ); ?>"><?php esc_html_e( 'Edit categories & boxes', 'subaru-parts-inventory' ); ?></a>
			</form>
		</div>
		<?php
	}

	public static function reseed() {
		check_admin_referer( 'sps_reseed' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		SPS_Categories::seed();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-settings&sps_msg=' . rawurlencode( __( 'Default categories restored.', 'subaru-parts-inventory' ) ) ) );
		exit;
	}
}
