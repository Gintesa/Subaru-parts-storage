<?php
/**
 * CSV import / export (spreadsheet friendly, round-trips all fields).
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Import_Export {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_sps_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_sps_template', array( __CLASS__, 'template' ) );
		add_action( 'admin_post_sps_import', array( __CLASS__, 'import' ) );
	}

	/**
	 * CSV columns in export order.
	 *
	 * @return string[]
	 */
	public static function columns() {
		return array(
			'sku',
			'part_number',
			'name',
			'description',
			'category',
			'fitment',
			'alt_numbers',
			'epc_ref',
			'condition',
			'condition_flags',
			'condition_notes',
			'mileage',
			'removed_from',
			'quantity',
			'status',
			'box_override',
			'bin',
			'purchase_price',
			'purchase_date',
			'source',
			'value',
			'sale_price',
			'weight',
			'public',
			'private_notes',
			'label_printed',
			'post_status',
		);
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . SPS_PT, __( 'Import / Export', 'subaru-parts-inventory' ), __( 'Import / Export', 'subaru-parts-inventory' ), SPS_CAP, 'sps-import-export', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		// phpcs:disable WordPress.Security.NonceVerification
		$report = isset( $_GET['sps_report'] ) ? get_transient( 'sps_import_report_' . get_current_user_id() ) : false;
		// phpcs:enable
		?>
		<div class="wrap sps-wrap">
			<h1><?php esc_html_e( 'Import / Export', 'subaru-parts-inventory' ); ?></h1>
			<?php if ( is_array( $report ) ) : ?>
				<div class="notice notice-<?php echo $report['errors'] ? 'warning' : 'success'; ?>"><p>
					<?php
					/* translators: 1: created, 2: updated, 3: skipped */
					echo esc_html( sprintf( __( 'Import finished: %1$d created, %2$d updated, %3$d skipped.', 'subaru-parts-inventory' ), $report['created'], $report['updated'], $report['skipped'] ) );
					?>
				</p>
				<?php if ( $report['errors'] ) : ?>
					<ul><?php foreach ( array_slice( $report['errors'], 0, 50 ) as $err ) : ?><li><?php echo esc_html( $err ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="sps-columns">
				<div class="sps-panel">
					<h2><?php esc_html_e( 'Export', 'subaru-parts-inventory' ); ?></h2>
					<p><?php esc_html_e( 'Download every part with all fields as CSV (opens in Excel, LibreOffice, Google Sheets). Also a good backup.', 'subaru-parts-inventory' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sps_export" />
						<?php wp_nonce_field( 'sps_export' ); ?>
						<p><label><input type="checkbox" name="in_stock_only" value="1" /> <?php esc_html_e( 'Only parts I still have', 'subaru-parts-inventory' ); ?></label></p>
						<button class="button button-primary"><?php esc_html_e( 'Download CSV', 'subaru-parts-inventory' ); ?></button>
					</form>
				</div>
				<div class="sps-panel">
					<h2><?php esc_html_e( 'Import', 'subaru-parts-inventory' ); ?></h2>
					<p><?php esc_html_e( 'Upload a CSV with a header row. Only "part_number" or "name" is required; other columns are optional and can be in any order.', 'subaru-parts-inventory' ); ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sps_template' ), 'sps_template' ) ); ?>"><?php esc_html_e( 'Download a template', 'subaru-parts-inventory' ); ?></a></p>
					<ul class="ul-disc">
						<li><?php echo wp_kses_post( __( '<code>category</code>: path such as <code>Engine Auxiliaries &gt; Turbocharger</code> (missing categories are created).', 'subaru-parts-inventory' ) ); ?></li>
						<li><?php echo wp_kses_post( sprintf( /* translators: %s: list */ __( '<code>condition</code>: %s', 'subaru-parts-inventory' ), '<code>' . implode( '</code>, <code>', array_keys( sps_conditions() ) ) . '</code>' ) ); ?></li>
						<li><?php echo wp_kses_post( sprintf( /* translators: %s: list */ __( '<code>status</code>: %s', 'subaru-parts-inventory' ), '<code>' . implode( '</code>, <code>', array_keys( sps_statuses() ) ) . '</code>' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<code>condition_flags</code> and <code>fitment</code>: separate multiple values with <code>|</code>.', 'subaru-parts-inventory' ) ); ?></li>
						<li><?php echo wp_kses_post( __( 'Rows with an existing <code>sku</code> update that part; otherwise a new part with a new label ID is created.', 'subaru-parts-inventory' ) ); ?></li>
					</ul>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sps_import" />
						<?php wp_nonce_field( 'sps_import' ); ?>
						<p><input type="file" name="csv" accept=".csv,text/csv" required /></p>
						<p><label><input type="checkbox" name="publish" value="1" checked /> <?php esc_html_e( 'Publish new parts (otherwise save as draft)', 'subaru-parts-inventory' ); ?></label></p>
						<button class="button button-primary"><?php esc_html_e( 'Import CSV', 'subaru-parts-inventory' ); ?></button>
					</form>
				</div>
			</div>
			<?php SPS_Library::panel(); ?>
			<?php SPS_Catalog::panel(); ?>
		</div>
		<?php
	}

	private static function send_headers( $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	}

	public static function export() {
		check_admin_referer( 'sps_export' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		$args = array(
			'post_type'      => SPS_PT,
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
		if ( ! empty( $_POST['in_stock_only'] ) ) {
			$args['meta_query'] = array( array( 'key' => '_sps_status', 'value' => array_keys( wp_list_filter( sps_statuses(), array( 'in_stock' => true ) ) ), 'compare' => 'IN' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		self::send_headers( 'subaru-parts-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel.
		fputcsv( $out, self::columns() );
		foreach ( get_posts( $args ) as $post ) {
			$cat = sps_part_category( $post->ID );
			$fit = wp_get_post_terms( $post->ID, SPS_TAX_FIT, array( 'fields' => 'names' ) );
			$row = array();
			foreach ( self::columns() as $col ) {
				switch ( $col ) {
					case 'name':
						$row[] = html_entity_decode( $post->post_title, ENT_QUOTES );
						break;
					case 'description':
						$row[] = $post->post_content;
						break;
					case 'category':
						$row[] = $cat ? html_entity_decode( sps_category_path( $cat, ' > ' ), ENT_QUOTES ) : '';
						break;
					case 'fitment':
						$row[] = is_wp_error( $fit ) ? '' : implode( '|', $fit );
						break;
					case 'condition_flags':
						$row[] = implode( '|', (array) sps_meta( $post->ID, 'condition_flags', array() ) );
						break;
					case 'post_status':
						$row[] = $post->post_status;
						break;
					default:
						$row[] = (string) sps_meta( $post->ID, $col );
				}
			}
			fputcsv( $out, self::csv_safe( $row ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Prevent spreadsheet formula injection.
	 */
	private static function csv_safe( array $row ) {
		return array_map(
			static function ( $v ) {
				$v = (string) $v;
				return ( '' !== $v && in_array( $v[0], array( '=', '+', '@' ), true ) ) ? "'" . $v : $v;
			},
			$row
		);
	}

	public static function template() {
		check_admin_referer( 'sps_template' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		self::send_headers( 'subaru-parts-template.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, self::columns() );
		fputcsv( $out, array( '', '14411AA850', 'Turbocharger assembly', 'Complete with gaskets', 'Engine Auxiliaries > Turbocharger', sps_setting( 'default_fitment' ), '', '', 'used', 'tested_ok|scratches', 'No shaft play, light scratches on compressor housing', '62000', '2017 WRX donor', '1', 'not_boxed', '', 'bag 1', '350', '2026-05-01', 'Local breaker', '450', '', '8.5', '1', '', '', 'publish' ) );
		fputcsv( $out, array( '', '26296VA120', 'Front brake pad kit', '', 'Suspension, Axle & Brake > Front Brake', sps_setting( 'default_fitment' ), '', '', 'new', '', '', '', '', '2', 'in_stock', '', '', '75', '', 'Dealer', '75', '', '', '1', '', '', 'publish' ) );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function import() {
		check_admin_referer( 'sps_import' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		$report = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$report['errors'][] = __( 'No file uploaded.', 'subaru-parts-inventory' );
		} else {
			$report = self::import_file( $_FILES['csv']['tmp_name'], ! empty( $_POST['publish'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		set_transient( 'sps_import_report_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-import-export&sps_report=1' ) );
		exit;
	}

	/**
	 * Import a CSV file.
	 *
	 * @param string $path    File path.
	 * @param bool   $publish Publish new parts.
	 * @return array{created:int,updated:int,skipped:int,errors:string[]}
	 */
	public static function import_file( $path, $publish = true ) {
		$report = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
		$fh     = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			$report['errors'][] = __( 'Could not read the file.', 'subaru-parts-inventory' );
			return $report;
		}
		$first = fgets( $fh );
		rewind( $fh );
		$delim = substr_count( (string) $first, ';' ) > substr_count( (string) $first, ',' ) ? ';' : ',';
		$head  = fgetcsv( $fh, 0, $delim );
		if ( ! $head ) {
			$report['errors'][] = __( 'Empty file.', 'subaru-parts-inventory' );
			return $report;
		}
		$head    = array_map(
			static function ( $h ) {
				$h = strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) );
				return str_replace( array( ' ', '-' ), '_', $h );
			},
			$head
		);
		$aliases = array( 'title' => 'name', 'part_name' => 'name', 'qty' => 'quantity', 'partnumber' => 'part_number', 'part_no' => 'part_number', 'notes' => 'condition_notes', 'box' => 'box_override', 'label_id' => 'sku' );
		$head    = array_map(
			static function ( $h ) use ( $aliases ) {
				return isset( $aliases[ $h ] ) ? $aliases[ $h ] : $h;
			},
			$head
		);
		$line = 1;
		wp_defer_term_counting( true );
		while ( ( $cells = fgetcsv( $fh, 0, $delim ) ) !== false ) {
			$line++;
			if ( ! array_filter( $cells, 'strlen' ) ) {
				continue;
			}
			$row = array();
			foreach ( $head as $i => $key ) {
				$row[ $key ] = isset( $cells[ $i ] ) ? trim( (string) $cells[ $i ] ) : '';
				if ( '' !== $row[ $key ] && "'" === $row[ $key ][0] && isset( $row[ $key ][1] ) && in_array( $row[ $key ][1], array( '=', '+', '@' ), true ) ) {
					$row[ $key ] = substr( $row[ $key ], 1 );
				}
			}
			$result = self::import_row( $row, $publish );
			if ( is_wp_error( $result ) ) {
				$report['skipped']++;
				/* translators: 1: line, 2: message */
				$report['errors'][] = sprintf( __( 'Line %1$d: %2$s', 'subaru-parts-inventory' ), $line, $result->get_error_message() );
			} else {
				$report[ $result ]++;
			}
		}
		wp_defer_term_counting( false );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		sps_flush_caches();
		return $report;
	}

	/**
	 * @return string|WP_Error "created" or "updated".
	 */
	public static function import_row( array $row, $publish = true ) {
		$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		$pn   = isset( $row['part_number'] ) ? $row['part_number'] : '';
		if ( '' !== $pn && ( '' === $name || empty( $row['category'] ) ) ) {
			$ident = SPS_Catalog::identify( $pn );
			if ( '' === $name ) {
				$name = $ident['name'];
			}
			if ( empty( $row['category'] ) && '' !== $ident['category_path'] && $ident['category_id'] ) {
				$row['category'] = $ident['category_path'];
			}
		}
		if ( '' === $name && '' === $pn ) {
			return new WP_Error( 'sps_missing', __( 'needs a part_number or name.', 'subaru-parts-inventory' ) );
		}
		$existing = 0;
		if ( ! empty( $row['sku'] ) ) {
			global $wpdb;
			$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_sps_sku' AND pm.meta_value = %s AND p.post_type = %s AND p.post_status <> 'trash' LIMIT 1", $row['sku'], SPS_PT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$statuses = array( 'publish', 'draft', 'private', 'pending' );
		$postarr  = array(
			'post_type'  => SPS_PT,
			'post_title' => '' !== $name ? $name : $pn,
		);
		if ( isset( $row['description'] ) ) {
			$postarr['post_content'] = wp_kses_post( $row['description'] );
		}
		if ( ! empty( $row['post_status'] ) && in_array( $row['post_status'], $statuses, true ) ) {
			$postarr['post_status'] = $row['post_status'];
		} elseif ( ! $existing ) {
			$postarr['post_status'] = $publish ? 'publish' : 'draft';
		}
		if ( $existing ) {
			$postarr['ID'] = $existing;
			if ( '' === $name ) {
				unset( $postarr['post_title'] );
			}
			$id = wp_update_post( $postarr, true );
		} else {
			$id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! $existing && ! empty( $row['sku'] ) ) {
			update_post_meta( $id, '_sps_sku', sanitize_text_field( $row['sku'] ) );
		}
		$meta = array_intersect_key( $row, sps_meta_fields() );
		foreach ( array( 'condition', 'status', 'public' ) as $k ) {
			if ( isset( $meta[ $k ] ) && '' === $meta[ $k ] ) {
				unset( $meta[ $k ] );
			}
		}
		if ( isset( $meta['condition'] ) ) {
			$meta['condition'] = sps_condition_key( self::match_key( $meta['condition'], sps_conditions() ) );
		}
		if ( isset( $meta['status'] ) ) {
			$meta['status'] = self::match_key( $meta['status'], sps_statuses() );
		}
		sps_save_part_meta( $id, $meta );
		if ( ! empty( $row['label_printed'] ) ) {
			update_post_meta( $id, '_sps_label_printed', sanitize_text_field( $row['label_printed'] ) );
		}
		if ( isset( $row['category'] ) && '' !== $row['category'] ) {
			$term_id = SPS_Categories::term_from_path( $row['category'] );
			if ( $term_id ) {
				wp_set_object_terms( $id, array( $term_id ), SPS_TAX_CAT );
			}
		} elseif ( ! $existing ) {
			$term_id = SPS_Categories::term_from_path( 'To Sort (Unidentified)' );
			wp_set_object_terms( $id, $term_id ? array( $term_id ) : array(), SPS_TAX_CAT );
		}
		if ( isset( $row['fitment'] ) ) {
			wp_set_object_terms( $id, array_filter( array_map( 'trim', explode( '|', $row['fitment'] ) ) ), SPS_TAX_FIT );
		}
		sps_refresh_derived_meta( $id );
		return $existing ? 'updated' : 'created';
	}

	/**
	 * Accept keys ("used_b") or labels / short codes ("USED-B", "Sold").
	 */
	private static function match_key( $value, array $options ) {
		$v = strtolower( trim( (string) $value ) );
		if ( isset( $options[ $v ] ) ) {
			return $v;
		}
		foreach ( $options as $key => $opt ) {
			if ( strtolower( $opt['label'] ) === $v || ( isset( $opt['short'] ) && strtolower( $opt['short'] ) === $v ) ) {
				return $key;
			}
		}
		return $value;
	}
}
