<?php
/**
 * Part-number library: identifies a part (name + catalog category/box) from its number.
 *
 * Lookup order:
 *  1. Exact number in the library table – entries you imported ("library") or
 *     names you entered yourself on parts ("mine", remembered automatically).
 *  2. Another number with the same 5-digit Subaru base in the table ("similar").
 *  3. Built-in rules: Subaru numbers start with a 5-digit group code
 *     (14411 = turbocharger, 22433 = ignition coil …) → generic name + category.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Catalog {

	const DB_VERSION = '1';

	public static function init() {
		add_action( 'admin_post_sps_library_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_sps_library_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_sps_library_clear', array( __CLASS__, 'handle_clear' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sps_catalog';
	}

	/**
	 * Create / update the library table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				pn_norm varchar(40) NOT NULL,
				part_number varchar(60) NOT NULL DEFAULT '',
				name varchar(255) NOT NULL DEFAULT '',
				category varchar(255) NOT NULL DEFAULT '',
				source varchar(20) NOT NULL DEFAULT 'library',
				updated datetime NULL DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY pn_norm (pn_norm)
			) {$charset};"
		);
		update_option( 'sps_catalog_db', self::DB_VERSION );
	}

	private static function ready() {
		if ( get_option( 'sps_catalog_db' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Built-in rules: number prefix => [ generic name, category path ].
	 * Longest matching prefix wins. These are suggestions – always editable.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function rules() {
		$em = 'Engine Main > ';
		$ea = 'Engine Auxiliaries > ';
		$mt = 'Manual Transmission > ';
		$dp = 'Differential & Propeller Shaft > ';
		$sb = 'Suspension, Axle & Brake > ';
		$be = 'Body Electrical > ';
		$bd = 'Body > ';
		$in = 'Interior & Seats > ';
		$ac = 'Heater & Air Conditioner > ';
		$mh = 'Maintenance, Fluids & Hardware > ';
		return array(
			// Engine main.
			'10103' => array( 'Short block / engine assembly', $em . 'Engine Assembly / Short Block' ),
			'11008' => array( 'Cylinder block', $em . 'Cylinder Block' ),
			'11044' => array( 'Cylinder head gasket', $em . 'Engine Gasket & Seal Kit' ),
			'11109' => array( 'Oil pan', $em . 'Oil Pan' ),
			'11126' => array( 'Oil drain plug', $em . 'Oil Pan' ),
			'11140' => array( 'Oil level gauge (dipstick)', $em . 'Oil Pump & Filter' ),
			'1231'  => array( 'Flywheel', $mt . 'Flywheel' ),
			'1234'  => array( 'Flywheel', $mt . 'Flywheel' ),
			'15010' => array( 'Oil pump', $em . 'Oil Pump & Filter' ),
			'15208' => array( 'Oil filter', $mh . 'Filters' ),
			'10'    => array( '', 'Engine Main' ),
			'11'    => array( '', 'Engine Main' ),
			'12'    => array( '', 'Engine Main' ),
			'13'    => array( '', $em . 'Camshaft & Timing Chain' ),
			// Engine auxiliaries.
			'14001' => array( 'Intake manifold', $ea . 'Intake Manifold' ),
			'14035' => array( 'Intake manifold gasket', $ea . 'Intake Manifold' ),
			'14411' => array( 'Turbocharger', $ea . 'Turbocharger' ),
			'14'    => array( '', $ea . 'Turbocharger' ),
			'16112' => array( 'Throttle body', $ea . 'Throttle Chamber' ),
			'16546' => array( 'Air filter element', $ea . 'Air Cleaner & Element' ),
			'16611' => array( 'Fuel injector', $ea . 'Fuel Injector & Fuel Rail' ),
			'16630' => array( 'High pressure fuel pump', $ea . 'High Pressure Fuel Pump' ),
			'16'    => array( '', $ea . 'Fuel Injector & Fuel Rail' ),
			'17'    => array( '', $ea . 'Fuel Injector & Fuel Rail' ),
			'21111' => array( 'Water pump', $ea . 'Water Pump' ),
			'21210' => array( 'Thermostat', $ea . 'Water Pipe & Thermostat' ),
			'21821' => array( 'Intercooler', $ea . 'Intercooler' ),
			'21'    => array( '', $ea . 'Water Pipe & Thermostat' ),
			'22053' => array( 'Crankshaft position sensor', $ea . 'Engine Sensors' ),
			'22056' => array( 'Camshaft position sensor', $ea . 'Engine Sensors' ),
			'22060' => array( 'Knock sensor', $ea . 'Engine Sensors' ),
			'22401' => array( 'Spark plug', $ea . 'Spark Plug & Ignition Coil' ),
			'22433' => array( 'Ignition coil', $ea . 'Spark Plug & Ignition Coil' ),
			'22611' => array( 'Engine control module (ECM)', $ea . 'Engine Control Module (ECM)' ),
			'22627' => array( 'Manifold pressure sensor', $ea . 'Engine Sensors' ),
			'22641' => array( 'Air/fuel ratio sensor', $ea . 'Engine Sensors' ),
			'22680' => array( 'Mass air flow sensor', $ea . 'Engine Sensors' ),
			'22690' => array( 'Oxygen sensor', $ea . 'Engine Sensors' ),
			'22'    => array( '', $ea . 'Engine Sensors' ),
			'23300' => array( 'Starter', $ea . 'Starter' ),
			'23700' => array( 'Alternator', $ea . 'Alternator' ),
			'23'    => array( '', $ea . 'Alternator' ),
			'410'   => array( 'Engine / transmission mount', $ea . 'Engine Mounting' ),
			'44'    => array( '', $ea . 'Exhaust (Pipes & Muffler)' ),
			'45111' => array( 'Radiator', $ea . 'Radiator & Cooling Fan' ),
			'4512'  => array( 'Radiator fan / motor', $ea . 'Radiator & Cooling Fan' ),
			'45150' => array( 'Coolant reservoir', $ea . 'Coolant Reservoir & Hoses' ),
			'4516'  => array( 'Radiator hose', $ea . 'Coolant Reservoir & Hoses' ),
			'45'    => array( '', $ea . 'Radiator & Cooling Fan' ),
			'46'    => array( '', $ea . 'Air Duct' ),
			// Transmission / driveline.
			'30100' => array( 'Clutch disc', $mt . 'Clutch (Disc, Cover, Release Bearing)' ),
			'30210' => array( 'Clutch cover (pressure plate)', $mt . 'Clutch (Disc, Cover, Release Bearing)' ),
			'30502' => array( 'Clutch release bearing', $mt . 'Clutch (Disc, Cover, Release Bearing)' ),
			'30'    => array( '', $mt . 'Clutch (Disc, Cover, Release Bearing)' ),
			'31'    => array( '', 'Manual Transmission' ),
			'32'    => array( '', 'Manual Transmission' ),
			'35'    => array( '', $mt . 'Control Device (Shifter & Linkage)' ),
			'36'    => array( '', $sb . 'Pedal System' ),
			'27111' => array( 'Propeller shaft', $dp . 'Propeller Shaft' ),
			'275'   => array( 'ABS / VDC hydraulic unit', $sb . 'ABS / VDC Unit' ),
			'27'    => array( '', 'Differential & Propeller Shaft' ),
			'283'   => array( 'Front drive shaft', $dp . 'Front Drive Shaft (CV Axle)' ),
			'284'   => array( 'Rear drive shaft', $dp . 'Rear Drive Shaft (CV Axle)' ),
			// Suspension, brakes, wheels, steering.
			'20202' => array( 'Front lower control arm', $sb . 'Front Suspension (Arms & Crossmember)' ),
			'2025'  => array( 'Rear suspension link / arm', $sb . 'Rear Suspension (Arms & Subframe)' ),
			'20310' => array( 'Front strut', $sb . 'Front Shock Absorber & Spring' ),
			'20365' => array( 'Rear shock absorber', $sb . 'Rear Shock Absorber & Spring' ),
			'20380' => array( 'Coil spring', $sb . 'Front Shock Absorber & Spring' ),
			'20401' => array( 'Front stabilizer bar', $sb . 'Stabilizer (Sway Bar & Links)' ),
			'20420' => array( 'Stabilizer link', $sb . 'Stabilizer (Sway Bar & Links)' ),
			'20451' => array( 'Rear stabilizer bar', $sb . 'Stabilizer (Sway Bar & Links)' ),
			'20'    => array( '', 'Suspension, Axle & Brake' ),
			'26292' => array( 'Front brake caliper', $sb . 'Front Brake' ),
			'26296' => array( 'Front brake pad kit', $sb . 'Front Brake' ),
			'26300' => array( 'Front brake disc rotor', $sb . 'Front Brake' ),
			'26401' => array( 'Brake master cylinder', $sb . 'Brake Master Cylinder & Booster' ),
			'26540' => array( 'Brake hose', $sb . 'Brake Pipe & Hose' ),
			'26692' => array( 'Rear brake caliper', $sb . 'Rear Brake' ),
			'26696' => array( 'Rear brake pad kit', $sb . 'Rear Brake' ),
			'26700' => array( 'Rear brake disc rotor', $sb . 'Rear Brake' ),
			'26'    => array( '', 'Suspension, Axle & Brake' ),
			'28103' => array( 'Tire pressure (TPMS) sensor', $sb . 'Tire & TPMS' ),
			'28111' => array( 'Wheel', $sb . 'Disc Wheel' ),
			'28'    => array( '', $sb . 'Front Axle & Hub' ),
			'34110' => array( 'Steering gearbox', $sb . 'Steering Gearbox & Tie Rods' ),
			'3416'  => array( 'Tie rod end', $sb . 'Steering Gearbox & Tie Rods' ),
			'34311' => array( 'Steering wheel', $sb . 'Steering Column & Wheel' ),
			'345'   => array( 'Steering column', $sb . 'Steering Column & Wheel' ),
			'34'    => array( '', $sb . 'Power Steering (Electric)' ),
			// Fuel tank.
			'42021' => array( 'Fuel tank', $bd . 'Fuel Tank & Fuel Pump' ),
			'42022' => array( 'Fuel pump assembly', $bd . 'Fuel Tank & Fuel Pump' ),
			'42'    => array( '', $bd . 'Fuel Tank & Fuel Pump' ),
			// Body.
			'57120' => array( 'Front fender', $bd . 'Front Fender' ),
			'57229' => array( 'Hood', $bd . 'Front Hood' ),
			'57704' => array( 'Bumper face', $bd . 'Front Bumper' ),
			'57'    => array( '', 'Body' ),
			'59'    => array( '', $bd . 'Mud Guard & Under Cover' ),
			'60'    => array( '', $bd . 'Door Parts – Front' ),
			'61'    => array( '', $bd . 'Door Parts – Front' ),
			'62'    => array( '', $bd . 'Window Regulator & Door Glass' ),
			'63'    => array( '', $bd . 'Seal & Weatherstrip' ),
			'65009' => array( 'Windshield glass', $bd . 'Windshield & Rear Glass' ),
			'65'    => array( '', $bd . 'Windshield & Rear Glass' ),
			'91036' => array( 'Door mirror', $bd . 'Door Mirror' ),
			'91121' => array( 'Front grille', $bd . 'Grille' ),
			'91'    => array( '', $bd . 'Molding & Exterior Trim' ),
			'93'    => array( 'Emblem', $bd . 'Emblem & Badge' ),
			'94'    => array( '', $bd . 'Door Trim Panel' ),
			'96'    => array( '', $bd . 'Rear Spoiler & Aero' ),
			// Interior.
			'64'    => array( '', $in . 'Front Seat' ),
			'66'    => array( '', $in . 'Instrument Panel (Dash)' ),
			'92'    => array( '', $in . 'Inner Trim (Pillars & Side Panels)' ),
			'95'    => array( '', $in . 'Floor Carpet & Mat' ),
			// Heater & A/C.
			'72311' => array( 'Climate control panel', $ac . 'Climate Control Panel' ),
			'72'    => array( '', $ac . 'Heater Unit & Blower' ),
			'73111' => array( 'A/C compressor', $ac . 'A/C Compressor' ),
			'73210' => array( 'A/C condenser', $ac . 'A/C Condenser' ),
			'73'    => array( '', $ac . 'A/C Pipe & Hose' ),
			// Body electrical.
			'81'    => array( 'Wiring harness', $be . 'Wiring Harness – Main' ),
			'82'    => array( '', $be . 'Fuse & Relay Box' ),
			'83'    => array( '', $be . 'Switch (Body)' ),
			'84001' => array( 'Headlamp assembly', $be . 'Head Lamp' ),
			'84201' => array( 'Rear combination lamp', $be . 'Rear Combination Lamp' ),
			'84501' => array( 'Fog lamp', $be . 'Fog Lamp' ),
			'84'    => array( '', 'Body Electrical' ),
			'85'    => array( 'Combination meter (gauge cluster)', $be . 'Combination Meter (Gauge Cluster)' ),
			'862'   => array( '', $be . 'Audio & Navigation' ),
			'86542' => array( 'Wiper blade', $mh . 'Wiper Blades' ),
			'865'   => array( '', $be . 'Wiper & Washer' ),
			'86'    => array( '', 'Body Electrical' ),
			'87'    => array( '', $be . 'Control Units (BIU, Keyless, etc.)' ),
			'88'    => array( '', $be . 'Key Kit & Key Lock' ),
			'98'    => array( '', $be . 'Air Bag (SRS)' ),
			// Hardware (9-digit numbers).
			'800'   => array( '', $mh . 'Bolts, Nuts & Washers' ),
			'801'   => array( '', $mh . 'Bolts, Nuts & Washers' ),
			'802'   => array( '', $mh . 'Bolts, Nuts & Washers' ),
			'803'   => array( '', $mh . 'Bolts, Nuts & Washers' ),
			'806'   => array( 'Gasket / O-ring', $mh . 'Gaskets & O-Rings' ),
			'90'    => array( '', $mh . 'Clips, Fasteners & Grommets' ),
			// Accessories / STI / fluids.
			'SOA868' => array( 'Engine oil / fluid', $mh . 'Oils, Fluids & Chemicals' ),
			'K0'    => array( '', $mh . 'Oils, Fluids & Chemicals' ),
			'ST'    => array( 'STI part', 'Accessories > STI Performance Parts' ),
			'SOA'   => array( 'Subaru accessory', 'Accessories' ),
			'E'     => array( 'Subaru accessory', 'Accessories' ),
			'J'     => array( 'Subaru accessory', 'Accessories' ),
			'H'     => array( 'Subaru accessory', 'Accessories' ),
		);
	}

	/**
	 * Identify a part number.
	 *
	 * Name priority: your own name for this number → official parts.subaru.com name →
	 * your imported CSV library → a similar number (same 5-digit base) → number-group rule.
	 * Category: your own → official catalog diagram → CSV → similar → rule (top level only).
	 *
	 * @return array<string,mixed>
	 */
	public static function identify( $number ) {
		global $wpdb;
		self::ready();
		$norm = sps_normalize_number( $number );
		$out  = array( 'found' => false, 'number' => $norm, 'name' => '', 'category_id' => 0, 'category_path' => '', 'box' => '', 'source' => '', 'oem' => null );
		if ( strlen( $norm ) < 3 ) {
			return $out;
		}
		$table = self::table();
		$cat_id = 0;
		$set_cat = static function ( $id ) use ( &$cat_id ) {
			if ( ! $cat_id && $id && term_exists( (int) $id, SPS_TAX_CAT ) ) {
				$cat_id = (int) $id;
			}
		};
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT name, category, source FROM {$table} WHERE pn_norm = %s", $norm ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$mine = null;
		$lib  = null;
		foreach ( (array) $rows as $r ) {
			if ( 'mine' === $r['source'] ) {
				$mine = $r;
			} else {
				$lib = $r;
			}
		}
		if ( $mine ) {
			$out['name']   = (string) $mine['name'];
			$out['source'] = 'mine';
			if ( '' !== $mine['category'] ) {
				$set_cat( SPS_Categories::term_from_path( $mine['category'], false ) );
			}
		}
		$oem = class_exists( 'SPS_Library' ) ? SPS_Library::details( $norm ) : null;
		if ( $oem ) {
			$out['oem'] = $oem;
			if ( '' === $out['name'] ) {
				$out['name']   = $oem['name'];
				$out['source'] = 'oem';
			}
			$set_cat( $oem['term_id'] );
		}
		if ( $lib ) {
			if ( '' === $out['name'] ) {
				$out['name']   = (string) $lib['name'];
				$out['source'] = 'library';
			}
			if ( '' !== $lib['category'] ) {
				$set_cat( SPS_Categories::term_from_path( $lib['category'], false ) );
			}
		}
		// Same 5-digit base number (e.g. 22433AA750 → 22433AA790).
		if ( ( '' === $out['name'] || ! $cat_id ) && preg_match( '/^\d{5}/', $norm, $m ) ) {
			$like = $wpdb->esc_like( $m[0] ) . '%';
			if ( class_exists( 'SPS_Library' ) && SPS_Library::loaded() ) {
				$sim = $wpdb->get_row( $wpdb->prepare( 'SELECT name, term_id, COUNT(*) AS n FROM ' . SPS_Library::t( 'parts' ) . " WHERE pn_norm LIKE %s GROUP BY name, term_id ORDER BY (top = 'Maintenance') ASC, n DESC LIMIT 1", $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				if ( $sim ) {
					if ( '' === $out['name'] ) {
						$out['name']   = (string) $sim['name'];
						$out['source'] = 'similar';
					}
					$set_cat( (int) $sim['term_id'] );
				}
			}
			$similar = $wpdb->get_row( $wpdb->prepare( "SELECT name, category, COUNT(*) AS n FROM {$table} WHERE pn_norm LIKE %s AND name <> '' GROUP BY name, category ORDER BY n DESC LIMIT 1", $like ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $similar ) {
				if ( '' === $out['name'] ) {
					$out['name']   = (string) $similar['name'];
					$out['source'] = 'similar';
				}
				if ( '' !== $similar['category'] ) {
					$set_cat( SPS_Categories::term_from_path( $similar['category'], false ) );
				}
			}
		}
		$rule = self::match_rule( $norm );
		if ( $rule ) {
			if ( '' === $out['name'] && '' !== $rule[0] ) {
				$out['name']   = $rule[0];
				$out['source'] = 'rule';
			}
			if ( ! $cat_id ) {
				$set_cat( SPS_Categories::term_from_path( $rule[1], false ) );
				if ( ! $cat_id && class_exists( 'SPS_Library' ) ) {
					$top = trim( explode( '>', $rule[1] )[0] );
					if ( isset( SPS_Library::LEGACY_MAP[ $top ] ) ) {
						$t = term_exists( SPS_Library::LEGACY_MAP[ $top ], SPS_TAX_CAT, 0 );
						$set_cat( $t ? (int) $t['term_id'] : 0 );
					}
				}
				if ( $cat_id && '' === $out['source'] ) {
					$out['source'] = 'rule';
				}
			}
		}
		if ( $cat_id ) {
			$term                 = get_term( $cat_id, SPS_TAX_CAT );
			$box                  = sps_term_box( $term );
			$out['category_id']   = $cat_id;
			$out['box']           = $box['code'];
			$out['category_path'] = html_entity_decode( sps_category_path( $term ), ENT_QUOTES );
		}
		$out['found'] = '' !== $out['name'] || $cat_id > 0;
		return $out;
	}

	private static function match_rule( $norm ) {
		$rules = self::rules();
		for ( $len = min( 6, strlen( $norm ) ); $len >= 1; $len-- ) {
			$prefix = substr( $norm, 0, $len );
			if ( isset( $rules[ $prefix ] ) ) {
				return $rules[ $prefix ];
			}
		}
		return null;
	}

	/**
	 * Save one entry. "mine" (names you typed) always wins; "library" never overwrites "mine".
	 */
	public static function upsert( $number, $name, $category = '', $source = 'library' ) {
		global $wpdb;
		self::ready();
		$norm = sps_normalize_number( $number );
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( strlen( $norm ) < 4 || '' === $name ) {
			return false;
		}
		$table    = self::table();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, source, name, category FROM {$table} WHERE pn_norm = %s", $norm ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data     = array(
			'pn_norm'     => $norm,
			'part_number' => substr( strtoupper( trim( (string) $number ) ), 0, 60 ),
			'name'        => substr( $name, 0, 255 ),
			'category'    => substr( (string) $category, 0, 255 ),
			'source'      => $source,
			'updated'     => current_time( 'mysql' ),
		);
		if ( ! $existing ) {
			return (bool) $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( 'library' === $source && 'mine' === $existing['source'] ) {
			return false;
		}
		if ( $existing['name'] === $data['name'] && $existing['category'] === $data['category'] && $existing['source'] === $source ) {
			return false;
		}
		if ( '' === $data['category'] ) {
			$data['category'] = $existing['category'];
		}
		return (bool) $wpdb->update( $table, $data, array( 'id' => $existing['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Remember the name/category you gave a part, so the same number fills itself in next time.
	 */
	public static function remember( $post_id ) {
		$pn   = (string) sps_meta( $post_id, 'part_number' );
		$name = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES );
		if ( '' === $pn || '' === $name || sps_normalize_number( $name ) === sps_normalize_number( $pn ) ) {
			return;
		}
		$cat = sps_part_category( $post_id );
		$path = $cat && 'To Sort (Unidentified)' !== html_entity_decode( $cat->name ) ? html_entity_decode( sps_category_path( $cat, ' > ' ), ENT_QUOTES ) : '';
		self::upsert( $pn, $name, $path, 'mine' );
	}

	/**
	 * @return array{library:int,mine:int}
	 */
	public static function counts() {
		global $wpdb;
		self::ready();
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT source, COUNT(*) AS n FROM {$table} GROUP BY source", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array( 'library' => 0, 'mine' => 0 );
		foreach ( (array) $rows as $r ) {
			$out[ 'mine' === $r['source'] ? 'mine' : 'library' ] += (int) $r['n'];
		}
		return $out;
	}

	/**
	 * Import a CSV / TSV / semicolon file. Header row optional; recognised columns:
	 * part_number|number|pn|part no, name|description|title, category|group|box.
	 *
	 * @return array{added:int,skipped:int}
	 */
	public static function import_file( $path ) {
		$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $fh ) {
			return array( 'added' => 0, 'skipped' => 0 );
		}
		$first = (string) fgets( $fh );
		rewind( $fh );
		$counts = array( "\t" => substr_count( $first, "\t" ), ';' => substr_count( $first, ';' ), ',' => substr_count( $first, ',' ) );
		arsort( $counts );
		$delim = key( $counts );
		$cols  = array( 'pn' => 0, 'name' => 1, 'cat' => -1 );
		$head  = fgetcsv( $fh, 0, $delim );
		$is_header = false;
		if ( $head ) {
			foreach ( $head as $i => $h ) {
				$h = strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) );
				if ( preg_match( '/^(part[ _-]?(number|no|#)|number|pn|oem|sku)$/', $h ) ) {
					$cols['pn'] = $i;
					$is_header  = true;
				} elseif ( preg_match( '/^(name|part[ _-]?name|description|title)$/', $h ) ) {
					$cols['name'] = $i;
					$is_header    = true;
				} elseif ( preg_match( '/^(category|group|box|section)$/', $h ) ) {
					$cols['cat'] = $i;
					$is_header   = true;
				}
			}
			if ( ! $is_header ) {
				rewind( $fh );
			}
		}
		$added   = 0;
		$skipped = 0;
		while ( ( $row = fgetcsv( $fh, 0, $delim ) ) !== false ) {
			$pn   = isset( $row[ $cols['pn'] ] ) ? trim( (string) $row[ $cols['pn'] ] ) : '';
			$name = isset( $row[ $cols['name'] ] ) ? trim( (string) $row[ $cols['name'] ] ) : '';
			$cat  = $cols['cat'] >= 0 && isset( $row[ $cols['cat'] ] ) ? trim( (string) $row[ $cols['cat'] ] ) : '';
			if ( self::upsert( $pn, $name, $cat, 'library' ) ) {
				$added++;
			} else {
				$skipped++;
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array( 'added' => $added, 'skipped' => $skipped );
	}

	/**
	 * Library panel on the Import / Export page.
	 */
	public static function panel() {
		$c   = self::counts();
		$msg = isset( $_GET['sps_lib'] ) ? sanitize_text_field( wp_unslash( $_GET['sps_lib'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="sps-panel" style="margin-top:16px">
			<h2><?php esc_html_e( 'Part name library', 'subaru-parts-inventory' ); ?></h2>
			<?php if ( $msg ) : ?><div class="notice notice-success inline"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
			<p><?php esc_html_e( 'When you type or scan a part number, the name and box are filled in from this library. It contains numbers you import here, plus every name you have typed on a part (remembered automatically). Unknown numbers fall back to built-in Subaru number-group rules for the box.', 'subaru-parts-inventory' ); ?></p>
			<p><strong><?php echo esc_html( number_format_i18n( $c['library'] ) ); ?></strong> <?php esc_html_e( 'imported', 'subaru-parts-inventory' ); ?> · <strong><?php echo esc_html( number_format_i18n( $c['mine'] ) ); ?></strong> <?php esc_html_e( 'remembered from your parts', 'subaru-parts-inventory' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sps_library_import" />
				<?php wp_nonce_field( 'sps_library_import' ); ?>
				<p><input type="file" name="library" accept=".csv,.txt,.tsv,text/csv,text/plain" required />
				<button class="button button-primary"><?php esc_html_e( 'Import library', 'subaru-parts-inventory' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'CSV (comma, semicolon or tab separated; save Excel files as CSV). Columns: part_number, name and optionally category (e.g. "Engine Auxiliaries > Turbocharger"). A header row is optional.', 'subaru-parts-inventory' ); ?></p>
			</form>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sps_library_export' ), 'sps_library_export' ) ); ?>"><?php esc_html_e( 'Download library CSV', 'subaru-parts-inventory' ); ?></a>
				<a class="button" onclick="return confirm('<?php echo esc_js( __( 'Remove all imported library entries? Names remembered from your own parts are kept.', 'subaru-parts-inventory' ) ); ?>')" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sps_library_clear' ), 'sps_library_clear' ) ); ?>"><?php esc_html_e( 'Clear imported entries', 'subaru-parts-inventory' ); ?></a>
			</p>
		</div>
		<?php
	}

	private static function back( $msg ) {
		wp_safe_redirect( add_query_arg( 'sps_lib', rawurlencode( $msg ), admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-import-export' ) ) );
		exit;
	}

	public static function handle_import() {
		check_admin_referer( 'sps_library_import' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		if ( empty( $_FILES['library']['tmp_name'] ) || ! is_uploaded_file( $_FILES['library']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			self::back( __( 'No file uploaded.', 'subaru-parts-inventory' ) );
		}
		$r = self::import_file( $_FILES['library']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		/* translators: 1: added, 2: skipped */
		self::back( sprintf( __( 'Library import: %1$d added or updated, %2$d skipped (empty, duplicate or your own names kept).', 'subaru-parts-inventory' ), $r['added'], $r['skipped'] ) );
	}

	public static function handle_export() {
		global $wpdb;
		check_admin_referer( 'sps_library_export' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		self::ready();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="subaru-part-library.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'part_number', 'name', 'category', 'source' ) );
		$table = self::table();
		foreach ( (array) $wpdb->get_results( "SELECT part_number, name, category, source FROM {$table} ORDER BY pn_norm", ARRAY_N ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function handle_clear() {
		global $wpdb;
		check_admin_referer( 'sps_library_clear' );
		if ( ! current_user_can( SPS_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'subaru-parts-inventory' ) );
		}
		self::ready();
		$wpdb->delete( self::table(), array( 'source' => 'library' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::back( __( 'Imported library entries removed.', 'subaru-parts-inventory' ) );
	}
}
