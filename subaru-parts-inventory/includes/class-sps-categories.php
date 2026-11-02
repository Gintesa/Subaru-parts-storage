<?php
/**
 * Subaru catalog category tree + storage box fields on categories.
 *
 * The default tree follows the Subaru OEM parts catalog grouping used on
 * parts.subaru.com / the Subaru EPC for the 2017 WRX (VA, FA20DIT, 6MT).
 * It can be freely edited under Subaru Parts → Categories & Boxes.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Categories {

	public static function init() {
		add_action( SPS_TAX_CAT . '_add_form_fields', array( __CLASS__, 'add_fields' ) );
		add_action( SPS_TAX_CAT . '_edit_form_fields', array( __CLASS__, 'edit_fields' ) );
		add_action( 'created_' . SPS_TAX_CAT, array( __CLASS__, 'save_fields' ) );
		add_action( 'edited_' . SPS_TAX_CAT, array( __CLASS__, 'save_fields' ) );
		add_filter( 'manage_edit-' . SPS_TAX_CAT . '_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'manage_' . SPS_TAX_CAT . '_custom_column', array( __CLASS__, 'column_content' ), 10, 3 );
		add_filter( SPS_TAX_CAT . '_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'set_object_terms', array( __CLASS__, 'flush_counts' ), 10, 4 );
		add_action( 'edited_term', array( __CLASS__, 'flush_term' ), 10, 3 );
	}

	/**
	 * Default tree: top-level group => [ box code, children[] ].
	 *
	 * @return array<string,array{0:string,1:string[]}>
	 */
	public static function default_tree() {
		return array(
			'Engine Main'                       => array(
				'EM',
				array(
					'Engine Assembly / Short Block',
					'Cylinder Block',
					'Cylinder Head',
					'Crankshaft & Piston',
					'Camshaft & Timing Chain',
					'Valve Mechanism',
					'Timing Chain Cover',
					'Oil Pan',
					'Oil Pump & Filter',
					'Engine Gasket & Seal Kit',
				),
			),
			'Engine Auxiliaries'                => array(
				'EA',
				array(
					'Air Cleaner & Element',
					'Air Duct',
					'Intake Manifold',
					'Throttle Chamber',
					'Fuel Injector & Fuel Rail',
					'High Pressure Fuel Pump',
					'Turbocharger',
					'Intercooler',
					'Exhaust Manifold',
					'Exhaust (Pipes & Muffler)',
					'Emission Control (PCV)',
					'Evaporative Emission (Canister)',
					'Engine Mounting',
					'Water Pump',
					'Water Pipe & Thermostat',
					'Radiator & Cooling Fan',
					'Coolant Reservoir & Hoses',
					'Oil Cooler',
					'V-Belt, Pulley & Tensioner',
					'Alternator',
					'Starter',
					'Spark Plug & Ignition Coil',
					'Engine Sensors',
					'Engine Control Module (ECM)',
					'Engine Wiring Harness',
				),
			),
			'Manual Transmission'               => array(
				'MT',
				array(
					'Transmission Assembly',
					'Transmission Case',
					'Main Shaft',
					'Drive Pinion Shaft',
					'Reverse Idler Gear',
					'Shifter Fork & Shifter Rail',
					'Front Differential (Transmission)',
					'Center Differential & Transfer',
					'Extension & Speed Sensor',
					'Clutch (Disc, Cover, Release Bearing)',
					'Flywheel',
					'Clutch Control (Master / Operating Cylinder)',
					'Control Device (Shifter & Linkage)',
					'Transmission Mounting',
				),
			),
			'Differential & Propeller Shaft'    => array(
				'DP',
				array(
					'Propeller Shaft',
					'Rear Differential',
					'Rear Differential Mounting',
					'Front Drive Shaft (CV Axle)',
					'Rear Drive Shaft (CV Axle)',
				),
			),
			'Suspension, Axle & Brake'          => array(
				'SB',
				array(
					'Front Suspension (Arms & Crossmember)',
					'Rear Suspension (Arms & Subframe)',
					'Front Shock Absorber & Spring',
					'Rear Shock Absorber & Spring',
					'Stabilizer (Sway Bar & Links)',
					'Front Axle & Hub',
					'Rear Axle & Hub',
					'Front Brake',
					'Rear Brake',
					'Brake Master Cylinder & Booster',
					'Brake Pipe & Hose',
					'ABS / VDC Unit',
					'Parking Brake',
					'Pedal System',
					'Power Steering (Electric)',
					'Steering Gearbox & Tie Rods',
					'Steering Column & Wheel',
					'Disc Wheel',
					'Tire & TPMS',
				),
			),
			'Body Electrical'                   => array(
				'BE',
				array(
					'Wiring Harness – Main',
					'Wiring Harness – Front',
					'Wiring Harness – Rear & Door',
					'Head Lamp',
					'Fog Lamp',
					'Rear Combination Lamp',
					'Lamp – Interior & License',
					'Combination Meter (Gauge Cluster)',
					'Multi Function Display',
					'Audio & Navigation',
					'Antenna & Camera',
					'Speaker',
					'Key Kit & Key Lock',
					'Switch (Body)',
					'Wiper & Washer',
					'Horn',
					'Battery & Cables',
					'Fuse & Relay Box',
					'Control Units (BIU, Keyless, etc.)',
					'Air Bag (SRS)',
				),
			),
			'Body'                              => array(
				'BD',
				array(
					'Body Panel',
					'Front Hood',
					'Front Fender',
					'Front Bumper',
					'Rear Bumper',
					'Grille',
					'Door Parts – Front',
					'Door Parts – Rear',
					'Door Trim Panel',
					'Door Mirror',
					'Window Regulator & Door Glass',
					'Windshield & Rear Glass',
					'Trunk Lid & Lock',
					'Rear Spoiler & Aero',
					'Molding & Exterior Trim',
					'Mud Guard & Under Cover',
					'Emblem & Badge',
					'Sunroof',
					'Fuel Tank & Fuel Pump',
					'Fuel Filler & Lid',
					'Seal & Weatherstrip',
				),
			),
			'Interior & Seats'                  => array(
				'IN',
				array(
					'Instrument Panel (Dash)',
					'Center Console',
					'Front Seat',
					'Rear Seat',
					'Seat Belt',
					'Inner Trim (Pillars & Side Panels)',
					'Roof Trim & Headliner',
					'Floor Carpet & Mat',
					'Trunk Trim',
					'Sun Visor & Inner Mirror',
					'Shift & Brake Boots / Knobs',
				),
			),
			'Heater & Air Conditioner'          => array(
				'AC',
				array(
					'Heater Unit & Blower',
					'Heater Duct & Vents',
					'A/C Compressor',
					'A/C Condenser',
					'Cooling Unit (Evaporator)',
					'A/C Pipe & Hose',
					'Climate Control Panel',
				),
			),
			'Accessories'                       => array(
				'AX',
				array(
					'Exterior Accessories',
					'Interior Accessories',
					'STI Performance Parts',
					'Cargo & Carrier',
					'Electronics Accessories',
				),
			),
			'Maintenance, Fluids & Hardware'    => array(
				'MH',
				array(
					'Filters',
					'Oils, Fluids & Chemicals',
					'Wiper Blades',
					'Bulbs',
					'Bolts, Nuts & Washers',
					'Clips, Fasteners & Grommets',
					'Gaskets & O-Rings',
					'Tools & Kits',
				),
			),
			'To Sort (Unidentified)'            => array( 'TS', array() ),
		);
	}

	/**
	 * Create any default categories that do not exist yet. Never overwrites user edits.
	 */
	public static function seed() {
		if ( ! taxonomy_exists( SPS_TAX_CAT ) ) {
			SPS_Post_Types::register();
		}
		// With the bundled parts.subaru.com catalog, the tree is built from it (SPS_Library::import).
		if ( class_exists( 'SPS_Library' ) && file_exists( SPS_Library::bundled_path() ) ) {
			$ts = self::ensure_term( 'To Sort (Unidentified)', 0 );
			if ( $ts && ! get_term_meta( $ts, 'sps_seeded', true ) ) {
				update_term_meta( $ts, 'sps_box_code', 'BOX-TS' );
				update_term_meta( $ts, 'sps_order', 999 );
				update_term_meta( $ts, 'sps_seeded', 1 );
			}
			update_term_meta( $ts, 'sps_lib', 1 );
			return;
		}
		$order = 0;
		foreach ( self::default_tree() as $group => $info ) {
			list( $code, $children ) = $info;
			$order += 10;
			$parent_id = self::ensure_term( $group, 0 );
			if ( ! $parent_id ) {
				continue;
			}
			if ( '' === (string) get_term_meta( $parent_id, 'sps_box_code', true ) && ! get_term_meta( $parent_id, 'sps_seeded', true ) ) {
				update_term_meta( $parent_id, 'sps_box_code', 'BOX-' . $code );
				update_term_meta( $parent_id, 'sps_order', $order );
			}
			update_term_meta( $parent_id, 'sps_seeded', 1 );
			$child_order = 0;
			foreach ( $children as $child ) {
				$child_order += 10;
				$child_id     = self::ensure_term( $child, $parent_id );
				if ( $child_id && ! get_term_meta( $child_id, 'sps_seeded', true ) ) {
					update_term_meta( $child_id, 'sps_order', $child_order );
					update_term_meta( $child_id, 'sps_seeded', 1 );
				}
			}
		}
	}

	private static function ensure_term( $name, $parent ) {
		$existing = term_exists( $name, SPS_TAX_CAT, $parent );
		if ( $existing ) {
			return (int) $existing['term_id'];
		}
		$res = wp_insert_term( $name, SPS_TAX_CAT, array( 'parent' => $parent ) );
		if ( is_wp_error( $res ) ) {
			if ( 'term_exists' === $res->get_error_code() ) {
				return (int) $res->get_error_data();
			}
			return 0;
		}
		return (int) $res['term_id'];
	}

	/**
	 * Resolve "Engine Auxiliaries > Turbocharger" to a term ID, creating missing levels.
	 */
	public static function term_from_path( $path, $create = true ) {
		$parts  = array_filter( array_map( 'trim', preg_split( '/\s*(?:>|›)\s*/u', (string) $path ) ), 'strlen' );
		$parent = 0;
		foreach ( $parts as $name ) {
			$name = wp_specialchars_decode( $name );
			$existing = term_exists( $name, SPS_TAX_CAT, $parent );
			if ( $existing ) {
				$parent = (int) $existing['term_id'];
				continue;
			}
			if ( ! $create ) {
				return 0;
			}
			$parent = self::ensure_term( $name, $parent );
			if ( ! $parent ) {
				return 0;
			}
		}
		return $parent;
	}

	public static function add_fields() {
		?>
		<div class="form-field">
			<label for="sps_box_code"><?php esc_html_e( 'Storage box code', 'subaru-parts-inventory' ); ?></label>
			<input type="text" name="sps_box_code" id="sps_box_code" value="" placeholder="BOX-EA-02" />
			<p><?php esc_html_e( 'Code printed on the physical box. Leave empty to use the parent category\'s box.', 'subaru-parts-inventory' ); ?></p>
		</div>
		<div class="form-field">
			<label for="sps_box_location"><?php esc_html_e( 'Box location', 'subaru-parts-inventory' ); ?></label>
			<input type="text" name="sps_box_location" id="sps_box_location" value="" placeholder="<?php esc_attr_e( 'Garage, shelf 2, left', 'subaru-parts-inventory' ); ?>" />
		</div>
		<div class="form-field">
			<label for="sps_order"><?php esc_html_e( 'Sort order', 'subaru-parts-inventory' ); ?></label>
			<input type="number" name="sps_order" id="sps_order" value="" />
		</div>
		<?php
		wp_nonce_field( 'sps_term_meta', 'sps_term_nonce' );
	}

	public static function edit_fields( $term ) {
		$inherited = sps_term_box( $term );
		?>
		<tr class="form-field">
			<th scope="row"><label for="sps_box_code"><?php esc_html_e( 'Storage box code', 'subaru-parts-inventory' ); ?></label></th>
			<td>
				<input type="text" name="sps_box_code" id="sps_box_code" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'sps_box_code', true ) ); ?>" placeholder="<?php echo esc_attr( $inherited['code'] ); ?>" />
				<p class="description">
					<?php
					if ( $inherited['code'] && (int) $inherited['term_id'] !== (int) $term->term_id ) {
						/* translators: 1: box code, 2: category name */
						printf( esc_html__( 'Currently stored in %1$s (inherited from %2$s). Set a code to give this category its own box.', 'subaru-parts-inventory' ), '<strong>' . esc_html( $inherited['code'] ) . '</strong>', esc_html( $inherited['term_name'] ) );
					} else {
						esc_html_e( 'Code printed on the physical box. Sub-categories without their own code are stored in this box.', 'subaru-parts-inventory' );
					}
					?>
				</p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="sps_box_location"><?php esc_html_e( 'Box location', 'subaru-parts-inventory' ); ?></label></th>
			<td><input type="text" name="sps_box_location" id="sps_box_location" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'sps_box_location', true ) ); ?>" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="sps_order"><?php esc_html_e( 'Sort order', 'subaru-parts-inventory' ); ?></label></th>
			<td><input type="number" name="sps_order" id="sps_order" value="<?php echo esc_attr( get_term_meta( $term->term_id, 'sps_order', true ) ); ?>" />
			<?php wp_nonce_field( 'sps_term_meta', 'sps_term_nonce' ); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Box label', 'subaru-parts-inventory' ); ?></th>
			<td><a class="button" target="_blank" href="<?php echo esc_url( SPS_Labels::print_url( array( $term->term_id ), 'box' ) ); ?>"><?php esc_html_e( 'Print box label', 'subaru-parts-inventory' ); ?></a></td>
		</tr>
		<?php
	}

	public static function save_fields( $term_id ) {
		if ( ! isset( $_POST['sps_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sps_term_nonce'] ), 'sps_term_meta' ) || ! current_user_can( SPS_CAP ) ) {
			return;
		}
		foreach ( array( 'sps_box_code', 'sps_box_location', 'sps_order' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				if ( 'sps_box_code' === $key ) {
					$value = strtoupper( $value );
				}
				update_term_meta( $term_id, $key, $value );
			}
		}
		delete_transient( 'sps_category_counts' );
	}

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'description' === $key ) {
				continue;
			}
			$new[ $key ] = $label;
			if ( 'name' === $key ) {
				$new['sps_box'] = __( 'Box', 'subaru-parts-inventory' );
			}
		}
		return $new;
	}

	public static function column_content( $content, $column, $term_id ) {
		if ( 'sps_box' !== $column ) {
			return $content;
		}
		$own = (string) get_term_meta( $term_id, 'sps_box_code', true );
		$box = sps_term_box( get_term( $term_id, SPS_TAX_CAT ) );
		if ( '' !== $own ) {
			$loc = (string) get_term_meta( $term_id, 'sps_box_location', true );
			return '<strong class="sps-box-code">' . esc_html( $own ) . '</strong>' . ( $loc ? '<br><small>' . esc_html( $loc ) . '</small>' : '' );
		}
		return $box['code'] ? '<span class="sps-box-inherited" title="' . esc_attr__( 'Inherited from parent', 'subaru-parts-inventory' ) . '">↳ ' . esc_html( $box['code'] ) . '</span>' : '—';
	}

	public static function row_actions( $actions, $term ) {
		$actions['sps_label'] = '<a target="_blank" href="' . esc_url( SPS_Labels::print_url( array( $term->term_id ), 'box' ) ) . '">' . esc_html__( 'Box label', 'subaru-parts-inventory' ) . '</a>';
		$actions['sps_parts'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . SPS_PT . '&' . SPS_TAX_CAT . '=' . $term->slug ) ) . '">' . esc_html__( 'Parts', 'subaru-parts-inventory' ) . '</a>';
		return $actions;
	}

	public static function flush_counts( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( SPS_TAX_CAT === $taxonomy ) {
			delete_transient( 'sps_category_counts' );
		}
	}

	public static function flush_term( $term_id, $tt_id, $taxonomy ) {
		if ( SPS_TAX_CAT === $taxonomy ) {
			delete_transient( 'sps_category_counts' );
		}
	}
}
