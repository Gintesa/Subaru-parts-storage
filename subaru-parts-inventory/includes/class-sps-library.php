<?php
/**
 * Official parts.subaru.com library for the car (bundled in data/, or uploaded).
 *
 * Stores every OEM part number with its official names, notes, list price,
 * diagram (assembly) + callout reference and catalog image, builds the
 * category tree exactly like parts.subaru.com (category → assembly diagram),
 * and serves catalog images – from the uploaded image pack if present,
 * otherwise from the parts.subaru.com image server.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Library {

	const DB_VERSION = '1';
	const BUNDLED    = 'data/subaru-2017-wrx-library.json.gz';
	const IMG_DIR    = 'sps-library';

	/** Box code per parts.subaru.com top-level category. */
	const BOXES = array(
		'Engine and Cooling'           => 'BOX-EN',
		'Manual Transmission'          => 'BOX-MT',
		'Driveline and Differential'   => 'BOX-DD',
		'Suspension and Brakes'        => 'BOX-SB',
		'Steering'                     => 'BOX-ST',
		'Electrical'                   => 'BOX-EL',
		'Body'                         => 'BOX-BD',
		'Door'                         => 'BOX-DR',
		'Interior'                     => 'BOX-IN',
		'Heating and Air Conditioning' => 'BOX-AC',
		'Maintenance'                  => 'BOX-MA',
		'Automatic Transmission'       => 'BOX-AT',
		'Accessories'                  => 'BOX-AX',
	);

	/** Earlier (v1.0–1.2) default top-level categories → parts.subaru.com category. */
	const LEGACY_MAP = array(
		'Engine Main'                    => 'Engine and Cooling',
		'Engine Auxiliaries'             => 'Engine and Cooling',
		'Manual Transmission'            => 'Manual Transmission',
		'Differential & Propeller Shaft' => 'Driveline and Differential',
		'Suspension, Axle & Brake'       => 'Suspension and Brakes',
		'Body Electrical'                => 'Electrical',
		'Body'                           => 'Body',
		'Interior & Seats'               => 'Interior',
		'Heater & Air Conditioner'       => 'Heating and Air Conditioning',
		'Accessories'                    => 'Accessories',
		'Maintenance, Fluids & Hardware' => 'Maintenance',
		'To Sort (Unidentified)'         => 'To Sort (Unidentified)',
	);

	public static function init() {
		add_action( 'admin_post_sps_oem_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_sps_oem_images', array( __CLASS__, 'handle_images' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Schema                                                              */
	/* ------------------------------------------------------------------ */

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'sps_oem' . ( 'parts' === $name ? '' : '_' . $name );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::t( 'parts' ) . " (
				pn_norm varchar(40) NOT NULL,
				part_number varchar(60) NOT NULL DEFAULT '',
				name varchar(255) NOT NULL DEFAULT '',
				aliases text NULL,
				notes text NULL,
				price decimal(10,2) NULL,
				url varchar(255) NOT NULL DEFAULT '',
				image_url varchar(255) NOT NULL DEFAULT '',
				image_file varchar(190) NOT NULL DEFAULT '',
				asm_code varchar(40) NOT NULL DEFAULT '',
				ref varchar(40) NOT NULL DEFAULT '',
				term_id bigint(20) unsigned NOT NULL DEFAULT 0,
				top varchar(80) NOT NULL DEFAULT '',
				my_ok tinyint(1) NOT NULL DEFAULT 1,
				PRIMARY KEY  (pn_norm),
				KEY top (top)
			) {$c};"
		);
		dbDelta(
			'CREATE TABLE ' . self::t( 'asm' ) . " (
				code varchar(40) NOT NULL,
				name varchar(255) NOT NULL DEFAULT '',
				raw_name varchar(255) NOT NULL DEFAULT '',
				top varchar(80) NOT NULL DEFAULT '',
				url varchar(255) NOT NULL DEFAULT '',
				diagram_url varchar(255) NOT NULL DEFAULT '',
				diagram_file varchar(190) NOT NULL DEFAULT '',
				term_id bigint(20) unsigned NOT NULL DEFAULT 0,
				sort int(11) NOT NULL DEFAULT 0,
				my_ok tinyint(1) NOT NULL DEFAULT 1,
				part_count int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY  (code),
				KEY top (top)
			) {$c};"
		);
		dbDelta(
			'CREATE TABLE ' . self::t( 'occ' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				pn_norm varchar(40) NOT NULL,
				asm_code varchar(40) NOT NULL,
				ref varchar(40) NOT NULL DEFAULT '',
				sort int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY pn_norm (pn_norm),
				KEY asm_code (asm_code)
			) {$c};"
		);
		update_option( 'sps_oem_db', self::DB_VERSION );
	}

	public static function ready() {
		if ( get_option( 'sps_oem_db' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function loaded() {
		$meta = get_option( 'sps_oem_meta' );
		return is_array( $meta ) && ! empty( $meta['parts'] );
	}

	/* ------------------------------------------------------------------ */
	/* Text helpers                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Model-year range in an assembly name: "-'17MY" → [null,17], "'18MY-" → [18,null], "'15MY-'16MY" → [15,16].
	 *
	 * @return array{0:int|null,1:int|null}|null
	 */
	public static function my_range( $name ) {
		if ( preg_match( "/'(\d{2})MY-'(\d{2})MY/", $name, $m ) ) {
			return array( (int) $m[1], (int) $m[2] );
		}
		if ( preg_match( "/-'(\d{2})MY/", $name, $m ) ) {
			return array( null, (int) $m[1] );
		}
		if ( preg_match( "/'(\d{2})MY-/", $name, $m ) ) {
			return array( (int) $m[1], null );
		}
		return null;
	}

	public static function my_fits( $range, $year = 17 ) {
		if ( ! $range ) {
			return true;
		}
		return ( null === $range[0] || $range[0] <= $year ) && ( null === $range[1] || $range[1] >= $year );
	}

	/**
	 * "AIR CLEANER & ELEMENT AIR CLEANER -'17MY" → "Air Cleaner & Element Air Cleaner (MY ≤17)".
	 */
	public static function pretty( $raw ) {
		$range = self::my_range( $raw );
		$s     = trim( preg_replace( "/\s*(?:'\d{2}MY-'\d{2}MY|-'\d{2}MY|'\d{2}MY-)\s*/", ' ', $raw ) );
		if ( strtoupper( $s ) === $s ) {
			$keep  = array( 'AT', 'MT', 'ABS', 'ADA', 'BSD/RCTA', 'I&S', 'DI', 'ECU', 'EPS', 'LED', 'LH', 'RH', 'PCV', 'TPMS', 'FLH', 'FRH', 'RFLH', 'RFRH', 'SD', 'PNL', 'ST', 'AY', 'F', 'R', 'B', 'O2', 'A/C', 'HID', 'SRS', 'VDC', 'EXC.EYESIGHT', 'CVT', 'DCCD' );
			$words = preg_split( '/(\s+)/', strtolower( $s ), -1, PREG_SPLIT_DELIM_CAPTURE );
			foreach ( $words as &$w ) {
				if ( '' === trim( $w ) ) {
					continue;
				}
				$core = trim( $w, '(),.;:' );
				if ( in_array( strtoupper( $core ), $keep, true ) ) {
					$w = str_ireplace( $core, strtoupper( $core ), $w );
				} elseif ( in_array( $core, array( 'and', 'or', 'of', 'for', 'with' ), true ) ) {
					continue;
				} else {
					$w = preg_replace_callback(
						'/(^|[(\/&-])([a-z])/',
						static function ( $m ) {
							return $m[1] . strtoupper( $m[2] );
						},
						$w
					);
				}
			}
			unset( $w );
			$s = implode( '', $words );
			$s = str_replace( 'Exc.eyesight', 'Exc. EyeSight', $s );
		}
		if ( $range ) {
			if ( null !== $range[0] && null !== $range[1] ) {
				$s .= sprintf( ' (MY%02d–%02d)', $range[0], $range[1] );
			} elseif ( null === $range[0] ) {
				$s .= sprintf( ' (MY ≤%02d)', $range[1] );
			} else {
				$s .= sprintf( ' (MY%02d+)', $range[0] );
			}
		}
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}

	/**
	 * "Engine Air Filter. Element Air Cleaner." → "Engine Air Filter".
	 */
	public static function short_name( $name ) {
		$name  = trim( (string) $name );
		$parts = preg_split( '/\.\s+(?=[A-Z0-9])/', rtrim( $name, '.' ) );
		$first = trim( $parts[0] );
		return '' !== $first ? $first : $name;
	}

	/* ------------------------------------------------------------------ */
	/* Import                                                              */
	/* ------------------------------------------------------------------ */

	public static function bundled_path() {
		return SPS_DIR . self::BUNDLED;
	}

	/**
	 * Read a library file (.json or .json.gz).
	 *
	 * @return array|WP_Error
	 */
	public static function read( $path ) {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw ) {
			return new WP_Error( 'sps_read', __( 'Could not read the library file.', 'subaru-parts-inventory' ) );
		}
		if ( "\x1f\x8b" === substr( $raw, 0, 2 ) ) {
			if ( ! function_exists( 'gzdecode' ) ) {
				return new WP_Error( 'sps_gz', __( 'This server cannot unpack .gz files. Upload the plain .json file instead.', 'subaru-parts-inventory' ) );
			}
			$raw = gzdecode( $raw );
		}
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) || empty( $data['categories'] ) ) {
			return new WP_Error( 'sps_json', __( 'This is not a parts.subaru.com library file (no "categories" found).', 'subaru-parts-inventory' ) );
		}
		return $data;
	}

	/**
	 * Import a library (replaces the previous one). Builds the category tree.
	 *
	 * @param array $data Decoded library.
	 * @return array{parts:int,assemblies:int,categories:int}
	 */
	public static function import( array $data ) {
		global $wpdb;
		self::install();
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_defer_term_counting( true );
		$wpdb->query( 'DELETE FROM ' . self::t( 'parts' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DELETE FROM ' . self::t( 'asm' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( 'DELETE FROM ' . self::t( 'occ' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared

		$year  = isset( $data['vehicle']['year'] ) ? (int) $data['vehicle']['year'] % 100 : 17;
		$cats  = $data['categories'];
		$order = array_keys( self::BOXES );
		usort(
			$cats,
			static function ( $a, $b ) use ( $order ) {
				$ia = array_search( $a['name'], $order, true );
				$ib = array_search( $b['name'], $order, true );
				return ( false === $ia ? 99 : $ia ) - ( false === $ib ? 99 : $ib );
			}
		);
		// Genuine accessories behave like one more category with one "assembly" per sub-category.
		if ( ! empty( $data['genuine_accessories']['subcategories'] ) ) {
			$acc = array( 'name' => 'Accessories', 'assemblies' => array() );
			foreach ( $data['genuine_accessories']['subcategories'] as $sub ) {
				$parts = array();
				foreach ( (array) $sub['products'] as $p ) {
					$parts[] = array(
						'ref'         => '',
						'name'        => $p['name'],
						'notes'       => null,
						'part_number' => $p['part_number'],
						'price_usd'   => isset( $p['price_usd'] ) ? $p['price_usd'] : null,
						'url'         => isset( $p['url'] ) ? $p['url'] : '',
						'image'       => isset( $p['image'] ) ? $p['image'] : '',
						'image_file'  => isset( $p['image_file'] ) ? $p['image_file'] : '',
					);
				}
				$acc['assemblies'][] = array(
					'code'         => 'ACC-' . sanitize_title( $sub['name'] ),
					'name'         => preg_replace( '/(?<=[a-z])(?=[A-Z])/', ' ', $sub['name'] ),
					'url'          => isset( $sub['url'] ) ? $sub['url'] : '',
					'diagram'      => '',
					'diagram_file' => '',
					'parts'        => $parts,
				);
			}
			$cats[] = $acc;
		}

		$tops   = array();
		$asm_rows = array();
		$occ_rows = array();
		$parts  = array();
		$sort   = 0;
		$top_order = 0;
		foreach ( $cats as $cat ) {
			$top_name = (string) $cat['name'];
			$top_id   = self::ensure_lib_term( $top_name, 0, ( $top_order += 10 ) );
			$tops[ $top_name ] = $top_id;
			if ( $top_id && '' === (string) get_term_meta( $top_id, 'sps_box_code', true ) ) {
				update_term_meta( $top_id, 'sps_box_code', isset( self::BOXES[ $top_name ] ) ? self::BOXES[ $top_name ] : 'BOX-' . strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $top_name ), 0, 2 ) ) );
			}
			$asm_order = 0;
			foreach ( (array) $cat['assemblies'] as $asm ) {
				$sort++;
				$range  = self::my_range( (string) $asm['name'] );
				$my_ok  = self::my_fits( $range, $year ) ? 1 : 0;
				$pretty = self::pretty( (string) $asm['name'] );
				$term   = $top_id ? self::ensure_lib_term( $pretty, $top_id, ( $asm_order += 10 ) ) : 0;
				$asm_rows[] = array(
					'code'         => (string) $asm['code'],
					'name'         => $pretty,
					'raw_name'     => (string) $asm['name'],
					'top'          => $top_name,
					'url'          => (string) ( $asm['url'] ?? '' ),
					'diagram_url'  => (string) ( $asm['diagram'] ?? '' ),
					'diagram_file' => (string) ( $asm['diagram_file'] ?? '' ),
					'term_id'      => (int) $term,
					'sort'         => $sort,
					'my_ok'        => $my_ok,
					'part_count'   => count( (array) $asm['parts'] ),
				);
				$psort = 0;
				foreach ( (array) $asm['parts'] as $p ) {
					$norm = sps_normalize_number( $p['part_number'] ?? '' );
					if ( '' === $norm ) {
						continue;
					}
					$occ_rows[] = array( $norm, (string) $asm['code'], (string) ( $p['ref'] ?? '' ), ++$psort );
					$cand = array(
						'part_number' => strtoupper( (string) $p['part_number'] ),
						'aliases'     => trim( (string) $p['name'] ),
						'notes'       => (string) ( $p['notes'] ?? '' ),
						'price'       => isset( $p['price_usd'] ) && '' !== $p['price_usd'] ? (float) $p['price_usd'] : null,
						'url'         => (string) ( $p['url'] ?? '' ),
						'image_url'   => (string) ( $p['image'] ?? '' ),
						'image_file'  => (string) ( $p['image_file'] ?? '' ),
						'asm_code'    => (string) $asm['code'],
						'ref'         => (string) ( $p['ref'] ?? '' ),
						'term_id'     => (int) $term,
						'top'         => $top_name,
						'my_ok'       => $my_ok,
					);
					// Primary placement: a real diagram (not the Maintenance shortcut list) that fits the model year.
					$score = ( 'Maintenance' !== $top_name ? 2 : 0 ) + $my_ok;
					if ( ! isset( $parts[ $norm ] ) ) {
						$cand['score']  = $score;
						$cand['any_ok'] = $my_ok;
						$parts[ $norm ] = $cand;
					} else {
						$parts[ $norm ]['any_ok'] = max( $parts[ $norm ]['any_ok'], $my_ok );
						foreach ( array( 'notes', 'image_url', 'image_file', 'url' ) as $k ) {
							if ( '' === $parts[ $norm ][ $k ] && '' !== $cand[ $k ] ) {
								$parts[ $norm ][ $k ] = $cand[ $k ];
							}
						}
						if ( null === $parts[ $norm ]['price'] && null !== $cand['price'] ) {
							$parts[ $norm ]['price'] = $cand['price'];
						}
						if ( $score > $parts[ $norm ]['score'] ) {
							foreach ( array( 'asm_code', 'ref', 'term_id', 'top' ) as $k ) {
								$parts[ $norm ][ $k ] = $cand[ $k ];
							}
							$parts[ $norm ]['score'] = $score;
						}
					}
				}
			}
		}

		foreach ( array_chunk( $asm_rows, 100 ) as $chunk ) {
			self::insert_rows( self::t( 'asm' ), $chunk );
		}
		foreach ( array_chunk( $occ_rows, 300 ) as $chunk ) {
			self::insert_rows(
				self::t( 'occ' ),
				array_map(
					static function ( $r ) {
						return array( 'pn_norm' => $r[0], 'asm_code' => $r[1], 'ref' => $r[2], 'sort' => $r[3] );
					},
					$chunk
				)
			);
		}
		$prow = array();
		foreach ( $parts as $norm => $p ) {
			$prow[] = array(
				'pn_norm'     => $norm,
				'part_number' => substr( $p['part_number'], 0, 60 ),
				'name'        => substr( self::short_name( $p['aliases'] ), 0, 255 ),
				'aliases'     => $p['aliases'],
				'notes'       => $p['notes'],
				'price'       => $p['price'],
				'url'         => substr( $p['url'], 0, 255 ),
				'image_url'   => substr( $p['image_url'], 0, 255 ),
				'image_file'  => substr( $p['image_file'], 0, 190 ),
				'asm_code'    => $p['asm_code'],
				'ref'         => substr( $p['ref'], 0, 40 ),
				'term_id'     => $p['term_id'],
				'top'         => $p['top'],
				'my_ok'       => $p['any_ok'],
			);
		}
		foreach ( array_chunk( $prow, 200 ) as $chunk ) {
			self::insert_rows( self::t( 'parts' ), $chunk );
		}
		wp_defer_term_counting( false );

		$stats = array(
			'parts'      => count( $parts ),
			'assemblies' => count( $asm_rows ),
			'categories' => count( $tops ),
			'vehicle'    => isset( $data['vehicle'] ) ? trim( ( $data['vehicle']['year'] ?? '' ) . ' ' . ( $data['vehicle']['make'] ?? '' ) . ' ' . ( $data['vehicle']['model'] ?? '' ) . ' ' . ( $data['vehicle']['trim'] ?? '' ) ) : '',
			'scraped_at' => (string) ( $data['scraped_at'] ?? '' ),
			'source'     => (string) ( $data['source'] ?? '' ),
			'imported'   => current_time( 'mysql' ),
		);
		update_option( 'sps_oem_meta', $stats, false );
		sps_flush_caches();
		delete_transient( 'sps_oem_tree' );
		return $stats;
	}

	private static function insert_rows( $table, array $rows ) {
		global $wpdb;
		if ( ! $rows ) {
			return;
		}
		$cols   = array_keys( $rows[0] );
		$values = array();
		$args   = array();
		foreach ( $rows as $row ) {
			$ph = array();
			foreach ( $cols as $col ) {
				if ( null === $row[ $col ] ) {
					$ph[] = 'NULL';
				} else {
					$ph[]   = is_int( $row[ $col ] ) ? '%d' : ( is_float( $row[ $col ] ) ? '%f' : '%s' );
					$args[] = $row[ $col ];
				}
			}
			$values[] = '(' . implode( ',', $ph ) . ')';
		}
		$sql = "INSERT INTO {$table} (`" . implode( '`,`', $cols ) . '`) VALUES ' . implode( ',', $values );
		$wpdb->query( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function ensure_lib_term( $name, $parent, $order ) {
		$existing = term_exists( $name, SPS_TAX_CAT, $parent );
		if ( $existing ) {
			$id = (int) $existing['term_id'];
		} else {
			$res = wp_insert_term( $name, SPS_TAX_CAT, array( 'parent' => $parent ) );
			if ( is_wp_error( $res ) ) {
				$id = 'term_exists' === $res->get_error_code() ? (int) $res->get_error_data() : 0;
			} else {
				$id = (int) $res['term_id'];
			}
		}
		if ( $id ) {
			update_term_meta( $id, 'sps_lib', 1 );
			if ( ! get_term_meta( $id, 'sps_seeded', true ) || ! get_term_meta( $id, 'sps_order', true ) ) {
				update_term_meta( $id, 'sps_order', $order );
			}
			update_term_meta( $id, 'sps_seeded', 1 );
		}
		return $id;
	}

	/**
	 * Move parts from the earlier (v1.0–1.2) default categories into the library tree
	 * and remove the old default categories that are now empty.
	 */
	public static function migrate_legacy_categories() {
		if ( ! self::loaded() ) {
			return;
		}
		$ids = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			$cat = sps_part_category( $id );
			if ( $cat && get_term_meta( $cat->term_id, 'sps_lib', true ) ) {
				continue;
			}
			$oem = self::get( sps_meta( $id, 'part_number' ) );
			if ( $oem && $oem['term_id'] && term_exists( (int) $oem['term_id'], SPS_TAX_CAT ) ) {
				wp_set_object_terms( $id, array( (int) $oem['term_id'] ), SPS_TAX_CAT );
				continue;
			}
			if ( ! $cat ) {
				continue;
			}
			$top = $cat;
			while ( $top->parent ) {
				$top = get_term( $top->parent, SPS_TAX_CAT );
			}
			$name = html_entity_decode( $top->name, ENT_QUOTES );
			if ( isset( self::LEGACY_MAP[ $name ] ) ) {
				$target = term_exists( self::LEGACY_MAP[ $name ], SPS_TAX_CAT, 0 );
				if ( $target && (int) $target['term_id'] !== (int) $cat->term_id ) {
					wp_set_object_terms( $id, array( (int) $target['term_id'] ), SPS_TAX_CAT );
				}
			}
		}
		// Remove empty, untouched legacy default categories (children first).
		$legacy = array();
		foreach ( SPS_Categories::default_tree() as $group => $info ) {
			$legacy[] = array( $group, $info[1] );
		}
		foreach ( $legacy as $l ) {
			list( $group, $children ) = $l;
			$parent = term_exists( $group, SPS_TAX_CAT, 0 );
			if ( ! $parent ) {
				continue;
			}
			$pid = (int) $parent['term_id'];
			foreach ( $children as $child ) {
				$t = term_exists( $child, SPS_TAX_CAT, $pid );
				if ( $t ) {
					self::maybe_delete_term( (int) $t['term_id'] );
				}
			}
			self::maybe_delete_term( $pid );
		}
		foreach ( $ids as $id ) {
			SPS_Catalog::remember( (int) $id );
		}
		sps_flush_caches();
	}

	private static function maybe_delete_term( $term_id ) {
		if ( get_term_meta( $term_id, 'sps_lib', true ) || ! get_term_meta( $term_id, 'sps_seeded', true ) ) {
			return;
		}
		if ( get_term_children( $term_id, SPS_TAX_CAT ) ) {
			return;
		}
		$has = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => SPS_TAX_CAT, 'terms' => $term_id, 'include_children' => false ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $has ) {
			wp_delete_term( $term_id, SPS_TAX_CAT );
		}
	}

	/**
	 * Install the bundled library (first install / upgrade).
	 */
	public static function install_bundled() {
		$path = self::bundled_path();
		if ( ! file_exists( $path ) ) {
			return false;
		}
		$data = self::read( $path );
		if ( is_wp_error( $data ) ) {
			return false;
		}
		self::import( $data );
		self::migrate_legacy_categories();
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Queries                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * OEM record for a part number, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get( $number ) {
		global $wpdb;
		$norm = sps_normalize_number( $number );
		if ( '' === $norm || ! self::loaded() ) {
			return null;
		}
		self::ready();
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'parts' ) . ' WHERE pn_norm = %s', $norm ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return $row ? $row : null;
	}

	/**
	 * Public-facing details for a part number (for cards, identify, labels).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function details( $number ) {
		global $wpdb;
		$row = self::get( $number );
		if ( ! $row ) {
			return null;
		}
		$asm = $wpdb->get_results( $wpdb->prepare( 'SELECT a.code, a.name, a.top, a.diagram_url, a.diagram_file, a.my_ok, o.ref FROM ' . self::t( 'occ' ) . ' o INNER JOIN ' . self::t( 'asm' ) . ' a ON a.code = o.asm_code WHERE o.pn_norm = %s ORDER BY a.my_ok DESC, (a.top = %s) ASC, a.sort ASC', $row['pn_norm'], 'Maintenance' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$diagrams = array();
		$seen     = array();
		foreach ( (array) $asm as $a ) {
			if ( isset( $seen[ $a['code'] ] ) ) {
				continue;
			}
			$seen[ $a['code'] ] = true;
			$diagrams[]         = array(
				'code'    => $a['code'],
				'name'    => $a['name'],
				'top'     => $a['top'],
				'ref'     => $a['ref'],
				'my_ok'   => (int) $a['my_ok'],
				'diagram' => self::image_url( $a['diagram_file'], $a['diagram_url'] ),
			);
		}
		return array(
			'part_number' => $row['part_number'],
			'name'        => $row['name'],
			'aliases'     => $row['aliases'],
			'notes'       => (string) $row['notes'],
			'price'       => null === $row['price'] ? null : (float) $row['price'],
			'url'         => $row['url'],
			'image'       => self::image_url( $row['image_file'], $row['image_url'] ),
			'ref'         => $row['ref'],
			'asm_code'    => $row['asm_code'],
			'top'         => $row['top'],
			'term_id'     => (int) $row['term_id'],
			'my_ok'       => (int) $row['my_ok'],
			'diagrams'    => $diagrams,
		);
	}

	/**
	 * Image URL: uploaded image pack first, then (if allowed) the online catalog image.
	 */
	public static function image_url( $file, $remote ) {
		if ( $file ) {
			$up   = wp_upload_dir( null, false );
			$path = trailingslashit( $up['basedir'] ) . self::IMG_DIR . '/' . ltrim( $file, '/' );
			if ( file_exists( $path ) ) {
				return trailingslashit( $up['baseurl'] ) . self::IMG_DIR . '/' . str_replace( '%2F', '/', rawurlencode( ltrim( $file, '/' ) ) );
			}
		}
		return sps_setting( 'remote_images' ) ? (string) $remote : '';
	}

	/**
	 * Search by number prefix or words in the official names.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function search( $q, $limit = 20 ) {
		global $wpdb;
		$q = trim( (string) $q );
		if ( strlen( $q ) < 2 || ! self::loaded() ) {
			return array();
		}
		$norm  = sps_normalize_number( $q );
		$where = array();
		$args  = array();
		if ( strlen( $norm ) >= 3 && preg_match( '/\d/', $norm ) ) {
			$where[] = 'p.pn_norm LIKE %s';
			$args[]  = $wpdb->esc_like( $norm ) . '%';
		}
		$words = array_filter( preg_split( '/\s+/', strtolower( $q ) ), static function ( $w ) {
			return strlen( $w ) >= 2;
		} );
		if ( $words ) {
			$and = array();
			foreach ( array_slice( $words, 0, 5 ) as $w ) {
				$and[]  = "(LOWER(p.aliases) LIKE %s OR REPLACE(LOWER(p.aliases), ' ', '') LIKE %s)";
				$args[] = '%' . $wpdb->esc_like( $w ) . '%';
				$args[] = '%' . $wpdb->esc_like( $w ) . '%';
			}
			$where[] = '(' . implode( ' AND ', $and ) . ')';
		}
		if ( ! $where ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.*, a.name AS asm_name FROM ' . self::t( 'parts' ) . ' p LEFT JOIN ' . self::t( 'asm' ) . ' a ON a.code = p.asm_code WHERE (' . implode( ' OR ', $where ) . ') LIMIT 300', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$rows   = is_array( $rows ) ? $rows : array();
		$phrase = strtolower( $q );
		foreach ( $rows as &$r ) {
			$name    = strtolower( $r['name'] );
			$aliases = strtolower( (string) $r['aliases'] );
			$score   = 0;
			if ( 0 === strpos( $r['pn_norm'], $norm ) && '' !== $norm ) {
				$score += 20;
			}
			if ( false !== strpos( $name, $phrase ) ) {
				$score += 10;
			} elseif ( false !== strpos( $aliases, $phrase ) ) {
				$score += 5;
			}
			foreach ( $words as $w ) {
				$re = '/\\b' . preg_quote( $w, '/' ) . '/';
				$score += preg_match( $re, $name ) ? 3 : ( preg_match( $re, $aliases ) ? 1 : ( false !== strpos( str_replace( ' ', '', $aliases ), $w ) ? 1 : -2 ) );
			}
			if ( false !== strpos( str_replace( ' ', '', $name ), str_replace( ' ', '', $phrase ) ) ) {
				$score += 4;
			}
			$score     += (int) $r['my_ok'] + ( 'Maintenance' !== $r['top'] ? 0.5 : 0 ) - strlen( $name ) / 40;
			$r['score'] = $score;
		}
		unset( $r );
		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'] ?: strcmp( $a['name'], $b['name'] );
			}
		);
		$rows = array_slice( (array) $rows, 0, (int) $limit );
		$out    = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'part_number' => $r['part_number'],
				'name'        => $r['name'],
				'aliases'     => $r['aliases'],
				'top'         => $r['top'],
				'asm'         => (string) $r['asm_name'],
				'ref'         => $r['ref'],
				'price'       => null === $r['price'] ? null : (float) $r['price'],
				'image'       => self::image_url( $r['image_file'], $r['image_url'] ),
				'category_id' => (int) $r['term_id'],
				'my_ok'       => (int) $r['my_ok'],
			);
		}
		return $out;
	}

	/**
	 * Inventory counts per normalised part number (parts you still have).
	 *
	 * @return array<string,array<int,array{id:int,sku:string,condition:string,quantity:int,status:string}>>
	 */
	public static function stock_index() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, n.meta_value AS pn FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = '_sps_part_number_norm' WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft') AND n.meta_value <> ''", SPS_PT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r->pn ][] = array(
				'id'        => (int) $r->ID,
				'sku'       => (string) sps_meta( $r->ID, 'sku' ),
				'condition' => (string) sps_meta( $r->ID, 'condition', 'used' ),
				'quantity'  => (int) sps_meta( $r->ID, 'quantity', 0 ),
				'status'    => (string) sps_meta( $r->ID, 'status', 'in_stock' ),
			);
		}
		return $out;
	}

	/**
	 * Category → assemblies tree with part and stock counts.
	 */
	public static function tree() {
		global $wpdb;
		if ( ! self::loaded() ) {
			return array();
		}
		$asm   = $wpdb->get_results( 'SELECT code, name, top, part_count, my_ok, diagram_file, diagram_url FROM ' . self::t( 'asm' ) . ' ORDER BY sort ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$occ   = $wpdb->get_results( 'SELECT asm_code, pn_norm FROM ' . self::t( 'occ' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$stock = self::stock_index();
		$have  = array();
		foreach ( (array) $occ as $o ) {
			if ( isset( $stock[ $o['pn_norm'] ] ) ) {
				$have[ $o['asm_code'] ][ $o['pn_norm'] ] = true;
			}
		}
		$tops = array();
		foreach ( (array) $asm as $a ) {
			if ( ! isset( $tops[ $a['top'] ] ) ) {
				$t                  = term_exists( $a['top'], SPS_TAX_CAT, 0 );
				$tops[ $a['top'] ]  = array(
					'name'       => $a['top'],
					'box'        => $t ? (string) get_term_meta( (int) $t['term_id'], 'sps_box_code', true ) : '',
					'assemblies' => array(),
					'have'       => 0,
				);
			}
			$n = isset( $have[ $a['code'] ] ) ? count( $have[ $a['code'] ] ) : 0;
			$tops[ $a['top'] ]['assemblies'][] = array(
				'code'  => $a['code'],
				'name'  => $a['name'],
				'parts' => (int) $a['part_count'],
				'have'  => $n,
				'my_ok' => (int) $a['my_ok'],
				'thumb' => $a['diagram_url'] || $a['diagram_file'] ? self::image_url( $a['diagram_file'], $a['diagram_url'] ) : '',
			);
			$tops[ $a['top'] ]['have'] += $n;
		}
		return array_values( $tops );
	}

	/**
	 * One assembly with its parts and what you have in stock.
	 */
	public static function assembly( $code ) {
		global $wpdb;
		$a = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'asm' ) . ' WHERE code = %s', $code ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $a ) {
			return null;
		}
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT o.ref, o.sort, p.* FROM ' . self::t( 'occ' ) . ' o INNER JOIN ' . self::t( 'parts' ) . ' p ON p.pn_norm = o.pn_norm WHERE o.asm_code = %s ORDER BY o.sort ASC', $code ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$stock = self::stock_index();
		$parts = array();
		foreach ( (array) $rows as $r ) {
			$parts[] = array(
				'ref'         => $r['ref'],
				'part_number' => $r['part_number'],
				'name'        => $r['name'],
				'aliases'     => $r['aliases'],
				'notes'       => (string) $r['notes'],
				'price'       => null === $r['price'] ? null : (float) $r['price'],
				'image'       => self::image_url( $r['image_file'], $r['image_url'] ),
				'url'         => $r['url'],
				'category_id' => (int) $a['term_id'],
				'stock'       => isset( $stock[ $r['pn_norm'] ] ) ? $stock[ $r['pn_norm'] ] : array(),
			);
		}
		return array(
			'code'    => $a['code'],
			'name'    => $a['name'],
			'raw'     => $a['raw_name'],
			'top'     => $a['top'],
			'my_ok'   => (int) $a['my_ok'],
			'url'     => $a['url'],
			'diagram' => $a['diagram_url'] || $a['diagram_file'] ? self::image_url( $a['diagram_file'], $a['diagram_url'] ) : '',
			'term_id' => (int) $a['term_id'],
			'parts'   => $parts,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Image pack                                                          */
	/* ------------------------------------------------------------------ */

	public static function image_dir() {
		$up = wp_upload_dir( null, false );
		return trailingslashit( $up['basedir'] ) . self::IMG_DIR;
	}

	/**
	 * Count images present in the image pack folder.
	 *
	 * @return array<string,int>
	 */
	public static function image_counts() {
		$out = array();
		foreach ( array( 'parts', 'diagrams', 'accessories', 'gear' ) as $sub ) {
			$dir         = self::image_dir() . '/images/' . $sub;
			$out[ $sub ] = is_dir( $dir ) ? count( (array) glob( $dir . '/*.{jpg,jpeg,png,gif,webp,JPG,PNG}', GLOB_BRACE ) ) : 0;
		}
		return $out;
	}

	/**
	 * Extract images from a zip of the image pack into uploads/sps-library/images/<sub>/.
	 * Only image files inside parts/, diagrams/, accessories/ or gear/ folders are taken.
	 *
	 * @return int|WP_Error Number of files extracted.
	 */
	public static function extract_images( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'sps_zip', __( 'This server has no ZIP support. Upload the images folder with FTP / your host\'s file manager instead.', 'subaru-parts-inventory' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new WP_Error( 'sps_zip', __( 'Could not open the ZIP file.', 'subaru-parts-inventory' ) );
		}
		$base = self::image_dir() . '/images/';
		$n    = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$name = (string) $zip->getNameIndex( $i );
			if ( ! preg_match( '#(?:^|/)(parts|diagrams|accessories|gear)/([^/]+\.(?:jpe?g|png|gif|webp))$#i', $name, $m ) ) {
				continue;
			}
			$file = $m[2];
			if ( preg_match( '#[\\\\/]|\.\.#', $file ) || '.' === $file[0] ) {
				continue;
			}
			$dir = $base . strtolower( $m[1] );
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return new WP_Error( 'sps_dir', __( 'Could not create the image folder in uploads.', 'subaru-parts-inventory' ) );
			}
			$data = $zip->getFromIndex( $i );
			if ( false === $data ) {
				continue;
			}
			// Must really be an image.
			$info = @getimagesizefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! $info ) {
				continue;
			}
			if ( false !== file_put_contents( $dir . '/' . $file, $data ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				$n++;
			}
		}
		$zip->close();
		return $n;
	}

	/* ------------------------------------------------------------------ */
	/* Admin panel                                                         */
	/* ------------------------------------------------------------------ */

	public static function panel() {
		$meta = get_option( 'sps_oem_meta' );
		$img  = self::image_counts();
		$msg  = isset( $_GET['sps_oem'] ) ? sanitize_text_field( wp_unslash( $_GET['sps_oem'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$max  = size_format( wp_max_upload_size() );
		?>
		<div class="sps-panel" style="margin-top:16px">
			<h2><?php esc_html_e( 'Official Subaru parts catalog (parts.subaru.com)', 'subaru-parts-inventory' ); ?></h2>
			<?php if ( $msg ) : ?><div class="notice notice-success inline"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
			<?php if ( is_array( $meta ) && ! empty( $meta['parts'] ) ) : ?>
				<p><strong><?php echo esc_html( $meta['vehicle'] ); ?></strong> — <?php echo esc_html( sprintf( /* translators: 1: parts, 2: diagrams, 3: categories */ __( '%1$s part numbers, %2$s diagrams, %3$s categories', 'subaru-parts-inventory' ), number_format_i18n( $meta['parts'] ), number_format_i18n( $meta['assemblies'] ), number_format_i18n( $meta['categories'] ) ) ); ?>
				<br><span class="description"><?php echo esc_html( sprintf( /* translators: 1: source, 2: date */ __( 'Source: %1$s, captured %2$s', 'subaru-parts-inventory' ), $meta['source'], substr( (string) $meta['scraped_at'], 0, 10 ) ) ); ?></span></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No catalog loaded.', 'subaru-parts-inventory' ); ?></p>
			<?php endif; ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
				<input type="hidden" name="action" value="sps_oem_import" />
				<?php wp_nonce_field( 'sps_oem_import' ); ?>
				<label><?php esc_html_e( 'Replace with a newer library file (.json or .json.gz):', 'subaru-parts-inventory' ); ?> <input type="file" name="library" accept=".json,.gz,application/json" /></label>
				<button class="button" name="bundled" value="0"><?php esc_html_e( 'Import file', 'subaru-parts-inventory' ); ?></button>
				<button class="button" name="bundled" value="1"><?php esc_html_e( 'Re-install bundled 2017 WRX catalog', 'subaru-parts-inventory' ); ?></button>
			</form>
			<h3><?php esc_html_e( 'Catalog images', 'subaru-parts-inventory' ); ?></h3>
			<p><?php echo esc_html( sprintf( /* translators: 1..4: counts */ __( 'Uploaded: %1$d part photos, %2$d diagrams, %3$d accessory and %4$d gear images.', 'subaru-parts-inventory' ), $img['parts'], $img['diagrams'], $img['accessories'], $img['gear'] ) ); ?>
				<?php if ( ! $img['parts'] && ! $img['diagrams'] ) : ?>
					<?php echo sps_setting( 'remote_images' ) ? esc_html__( 'Until you upload them, images are shown from the parts.subaru.com image server.', 'subaru-parts-inventory' ) : esc_html__( 'Online images are switched off in Settings, so no catalog images are shown yet.', 'subaru-parts-inventory' ); ?>
				<?php endif; ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sps_oem_images" />
				<?php wp_nonce_field( 'sps_oem_images' ); ?>
				<p><input type="file" name="zips[]" accept=".zip,application/zip" multiple required />
				<button class="button button-primary"><?php esc_html_e( 'Upload image ZIPs', 'subaru-parts-inventory' ); ?></button></p>
				<p class="description"><?php echo esc_html( sprintf( /* translators: 1: max size, 2: folder */ __( 'Select one or more subaru_2017_wrx_images_partN.zip files (max %1$s each on this server). If they are bigger, upload the "images" folder by FTP / your host\'s file manager to %2$s instead – the plugin finds them there automatically.', 'subaru-parts-inventory' ), $max, 'wp-content/uploads/' . self::IMG_DIR . '/images/' ) ); ?></p>
			</form>
		</div>
		<?php
	}

	private static function back( $msg ) {
		wp_safe_redirect( add_query_arg( 'sps_oem', rawurlencode( $msg ), admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-import-export' ) ) );
		exit;
	}

	public static function handle_import() {
		check_admin_referer( 'sps_oem_import' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		if ( ! empty( $_POST['bundled'] ) ) {
			$data = self::read( self::bundled_path() );
		} elseif ( ! empty( $_FILES['library']['tmp_name'] ) && is_uploaded_file( $_FILES['library']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$data = self::read( $_FILES['library']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} else {
			self::back( __( 'Choose a library file first.', 'subaru-parts-inventory' ) );
		}
		if ( is_wp_error( $data ) ) {
			self::back( $data->get_error_message() );
		}
		$r = self::import( $data );
		self::migrate_legacy_categories();
		/* translators: 1: parts, 2: diagrams */
		self::back( sprintf( __( 'Catalog imported: %1$d part numbers, %2$d diagrams. Categories updated.', 'subaru-parts-inventory' ), $r['parts'], $r['assemblies'] ) );
	}

	public static function handle_images() {
		check_admin_referer( 'sps_oem_images' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$total  = 0;
		$errors = array();
		$files  = isset( $_FILES['zips'] ) ? $_FILES['zips'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( (array) ( $files['tmp_name'] ?? array() ) as $i => $tmp ) {
			if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
				$errors[] = ( $files['name'][ $i ] ?? '?' ) . ': ' . __( 'upload failed (too big for this server?)', 'subaru-parts-inventory' );
				continue;
			}
			$n = self::extract_images( $tmp );
			if ( is_wp_error( $n ) ) {
				$errors[] = $files['name'][ $i ] . ': ' . $n->get_error_message();
			} else {
				$total += $n;
			}
		}
		/* translators: %d: count */
		$msg = sprintf( __( '%d images added.', 'subaru-parts-inventory' ), $total );
		self::back( $errors ? $msg . ' ' . implode( ' ', $errors ) : $msg );
	}
}
