<?php
/**
 * Front-end "parts app": catalogue for visitors, plus scanner, add/edit form,
 * boxes, label queue and activity for logged-in parts managers.
 *
 * Styled after the Gintesa "Subaru Tracker" page (dark, neon accent, Inter + JetBrains Mono).
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_App {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'sps-app-fonts', 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@400;500;600;700&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_style( 'sps-app', SPS_URL . 'assets/css/app.css', array( 'sps-app-fonts' ), SPS_VERSION );
		wp_register_script( 'sps-qrcode', SPS_URL . 'assets/js/vendor/qrcode.js', array(), '1.4.4', true );
		wp_register_script( 'sps-app', SPS_URL . 'assets/js/app.js', array(), SPS_VERSION, true );
	}

	/**
	 * SVG icon set (stroke icons, same style as the tracker page).
	 */
	public static function icon( $name ) {
		$paths = array(
			'scan'     => '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><path d="M7 12h10"/>',
			'add'      => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M12 8v8M8 12h8"/>',
			'parts'    => '<path d="M14.7 6.3a4 4 0 0 0-5.6 5.6l-6 6a1.4 1.4 0 0 0 2 2l6-6a4 4 0 0 0 5.6-5.6l-2.4 2.4-2-2 2.4-2.4z"/>',
			'box'      => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
			'label'    => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
			'tools'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
			'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
			'camera'   => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
		);
		$sw = 'search' === $name ? '2' : '1.6';
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $sw . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( isset( $paths[ $name ] ) ? $paths[ $name ] : '' ) . '</svg>';
	}

	/**
	 * Collapsible section wrapper (tracker "acc" component).
	 */
	private static function section( $key, $icon, $title, $body, $collapsed = false ) {
		return sprintf(
			'<section class="acc%1$s" data-acc="%2$s" id="sps-sec-%2$s"><button type="button" class="acc-head" data-toggle="%2$s" aria-expanded="%3$s"><span class="icon">%4$s</span><h2>%5$s</h2><span class="count" data-count="%2$s"></span><span class="chev" aria-hidden="true">▾</span></button><div class="acc-body">%6$s</div></section>',
			$collapsed ? ' collapsed' : '',
			esc_attr( $key ),
			$collapsed ? 'false' : 'true',
			self::icon( $icon ),
			esc_html( $title ),
			$body
		);
	}

	/**
	 * Render the app.
	 *
	 * @param array $atts Shortcode attributes (category, condition, per_page, title).
	 */
	public static function render( array $atts ) {
		$manager = current_user_can( SPS_EDIT_CAP );
		wp_enqueue_style( 'sps-app' );
		wp_enqueue_script( 'sps-app' );
		if ( $manager ) {
			wp_enqueue_script( 'sps-qrcode' );
		}

		$conditions = sps_conditions();
		$cfg        = array(
			'rest'         => esc_url_raw( rest_url( SPS_REST::NS . '/' ) ),
			'nonce'        => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'ajax'         => admin_url( 'admin-ajax.php' ),
			'manager'      => $manager,
			'canBoxes'     => current_user_can( SPS_CAP ),
			'canUpload'    => current_user_can( 'upload_files' ),
			'jsqr'         => SPS_URL . 'assets/js/vendor/jsQR.min.js?ver=1.4.0',
			'printUrl'     => admin_url( 'admin.php?action=sps_print_labels&type=part' ),
			'admin'        => array(
				'list'     => admin_url( 'edit.php?post_type=' . SPS_PT ),
				'dash'     => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-dashboard' ),
				'cats'     => admin_url( 'edit-tags.php?taxonomy=' . SPS_TAX_CAT . '&post_type=' . SPS_PT ),
				'import'   => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-import-export' ),
				'settings' => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-settings' ),
				'labels'   => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-labels' ),
			),
			'conditions'   => $conditions,
			'flags'        => sps_condition_flags(),
			'statuses'     => sps_statuses(),
			'categories'   => sps_category_tree(),
			'templates'    => array_map(
				static function ( $t ) {
					return $t['name'];
				},
				SPS_Labels::templates()
			),
			'template'     => sps_setting( 'label_template' ),
			'fitment'      => (string) sps_setting( 'default_fitment' ),
			'fitments'     => $manager ? array_values( (array) get_terms( array( 'taxonomy' => SPS_TAX_FIT, 'hide_empty' => false, 'fields' => 'names' ) ) ) : array(),
			'currency'     => (string) sps_setting( 'currency' ),
			'unit'         => (string) sps_setting( 'distance_unit' ),
			'perPage'      => (int) $atts['per_page'] ? (int) $atts['per_page'] : (int) sps_setting( 'per_page' ),
			'showPrices'   => (bool) sps_setting( 'show_prices' ),
			'showQty'      => (bool) sps_setting( 'show_quantity' ),
			'start'        => array(
				'category'  => (string) $atts['category'],
				'condition' => (string) $atts['condition'],
			),
			'i18n'         => self::strings(),
		);

		$title = '' !== (string) $atts['title'] ? (string) $atts['title'] : __( 'Parts storage', 'subaru-parts-inventory' );
		$sub   = (string) sps_setting( 'default_fitment' );

		ob_start();
		?>
		<div id="sps-app" class="sps-app<?php echo $manager ? ' is-manager' : ''; ?>" data-sps-app>
			<nav class="topnav" aria-label="<?php esc_attr_e( 'Sections', 'subaru-parts-inventory' ); ?>">
				<div class="nav-btns">
					<?php if ( $manager ) : ?>
						<button type="button" class="nav-btn" data-nav="scan"><?php esc_html_e( 'Scan', 'subaru-parts-inventory' ); ?></button>
						<button type="button" class="nav-btn" data-nav="add"><?php esc_html_e( 'Add part', 'subaru-parts-inventory' ); ?></button>
					<?php endif; ?>
					<button type="button" class="nav-btn" data-nav="inv"><?php esc_html_e( 'Inventory', 'subaru-parts-inventory' ); ?></button>
					<button type="button" class="nav-btn" data-nav="boxes"><?php esc_html_e( 'Boxes', 'subaru-parts-inventory' ); ?></button>
					<?php if ( $manager ) : ?>
						<button type="button" class="nav-btn" data-nav="labels"><?php esc_html_e( 'Labels', 'subaru-parts-inventory' ); ?></button>
						<button type="button" class="nav-btn" data-nav="log"><?php esc_html_e( 'Activity', 'subaru-parts-inventory' ); ?></button>
						<button type="button" class="nav-btn" data-nav="tools"><?php esc_html_e( 'Tools', 'subaru-parts-inventory' ); ?></button>
					<?php endif; ?>
				</div>
				<div class="search">
					<?php echo self::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<input type="search" data-global-search placeholder="<?php esc_attr_e( 'Part number, name, label ID…', 'subaru-parts-inventory' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search parts', 'subaru-parts-inventory' ); ?>">
				</div>
			</nav>

			<header class="hero">
				<div class="badge">◈ <?php esc_html_e( 'Subaru parts', 'subaru-parts-inventory' ); ?></div>
				<h1><?php echo esc_html( $title ); ?> <b><?php esc_html_e( 'inventory', 'subaru-parts-inventory' ); ?></b></h1>
				<?php if ( $sub ) : ?><div class="sub"><?php echo esc_html( $sub ); ?></div><?php endif; ?>
				<div class="save-pill"><i data-status-dot></i><span data-status><?php esc_html_e( 'Loading…', 'subaru-parts-inventory' ); ?></span></div>
				<?php if ( $manager ) : ?>
					<div class="hero-actions">
						<button type="button" class="btn" data-action="open-scan"><?php echo self::icon( 'scan' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Scan label', 'subaru-parts-inventory' ); ?></button>
						<button type="button" class="btn ghost" data-action="open-add"><?php echo self::icon( 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Add a part', 'subaru-parts-inventory' ); ?></button>
					</div>
				<?php endif; ?>
				<div class="stats-strip" data-stats></div>
			</header>

			<div class="body">
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput -- section bodies are static markup built below.
				if ( $manager ) {
					echo self::section( 'scan', 'scan', __( 'Scanner', 'subaru-parts-inventory' ), self::scan_body(), true );
					echo self::section( 'add', 'add', __( 'Add a part', 'subaru-parts-inventory' ), self::form_body(), true );
				}
				echo self::section( 'inv', 'parts', __( 'Inventory', 'subaru-parts-inventory' ), self::inventory_body( $manager ) );
				echo self::section( 'boxes', 'box', __( 'Storage boxes', 'subaru-parts-inventory' ), '<p class="hint">' . esc_html__( 'Each box holds one Subaru catalog group. Tap a box to list what is inside.', 'subaru-parts-inventory' ) . '</p><div class="box-grid" data-boxes></div>', true );
				if ( $manager ) {
					echo self::section( 'labels', 'label', __( 'Labels to print', 'subaru-parts-inventory' ), self::labels_body(), true );
					echo self::section( 'log', 'activity', __( 'Condition log & activity', 'subaru-parts-inventory' ), '<div class="log-list" data-activity></div>', true );
					echo self::section( 'tools', 'tools', __( 'Tools', 'subaru-parts-inventory' ), self::tools_body(), true );
				}
				// phpcs:enable
				?>
			</div>
			<div class="toast" data-toast role="status" aria-live="polite" hidden></div>
			<script type="application/json" data-sps-config><?php echo wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>
			<noscript><p class="empty-note"><?php esc_html_e( 'Please enable JavaScript to use the parts inventory.', 'subaru-parts-inventory' ); ?></p></noscript>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function scan_body() {
		ob_start();
		?>
		<div class="scan-wrap">
			<div class="scan-view" data-scan-view hidden>
				<video playsinline muted></video>
				<div class="scan-frame" aria-hidden="true"><i></i><i></i><i></i><i></i><span class="scan-line"></span></div>
				<button type="button" class="btn faint mini scan-stop" data-action="scan-stop"><?php esc_html_e( 'Stop camera', 'subaru-parts-inventory' ); ?></button>
			</div>
			<div class="scan-controls">
				<button type="button" class="btn" data-action="scan-start"><?php echo self::icon( 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Start camera', 'subaru-parts-inventory' ); ?></button>
				<form class="scan-manual" data-scan-form>
					<input type="text" name="code" placeholder="<?php esc_attr_e( 'Type or scan: label ID, part number, BOX code', 'subaru-parts-inventory' ); ?>" autocomplete="off" spellcheck="false" inputmode="text">
					<button type="submit" class="btn ghost"><?php esc_html_e( 'Find', 'subaru-parts-inventory' ); ?></button>
				</form>
			</div>
			<p class="hint"><?php esc_html_e( 'Point the camera at a part or box label QR code. USB / Bluetooth barcode scanners type straight into the field.', 'subaru-parts-inventory' ); ?></p>
			<div class="scan-result" data-scan-result></div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function form_body() {
		ob_start();
		?>
		<form class="form part-form" data-part-form novalidate>
			<div class="form-title" data-form-title><?php esc_html_e( 'New part', 'subaru-parts-inventory' ); ?></div>
			<input type="hidden" name="part_id" value="">
			<div class="fgrid">
				<div class="pn-field">
					<label class="field" for="sps-f-pn"><?php esc_html_e( 'OEM part number', 'subaru-parts-inventory' ); ?></label>
					<div class="inline">
						<input type="text" id="sps-f-pn" name="part_number" class="mono" placeholder="14411AA850" autocomplete="off" spellcheck="false">
						<button type="button" class="icon-btn" data-action="scan-into-pn" title="<?php esc_attr_e( 'Scan barcode on the box', 'subaru-parts-inventory' ); ?>"><?php echo self::icon( 'scan' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					</div>
					<div class="warn" data-dup hidden></div>
				</div>
				<div>
					<label class="field" for="sps-f-name"><?php esc_html_e( 'Name', 'subaru-parts-inventory' ); ?></label>
					<input type="text" id="sps-f-name" name="name" placeholder="<?php esc_attr_e( 'Turbocharger assembly', 'subaru-parts-inventory' ); ?>">
				</div>
				<div class="full">
					<label class="field" for="sps-f-cat"><?php esc_html_e( 'Subaru catalog category', 'subaru-parts-inventory' ); ?></label>
					<select id="sps-f-cat" name="category_id" data-cat-select></select>
					<div class="hint"><?php esc_html_e( 'Goes in box', 'subaru-parts-inventory' ); ?> <b class="boxcode" data-box-preview>—</b> <span data-box-loc></span></div>
				</div>
			</div>

			<label class="field gap"><?php esc_html_e( 'Condition', 'subaru-parts-inventory' ); ?></label>
			<div class="svc-pick cond-pick" data-cond-pick></div>
			<div class="used-only">
				<label class="field gap"><?php esc_html_e( 'Checklist', 'subaru-parts-inventory' ); ?></label>
				<div class="svc-pick" data-flag-pick></div>
			</div>
			<div class="fgrid gap">
				<div class="full">
					<label class="field" for="sps-f-notes"><?php esc_html_e( 'Condition notes (public)', 'subaru-parts-inventory' ); ?></label>
					<textarea id="sps-f-notes" name="condition_notes" rows="2" placeholder="<?php esc_attr_e( 'e.g. small crack on lower tab, all clips intact, no shaft play', 'subaru-parts-inventory' ); ?>"></textarea>
				</div>
				<div>
					<label class="field" for="sps-f-qty"><?php esc_html_e( 'Quantity', 'subaru-parts-inventory' ); ?></label>
					<input type="number" id="sps-f-qty" name="quantity" min="0" step="1" value="1">
				</div>
				<div>
					<label class="field" for="sps-f-status"><?php esc_html_e( 'Status', 'subaru-parts-inventory' ); ?></label>
					<select id="sps-f-status" name="status" data-status-select></select>
				</div>
				<div class="used-only">
					<label class="field" for="sps-f-km"><?php echo esc_html( sprintf( /* translators: %s: unit */ __( 'Mileage (%s)', 'subaru-parts-inventory' ), sps_setting( 'distance_unit' ) ) ); ?></label>
					<input type="number" id="sps-f-km" name="mileage" min="0" step="1">
				</div>
				<div class="used-only">
					<label class="field" for="sps-f-from"><?php esc_html_e( 'Removed from (private)', 'subaru-parts-inventory' ); ?></label>
					<input type="text" id="sps-f-from" name="removed_from" placeholder="<?php esc_attr_e( 'Donor car / VIN', 'subaru-parts-inventory' ); ?>">
				</div>
				<div class="full">
					<label class="field" for="sps-f-fit"><?php esc_html_e( 'Fits (comma separated)', 'subaru-parts-inventory' ); ?></label>
					<input type="text" id="sps-f-fit" name="fitment" list="sps-fitments">
					<datalist id="sps-fitments" data-fitments></datalist>
				</div>
			</div>

			<details class="more-fields">
				<summary><?php esc_html_e( 'More: numbers, storage, prices, description', 'subaru-parts-inventory' ); ?></summary>
				<div class="fgrid">
					<div><label class="field"><?php esc_html_e( 'Alternate / superseded numbers', 'subaru-parts-inventory' ); ?></label><input type="text" name="alt_numbers" class="mono"></div>
					<div><label class="field"><?php esc_html_e( 'Catalog diagram ref.', 'subaru-parts-inventory' ); ?></label><input type="text" name="epc_ref"></div>
					<div><label class="field"><?php esc_html_e( 'Box override', 'subaru-parts-inventory' ); ?></label><input type="text" name="box_override" class="mono" placeholder="<?php esc_attr_e( 'Only if not in category box', 'subaru-parts-inventory' ); ?>"></div>
					<div><label class="field"><?php esc_html_e( 'Position in box', 'subaru-parts-inventory' ); ?></label><input type="text" name="bin" placeholder="<?php esc_attr_e( 'bag 3, bottom', 'subaru-parts-inventory' ); ?>"></div>
					<div><label class="field"><?php echo esc_html( sprintf( /* translators: %s: currency */ __( 'Purchase price, %s', 'subaru-parts-inventory' ), sps_setting( 'currency' ) ) ); ?></label><input type="number" name="purchase_price" min="0" step="0.01"></div>
					<div><label class="field"><?php esc_html_e( 'Purchase date', 'subaru-parts-inventory' ); ?></label><input type="date" name="purchase_date"></div>
					<div><label class="field"><?php esc_html_e( 'Source / supplier', 'subaru-parts-inventory' ); ?></label><input type="text" name="source"></div>
					<div><label class="field"><?php echo esc_html( sprintf( /* translators: %s: currency */ __( 'Est. value, %s', 'subaru-parts-inventory' ), sps_setting( 'currency' ) ) ); ?></label><input type="number" name="value" min="0" step="0.01"></div>
					<div><label class="field"><?php echo esc_html( sprintf( /* translators: %s: currency */ __( 'Asking price, %s', 'subaru-parts-inventory' ), sps_setting( 'currency' ) ) ); ?></label><input type="number" name="sale_price" min="0" step="0.01"></div>
					<div><label class="field"><?php esc_html_e( 'Weight, kg', 'subaru-parts-inventory' ); ?></label><input type="number" name="weight" min="0" step="0.01"></div>
					<div class="full"><label class="field"><?php esc_html_e( 'Description (public)', 'subaru-parts-inventory' ); ?></label><textarea name="description" rows="2"></textarea></div>
					<div class="full"><label class="field"><?php esc_html_e( 'Private notes', 'subaru-parts-inventory' ); ?></label><textarea name="private_notes" rows="2"></textarea></div>
				</div>
			</details>

			<div class="fgrid gap">
				<div class="full">
					<label class="field"><?php esc_html_e( 'Photos', 'subaru-parts-inventory' ); ?></label>
					<div class="photo-row" data-photos></div>
					<label class="btn faint mini file-btn"><?php echo self::icon( 'camera' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Take / choose photos', 'subaru-parts-inventory' ); ?>
						<input type="file" accept="image/*" multiple data-photo-input hidden>
					</label>
				</div>
				<div class="full">
					<label class="field" for="sps-f-note"><?php esc_html_e( 'Condition log entry (optional)', 'subaru-parts-inventory' ); ?></label>
					<input type="text" id="sps-f-note" name="note" placeholder="<?php esc_attr_e( 'e.g. bench tested OK, cleaned', 'subaru-parts-inventory' ); ?>">
				</div>
				<label class="checkline"><input type="checkbox" name="public" value="1" <?php checked( (int) sps_setting( 'public_default' ), 1 ); ?>> <?php esc_html_e( 'Show in public catalogue', 'subaru-parts-inventory' ); ?></label>
			</div>
			<div class="actions">
				<button type="button" class="btn faint mini" data-action="form-reset"><?php esc_html_e( 'Clear', 'subaru-parts-inventory' ); ?></button>
				<button type="submit" class="btn" data-submit><?php esc_html_e( 'Save part', 'subaru-parts-inventory' ); ?></button>
			</div>
			<div class="form-done" data-form-done hidden></div>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function inventory_body( $manager ) {
		ob_start();
		?>
		<div class="chips" data-cond-chips>
			<button type="button" class="chip on" data-cond=""><?php esc_html_e( 'All', 'subaru-parts-inventory' ); ?></button>
			<button type="button" class="chip" data-cond="new"><?php esc_html_e( 'New', 'subaru-parts-inventory' ); ?></button>
			<button type="button" class="chip" data-cond="used"><?php esc_html_e( 'Used', 'subaru-parts-inventory' ); ?></button>
			<button type="button" class="chip" data-cond="core"><?php esc_html_e( 'Core', 'subaru-parts-inventory' ); ?></button>
		</div>
		<div class="filters">
			<select data-filter="category" aria-label="<?php esc_attr_e( 'Category', 'subaru-parts-inventory' ); ?>"></select>
			<?php if ( $manager ) : ?>
				<select data-filter="status" aria-label="<?php esc_attr_e( 'Status', 'subaru-parts-inventory' ); ?>"></select>
			<?php endif; ?>
			<select data-filter="orderby" aria-label="<?php esc_attr_e( 'Sort', 'subaru-parts-inventory' ); ?>">
				<option value="newest"><?php esc_html_e( 'Newest first', 'subaru-parts-inventory' ); ?></option>
				<?php if ( $manager ) : ?><option value="updated"><?php esc_html_e( 'Recently updated', 'subaru-parts-inventory' ); ?></option><?php endif; ?>
				<option value="part_number"><?php esc_html_e( 'Part number', 'subaru-parts-inventory' ); ?></option>
				<option value="name"><?php esc_html_e( 'Name', 'subaru-parts-inventory' ); ?></option>
			</select>
		</div>
		<div class="list-head inv-row" aria-hidden="true"><span></span><span><?php esc_html_e( 'Part', 'subaru-parts-inventory' ); ?></span><span><?php esc_html_e( 'Condition', 'subaru-parts-inventory' ); ?></span><span><?php esc_html_e( 'Box', 'subaru-parts-inventory' ); ?></span><span class="r"><?php esc_html_e( 'Qty', 'subaru-parts-inventory' ); ?></span></div>
		<div class="inv-list" data-inv-list></div>
		<button type="button" class="btn faint mini more" data-action="more" hidden><?php esc_html_e( 'Show more', 'subaru-parts-inventory' ); ?></button>
		<?php
		return ob_get_clean();
	}

	private static function labels_body() {
		ob_start();
		?>
		<p class="hint"><?php esc_html_e( 'Parts that do not have a printed label yet. Printed labels are ticked off automatically after the print dialog closes.', 'subaru-parts-inventory' ); ?></p>
		<div class="tune-controls">
			<select data-label-template aria-label="<?php esc_attr_e( 'Label format', 'subaru-parts-inventory' ); ?>"></select>
			<button type="button" class="btn mini" data-action="print-selected"><?php esc_html_e( 'Print selected', 'subaru-parts-inventory' ); ?></button>
			<button type="button" class="btn ghost mini" data-action="select-all"><?php esc_html_e( 'Select all', 'subaru-parts-inventory' ); ?></button>
			<a class="btn faint mini" data-box-labels-link href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-labels' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Box labels…', 'subaru-parts-inventory' ); ?></a>
		</div>
		<div class="label-list" data-label-list></div>
		<?php
		return ob_get_clean();
	}

	private static function tools_body() {
		$links = array(
			'dash'     => __( 'Admin dashboard', 'subaru-parts-inventory' ),
			'list'     => __( 'All parts (admin list, bulk actions)', 'subaru-parts-inventory' ),
			'cats'     => __( 'Categories & box codes', 'subaru-parts-inventory' ),
			'import'   => __( 'Import / export CSV', 'subaru-parts-inventory' ),
			'labels'   => __( 'Label printing page', 'subaru-parts-inventory' ),
			'settings' => __( 'Settings', 'subaru-parts-inventory' ),
		);
		$admin = array(
			'dash'     => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-dashboard' ),
			'list'     => admin_url( 'edit.php?post_type=' . SPS_PT ),
			'cats'     => admin_url( 'edit-tags.php?taxonomy=' . SPS_TAX_CAT . '&post_type=' . SPS_PT ),
			'import'   => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-import-export' ),
			'labels'   => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-labels' ),
			'settings' => admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-settings' ),
		);
		$html = '<div class="figs" data-tool-figs></div><div class="tool-links">';
		foreach ( $links as $key => $label ) {
			if ( in_array( $key, array( 'cats', 'import', 'settings' ), true ) && ! current_user_can( SPS_CAP ) ) {
				continue;
			}
			$html .= '<a class="btn blue mini" href="' . esc_url( $admin[ $key ] ) . '">' . esc_html( $label ) . '</a>';
		}
		return $html . '</div>';
	}

	/**
	 * UI strings for app.js.
	 */
	private static function strings() {
		return array(
			'loading'      => __( 'Loading…', 'subaru-parts-inventory' ),
			'live'         => __( 'Live', 'subaru-parts-inventory' ),
			'offline'      => __( 'Offline', 'subaru-parts-inventory' ),
			'readonly'     => __( 'Catalogue', 'subaru-parts-inventory' ),
			'error'        => __( 'Something went wrong. Please try again.', 'subaru-parts-inventory' ),
			'none'         => __( 'No parts found.', 'subaru-parts-inventory' ),
			'allCats'      => __( 'All categories', 'subaru-parts-inventory' ),
			'choose'       => __( '— Select category —', 'subaru-parts-inventory' ),
			'inStock'      => __( 'Everything I still have', 'subaru-parts-inventory' ),
			'allStatuses'  => __( 'All statuses (incl. sold)', 'subaru-parts-inventory' ),
			'parts'        => __( 'Parts', 'subaru-parts-inventory' ),
			'pieces'       => __( 'Pieces', 'subaru-parts-inventory' ),
			'newUsed'      => __( 'New / used', 'subaru-parts-inventory' ),
			'value'        => __( 'Est. value', 'subaru-parts-inventory' ),
			'unlabelled'   => __( 'No label', 'subaru-parts-inventory' ),
			'noPhoto'      => __( 'no photo', 'subaru-parts-inventory' ),
			'cost'         => __( 'cost', 'subaru-parts-inventory' ),
			'boxes'        => __( 'Boxes', 'subaru-parts-inventory' ),
			'inBox'        => __( 'parts', 'subaru-parts-inventory' ),
			'boxLabel'     => __( 'Box label', 'subaru-parts-inventory' ),
			'editBox'      => __( 'Edit', 'subaru-parts-inventory' ),
			'boxCode'      => __( 'Box code', 'subaru-parts-inventory' ),
			'boxLocation'  => __( 'Location', 'subaru-parts-inventory' ),
			'save'         => __( 'Save', 'subaru-parts-inventory' ),
			'cancel'       => __( 'Cancel', 'subaru-parts-inventory' ),
			'saving'       => __( 'Saving…', 'subaru-parts-inventory' ),
			'saved'        => __( 'Saved', 'subaru-parts-inventory' ),
			'newPart'      => __( 'New part', 'subaru-parts-inventory' ),
			'editing'      => __( 'Editing', 'subaru-parts-inventory' ),
			'savePart'     => __( 'Save part', 'subaru-parts-inventory' ),
			'saveChanges'  => __( 'Save changes', 'subaru-parts-inventory' ),
			'printLabel'   => __( 'Print label', 'subaru-parts-inventory' ),
			'addAnother'   => __( 'Add another (same category)', 'subaru-parts-inventory' ),
			'edit'         => __( 'Edit', 'subaru-parts-inventory' ),
			'editAdmin'    => __( 'Open in admin', 'subaru-parts-inventory' ),
			'publicPage'   => __( 'Public page', 'subaru-parts-inventory' ),
			'trash'        => __( 'Move to trash', 'subaru-parts-inventory' ),
			'confirmTrash' => __( 'Move this part to the trash?', 'subaru-parts-inventory' ),
			'addNote'      => __( 'Add note', 'subaru-parts-inventory' ),
			'notePh'       => __( 'Condition / inspection note…', 'subaru-parts-inventory' ),
			'qty'          => __( 'Qty', 'subaru-parts-inventory' ),
			'box'          => __( 'Box', 'subaru-parts-inventory' ),
			'fits'         => __( 'Fits', 'subaru-parts-inventory' ),
			'category'     => __( 'Category', 'subaru-parts-inventory' ),
			'mileage'      => __( 'Mileage', 'subaru-parts-inventory' ),
			'removedFrom'  => __( 'Removed from', 'subaru-parts-inventory' ),
			'altNumbers'   => __( 'Alt. numbers', 'subaru-parts-inventory' ),
			'price'        => __( 'Price', 'subaru-parts-inventory' ),
			'labelId'      => __( 'Label ID', 'subaru-parts-inventory' ),
			'labelPrinted' => __( 'label printed', 'subaru-parts-inventory' ),
			'labelMissing' => __( 'no label yet', 'subaru-parts-inventory' ),
			'hidden'       => __( 'hidden', 'subaru-parts-inventory' ),
			'log'          => __( 'Condition log', 'subaru-parts-inventory' ),
			'more'         => __( 'Show more', 'subaru-parts-inventory' ),
			'cameraStart'  => __( 'Starting camera…', 'subaru-parts-inventory' ),
			'cameraNo'     => __( 'Camera not available. It needs HTTPS and camera permission. You can still type or use a USB scanner.', 'subaru-parts-inventory' ),
			'notFound'     => __( 'Nothing found for', 'subaru-parts-inventory' ),
			'addAsNew'     => __( 'Add as new part', 'subaru-parts-inventory' ),
			'boxContents'  => __( 'In this box', 'subaru-parts-inventory' ),
			'scanned'      => __( 'Scanned', 'subaru-parts-inventory' ),
			'dup'          => __( 'Already in inventory:', 'subaru-parts-inventory' ),
			'needPnOrName' => __( 'Enter a part number or a name.', 'subaru-parts-inventory' ),
			'uploading'    => __( 'Uploading photos…', 'subaru-parts-inventory' ),
			'removePhoto'  => __( 'Remove photo', 'subaru-parts-inventory' ),
			'noLabels'     => __( 'All parts have labels. 🎉', 'subaru-parts-inventory' ),
			'selectSome'   => __( 'Select at least one part.', 'subaru-parts-inventory' ),
			'noActivity'   => __( 'No log entries yet.', 'subaru-parts-inventory' ),
			'printed'      => __( 'Labels opened for printing', 'subaru-parts-inventory' ),
			'qtyNow'       => __( 'Quantity', 'subaru-parts-inventory' ),
			'statusNow'    => __( 'Status', 'subaru-parts-inventory' ),
			'noteAdded'    => __( 'Note added', 'subaru-parts-inventory' ),
			'trashed'      => __( 'Moved to trash', 'subaru-parts-inventory' ),
			'scanPnHint'   => __( 'Scanning for the part number barcode…', 'subaru-parts-inventory' ),
			'of'           => __( 'of', 'subaru-parts-inventory' ),
			'newLbl'       => __( 'New', 'subaru-parts-inventory' ),
			'checklist'    => __( 'Checklist', 'subaru-parts-inventory' ),
			'usedLbl'      => __( 'Used', 'subaru-parts-inventory' ),
		);
	}
}
