<?php
/**
 * Back-office: part editor, list table, dashboard and scan / quick-update screen.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes_' . SPS_PT, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . SPS_PT, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'after_title' ) );

		// List table.
		add_filter( 'manage_' . SPS_PT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . SPS_PT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . SPS_PT . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'list_query' ) );
		add_filter( 'posts_search', array( __CLASS__, 'search_meta' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . SPS_PT, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . SPS_PT, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_action_sps_duplicate', array( __CLASS__, 'duplicate' ) );
		add_action( 'admin_post_sps_quick_update', array( __CLASS__, 'quick_update' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Menu & assets                                                       */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		$parent = 'edit.php?post_type=' . SPS_PT;
		add_submenu_page( $parent, __( 'Inventory Dashboard', 'subaru-parts-inventory' ), __( 'Dashboard', 'subaru-parts-inventory' ), SPS_EDIT_CAP, 'sps-dashboard', array( __CLASS__, 'dashboard_page' ), 0 );
		add_submenu_page( $parent, __( 'Scan / Quick update', 'subaru-parts-inventory' ), __( 'Scan / Lookup', 'subaru-parts-inventory' ), SPS_EDIT_CAP, 'sps-scan', array( __CLASS__, 'scan_page' ) );
	}

	public static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return SPS_PT === $screen->post_type || false !== strpos( (string) $screen->id, 'sps-' ) || ( isset( $screen->taxonomy ) && in_array( $screen->taxonomy, array( SPS_TAX_CAT, SPS_TAX_FIT ), true ) );
	}

	public static function assets() {
		if ( ! self::is_plugin_screen() ) {
			return;
		}
		wp_enqueue_style( 'sps-admin', SPS_URL . 'assets/css/admin.css', array(), SPS_VERSION );
		$screen = get_current_screen();
		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'sps-admin', SPS_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-api-fetch' ), SPS_VERSION, true );
		wp_localize_script(
			'sps-admin',
			'SPSAdmin',
			array(
				'restPath'   => '/subaru-parts/v1/',
				'conditions' => sps_conditions(),
				'postId'     => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
				'scanUrl'    => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-scan' ),
				'i18n'       => array(
					'galleryTitle'  => __( 'Part photos', 'subaru-parts-inventory' ),
					'galleryButton' => __( 'Use these photos', 'subaru-parts-inventory' ),
					'duplicate'     => __( 'You already have this part number in inventory:', 'subaru-parts-inventory' ),
					'noCamera'      => __( 'Camera scanning is not supported in this browser. Use your phone camera app to scan the QR code, or type the code.', 'subaru-parts-inventory' ),
					'remove'        => __( 'Remove', 'subaru-parts-inventory' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Editor                                                              */
	/* ------------------------------------------------------------------ */

	public static function title_placeholder( $text, $post ) {
		return SPS_PT === $post->post_type ? __( 'Part name, e.g. Turbocharger assembly', 'subaru-parts-inventory' ) : $text;
	}

	public static function after_title( $post ) {
		if ( SPS_PT !== $post->post_type ) {
			return;
		}
		$sku = sps_meta( $post->ID, 'sku' );
		echo '<div class="sps-after-title">';
		if ( $sku ) {
			printf( '<span class="sps-sku-pill">%s %s</span>', esc_html__( 'Label ID', 'subaru-parts-inventory' ), '<strong>' . esc_html( $sku ) . '</strong>' );
		}
		echo '<span class="description">' . esc_html__( 'Use the editor below for a free description (what it is, what is included). Condition details go in the Condition box.', 'subaru-parts-inventory' ) . '</span></div>';
	}

	public static function meta_boxes( $post ) {
		add_meta_box( 'sps_identity', __( 'Part identification', 'subaru-parts-inventory' ), array( __CLASS__, 'box_identity' ), SPS_PT, 'normal', 'high' );
		add_meta_box( 'sps_condition', __( 'Condition', 'subaru-parts-inventory' ), array( __CLASS__, 'box_condition' ), SPS_PT, 'normal', 'high' );
		add_meta_box( 'sps_inspections', __( 'Condition log / inspection notes', 'subaru-parts-inventory' ), array( __CLASS__, 'box_inspections' ), SPS_PT, 'normal', 'default' );
		add_meta_box( 'sps_money', __( 'Purchase & value (private)', 'subaru-parts-inventory' ), array( __CLASS__, 'box_money' ), SPS_PT, 'normal', 'default' );
		add_meta_box( 'sps_gallery', __( 'Photo gallery', 'subaru-parts-inventory' ), array( __CLASS__, 'box_gallery' ), SPS_PT, 'normal', 'default' );
		add_meta_box( 'sps_stock', __( 'Category, box & stock', 'subaru-parts-inventory' ), array( __CLASS__, 'box_stock' ), SPS_PT, 'side', 'high' );
		add_meta_box( 'sps_label', __( 'Physical label', 'subaru-parts-inventory' ), array( __CLASS__, 'box_label' ), SPS_PT, 'side', 'default' );
	}

	private static function field( $post_id, $key, $label, $args = array() ) {
		$args  = wp_parse_args( $args, array( 'type' => 'text', 'placeholder' => '', 'help' => '', 'class' => '', 'attrs' => '' ) );
		$value = sps_meta( $post_id, $key );
		if ( 'removed_from' === $key && '' === $value && 'auto-draft' === get_post_status( $post_id ) ) {
			$value = (string) sps_setting( 'default_removed_from' );
		}
		if ( 'part_number' === $key && '' === $value && isset( $_GET['sps_pn'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- prefill only.
			$value = sps_sanitize_field( 'part_number', wp_unslash( $_GET['sps_pn'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		$id    = 'sps_' . $key;
		echo '<p class="sps-field ' . esc_attr( $args['class'] ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( 'textarea' === $args['type'] ) {
			printf( '<textarea id="%1$s" name="sps[%2$s]" rows="4" placeholder="%3$s">%4$s</textarea>', esc_attr( $id ), esc_attr( $key ), esc_attr( $args['placeholder'] ), esc_textarea( $value ) );
		} else {
			printf(
				'<input type="%1$s" id="%2$s" name="sps[%3$s]" value="%4$s" placeholder="%5$s" %6$s />',
				esc_attr( $args['type'] ),
				esc_attr( $id ),
				esc_attr( $key ),
				esc_attr( $value ),
				esc_attr( $args['placeholder'] ),
				$args['attrs'] // phpcs:ignore WordPress.Security.EscapeOutput -- static attribute strings.
			);
		}
		if ( $args['help'] ) {
			echo '<span class="description">' . esc_html( $args['help'] ) . '</span>';
		}
		echo '</p>';
	}

	public static function box_identity( $post ) {
		wp_nonce_field( 'sps_save_part', 'sps_nonce' );
		echo '<div class="sps-grid">';
		self::field( $post->ID, 'part_number', __( 'OEM part number', 'subaru-parts-inventory' ), array( 'placeholder' => '14411AA850', 'help' => __( 'As printed on the part / Subaru catalog. Printed on the label.', 'subaru-parts-inventory' ), 'class' => 'sps-field--wide sps-part-number', 'attrs' => 'autocomplete="off" spellcheck="false"' ) );
		self::field( $post->ID, 'alt_numbers', __( 'Superseded / alternate numbers', 'subaru-parts-inventory' ), array( 'placeholder' => '14411AA780, 14411AA790', 'help' => __( 'Comma separated. Searchable.', 'subaru-parts-inventory' ) ) );
		self::field( $post->ID, 'epc_ref', __( 'Catalog diagram ref.', 'subaru-parts-inventory' ), array( 'placeholder' => '14411 / ref #3', 'help' => __( 'Reference number on the parts.subaru.com diagram.', 'subaru-parts-inventory' ) ) );
		echo '</div><div id="sps-duplicate-warning" class="notice notice-warning inline" hidden></div>';

		$fitment = wp_get_post_terms( $post->ID, SPS_TAX_FIT, array( 'fields' => 'names' ) );
		$fitment = is_wp_error( $fitment ) ? array() : $fitment;
		if ( ! $fitment && 'auto-draft' === $post->post_status && sps_setting( 'default_fitment' ) ) {
			$fitment = array( sps_setting( 'default_fitment' ) );
		}
		echo '<p class="sps-field sps-field--full"><label for="sps_fitment">' . esc_html__( 'Fits (vehicles)', 'subaru-parts-inventory' ) . '</label>';
		printf( '<input type="text" id="sps_fitment" name="sps_fitment" value="%s" list="sps-fitment-list" />', esc_attr( implode( ', ', $fitment ) ) );
		echo '<datalist id="sps-fitment-list">';
		foreach ( get_terms( array( 'taxonomy' => SPS_TAX_FIT, 'hide_empty' => false, 'fields' => 'names' ) ) as $name ) {
			echo '<option value="' . esc_attr( $name ) . '"></option>';
		}
		echo '</datalist><span class="description">' . esc_html__( 'Comma separated, e.g. "2017 Subaru WRX 2.0L 6MT Limited, 2015-2021 WRX".', 'subaru-parts-inventory' ) . '</span></p>';
	}

	public static function box_condition( $post ) {
		$current = sps_meta( $post->ID, 'condition', 'used' );
		$flags   = (array) sps_meta( $post->ID, 'condition_flags', array() );
		echo '<div class="sps-condition-picker" role="radiogroup">';
		foreach ( sps_conditions() as $key => $c ) {
			printf(
				'<label class="sps-cond sps-cond--%1$s" style="--sps-badge:%2$s"><input type="radio" name="sps[condition]" value="%3$s" %4$s /><span class="sps-cond__short">%5$s</span><span class="sps-cond__label">%6$s</span><span class="sps-cond__desc">%7$s</span></label>',
				esc_attr( $c['group'] ),
				esc_attr( $c['color'] ),
				esc_attr( $key ),
				checked( $current, $key, false ),
				esc_html( $c['short'] ),
				esc_html( $c['label'] ),
				esc_html( $c['desc'] )
			);
		}
		echo '</div>';

		echo '<div class="sps-used-only"><h4>' . esc_html__( 'Condition checklist', 'subaru-parts-inventory' ) . '</h4><div class="sps-flags">';
		foreach ( sps_condition_flags() as $key => $label ) {
			printf( '<label><input type="checkbox" name="sps[condition_flags][]" value="%1$s" %2$s /> %3$s</label>', esc_attr( $key ), checked( in_array( $key, $flags, true ), true, false ), esc_html( $label ) );
		}
		echo '</div></div>';

		self::field( $post->ID, 'condition_notes', __( 'Condition notes (shown publicly)', 'subaru-parts-inventory' ), array( 'type' => 'textarea', 'class' => 'sps-field--full', 'placeholder' => __( 'e.g. Small crack on lower mounting tab, all clips intact, no leaks. Shaft play checked OK.', 'subaru-parts-inventory' ) ) );
		echo '<div class="sps-grid sps-used-only">';
		/* translators: %s: km or mi */
		self::field( $post->ID, 'mileage', sprintf( __( 'Mileage on part (%s)', 'subaru-parts-inventory' ), sps_setting( 'distance_unit' ) ), array( 'type' => 'number', 'attrs' => 'min="0" step="1"' ) );
		self::field( $post->ID, 'removed_from', __( 'Removed from (donor vehicle / VIN)', 'subaru-parts-inventory' ), array( 'placeholder' => 'JF1VA1… / 2016 WRX donor', 'help' => __( 'Private. New parts default to the VIN set in Settings.', 'subaru-parts-inventory' ) ) );
		echo '</div>';
	}

	public static function box_inspections( $post ) {
		$log = sps_get_inspections( $post->ID );
		echo '<p class="description">' . esc_html__( 'Dated notes about the part\'s condition: when inspected, tested, cleaned, damage found, etc. Kept as a history (private).', 'subaru-parts-inventory' ) . '</p>';
		echo '<table class="widefat striped sps-log"><thead><tr><th style="width:120px">' . esc_html__( 'Date', 'subaru-parts-inventory' ) . '</th><th style="width:110px">' . esc_html__( 'Grade', 'subaru-parts-inventory' ) . '</th><th>' . esc_html__( 'Note', 'subaru-parts-inventory' ) . '</th><th style="width:120px">' . esc_html__( 'By', 'subaru-parts-inventory' ) . '</th><th style="width:40px"></th></tr></thead><tbody>';
		foreach ( $log as $i => $entry ) {
			printf(
				'<tr><td>%1$s<input type="hidden" name="sps_log_keep[]" value="%6$s" /></td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td><button type="button" class="button-link sps-log-remove" aria-label="%5$s">✕</button></td></tr>',
				esc_html( $entry['date'] ),
				$entry['condition'] ? sps_condition_badge( $entry['condition'] ) : '', // phpcs:ignore WordPress.Security.EscapeOutput
				nl2br( esc_html( $entry['note'] ) ),
				esc_html( $entry['user'] ),
				esc_attr__( 'Remove', 'subaru-parts-inventory' ),
				esc_attr( md5( wp_json_encode( $entry ) ) )
			);
		}
		if ( ! $log ) {
			echo '<tr class="sps-log-empty"><td colspan="5">' . esc_html__( 'No entries yet.', 'subaru-parts-inventory' ) . '</td></tr>';
		}
		echo '</tbody></table><input type="hidden" name="sps_log_present" value="1" />';

		echo '<div class="sps-log-add"><h4>' . esc_html__( 'Add entry', 'subaru-parts-inventory' ) . '</h4><div class="sps-grid">';
		printf( '<p class="sps-field"><label>%1$s</label><input type="date" name="sps_log_new[date]" value="%2$s" /></p>', esc_html__( 'Date', 'subaru-parts-inventory' ), esc_attr( current_time( 'Y-m-d' ) ) );
		echo '<p class="sps-field"><label>' . esc_html__( 'Grade at inspection', 'subaru-parts-inventory' ) . '</label><select name="sps_log_new[condition]"><option value="">—</option>';
		foreach ( sps_conditions() as $key => $c ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $c['label'] ) . '</option>';
		}
		echo '</select></p></div>';
		echo '<p class="sps-field sps-field--full"><textarea name="sps_log_new[note]" rows="2" placeholder="' . esc_attr__( 'e.g. Bench tested, holds pressure. Replaced missing bolt.', 'subaru-parts-inventory' ) . '"></textarea></p></div>';
	}

	public static function box_money( $post ) {
		$cur = sps_setting( 'currency' );
		echo '<div class="sps-grid">';
		/* translators: %s: currency */
		self::field( $post->ID, 'purchase_price', sprintf( __( 'Purchase price (%s)', 'subaru-parts-inventory' ), $cur ), array( 'type' => 'number', 'attrs' => 'min="0" step="0.01"' ) );
		self::field( $post->ID, 'purchase_date', __( 'Purchase date', 'subaru-parts-inventory' ), array( 'type' => 'date' ) );
		self::field( $post->ID, 'source', __( 'Source / supplier', 'subaru-parts-inventory' ), array( 'placeholder' => __( 'Dealer, eBay seller, donor car…', 'subaru-parts-inventory' ) ) );
		/* translators: %s: currency */
		self::field( $post->ID, 'value', sprintf( __( 'Estimated value (%s)', 'subaru-parts-inventory' ), $cur ), array( 'type' => 'number', 'attrs' => 'min="0" step="0.01"', 'help' => __( 'Per item. Leave empty to estimate it automatically from the parts.subaru.com list price and the condition (see Settings).', 'subaru-parts-inventory' ) ) );
		/* translators: %s: currency */
		self::field( $post->ID, 'sale_price', sprintf( __( 'Asking price (%s)', 'subaru-parts-inventory' ), $cur ), array( 'type' => 'number', 'attrs' => 'min="0" step="0.01"', 'help' => __( 'Shown publicly only if enabled in Settings.', 'subaru-parts-inventory' ) ) );
		self::field( $post->ID, 'weight', __( 'Weight (kg)', 'subaru-parts-inventory' ), array( 'type' => 'number', 'attrs' => 'min="0" step="0.01"' ) );
		/* translators: 1: shop, 2: currency */
		self::field( $post->ID, 'shop_price', sprintf( __( '%1$s price (%2$s)', 'subaru-parts-inventory' ), sps_setting( 'shop_name' ), sps_setting( 'shop_currency' ) ), array( 'type' => 'number', 'attrs' => 'min="0" step="0.01"', 'help' => __( 'New-part price in the shop. Used for the value estimate (× condition %). Remembered for this part number.', 'subaru-parts-inventory' ) ) );
		$search = sps_shop_search_url( sps_meta( $post->ID, 'part_number' ) );
		if ( $search ) {
			/* translators: %s: shop */
			echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $search ) . '">🔎 ' . esc_html( sprintf( __( 'Check on %s', 'subaru-parts-inventory' ), sps_setting( 'shop_name' ) ) ) . '</a></p>';
		}
		echo '</div>';
		self::field( $post->ID, 'private_notes', __( 'Private notes', 'subaru-parts-inventory' ), array( 'type' => 'textarea', 'class' => 'sps-field--full' ) );
	}

	public static function box_gallery( $post ) {
		$ids = array_filter( array_map( 'intval', explode( ',', (string) sps_meta( $post->ID, 'gallery' ) ) ) );
		echo '<p class="description">' . esc_html__( 'Extra photos (damage close-ups, part number stamp, connectors). The main photo is set in the "Main photo" box.', 'subaru-parts-inventory' ) . '</p>';
		echo '<ul class="sps-gallery">';
		foreach ( $ids as $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $src ) {
				printf( '<li data-id="%1$d"><img src="%2$s" alt="" /><button type="button" class="sps-gallery-remove" aria-label="%3$s">✕</button></li>', (int) $id, esc_url( $src ), esc_attr__( 'Remove', 'subaru-parts-inventory' ) );
			}
		}
		echo '</ul><input type="hidden" id="sps_gallery" name="sps_gallery" value="' . esc_attr( implode( ',', $ids ) ) . '" />';
		echo '<p><button type="button" class="button sps-gallery-add">' . esc_html__( 'Add photos', 'subaru-parts-inventory' ) . '</button></p>';
	}

	public static function box_stock( $post ) {
		$cat      = sps_part_category( $post->ID );
		$cat_id   = $cat ? $cat->term_id : 0;
		$status   = sps_meta( $post->ID, 'status', 'not_boxed' );
		$qty      = sps_meta( $post->ID, 'quantity', 1 );
		$public   = 'auto-draft' === $post->post_status ? (int) sps_setting( 'public_default' ) : (int) sps_meta( $post->ID, 'public', 0 );
		$box      = sps_part_box( $post->ID );
		echo '<p class="sps-field"><label for="sps_category_id">' . esc_html__( 'Subaru catalog category', 'subaru-parts-inventory' ) . '</label>';
		echo '<select id="sps_category_id" name="sps_category_id" class="sps-category-select"><option value="0">' . esc_html__( '— Select —', 'subaru-parts-inventory' ) . '</option>' . sps_category_options( $cat_id ) . '</select></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="sps-box-display"><span class="sps-box-display__label">' . esc_html__( 'Goes in box', 'subaru-parts-inventory' ) . '</span> <strong class="sps-box-display__code">' . esc_html( $box['code'] ? $box['code'] : '—' ) . '</strong> <span class="sps-box-display__loc">' . esc_html( $box['location'] ) . '</span></div>';
		self::field( $post->ID, 'box_override', __( 'Different box (override)', 'subaru-parts-inventory' ), array( 'placeholder' => __( 'Only if not in the category box', 'subaru-parts-inventory' ) ) );
		self::field( $post->ID, 'bin', __( 'Position / bag in box', 'subaru-parts-inventory' ), array( 'placeholder' => __( 'e.g. bag 3, bottom', 'subaru-parts-inventory' ) ) );
		echo '<div class="sps-grid sps-grid--2">';
		echo '<p class="sps-field"><label for="sps_quantity">' . esc_html__( 'Quantity', 'subaru-parts-inventory' ) . '</label><input type="number" min="0" step="1" id="sps_quantity" name="sps[quantity]" value="' . esc_attr( $qty ) . '" /></p>';
		echo '<p class="sps-field"><label for="sps_status">' . esc_html__( 'Status', 'subaru-parts-inventory' ) . '</label><select id="sps_status" name="sps[status]">';
		foreach ( sps_statuses() as $key => $s ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $status, $key, false ) . '>' . esc_html( $s['label'] ) . '</option>';
		}
		echo '</select></p></div>';
		echo '<input type="hidden" name="sps[public]" value="0" /><label class="sps-check"><input type="checkbox" name="sps[public]" value="1" ' . checked( $public, 1, false ) . ' /> ' . esc_html__( 'Show in public catalogue', 'subaru-parts-inventory' ) . '</label>';
	}

	public static function box_label( $post ) {
		$sku     = sps_meta( $post->ID, 'sku' );
		$printed = sps_meta( $post->ID, 'label_printed' );
		if ( ! $sku ) {
			echo '<p class="description">' . esc_html__( 'The label ID is assigned when the part is saved.', 'subaru-parts-inventory' ) . '</p>';
			return;
		}
		echo '<div class="sps-label-mini"><div class="sps-qr" data-qr="' . esc_attr( sps_scan_url( $sku ) ) . '" data-size="84"></div><div><strong>' . esc_html( sps_meta( $post->ID, 'part_number', '—' ) ) . '</strong><br>' . esc_html( get_the_title( $post ) ) . '<br><code>' . esc_html( $sku ) . '</code></div></div>';
		echo '<p>' . ( $printed
			/* translators: %s: date */
			? '✅ ' . esc_html( sprintf( __( 'Label printed %s', 'subaru-parts-inventory' ), mysql2date( get_option( 'date_format' ), $printed ) ) )
			: '⚠️ ' . esc_html__( 'Label not printed yet', 'subaru-parts-inventory' ) ) . '</p>';
		echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( SPS_Labels::print_url( array( $post->ID ) ) ) . '">' . esc_html__( 'Print label', 'subaru-parts-inventory' ) . '</a></p>';
		wp_enqueue_script( 'sps-qrcode', SPS_URL . 'assets/js/vendor/qrcode.js', array(), '1.4.4', true );
	}

	/**
	 * Save handler.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( ! isset( $_POST['sps_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sps_nonce'] ), 'sps_save_part' ) ) {
			return; // Saved outside the editor (import etc.); derived meta handled by SPS_Post_Types::after_save().
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['sps'] ) && is_array( $_POST['sps'] ) ? wp_unslash( $_POST['sps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized per field.
		if ( ! isset( $raw['condition_flags'] ) ) {
			$raw['condition_flags'] = array();
		}
		$before_condition = sps_meta( $post_id, 'condition' );
		sps_save_part_meta( $post_id, $raw );

		if ( isset( $_POST['sps_gallery'] ) ) {
			$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['sps_gallery'] ) ) ) ) );
			update_post_meta( $post_id, '_sps_gallery', implode( ',', $ids ) );
		}

		if ( isset( $_POST['sps_category_id'] ) ) {
			$term_id = absint( $_POST['sps_category_id'] );
			if ( ! $term_id ) {
				$term_id = SPS_Categories::term_from_path( 'To Sort (Unidentified)' );
			}
			wp_set_object_terms( $post_id, $term_id ? array( $term_id ) : array(), SPS_TAX_CAT );
		}

		if ( isset( $_POST['sps_fitment'] ) ) {
			$names = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['sps_fitment'] ) ) ) ) );
			wp_set_object_terms( $post_id, $names, SPS_TAX_FIT );
		}

		// Inspection log: removals then additions.
		if ( isset( $_POST['sps_log_present'] ) ) {
			$keep = isset( $_POST['sps_log_keep'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['sps_log_keep'] ) ) : array();
			$log  = get_post_meta( $post_id, '_sps_inspections', true );
			if ( is_array( $log ) ) {
				$log = array_values(
					array_filter(
						$log,
						static function ( $entry ) use ( $keep ) {
							return in_array( md5( wp_json_encode( $entry ) ), $keep, true );
						}
					)
				);
				update_post_meta( $post_id, '_sps_inspections', $log );
			}
		}
		if ( ! empty( $_POST['sps_log_new']['note'] ) ) {
			$new = wp_unslash( $_POST['sps_log_new'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in sps_add_inspection.
			sps_add_inspection( $post_id, $new['note'], isset( $new['condition'] ) ? $new['condition'] : '', isset( $new['date'] ) ? $new['date'] : '' );
		} elseif ( $before_condition && isset( $raw['condition'] ) && $before_condition !== $raw['condition'] ) {
			$c = sps_conditions();
			/* translators: 1: old grade, 2: new grade */
			sps_add_inspection( $post_id, sprintf( __( 'Condition changed: %1$s → %2$s', 'subaru-parts-inventory' ), $c[ $before_condition ]['short'] ?? $before_condition, $c[ $raw['condition'] ]['short'] ?? $raw['condition'] ), $raw['condition'] );
		}
		sps_refresh_derived_meta( $post_id );
	}

	/* ------------------------------------------------------------------ */
	/* List table                                                          */
	/* ------------------------------------------------------------------ */

	public static function columns( $columns ) {
		return array(
			'cb'              => $columns['cb'],
			'sps_thumb'       => '<span class="screen-reader-text">' . esc_html__( 'Photo', 'subaru-parts-inventory' ) . '</span>',
			'sps_part_number' => __( 'Part number', 'subaru-parts-inventory' ),
			'title'           => __( 'Name', 'subaru-parts-inventory' ),
			'sps_condition'   => __( 'Condition', 'subaru-parts-inventory' ),
			'sps_category'    => __( 'Category', 'subaru-parts-inventory' ),
			'sps_box'         => __( 'Box', 'subaru-parts-inventory' ),
			'sps_qty'         => __( 'Qty', 'subaru-parts-inventory' ),
			'sps_status'      => __( 'Status', 'subaru-parts-inventory' ),
			'sps_sku'         => __( 'Label', 'subaru-parts-inventory' ),
			'taxonomy-' . SPS_TAX_FIT => __( 'Fits', 'subaru-parts-inventory' ),
		);
	}

	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'sps_thumb':
				$thumb = get_the_post_thumbnail( $post_id, array( 48, 48 ) );
				echo $thumb ? $thumb : '<span class="sps-nothumb dashicons dashicons-format-image"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'sps_part_number':
				echo '<strong class="sps-pn">' . esc_html( sps_meta( $post_id, 'part_number', '—' ) ) . '</strong>';
				$alt = sps_meta( $post_id, 'alt_numbers' );
				if ( $alt ) {
					echo '<br><small class="sps-muted">' . esc_html( $alt ) . '</small>';
				}
				break;
			case 'sps_condition':
				echo sps_condition_badge( sps_meta( $post_id, 'condition' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
			case 'sps_category':
				$cat = sps_part_category( $post_id );
				if ( $cat ) {
					$url = add_query_arg( array( 'post_type' => SPS_PT, SPS_TAX_CAT => $cat->slug ), admin_url( 'edit.php' ) );
					echo '<a href="' . esc_url( $url ) . '">' . esc_html( sps_category_path( $cat ) ) . '</a>';
				} else {
					echo '—';
				}
				break;
			case 'sps_box':
				$box = sps_part_box( $post_id );
				echo '<span class="sps-box-code">' . esc_html( $box['code'] ? $box['code'] : '—' ) . '</span>';
				if ( $box['bin'] ) {
					echo '<br><small>' . esc_html( $box['bin'] ) . '</small>';
				}
				break;
			case 'sps_qty':
				echo esc_html( (string) sps_meta( $post_id, 'quantity', 0 ) );
				break;
			case 'sps_status':
				$statuses = sps_statuses();
				$s        = sps_meta( $post_id, 'status', 'in_stock' );
				echo '<span class="sps-status sps-status--' . esc_attr( $s ) . '">' . esc_html( isset( $statuses[ $s ] ) ? $statuses[ $s ]['label'] : $s ) . '</span>';
				if ( ! (int) sps_meta( $post_id, 'public', 0 ) ) {
					echo ' <span class="dashicons dashicons-hidden" title="' . esc_attr__( 'Hidden from public catalogue', 'subaru-parts-inventory' ) . '"></span>';
				}
				break;
			case 'sps_sku':
				echo '<code>' . esc_html( sps_meta( $post_id, 'sku' ) ) . '</code><br>';
				echo sps_meta( $post_id, 'label_printed' )
					? '<span class="sps-ok" title="' . esc_attr__( 'Label printed', 'subaru-parts-inventory' ) . '">✔ ' . esc_html__( 'printed', 'subaru-parts-inventory' ) . '</span>'
					: '<a class="sps-warn" target="_blank" href="' . esc_url( SPS_Labels::print_url( array( $post_id ) ) ) . '">' . esc_html__( 'print label', 'subaru-parts-inventory' ) . '</a>';
				break;
		}
	}

	public static function sortable_columns( $columns ) {
		$columns['sps_part_number'] = 'sps_part_number';
		$columns['sps_qty']         = 'sps_qty';
		$columns['sps_sku']         = 'sps_sku';
		$columns['sps_condition']   = 'sps_condition';
		return $columns;
	}

	public static function filters( $post_type ) {
		if ( SPS_PT !== $post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$cat       = isset( $_GET[ SPS_TAX_CAT ] ) ? sanitize_title( wp_unslash( $_GET[ SPS_TAX_CAT ] ) ) : '';
		$condition = isset( $_GET['sps_condition'] ) ? sanitize_key( $_GET['sps_condition'] ) : '';
		$status    = isset( $_GET['sps_status'] ) ? sanitize_key( $_GET['sps_status'] ) : '';
		$label     = isset( $_GET['sps_label'] ) ? sanitize_key( $_GET['sps_label'] ) : '';
		// phpcs:enable
		echo '<select name="' . esc_attr( SPS_TAX_CAT ) . '"><option value="">' . esc_html__( 'All categories', 'subaru-parts-inventory' ) . '</option>' . sps_category_options( $cat, true, 'slug' ) . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<select name="sps_condition"><option value="">' . esc_html__( 'Any condition', 'subaru-parts-inventory' ) . '</option>';
		foreach ( sps_conditions() as $k => $c ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $condition, $k, false ) . '>' . esc_html( $c['label'] ) . '</option>';
		}
		echo '</select><select name="sps_status"><option value="">' . esc_html__( 'Any status', 'subaru-parts-inventory' ) . '</option><option value="instock" ' . selected( $status, 'instock', false ) . '>' . esc_html__( 'Anything I still have', 'subaru-parts-inventory' ) . '</option>';
		foreach ( sps_statuses() as $k => $s ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $s['label'] ) . '</option>';
		}
		echo '</select><select name="sps_label"><option value="">' . esc_html__( 'Label: any', 'subaru-parts-inventory' ) . '</option><option value="no" ' . selected( $label, 'no', false ) . '>' . esc_html__( 'Label not printed', 'subaru-parts-inventory' ) . '</option><option value="yes" ' . selected( $label, 'yes', false ) . '>' . esc_html__( 'Label printed', 'subaru-parts-inventory' ) . '</option></select>';
	}

	/**
	 * Apply list filters and sorting.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function list_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || SPS_PT !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$meta = (array) $query->get( 'meta_query' );
		if ( ! empty( $_GET['sps_condition'] ) ) {
			$c = sanitize_key( $_GET['sps_condition'] );
			if ( 0 === strpos( $c, 'g' ) && in_array( substr( $c, 1 ), array( 'new', 'used' ), true ) ) {
				$group  = substr( $c, 1 );
				$keys   = array_keys( wp_list_filter( sps_conditions(), array( 'group' => $group ) ) );
				$meta[] = array( 'key' => '_sps_condition', 'value' => $keys, 'compare' => 'IN' );
			} else {
				$meta[] = array( 'key' => '_sps_condition', 'value' => $c );
			}
		}
		if ( ! empty( $_GET['sps_status'] ) ) {
			$s = sanitize_key( $_GET['sps_status'] );
			if ( 'instock' === $s ) {
				$meta[] = array( 'key' => '_sps_status', 'value' => array_keys( wp_list_filter( sps_statuses(), array( 'in_stock' => true ) ) ), 'compare' => 'IN' );
			} else {
				$meta[] = array( 'key' => '_sps_status', 'value' => $s );
			}
		}
		if ( ! empty( $_GET['sps_label'] ) ) {
			$meta[] = 'yes' === $_GET['sps_label']
				? array( 'key' => '_sps_label_printed', 'compare' => 'EXISTS' )
				: array( 'key' => '_sps_label_printed', 'compare' => 'NOT EXISTS' );
		}
		// phpcs:enable
		if ( $meta ) {
			$query->set( 'meta_query', $meta );
		}
		$orderby = $query->get( 'orderby' );
		$map     = array(
			'sps_part_number' => array( '_sps_part_number', 'meta_value' ),
			'sps_qty'         => array( '_sps_quantity', 'meta_value_num' ),
			'sps_sku'         => array( '_sps_sku', 'meta_value' ),
			'sps_condition'   => array( '_sps_condition', 'meta_value' ),
		);
		if ( isset( $map[ $orderby ] ) ) {
			$query->set( 'meta_key', $map[ $orderby ][0] );
			$query->set( 'orderby', $map[ $orderby ][1] );
		}
	}

	/**
	 * Admin search also matches SKU, part number (with or without dashes) and alternate numbers.
	 *
	 * @param string   $search SQL.
	 * @param WP_Query $query  Query.
	 */
	public static function search_meta( $search, $query ) {
		global $wpdb;
		if ( ! is_admin() || ! $query->is_main_query() || SPS_PT !== $query->get( 'post_type' ) || '' === (string) $query->get( 's' ) || '' === $search ) {
			return $search;
		}
		$ids = sps_search_meta_ids( $query->get( 's' ) );
		if ( ! $ids ) {
			return $search;
		}
		$in = implode( ',', array_map( 'intval', $ids ) );
		return preg_replace( '/^\s*AND\s*\(/', " AND ( {$wpdb->posts}.ID IN ({$in}) OR (", $search, 1 ) . ')';
	}

	public static function bulk_actions( $actions ) {
		$actions['sps_print_labels'] = __( 'Print labels', 'subaru-parts-inventory' );
		$actions['sps_mark_printed'] = __( 'Mark labels as printed', 'subaru-parts-inventory' );
		$actions['sps_hide']         = __( 'Hide from public catalogue', 'subaru-parts-inventory' );
		$actions['sps_show']         = __( 'Show in public catalogue', 'subaru-parts-inventory' );
		foreach ( sps_statuses() as $key => $s ) {
			/* translators: %s: status */
			$actions[ 'sps_status_' . $key ] = sprintf( __( 'Set status: %s', 'subaru-parts-inventory' ), $s['label'] );
		}
		return $actions;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		$ids = array_filter( array_map( 'intval', $ids ), static function ( $id ) {
			return current_user_can( 'edit_post', $id );
		} );
		if ( 'sps_print_labels' === $action ) {
			return SPS_Labels::print_url( $ids );
		}
		$done = 0;
		foreach ( $ids as $id ) {
			if ( 'sps_mark_printed' === $action ) {
				update_post_meta( $id, '_sps_label_printed', current_time( 'mysql' ) );
			} elseif ( 'sps_hide' === $action || 'sps_show' === $action ) {
				sps_save_part_meta( $id, array( 'public' => 'sps_show' === $action ? 1 : 0 ) );
			} elseif ( 0 === strpos( $action, 'sps_status_' ) ) {
				sps_save_part_meta( $id, array( 'status' => substr( $action, 11 ) ) );
			} else {
				continue;
			}
			$done++;
		}
		return $done ? add_query_arg( 'sps_bulk_done', $done, $redirect ) : $redirect;
	}

	public static function row_actions( $actions, $post ) {
		if ( SPS_PT !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$actions['sps_label']     = '<a target="_blank" href="' . esc_url( SPS_Labels::print_url( array( $post->ID ) ) ) . '">' . esc_html__( 'Label', 'subaru-parts-inventory' ) . '</a>';
		$actions['sps_duplicate'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?action=sps_duplicate&post=' . $post->ID ), 'sps_duplicate_' . $post->ID ) ) . '" title="' . esc_attr__( 'Copy this part (new label ID)', 'subaru-parts-inventory' ) . '">' . esc_html__( 'Duplicate', 'subaru-parts-inventory' ) . '</a>';
		unset( $actions['inline hide-if-no-js'] );
		return $actions;
	}

	public static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['sps_bulk_done'] ) ) {
			/* translators: %d: number of parts */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d part updated.', '%d parts updated.', absint( $_GET['sps_bulk_done'] ), 'subaru-parts-inventory' ), absint( $_GET['sps_bulk_done'] ) ) ) . '</p></div>';
		}
		if ( ! empty( $_GET['sps_msg'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['sps_msg'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	/**
	 * Duplicate a part as a draft with a fresh label ID (e.g. second identical used part).
	 */
	public static function duplicate() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'sps_duplicate_' . $id );
		$post = get_post( $id );
		if ( ! $post || SPS_PT !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		$new_id = wp_insert_post(
			array(
				'post_type'    => SPS_PT,
				'post_status'  => 'draft',
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
			)
		);
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}
		$data = array();
		foreach ( array_keys( sps_meta_fields() ) as $key ) {
			$data[ $key ] = sps_meta( $id, $key );
		}
		sps_save_part_meta( $new_id, $data );
		foreach ( array( SPS_TAX_CAT, SPS_TAX_FIT ) as $tax ) {
			wp_set_object_terms( $new_id, wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) ), $tax );
		}
		$thumb = get_post_thumbnail_id( $id );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}
		wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Inventory statistics (cached).
	 *
	 * @return array<string,mixed>
	 */
	public static function stats() {
		$stats = get_transient( 'sps_stats' );
		if ( is_array( $stats ) ) {
			return $stats;
		}
		$ids        = get_posts( array( 'post_type' => SPS_PT, 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true ) );
		$conditions = sps_conditions();
		$statuses   = sps_statuses();
		$stats      = array(
			'parts'        => 0,
			'items'        => 0,
			'value'        => 0.0,
			'value_manual' => 0,
			'value_est'    => 0,
			'value_shop'   => 0,
			'value_none'   => 0,
			'cost'         => 0.0,
			'by_group'     => array_fill_keys( array_keys( sps_conditions() ), 0 ),
			'by_condition' => array_fill_keys( array_keys( $conditions ), 0 ),
			'by_status'    => array_fill_keys( array_keys( $statuses ), 0 ),
			'by_category'  => array(),
			'unlabelled'   => 0,
			'no_photo'     => 0,
			'no_category'  => 0,
		);
		foreach ( $ids as $id ) {
			$status = sps_meta( $id, 'status', 'in_stock' );
			if ( isset( $stats['by_status'][ $status ] ) ) {
				$stats['by_status'][ $status ]++;
			}
			if ( empty( $statuses[ $status ]['in_stock'] ) ) {
				continue;
			}
			$qty  = (int) sps_meta( $id, 'quantity', 0 );
			$cond = sps_meta( $id, 'condition', 'used' );
			$stats['parts']++;
			$stats['items'] += $qty;
			$pv              = sps_part_value( $id );
			$stats['value'] += $pv['value'] * $qty;
			$stats[ 'value_' . ( 'manual' === $pv['source'] ? 'manual' : ( 'none' === $pv['source'] ? 'none' : 'est' ) ) ]++;
			if ( 'shop' === $pv['source'] ) {
				$stats['value_shop']++;
			}
			$stats['cost']  += (float) sps_meta( $id, 'purchase_price', 0 ) * $qty;
			if ( isset( $conditions[ $cond ] ) ) {
				$stats['by_condition'][ $cond ]++;
				$stats['by_group'][ $conditions[ $cond ]['group'] ]++;
			}
			if ( ! sps_meta( $id, 'label_printed' ) ) {
				$stats['unlabelled']++;
			}
			if ( ! has_post_thumbnail( $id ) ) {
				$stats['no_photo']++;
			}
			$cat = sps_part_category( $id );
			if ( ! $cat ) {
				$stats['no_category']++;
				continue;
			}
			$top = $cat;
			while ( $top->parent ) {
				$top = get_term( $top->parent, SPS_TAX_CAT );
			}
			if ( ! isset( $stats['by_category'][ $top->term_id ] ) ) {
				$stats['by_category'][ $top->term_id ] = array( 'parts' => 0, 'items' => 0, 'value' => 0.0 );
			}
			$stats['by_category'][ $top->term_id ]['parts']++;
			$stats['by_category'][ $top->term_id ]['items'] += $qty;
			$stats['by_category'][ $top->term_id ]['value'] += $pv['value'] * $qty;
		}
		set_transient( 'sps_stats', $stats, HOUR_IN_SECONDS );
		return $stats;
	}

	public static function dashboard_page() {
		$s        = self::stats();
		$base     = admin_url( 'edit.php?post_type=' . SPS_PT );
		$statuses = sps_statuses();
		?>
		<div class="wrap sps-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Subaru Parts – Inventory Dashboard', 'subaru-parts-inventory' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . SPS_PT ) ); ?>"><?php esc_html_e( 'Add part', 'subaru-parts-inventory' ); ?></a>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-scan' ) ); ?>"><?php esc_html_e( 'Scan / Lookup', 'subaru-parts-inventory' ); ?></a>
			<hr class="wp-header-end">

			<div class="sps-cards">
				<div class="sps-card"><span class="sps-card__num"><?php echo esc_html( number_format_i18n( $s['parts'] ) ); ?></span><span class="sps-card__label"><?php esc_html_e( 'Parts in stock', 'subaru-parts-inventory' ); ?></span></div>
				<div class="sps-card"><span class="sps-card__num"><?php echo esc_html( number_format_i18n( $s['items'] ) ); ?></span><span class="sps-card__label"><?php esc_html_e( 'Total pieces', 'subaru-parts-inventory' ); ?></span></div>
				<div class="sps-card sps-card--new"><span class="sps-card__num"><?php echo esc_html( number_format_i18n( $s['by_group']['new'] ) ); ?></span><span class="sps-card__label"><?php esc_html_e( 'New', 'subaru-parts-inventory' ); ?></span></div>
				<div class="sps-card sps-card--used"><span class="sps-card__num"><?php echo esc_html( number_format_i18n( $s['parts'] - $s['by_group']['new'] ) ); ?></span><span class="sps-card__label"><?php esc_html_e( 'Used / bad / unusable', 'subaru-parts-inventory' ); ?></span></div>
				<div class="sps-card"><span class="sps-card__num"><?php echo esc_html( sps_money( $s['value'] ) ); ?></span><span class="sps-card__label"><?php esc_html_e( 'Estimated value', 'subaru-parts-inventory' ); ?><?php if ( $s['value_est'] || $s['value_none'] ) : ?><br><small><?php echo esc_html( sprintf( /* translators: 1: estimated, 2: no price */ __( '~ %1$d estimated (%3$d from shop price), %2$d without price', 'subaru-parts-inventory' ), $s['value_est'], $s['value_none'], $s['value_shop'] ) ); ?></small><?php endif; ?></span></div>
				<div class="sps-card"><span class="sps-card__num"><?php echo esc_html( sps_money( $s['cost'] ) ); ?></span><span class="sps-card__label"><?php esc_html_e( 'Purchase cost', 'subaru-parts-inventory' ); ?></span></div>
			</div>

			<?php if ( $s['unlabelled'] || $s['no_category'] || $s['no_photo'] || ! empty( $s['by_status']['not_boxed'] ) ) : ?>
			<div class="sps-todo">
				<h2><?php esc_html_e( 'To do', 'subaru-parts-inventory' ); ?></h2>
				<ul>
					<?php if ( $s['unlabelled'] ) : ?>
						<li>🏷️ <?php /* translators: %d: count */ echo esc_html( sprintf( _n( '%d part has no printed label.', '%d parts have no printed label.', $s['unlabelled'], 'subaru-parts-inventory' ), $s['unlabelled'] ) ); ?>
							<a class="button button-primary button-small" target="_blank" href="<?php echo esc_url( SPS_Labels::print_url( 'unlabelled' ) ); ?>"><?php esc_html_e( 'Print all missing labels', 'subaru-parts-inventory' ); ?></a>
							<a href="<?php echo esc_url( $base . '&sps_label=no' ); ?>"><?php esc_html_e( 'Show them', 'subaru-parts-inventory' ); ?></a></li>
					<?php endif; ?>
					<?php if ( ! empty( $s['by_status']['not_boxed'] ) ) : ?>
						<li>📥 <?php /* translators: %d: count */ echo esc_html( sprintf( _n( '%d part is not in a box yet.', '%d parts are not in a box yet.', $s['by_status']['not_boxed'], 'subaru-parts-inventory' ), $s['by_status']['not_boxed'] ) ); ?>
							<a href="<?php echo esc_url( $base . '&sps_status=not_boxed' ); ?>"><?php esc_html_e( 'Show them', 'subaru-parts-inventory' ); ?></a></li>
					<?php endif; ?>
					<?php if ( $s['no_category'] ) : ?>
						<li>📦 <?php /* translators: %d: count */ echo esc_html( sprintf( _n( '%d part has no category (box).', '%d parts have no category (box).', $s['no_category'], 'subaru-parts-inventory' ), $s['no_category'] ) ); ?></li>
					<?php endif; ?>
					<?php if ( $s['no_photo'] ) : ?>
						<li>📷 <?php /* translators: %d: count */ echo esc_html( sprintf( _n( '%d part has no photo.', '%d parts have no photo.', $s['no_photo'], 'subaru-parts-inventory' ), $s['no_photo'] ) ); ?></li>
					<?php endif; ?>
				</ul>
			</div>
			<?php endif; ?>

			<div class="sps-columns">
				<div class="sps-panel">
					<h2><?php esc_html_e( 'Boxes by Subaru catalog group', 'subaru-parts-inventory' ); ?></h2>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Group', 'subaru-parts-inventory' ); ?></th><th><?php esc_html_e( 'Box', 'subaru-parts-inventory' ); ?></th><th class="num"><?php esc_html_e( 'Parts', 'subaru-parts-inventory' ); ?></th><th class="num"><?php esc_html_e( 'Pieces', 'subaru-parts-inventory' ); ?></th><th class="num"><?php esc_html_e( 'Value', 'subaru-parts-inventory' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php
						$tops = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'parent' => 0, 'hide_empty' => false ) );
						usort( $tops, static function ( $a, $b ) {
							return (int) get_term_meta( $a->term_id, 'sps_order', true ) - (int) get_term_meta( $b->term_id, 'sps_order', true );
						} );
						foreach ( $tops as $term ) :
							$row = isset( $s['by_category'][ $term->term_id ] ) ? $s['by_category'][ $term->term_id ] : array( 'parts' => 0, 'items' => 0, 'value' => 0 );
							?>
							<tr>
								<td><a href="<?php echo esc_url( $base . '&' . SPS_TAX_CAT . '=' . $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></a></td>
								<td><span class="sps-box-code"><?php echo esc_html( get_term_meta( $term->term_id, 'sps_box_code', true ) ); ?></span></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['parts'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['items'] ) ); ?></td>
								<td class="num"><?php echo esc_html( $row['value'] ? sps_money( $row['value'] ) : '' ); ?></td>
								<td><a target="_blank" href="<?php echo esc_url( SPS_Labels::print_url( array( $term->term_id ), 'box' ) ); ?>"><?php esc_html_e( 'Box label', 'subaru-parts-inventory' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="sps-panel">
					<h2><?php esc_html_e( 'By condition', 'subaru-parts-inventory' ); ?></h2>
					<?php self::bars( $s['by_condition'], $s['parts'], 'condition' ); ?>
					<h2><?php esc_html_e( 'By status (all parts)', 'subaru-parts-inventory' ); ?></h2>
					<table class="widefat striped"><tbody>
					<?php foreach ( $s['by_status'] as $key => $count ) : ?>
						<tr><td><a href="<?php echo esc_url( $base . '&sps_status=' . $key ); ?>"><?php echo esc_html( $statuses[ $key ]['label'] ); ?></a></td><td class="num"><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
					<h2><?php esc_html_e( 'Recently added', 'subaru-parts-inventory' ); ?></h2>
					<ul class="sps-recent">
					<?php
					foreach ( get_posts( array( 'post_type' => SPS_PT, 'posts_per_page' => 8, 'post_status' => 'any' ) ) as $p ) {
						printf( '<li>%1$s <a href="%2$s"><strong>%3$s</strong> %4$s</a> <code>%5$s</code></li>', sps_condition_badge( sps_meta( $p->ID, 'condition' ) ), esc_url( get_edit_post_link( $p->ID ) ), esc_html( sps_meta( $p->ID, 'part_number' ) ), esc_html( get_the_title( $p ) ), esc_html( sps_meta( $p->ID, 'sku' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	private static function bars( $counts, $total, $type ) {
		$conditions = sps_conditions();
		echo '<div class="sps-bars">';
		foreach ( $counts as $key => $count ) {
			$pct = $total ? round( 100 * $count / $total ) : 0;
			printf(
				'<a class="sps-bar" href="%1$s"><span class="sps-bar__label">%2$s</span><span class="sps-bar__track"><span class="sps-bar__fill" style="width:%3$d%%;background:%4$s"></span></span><span class="sps-bar__num">%5$s</span></a>',
				esc_url( admin_url( 'edit.php?post_type=' . SPS_PT . '&sps_condition=' . $key ) ),
				esc_html( $conditions[ $key ]['label'] ),
				(int) $pct,
				esc_attr( $conditions[ $key ]['color'] ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------ */
	/* Scan / quick update (mobile friendly, opened from QR labels)         */
	/* ------------------------------------------------------------------ */

	public static function scan_page() {
		$code    = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$is_box  = false;
		$box_ids = array();
		$ids     = array();
		if ( $code && ! preg_match( '/^BOX:/i', $code ) ) {
			$ids = sps_find_parts_by_code( $code, false );
		}
		if ( $code && ! $ids ) {
			// Box label QR codes contain "BOX:<box code>" (or "BOX:<category slug>").
			$box_code = trim( preg_replace( '/^BOX:/i', '', $code ) );
			$terms    = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false, 'meta_key' => 'sps_box_code', 'meta_value' => strtoupper( $box_code ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $terms && ! is_wp_error( $terms ) ) {
				$is_box = $terms[0];
			} else {
				$by_slug = get_term_by( 'slug', sanitize_title( $box_code ), SPS_TAX_CAT );
				$is_box  = $by_slug ? $by_slug : false;
			}
		}
		?>
		<div class="wrap sps-wrap sps-scan">
			<h1><?php esc_html_e( 'Scan / Lookup', 'subaru-parts-inventory' ); ?></h1>
			<form method="get" class="sps-scan-form">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( SPS_PT ); ?>" />
				<input type="hidden" name="page" value="sps-scan" />
				<input type="search" name="code" value="<?php echo esc_attr( $code ); ?>" placeholder="<?php esc_attr_e( 'Scan or type label ID, part number or box code', 'subaru-parts-inventory' ); ?>" autofocus autocomplete="off" />
				<button class="button button-primary"><?php esc_html_e( 'Find', 'subaru-parts-inventory' ); ?></button>
				<button type="button" class="button sps-camera-btn"><span class="dashicons dashicons-camera"></span> <?php esc_html_e( 'Camera', 'subaru-parts-inventory' ); ?></button>
			</form>
			<div class="sps-camera" hidden><video playsinline muted></video><button type="button" class="button sps-camera-stop"><?php esc_html_e( 'Stop', 'subaru-parts-inventory' ); ?></button></div>
			<p class="description"><?php esc_html_e( 'Tip: scanning a part label QR with your phone camera opens this screen directly. USB barcode scanners work in the search field.', 'subaru-parts-inventory' ); ?></p>
			<?php
			if ( $is_box ) {
				$box_ids  = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'any', 'posts_per_page' => 200, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => SPS_TAX_CAT, 'terms' => $is_box->term_id, 'include_children' => true ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				echo '<h2>📦 ' . esc_html( trim( sps_term_box( $is_box )['code'] . ' – ' . html_entity_decode( sps_category_path( $is_box ) ), ' –' ) ) . '</h2>';
				$wanted = sps_term_box( $is_box );
				$ids    = array_values( array_filter( $box_ids, static function ( $id ) use ( $wanted ) {
					$box = sps_part_box( $id );
					return $box['code'] === $wanted['code'];
				} ) );
				if ( ! $ids ) {
					echo '<p>' . esc_html__( 'This box is empty.', 'subaru-parts-inventory' ) . '</p>';
				}
			} elseif ( $code && ! $ids ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Nothing found.', 'subaru-parts-inventory' ) . ' <a href="' . esc_url( admin_url( 'post-new.php?post_type=' . SPS_PT . '&sps_pn=' . rawurlencode( $code ) ) ) . '">' . esc_html__( 'Add it as a new part', 'subaru-parts-inventory' ) . '</a></p></div>';
			}
			if ( count( $ids ) === 1 && ! $is_box ) {
				self::scan_card( $ids[0] );
			} elseif ( $ids ) {
				echo '<table class="widefat striped sps-scan-list"><tbody>';
				foreach ( $ids as $id ) {
					$box = sps_part_box( $id );
					printf(
						'<tr><td>%1$s</td><td><a href="%2$s"><strong>%3$s</strong><br>%4$s</a></td><td><code>%5$s</code></td><td>%6$s</td><td>%7$s × %8$d</td></tr>',
						sps_condition_badge( sps_meta( $id, 'condition' ) ), // phpcs:ignore WordPress.Security.EscapeOutput
						esc_url( add_query_arg( 'code', rawurlencode( sps_meta( $id, 'sku' ) ) ) ),
						esc_html( sps_meta( $id, 'part_number' ) ),
						esc_html( get_the_title( $id ) ),
						esc_html( sps_meta( $id, 'sku' ) ),
						esc_html( $box['code'] ),
						esc_html( sps_statuses()[ sps_meta( $id, 'status', 'in_stock' ) ]['label'] ?? '' ),
						(int) sps_meta( $id, 'quantity', 0 )
					);
				}
				echo '</tbody></table>';
			}
			?>
		</div>
		<?php
	}

	private static function scan_card( $id ) {
		$d        = sps_part_data( $id, 'admin' );
		$statuses = sps_statuses();
		?>
		<div class="sps-scan-card">
			<div class="sps-scan-card__head">
				<?php if ( $d['images'] ) : ?>
					<img src="<?php echo esc_url( $d['images'][0]['thumb'] ); ?>" alt="" />
				<?php endif; ?>
				<div>
					<div class="sps-scan-card__pn"><?php echo esc_html( $d['part_number'] ? $d['part_number'] : '—' ); ?></div>
					<div class="sps-scan-card__name"><?php echo esc_html( $d['name'] ); ?></div>
					<div><?php echo sps_condition_badge( $d['condition'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <code><?php echo esc_html( $d['sku'] ); ?></code></div>
				</div>
			</div>
			<dl class="sps-dl">
				<dt><?php esc_html_e( 'Category', 'subaru-parts-inventory' ); ?></dt><dd><?php echo esc_html( $d['category'] ? $d['category']['path'] : '—' ); ?></dd>
				<dt><?php esc_html_e( 'Box', 'subaru-parts-inventory' ); ?></dt><dd><strong><?php echo esc_html( $d['box']['code'] ? $d['box']['code'] : '—' ); ?></strong> <?php echo esc_html( trim( $d['box']['location'] . ' ' . $d['box']['bin'] ) ); ?></dd>
				<dt><?php esc_html_e( 'Status', 'subaru-parts-inventory' ); ?></dt><dd><?php echo esc_html( $d['status_label'] ); ?> · <?php esc_html_e( 'Qty', 'subaru-parts-inventory' ); ?> <strong><?php echo (int) $d['quantity']; ?></strong></dd>
				<?php if ( $d['condition_flags'] ) : ?>
					<dt><?php esc_html_e( 'Checklist', 'subaru-parts-inventory' ); ?></dt><dd><?php echo esc_html( implode( ', ', $d['condition_flags'] ) ); ?></dd>
				<?php endif; ?>
				<?php if ( $d['condition_notes'] ) : ?>
					<dt><?php esc_html_e( 'Condition notes', 'subaru-parts-inventory' ); ?></dt><dd><?php echo nl2br( esc_html( $d['condition_notes'] ) ); ?></dd>
				<?php endif; ?>
				<?php if ( $d['fitment'] ) : ?>
					<dt><?php esc_html_e( 'Fits', 'subaru-parts-inventory' ); ?></dt><dd><?php echo esc_html( implode( ', ', $d['fitment'] ) ); ?></dd>
				<?php endif; ?>
			</dl>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sps-quick">
				<input type="hidden" name="action" value="sps_quick_update" />
				<input type="hidden" name="post_id" value="<?php echo (int) $id; ?>" />
				<?php wp_nonce_field( 'sps_quick_' . $id ); ?>
				<div class="sps-quick__qty">
					<button class="button" name="qty_delta" value="-1">−1</button>
					<span><?php echo (int) $d['quantity']; ?></span>
					<button class="button" name="qty_delta" value="1">+1</button>
				</div>
				<div class="sps-quick__status">
					<?php foreach ( array( 'sold', 'installed', 'reserved', 'in_stock' ) as $st ) : ?>
						<button class="button<?php echo $d['status'] === $st ? ' button-primary' : ''; ?>" name="status" value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $statuses[ $st ]['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
				<label><?php esc_html_e( 'Add condition / inspection note', 'subaru-parts-inventory' ); ?>
					<textarea name="note" rows="2"></textarea></label>
				<select name="note_condition"><option value=""><?php esc_html_e( 'Grade unchanged', 'subaru-parts-inventory' ); ?></option>
					<?php foreach ( sps_conditions() as $k => $c ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $c['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button button-primary" name="save_note" value="1"><?php esc_html_e( 'Save note', 'subaru-parts-inventory' ); ?></button>
			</form>

			<p class="sps-scan-card__links">
				<a class="button" href="<?php echo esc_url( $d['edit_url'] ); ?>"><?php esc_html_e( 'Edit part', 'subaru-parts-inventory' ); ?></a>
				<a class="button" target="_blank" href="<?php echo esc_url( SPS_Labels::print_url( array( $id ) ) ); ?>"><?php esc_html_e( 'Reprint label', 'subaru-parts-inventory' ); ?></a>
				<?php if ( sps_is_visible( $id ) ) : ?>
					<a class="button" href="<?php echo esc_url( $d['url'] ); ?>"><?php esc_html_e( 'Public page', 'subaru-parts-inventory' ); ?></a>
				<?php endif; ?>
			</p>
			<?php if ( $d['inspections'] ) : ?>
				<h3><?php esc_html_e( 'Condition log', 'subaru-parts-inventory' ); ?></h3>
				<ul class="sps-log-list">
				<?php foreach ( array_slice( $d['inspections'], 0, 10 ) as $entry ) : ?>
					<li><strong><?php echo esc_html( $entry['date'] ); ?></strong> <?php echo $entry['condition'] ? sps_condition_badge( $entry['condition'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $entry['note'] ); ?></li>
				<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function quick_update() {
		$id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		check_admin_referer( 'sps_quick_' . $id );
		if ( ! $id || SPS_PT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		$msg = __( 'Saved.', 'subaru-parts-inventory' );
		if ( isset( $_POST['qty_delta'] ) ) {
			$qty = max( 0, (int) sps_meta( $id, 'quantity', 0 ) + (int) $_POST['qty_delta'] );
			sps_save_part_meta( $id, array( 'quantity' => $qty ) );
			/* translators: %d: quantity */
			$msg = sprintf( __( 'Quantity is now %d.', 'subaru-parts-inventory' ), $qty );
		}
		if ( ! empty( $_POST['status'] ) ) {
			$status = sanitize_key( $_POST['status'] );
			sps_save_part_meta( $id, array( 'status' => $status ) );
			sps_add_inspection( $id, sprintf( /* translators: %s: status */ __( 'Status set to: %s', 'subaru-parts-inventory' ), sps_statuses()[ $status ]['label'] ?? $status ) );
			$msg = __( 'Status updated.', 'subaru-parts-inventory' );
		}
		if ( ! empty( $_POST['save_note'] ) ) {
			$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
			$cond = isset( $_POST['note_condition'] ) ? sanitize_key( $_POST['note_condition'] ) : '';
			if ( $cond && array_key_exists( $cond, sps_conditions() ) ) {
				sps_save_part_meta( $id, array( 'condition' => $cond ) );
				if ( '' === trim( $note ) ) {
					$note = __( 'Condition re-graded.', 'subaru-parts-inventory' );
				}
			}
			sps_add_inspection( $id, $note, $cond );
			$msg = __( 'Note added.', 'subaru-parts-inventory' );
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => SPS_PT, 'page' => 'sps-scan', 'code' => rawurlencode( sps_meta( $id, 'sku' ) ), 'sps_msg' => rawurlencode( $msg ) ), admin_url( 'edit.php' ) ) );
		exit;
	}
}
