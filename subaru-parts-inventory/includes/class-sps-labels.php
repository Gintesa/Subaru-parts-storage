<?php
/**
 * Printable part and box labels (QR code + Code 128 barcode).
 *
 * Supports single-label roll printers (Brother QL, DYMO, Zebra) and
 * A4 / Letter label sheets (Avery). QR codes point to the scan URL, so a
 * phone camera opens the part directly.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Labels {

	public static function init() {
		add_action( 'admin_action_sps_print_labels', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_sps_mark_printed', array( __CLASS__, 'ajax_mark_printed' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	/**
	 * Label formats (millimetres).
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function templates() {
		return array(
			'brother_62x29'  => array( 'name' => 'Brother QL – DK-11209 (62 × 29 mm)', 'w' => 62, 'h' => 29, 'mode' => 'roll' ),
			'brother_90x29'  => array( 'name' => 'Brother QL – DK-11201 (90 × 29 mm)', 'w' => 90, 'h' => 29, 'mode' => 'roll' ),
			'brother_100x62' => array( 'name' => 'Brother QL – DK-11202 (100 × 62 mm)', 'w' => 100, 'h' => 62, 'mode' => 'roll' ),
			'brother_62x40'  => array( 'name' => 'Brother QL – DK-22205 cut to 40 mm (62 × 40 mm)', 'w' => 62, 'h' => 40, 'mode' => 'roll' ),
			'dymo_54x25'     => array( 'name' => 'DYMO 11352 (54 × 25 mm)', 'w' => 54, 'h' => 25, 'mode' => 'roll' ),
			'dymo_89x36'     => array( 'name' => 'DYMO 99012 / S0722400 (89 × 36 mm)', 'w' => 89, 'h' => 36, 'mode' => 'roll' ),
			'zebra_57x32'    => array( 'name' => 'Thermal 2.25 × 1.25 in (57 × 32 mm)', 'w' => 57, 'h' => 32, 'mode' => 'roll' ),
			'zebra_102x51'   => array( 'name' => 'Thermal 4 × 2 in (102 × 51 mm)', 'w' => 102, 'h' => 51, 'mode' => 'roll' ),
			'zebra_102x152'  => array( 'name' => 'Thermal 4 × 6 in (102 × 152 mm) – box label', 'w' => 152, 'h' => 102, 'mode' => 'roll' ),
			'avery_l7160'    => array( 'name' => 'A4 sheet – Avery L7160 (21 per sheet, 63.5 × 38.1 mm)', 'w' => 63.5, 'h' => 38.1, 'mode' => 'sheet', 'pw' => 210, 'ph' => 297, 'cols' => 3, 'rows' => 7, 'top' => 15.15, 'left' => 7.25, 'gx' => 2.5, 'gy' => 0 ),
			'avery_l7163'    => array( 'name' => 'A4 sheet – Avery L7163 (14 per sheet, 99.1 × 38.1 mm)', 'w' => 99.1, 'h' => 38.1, 'mode' => 'sheet', 'pw' => 210, 'ph' => 297, 'cols' => 2, 'rows' => 7, 'top' => 15.15, 'left' => 4.65, 'gx' => 2.5, 'gy' => 0 ),
			'avery_l7165'    => array( 'name' => 'A4 sheet – Avery L7165 (8 per sheet, 99.1 × 67.7 mm)', 'w' => 99.1, 'h' => 67.7, 'mode' => 'sheet', 'pw' => 210, 'ph' => 297, 'cols' => 2, 'rows' => 4, 'top' => 13.1, 'left' => 4.65, 'gx' => 2.5, 'gy' => 0 ),
			'avery_5160'     => array( 'name' => 'Letter sheet – Avery 5160 (30 per sheet, 2.63 × 1 in)', 'w' => 66.675, 'h' => 25.4, 'mode' => 'sheet', 'pw' => 215.9, 'ph' => 279.4, 'cols' => 3, 'rows' => 10, 'top' => 12.7, 'left' => 4.7625, 'gx' => 3.175, 'gy' => 0 ),
			'avery_5163'     => array( 'name' => 'Letter sheet – Avery 5163 (10 per sheet, 4 × 2 in)', 'w' => 101.6, 'h' => 50.8, 'mode' => 'sheet', 'pw' => 215.9, 'ph' => 279.4, 'cols' => 2, 'rows' => 5, 'top' => 12.7, 'left' => 3.97, 'gx' => 4.76, 'gy' => 0 ),
			'a6_box'         => array( 'name' => 'A6 page (148 × 105 mm) – box label', 'w' => 148, 'h' => 105, 'mode' => 'roll' ),
			'a4_box'         => array( 'name' => 'A4 landscape page – large box label', 'w' => 297, 'h' => 210, 'mode' => 'roll' ),
		);
	}

	/**
	 * Optional label content.
	 *
	 * @return array<string,string>
	 */
	public static function fields() {
		return array(
			'name'      => __( 'Part name', 'subaru-parts-inventory' ),
			'qr'        => __( 'QR code (scan to open part)', 'subaru-parts-inventory' ),
			'barcode'   => __( 'Barcode of part number (Code 128)', 'subaru-parts-inventory' ),
			'condition' => __( 'Condition grade', 'subaru-parts-inventory' ),
			'sku'       => __( 'Label ID', 'subaru-parts-inventory' ),
			'box'       => __( 'Box code', 'subaru-parts-inventory' ),
			'category'  => __( 'Category', 'subaru-parts-inventory' ),
			'fitment'   => __( 'Fitment', 'subaru-parts-inventory' ),
		);
	}

	/**
	 * Link to the print view.
	 *
	 * @param int[]|string $ids  IDs, or "unlabelled".
	 * @param string       $type part|box.
	 */
	public static function print_url( $ids, $type = 'part' ) {
		return add_query_arg(
			array(
				'action' => 'sps_print_labels',
				'type'   => $type,
				'ids'    => is_array( $ids ) ? implode( ',', array_map( 'intval', $ids ) ) : $ids,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . SPS_PT, __( 'Labels', 'subaru-parts-inventory' ), __( 'Print Labels', 'subaru-parts-inventory' ), SPS_EDIT_CAP, 'sps-labels', array( __CLASS__, 'labels_page' ) );
	}

	/**
	 * Picker page: choose which part / box labels to print.
	 */
	public static function labels_page() {
		$unlabelled = self::unlabelled_ids();
		?>
		<div class="wrap sps-wrap">
			<h1><?php esc_html_e( 'Print labels', 'subaru-parts-inventory' ); ?></h1>
			<div class="sps-columns">
				<div class="sps-panel">
					<h2><?php esc_html_e( 'Part labels', 'subaru-parts-inventory' ); ?></h2>
					<p><?php esc_html_e( 'Each label shows the OEM part number, the part name, the condition grade, the label ID and the box it belongs in. The QR code opens the part on your phone.', 'subaru-parts-inventory' ); ?></p>
					<p>
						<a class="button button-primary" target="_blank" href="<?php echo esc_url( self::print_url( 'unlabelled' ) ); ?>" <?php disabled( ! $unlabelled ); ?>>
							<?php /* translators: %d: count */ echo esc_html( sprintf( __( 'Print missing labels (%d)', 'subaru-parts-inventory' ), count( $unlabelled ) ) ); ?>
						</a>
					</p>
					<p><?php esc_html_e( 'Or select parts in the parts list and use the bulk action "Print labels".', 'subaru-parts-inventory' ); ?>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SPS_PT ) ); ?>"><?php esc_html_e( 'Go to parts list', 'subaru-parts-inventory' ); ?></a></p>
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" target="_blank">
						<input type="hidden" name="action" value="sps_print_labels" />
						<input type="hidden" name="type" value="part" />
						<label><?php esc_html_e( 'All parts in category:', 'subaru-parts-inventory' ); ?>
						<select name="cat"><?php echo sps_category_options( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select></label>
						<button class="button"><?php esc_html_e( 'Print', 'subaru-parts-inventory' ); ?></button>
					</form>
				</div>
				<div class="sps-panel">
					<h2><?php esc_html_e( 'Box labels', 'subaru-parts-inventory' ); ?></h2>
					<p><?php esc_html_e( 'Large labels for your category boxes: box code, Subaru catalog group and the sub-categories stored inside. Scanning the QR lists everything in the box.', 'subaru-parts-inventory' ); ?></p>
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" target="_blank">
						<input type="hidden" name="action" value="sps_print_labels" />
						<input type="hidden" name="type" value="box" />
						<input type="hidden" name="template" value="a6_box" />
						<div class="sps-box-pick">
						<?php
						$terms = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false, 'meta_key' => 'sps_box_code', 'meta_compare' => '!=', 'meta_value' => '' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
						foreach ( (array) $terms as $term ) {
							printf( '<label><input type="checkbox" name="ids[]" value="%1$d" checked /> <strong>%2$s</strong> %3$s</label>', (int) $term->term_id, esc_html( get_term_meta( $term->term_id, 'sps_box_code', true ) ), esc_html( sps_category_path( $term ) ) );
						}
						?>
						</div>
						<button class="button button-primary"><?php esc_html_e( 'Print selected box labels', 'subaru-parts-inventory' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Parts still in stock without a printed label.
	 *
	 * @return int[]
	 */
	public static function unlabelled_ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => SPS_PT,
					'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
					'fields'         => 'ids',
					'posts_per_page' => 500,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array( 'key' => '_sps_label_printed', 'compare' => 'NOT EXISTS' ),
						array( 'key' => '_sps_status', 'value' => array_keys( wp_list_filter( sps_statuses(), array( 'in_stock' => true ) ) ), 'compare' => 'IN' ),
					),
				)
			)
		);
	}

	/**
	 * Build the list of label payloads.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect( $type, $ids, $copies_mode ) {
		$labels = array();
		if ( 'box' === $type ) {
			foreach ( $ids as $term_id ) {
				$term = get_term( $term_id, SPS_TAX_CAT );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$box      = sps_term_box( $term );
				$children = array();
				foreach ( get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'parent' => $term->term_id, 'hide_empty' => false ) ) as $child ) {
					$own = (string) get_term_meta( $child->term_id, 'sps_box_code', true );
					if ( '' === $own || $own === $box['code'] ) {
						$children[] = html_entity_decode( $child->name );
					}
				}
				$labels[] = array(
					'kind'     => 'box',
					'code'     => $box['code'] ? $box['code'] : $term->name,
					'title'    => html_entity_decode( sps_category_path( $term ) ),
					'location' => $box['location'],
					'children' => $children,
					'qr'       => sps_scan_url( 'BOX:' . ( $box['code'] ? $box['code'] : $term->slug ) ),
				);
			}
			return $labels;
		}
		$conditions = sps_conditions();
		foreach ( $ids as $id ) {
			if ( SPS_PT !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$sku = (string) sps_meta( $id, 'sku' );
			if ( '' === $sku ) {
				sps_refresh_derived_meta( $id );
				$sku = (string) sps_meta( $id, 'sku' );
			}
			$cond    = sps_meta( $id, 'condition', 'used_b' );
			$cat     = sps_part_category( $id );
			$fit     = wp_get_post_terms( $id, SPS_TAX_FIT, array( 'fields' => 'names' ) );
			$label   = array(
				'kind'        => 'part',
				'id'          => (int) $id,
				'part_number' => (string) sps_meta( $id, 'part_number' ),
				'name'        => html_entity_decode( get_the_title( $id ), ENT_QUOTES ),
				'sku'         => $sku,
				'condition'   => isset( $conditions[ $cond ] ) ? $conditions[ $cond ] : null,
				'box'         => sps_part_box( $id ),
				'category'    => $cat ? html_entity_decode( $cat->name ) : '',
				'fitment'     => is_wp_error( $fit ) ? '' : implode( ', ', $fit ),
				'qr'          => sps_scan_url( $sku ),
			);
			$copies = 'qty' === $copies_mode ? max( 1, (int) sps_meta( $id, 'quantity', 1 ) ) : 1;
			for ( $i = 0; $i < $copies; $i++ ) {
				$labels[] = $label;
			}
		}
		return $labels;
	}

	/**
	 * Print view (standalone HTML document).
	 */
	public static function render() {
		if ( ! current_user_can( SPS_EDIT_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification -- read-only view.
		$type      = isset( $_GET['type'] ) && 'box' === $_GET['type'] ? 'box' : 'part';
		$templates = self::templates();
		$tpl_key   = isset( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : (string) sps_setting( 'label_template' );
		if ( ! isset( $templates[ $tpl_key ] ) ) {
			$tpl_key = 'box' === $type ? 'a6_box' : 'brother_62x29';
		}
		$tpl    = $templates[ $tpl_key ];
		$copies = isset( $_GET['copies'] ) && 'qty' === $_GET['copies'] ? 'qty' : 'one';
		$skip   = isset( $_GET['skip'] ) ? max( 0, (int) $_GET['skip'] ) : 0;
		$fields = isset( $_GET['f'] ) ? array_map( 'sanitize_key', (array) $_GET['f'] ) : (array) sps_setting( 'label_fields' );
		$raw_ids = isset( $_GET['ids'] ) ? wp_unslash( $_GET['ids'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( 'unlabelled' === $raw_ids ) {
			$ids = self::unlabelled_ids();
		} else {
			$ids = array_filter( array_map( 'intval', is_array( $raw_ids ) ? $raw_ids : explode( ',', (string) $raw_ids ) ) );
		}
		if ( 'part' === $type && ! empty( $_GET['cat'] ) ) {
			$ids = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 500, 'tax_query' => array( array( 'taxonomy' => SPS_TAX_CAT, 'terms' => absint( $_GET['cat'] ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		// phpcs:enable
		$labels = self::collect( $type, $ids, $copies );
		$part_ids = array_values( array_unique( array_filter( array_column( $labels, 'id' ) ) ) );

		$w = (float) $tpl['w'];
		$h = (float) $tpl['h'];
		// Scale typography with label size.
		$scale  = max( 0.8, min( $h / 29, $w / 62, 3.2 ) );
		$pad    = max( 1.5, min( $w, $h ) * 0.06 );
		$show_barcode = in_array( 'barcode', $fields, true ) && ( $h >= 34 || ! in_array( 'qr', $fields, true ) );
		$qr_size = min( $h - 2 * $pad - ( $show_barcode ? 0.3 * $h : 0 ), $w * 0.42 );

		$is_sheet = 'sheet' === $tpl['mode'];
		$page_w   = $is_sheet ? $tpl['pw'] : $w;
		$page_h   = $is_sheet ? $tpl['ph'] : $h;

		$self_url = remove_query_arg( array( 'template', 'copies', 'skip', 'f' ) );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php esc_html_e( 'Print labels', 'subaru-parts-inventory' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( SPS_URL . 'assets/css/labels.css?ver=' . SPS_VERSION ); ?>">
<style>
@page { size: <?php echo esc_html( $page_w ); ?>mm <?php echo esc_html( $page_h ); ?>mm; margin: 0; }
:root {
	--lw: <?php echo esc_html( $w ); ?>mm; --lh: <?php echo esc_html( $h ); ?>mm;
	--pw: <?php echo esc_html( $page_w ); ?>mm; --ph: <?php echo esc_html( $page_h ); ?>mm;
	--pad: <?php echo esc_html( round( $pad, 2 ) ); ?>mm; --qr: <?php echo esc_html( round( max( 8, $qr_size ), 2 ) ); ?>mm;
	--s: <?php echo esc_html( round( $scale, 3 ) ); ?>;
<?php if ( $is_sheet ) : ?>
	--cols: <?php echo (int) $tpl['cols']; ?>; --top: <?php echo esc_html( $tpl['top'] ); ?>mm; --left: <?php echo esc_html( $tpl['left'] ); ?>mm;
	--gx: <?php echo esc_html( $tpl['gx'] ); ?>mm; --gy: <?php echo esc_html( $tpl['gy'] ); ?>mm;
<?php endif; ?>
}
</style>
</head>
<body class="sps-print sps-print--<?php echo esc_attr( $tpl['mode'] ); ?> sps-print--<?php echo esc_attr( $type ); ?>">
	<form class="sps-controls" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<?php
		foreach ( array( 'action', 'type', 'ids', 'cat' ) as $keep ) {
			if ( isset( $_GET[ $keep ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$val = wp_unslash( $_GET[ $keep ] ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
				$val = is_array( $val ) ? implode( ',', array_map( 'intval', $val ) ) : sanitize_text_field( $val );
				echo '<input type="hidden" name="' . esc_attr( $keep ) . '" value="' . esc_attr( $val ) . '">';
			}
		}
		?>
		<strong>
		<?php
		/* translators: %d: number of labels */
		echo esc_html( sprintf( _n( '%d label', '%d labels', count( $labels ), 'subaru-parts-inventory' ), count( $labels ) ) );
		?>
		</strong>
		<label><?php esc_html_e( 'Format', 'subaru-parts-inventory' ); ?>
			<select name="template" onchange="this.form.submit()">
				<?php foreach ( $templates as $key => $t ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $tpl_key, $key ); ?>><?php echo esc_html( $t['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php if ( 'part' === $type ) : ?>
			<label><?php esc_html_e( 'Copies', 'subaru-parts-inventory' ); ?>
				<select name="copies" onchange="this.form.submit()">
					<option value="one" <?php selected( $copies, 'one' ); ?>><?php esc_html_e( 'One per part', 'subaru-parts-inventory' ); ?></option>
					<option value="qty" <?php selected( $copies, 'qty' ); ?>><?php esc_html_e( 'One per piece (quantity)', 'subaru-parts-inventory' ); ?></option>
				</select>
			</label>
			<span class="sps-controls__fields"><input type="hidden" name="f[]" value="pn">
			<?php foreach ( self::fields() as $key => $label ) : ?>
				<label><input type="checkbox" name="f[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $fields, true ) ); ?> onchange="this.form.submit()"> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			</span>
		<?php endif; ?>
		<?php if ( $is_sheet ) : ?>
			<label><?php esc_html_e( 'Skip used labels on sheet', 'subaru-parts-inventory' ); ?>
				<input type="number" name="skip" min="0" max="<?php echo (int) ( $tpl['cols'] * $tpl['rows'] - 1 ); ?>" value="<?php echo (int) $skip; ?>" onchange="this.form.submit()" style="width:4em"></label>
		<?php endif; ?>
		<button type="button" class="sps-btn sps-btn--primary" id="sps-print"><?php esc_html_e( 'Print', 'subaru-parts-inventory' ); ?></button>
		<?php if ( $part_ids ) : ?>
			<button type="button" class="sps-btn" id="sps-mark"><?php esc_html_e( 'Mark as labelled', 'subaru-parts-inventory' ); ?></button>
			<span id="sps-mark-status" role="status"></span>
		<?php endif; ?>
		<p class="sps-controls__hint">
			<?php
			if ( 'roll' === $tpl['mode'] ) {
				/* translators: 1: width, 2: height */
				echo esc_html( sprintf( __( 'In the print dialog choose your label printer, paper size %1$s × %2$s mm, margins "None", scale 100%%.', 'subaru-parts-inventory' ), $w, $h ) );
			} else {
				esc_html_e( 'In the print dialog set margins to "None" and scale to 100% (actual size).', 'subaru-parts-inventory' );
			}
			if ( in_array( 'barcode', $fields, true ) && ! $show_barcode ) {
				echo ' ' . esc_html__( 'The barcode needs labels at least 34 mm tall (or turn off the QR code).', 'subaru-parts-inventory' );
			}
			?>
		</p>
	</form>

	<main class="sps-pages">
	<?php
	if ( ! $labels ) {
		echo '<p class="sps-empty">' . esc_html__( 'No labels to print.', 'subaru-parts-inventory' ) . '</p>';
	}
	if ( $is_sheet ) {
		$per_page = (int) ( $tpl['cols'] * $tpl['rows'] );
		$cells    = array_merge( array_fill( 0, min( $skip, $per_page - 1 ), null ), $labels );
		foreach ( array_chunk( $cells, $per_page ) as $page ) {
			echo '<section class="sps-page sps-sheet">';
			foreach ( $page as $label ) {
				echo $label ? self::label_html( $label, $fields, $show_barcode ) : '<div class="sps-label sps-label--blank"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</section>';
		}
	} else {
		foreach ( $labels as $label ) {
			echo '<section class="sps-page">' . self::label_html( $label, $fields, $show_barcode ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
	?>
	</main>
	<script src="<?php echo esc_url( SPS_URL . 'assets/js/vendor/qrcode.js?ver=1.4.4' ); ?>"></script>
	<script>
	window.SPSLabels = <?php echo wp_json_encode( array( 'ids' => $part_ids, 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sps_mark_printed' ), 'done' => __( 'Marked as labelled ✔', 'subaru-parts-inventory' ) ) ); ?>;
	</script>
	<script src="<?php echo esc_url( SPS_URL . 'assets/js/labels.js?ver=' . SPS_VERSION ); ?>"></script>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Markup of one label.
	 */
	private static function label_html( array $l, array $fields, $show_barcode ) {
		$has = static function ( $f ) use ( $fields ) {
			return in_array( $f, $fields, true );
		};
		if ( 'box' === $l['kind'] ) {
			$html  = '<div class="sps-label sps-label--box">';
			$html .= '<div class="sps-qr" data-qr="' . esc_attr( $l['qr'] ) . '"></div>';
			$html .= '<div class="sps-label__body">';
			$html .= '<div class="sps-label__boxcode">' . esc_html( $l['code'] ) . '</div>';
			$html .= '<div class="sps-label__title">' . esc_html( $l['title'] ) . '</div>';
			if ( $l['location'] ) {
				$html .= '<div class="sps-label__meta">' . esc_html( $l['location'] ) . '</div>';
			}
			if ( $l['children'] ) {
				$html .= '<ul class="sps-label__children"><li>' . implode( '</li><li>', array_map( 'esc_html', $l['children'] ) ) . '</li></ul>';
			}
			$html .= '</div></div>';
			return $html;
		}

		$cond  = $l['condition'];
		$html  = '<div class="sps-label sps-label--part' . ( $has( 'qr' ) ? ' has-qr' : '' ) . ( $show_barcode ? ' has-barcode' : '' ) . '">';
		$html .= '<div class="sps-label__top">';
		if ( $has( 'qr' ) ) {
			$html .= '<div class="sps-qr" data-qr="' . esc_attr( $l['qr'] ) . '"></div>';
		}
		$html .= '<div class="sps-label__body">';
		$html .= '<div class="sps-label__pn">' . esc_html( '' !== $l['part_number'] ? $l['part_number'] : '—' ) . '</div>';
		if ( $has( 'name' ) ) {
			$html .= '<div class="sps-label__name">' . esc_html( $l['name'] ) . '</div>';
		}
		if ( $has( 'category' ) && $l['category'] ) {
			$html .= '<div class="sps-label__meta">' . esc_html( $l['category'] ) . '</div>';
		}
		if ( $has( 'fitment' ) && $l['fitment'] ) {
			$html .= '<div class="sps-label__meta">' . esc_html( $l['fitment'] ) . '</div>';
		}
		$html .= '<div class="sps-label__foot">';
		if ( $has( 'condition' ) && $cond ) {
			$html .= '<span class="sps-label__cond sps-label__cond--' . esc_attr( $cond['group'] ) . '">' . esc_html( $cond['short'] ) . '</span>';
		}
		if ( $has( 'sku' ) ) {
			$html .= '<span class="sps-label__sku">' . esc_html( $l['sku'] ) . '</span>';
		}
		if ( $has( 'box' ) && $l['box']['code'] ) {
			$html .= '<span class="sps-label__box">▣ ' . esc_html( $l['box']['code'] ) . '</span>';
		}
		$html .= '</div></div></div>';
		if ( $show_barcode && '' !== $l['part_number'] ) {
			$html .= '<div class="sps-label__barcode">' . self::code128_svg( $l['part_number'] ) . '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	public static function ajax_mark_printed() {
		check_ajax_referer( 'sps_mark_printed' );
		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$n   = 0;
		foreach ( $ids as $id ) {
			if ( SPS_PT === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
				update_post_meta( $id, '_sps_label_printed', current_time( 'mysql' ) );
				$n++;
			}
		}
		delete_transient( 'sps_stats' );
		wp_send_json_success( array( 'updated' => $n ) );
	}

	/* ------------------------------------------------------------------ */
	/* Code 128 (subset B) barcode as SVG                                  */
	/* ------------------------------------------------------------------ */

	const CODE128 = array(
		'212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
		'221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
		'221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
		'212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
		'231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
		'231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
		'314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
		'112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
		'111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
		'214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
		'114131', '311141', '411131', '211412', '211214', '211232', '2331112',
	);

	/**
	 * Encode printable ASCII as a Code 128-B barcode.
	 *
	 * @param string $text Text (non-printable characters are dropped).
	 * @return int[] Code values including start, checksum and stop.
	 */
	public static function code128_values( $text ) {
		$text   = preg_replace( '/[^\x20-\x7E]/', '', (string) $text );
		$values = array( 104 );
		$sum    = 104;
		$len    = strlen( $text );
		for ( $i = 0; $i < $len; $i++ ) {
			$v        = ord( $text[ $i ] ) - 32;
			$values[] = $v;
			$sum     += $v * ( $i + 1 );
		}
		$values[] = $sum % 103;
		$values[] = 106;
		return $values;
	}

	public static function code128_svg( $text ) {
		$x    = 10; // Quiet zone (modules).
		$bars = '';
		foreach ( self::code128_values( $text ) as $value ) {
			$pattern = self::CODE128[ $value ];
			$len     = strlen( $pattern );
			for ( $i = 0; $i < $len; $i++ ) {
				$width = (int) $pattern[ $i ];
				if ( 0 === $i % 2 ) {
					$bars .= 'M' . $x . ',0h' . $width . 'v1h-' . $width . 'z';
				}
				$x += $width;
			}
		}
		$total = $x + 10;
		return '<svg class="sps-barcode" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' 1" preserveAspectRatio="none" role="img" aria-label="' . esc_attr( $text ) . '"><path d="' . $bars . '" fill="#000"/></svg>';
	}
}
