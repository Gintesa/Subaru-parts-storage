<?php
/**
 * Shared helpers: vocabularies, settings, part data access.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

const SPS_PT       = 'sps_part';
const SPS_TAX_CAT  = 'sps_category';
const SPS_TAX_FIT  = 'sps_fitment';
const SPS_CAP      = 'manage_sps_parts';
const SPS_EDIT_CAP = 'edit_sps_parts';

/**
 * Condition grades. "group" is new|used|core and drives filters and stats.
 *
 * @return array<string,array{label:string,short:string,group:string,color:string,desc:string}>
 */
function sps_conditions() {
	return array(
		'new'      => array(
			'label' => __( 'New – sealed / OEM packaging', 'subaru-parts-inventory' ),
			'short' => 'NEW',
			'group' => 'new',
			'color' => '#18794e',
			'desc'  => __( 'Never fitted, in original packaging.', 'subaru-parts-inventory' ),
		),
		'new_open' => array(
			'label' => __( 'New – open box / never fitted', 'subaru-parts-inventory' ),
			'short' => 'NEW-OB',
			'group' => 'new',
			'color' => '#2f9e63',
			'desc'  => __( 'Unused, packaging opened or missing.', 'subaru-parts-inventory' ),
		),
		'used_a'   => array(
			'label' => __( 'Used – A: Excellent', 'subaru-parts-inventory' ),
			'short' => 'USED-A',
			'group' => 'used',
			'color' => '#1f5fa8',
			'desc'  => __( 'Like new, minimal signs of use, fully functional.', 'subaru-parts-inventory' ),
		),
		'used_b'   => array(
			'label' => __( 'Used – B: Good', 'subaru-parts-inventory' ),
			'short' => 'USED-B',
			'group' => 'used',
			'color' => '#3b7dc4',
			'desc'  => __( 'Normal wear, cosmetic marks, fully functional.', 'subaru-parts-inventory' ),
		),
		'used_c'   => array(
			'label' => __( 'Used – C: Fair', 'subaru-parts-inventory' ),
			'short' => 'USED-C',
			'group' => 'used',
			'color' => '#b7791f',
			'desc'  => __( 'Heavy wear or visible damage, still usable.', 'subaru-parts-inventory' ),
		),
		'used_d'   => array(
			'label' => __( 'Used – D: Poor / needs repair', 'subaru-parts-inventory' ),
			'short' => 'USED-D',
			'group' => 'used',
			'color' => '#c05621',
			'desc'  => __( 'Needs repair or refurbishment before use.', 'subaru-parts-inventory' ),
		),
		'core'     => array(
			'label' => __( 'Core / for parts only', 'subaru-parts-inventory' ),
			'short' => 'CORE',
			'group' => 'core',
			'color' => '#9b2c2c',
			'desc'  => __( 'Not usable as-is; for rebuild, core exchange or donor parts.', 'subaru-parts-inventory' ),
		),
	);
}

/**
 * Condition checklist flags for used parts.
 *
 * @return array<string,string>
 */
function sps_condition_flags() {
	return array(
		'tested_ok'     => __( 'Tested – working', 'subaru-parts-inventory' ),
		'untested'      => __( 'Untested', 'subaru-parts-inventory' ),
		'cleaned'       => __( 'Cleaned', 'subaru-parts-inventory' ),
		'refurbished'   => __( 'Refurbished / rebuilt', 'subaru-parts-inventory' ),
		'scratches'     => __( 'Scratches / scuffs', 'subaru-parts-inventory' ),
		'dents'         => __( 'Dents', 'subaru-parts-inventory' ),
		'cracks'        => __( 'Cracks', 'subaru-parts-inventory' ),
		'corrosion'     => __( 'Corrosion / rust', 'subaru-parts-inventory' ),
		'broken_tabs'   => __( 'Broken tabs / clips', 'subaru-parts-inventory' ),
		'missing_parts' => __( 'Missing hardware / components', 'subaru-parts-inventory' ),
		'worn'          => __( 'Worn (bushings, seals, friction)', 'subaru-parts-inventory' ),
		'leaking'       => __( 'Leaking / weeping', 'subaru-parts-inventory' ),
		'faded'         => __( 'Faded / UV damage', 'subaru-parts-inventory' ),
		'paint'         => __( 'Paint damage / needs paint', 'subaru-parts-inventory' ),
		'cut_wiring'    => __( 'Cut / damaged wiring or connector', 'subaru-parts-inventory' ),
		'modified'      => __( 'Modified / non-stock', 'subaru-parts-inventory' ),
	);
}

/**
 * Stock statuses. "public" statuses can appear in the public catalogue.
 *
 * @return array<string,array{label:string,public:bool,in_stock:bool}>
 */
function sps_statuses() {
	return array(
		'in_stock'  => array( 'label' => __( 'In stock', 'subaru-parts-inventory' ), 'public' => true, 'in_stock' => true ),
		'listed'    => array( 'label' => __( 'Listed for sale', 'subaru-parts-inventory' ), 'public' => true, 'in_stock' => true ),
		'reserved'  => array( 'label' => __( 'Reserved', 'subaru-parts-inventory' ), 'public' => true, 'in_stock' => true ),
		'on_car'    => array( 'label' => __( 'Still on donor car', 'subaru-parts-inventory' ), 'public' => true, 'in_stock' => true ),
		'installed' => array( 'label' => __( 'Installed on my car', 'subaru-parts-inventory' ), 'public' => false, 'in_stock' => false ),
		'sold'      => array( 'label' => __( 'Sold', 'subaru-parts-inventory' ), 'public' => false, 'in_stock' => false ),
		'scrapped'  => array( 'label' => __( 'Scrapped', 'subaru-parts-inventory' ), 'public' => false, 'in_stock' => false ),
	);
}

/**
 * Plugin settings with defaults.
 *
 * @return array<string,mixed>
 */
function sps_default_settings() {
	return array(
		'sku_prefix'        => 'SP-',
		'sku_digits'        => 5,
		'default_fitment'   => '2017 Subaru WRX 2.0L 6MT Limited',
		'distance_unit'     => 'km',
		'currency'          => '€',
		'label_template'    => 'brother_62x29',
		'label_fields'      => array( 'name', 'qr', 'barcode', 'condition', 'box', 'sku' ),
		'public_default'    => 1,
		'show_prices'       => 0,
		'show_quantity'     => 1,
		'show_location'     => 0,
		'per_page'          => 24,
		'catalog_page_id'   => 0,
		'delete_on_uninstall' => 0,
	);
}

/**
 * @param string|null $key   Setting key, or null for all.
 * @param bool        $reset Drop the cached copy first.
 * @return mixed
 */
function sps_setting( $key = null, $reset = false ) {
	static $cache = null;
	if ( null === $cache || $reset ) {
		$saved = get_option( 'sps_settings', array() );
		$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), sps_default_settings() );
	}
	if ( null === $key ) {
		return $cache;
	}
	return isset( $cache[ $key ] ) ? $cache[ $key ] : null;
}
add_action(
	'update_option_sps_settings',
	static function () {
		sps_setting( null, true );
	}
);

/**
 * Meta field keys (without the _sps_ prefix) and their sanitizers.
 *
 * @return array<string,string>
 */
function sps_meta_fields() {
	return array(
		'part_number'    => 'part_number',
		'alt_numbers'    => 'text',
		'epc_ref'        => 'text',
		'condition'      => 'condition',
		'condition_flags'=> 'flags',
		'condition_notes'=> 'textarea',
		'mileage'        => 'int',
		'removed_from'   => 'text',
		'quantity'       => 'int',
		'status'         => 'status',
		'box_override'   => 'text',
		'bin'            => 'text',
		'purchase_price' => 'money',
		'purchase_date'  => 'date',
		'source'         => 'text',
		'value'          => 'money',
		'sale_price'     => 'money',
		'weight'         => 'decimal',
		'public'         => 'bool',
		'private_notes'  => 'textarea',
	);
}

/**
 * Sanitize a meta value according to its type.
 *
 * @param string $type  Sanitizer type.
 * @param mixed  $value Raw value.
 * @return mixed
 */
function sps_sanitize_field( $type, $value ) {
	switch ( $type ) {
		case 'part_number':
			return strtoupper( trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $value ) ) ) );
		case 'textarea':
			return sanitize_textarea_field( (string) $value );
		case 'int':
			return '' === $value || null === $value ? '' : max( 0, (int) $value );
		case 'money':
		case 'decimal':
			$value = str_replace( array( ',', ' ' ), array( '.', '' ), (string) $value );
			return '' === $value ? '' : round( (float) $value, 2 );
		case 'date':
			$value = sanitize_text_field( (string) $value );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
		case 'bool':
			return empty( $value ) || 'no' === $value || '0' === $value ? 0 : 1;
		case 'condition':
			return array_key_exists( (string) $value, sps_conditions() ) ? (string) $value : 'used_b';
		case 'status':
			return array_key_exists( (string) $value, sps_statuses() ) ? (string) $value : 'in_stock';
		case 'flags':
			if ( is_string( $value ) ) {
				$value = preg_split( '/[|,]/', $value );
			}
			$value = array_map( 'sanitize_key', (array) $value );
			return array_values( array_intersect( $value, array_keys( sps_condition_flags() ) ) );
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Normalise a part number for matching: "16102-AA060" → "16102AA060".
 */
function sps_normalize_number( $number ) {
	return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $number ) );
}

/**
 * Read a part meta value.
 */
function sps_meta( $post_id, $key, $default = '' ) {
	$value = get_post_meta( $post_id, '_sps_' . $key, true );
	return ( '' === $value || null === $value || false === $value ) ? $default : $value;
}

/**
 * Persist all meta for a part and update derived fields.
 *
 * @param int   $post_id Part ID.
 * @param array $data    key => raw value (only keys present are written).
 */
function sps_save_part_meta( $post_id, array $data ) {
	foreach ( sps_meta_fields() as $key => $type ) {
		if ( ! array_key_exists( $key, $data ) ) {
			continue;
		}
		update_post_meta( $post_id, '_sps_' . $key, sps_sanitize_field( $type, $data[ $key ] ) );
	}
	sps_refresh_derived_meta( $post_id );
}

/**
 * Derived meta: normalised part number, SKU, visibility flag.
 */
function sps_refresh_derived_meta( $post_id ) {
	update_post_meta( $post_id, '_sps_part_number_norm', sps_normalize_number( sps_meta( $post_id, 'part_number' ) ) );
	if ( '' === sps_meta( $post_id, 'sku' ) ) {
		update_post_meta( $post_id, '_sps_sku', sps_generate_sku() );
	}
	if ( '' === get_post_meta( $post_id, '_sps_quantity', true ) ) {
		update_post_meta( $post_id, '_sps_quantity', 1 );
	}
	if ( '' === get_post_meta( $post_id, '_sps_status', true ) ) {
		update_post_meta( $post_id, '_sps_status', 'in_stock' );
	}
	if ( '' === get_post_meta( $post_id, '_sps_condition', true ) ) {
		update_post_meta( $post_id, '_sps_condition', 'used_b' );
	}
	if ( '' === get_post_meta( $post_id, '_sps_public', true ) ) {
		update_post_meta( $post_id, '_sps_public', (int) sps_setting( 'public_default' ) );
	}
	$statuses = sps_statuses();
	$status   = sps_meta( $post_id, 'status', 'in_stock' );
	$visible  = (int) sps_meta( $post_id, 'public', 0 ) && ! empty( $statuses[ $status ]['public'] );
	update_post_meta( $post_id, '_sps_visible', $visible ? 1 : 0 );
	sps_flush_caches();
}

/** Drop cached counts / stats. */
function sps_flush_caches() {
	delete_transient( 'sps_category_counts' );
	delete_transient( 'sps_stats' );
	delete_transient( 'sps_public_counts' );
}

/**
 * Next free SKU / label ID, e.g. "SP-00042".
 */
function sps_generate_sku() {
	global $wpdb;
	$prefix  = (string) sps_setting( 'sku_prefix' );
	$digits  = max( 3, min( 10, (int) sps_setting( 'sku_digits' ) ) );
	$counter = (int) get_option( 'sps_sku_counter', 0 );
	do {
		$counter++;
		$sku    = $prefix . str_pad( (string) $counter, $digits, '0', STR_PAD_LEFT );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sps_sku' AND meta_value = %s LIMIT 1", $sku ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	} while ( $exists );
	update_option( 'sps_sku_counter', $counter, false );
	return $sku;
}

/**
 * Find part IDs by SKU, part number (exact or normalised) or alternate numbers.
 *
 * @param string $code   Scanned or typed code.
 * @param bool   $exact  Only exact SKU / part number matches.
 * @return int[]
 */
function sps_find_parts_by_code( $code, $exact = true ) {
	global $wpdb;
	$code = trim( (string) $code );
	if ( '' === $code ) {
		return array();
	}
	// A scanned QR may contain the full scan URL.
	if ( false !== strpos( $code, 'sps_scan=' ) ) {
		$query = wp_parse_url( $code, PHP_URL_QUERY );
		parse_str( (string) $query, $args );
		if ( ! empty( $args['sps_scan'] ) ) {
			$code = (string) $args['sps_scan'];
		}
	}
	$norm = sps_normalize_number( $code );
	$ids  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft')
			 AND ( ( pm.meta_key = '_sps_sku' AND pm.meta_value = %s ) OR ( pm.meta_key = '_sps_part_number_norm' AND pm.meta_value = %s AND %s <> '' ) )",
			SPS_PT,
			$code,
			$norm,
			$norm
		)
	);
	if ( ! $ids && ! $exact ) {
		$ids = sps_search_meta_ids( $code );
	}
	return array_map( 'intval', $ids );
}

/**
 * Part IDs whose SKU, part number or alternate numbers contain the term.
 *
 * @return int[]
 */
function sps_search_meta_ids( $term ) {
	global $wpdb;
	$term = trim( (string) $term );
	if ( '' === $term ) {
		return array();
	}
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	$norm = sps_normalize_number( $term );
	$nlik = '%' . $wpdb->esc_like( '' === $norm ? $term : $norm ) . '%';
	$ids  = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
			 WHERE ( meta_key IN ('_sps_sku','_sps_part_number','_sps_alt_numbers','_sps_epc_ref') AND meta_value LIKE %s )
			    OR ( meta_key = '_sps_part_number_norm' AND meta_value LIKE %s )",
			$like,
			$nlik
		)
	);
	return array_map( 'intval', $ids );
}

/**
 * The part's single category term (deepest one if several are assigned).
 *
 * @return WP_Term|null
 */
function sps_part_category( $post_id ) {
	$terms = get_the_terms( $post_id, SPS_TAX_CAT );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	usort(
		$terms,
		static function ( $a, $b ) {
			return count( get_ancestors( $b->term_id, SPS_TAX_CAT, 'taxonomy' ) ) - count( get_ancestors( $a->term_id, SPS_TAX_CAT, 'taxonomy' ) );
		}
	);
	return $terms[0];
}

/**
 * "Engine Auxiliaries › Turbocharger"
 */
function sps_category_path( $term, $sep = ' › ' ) {
	if ( ! $term ) {
		return '';
	}
	$names = array( $term->name );
	foreach ( get_ancestors( $term->term_id, SPS_TAX_CAT, 'taxonomy' ) as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, SPS_TAX_CAT );
		if ( $ancestor && ! is_wp_error( $ancestor ) ) {
			array_unshift( $names, $ancestor->name );
		}
	}
	return implode( $sep, $names );
}

/**
 * Storage box for a category: own box code, else the nearest ancestor's.
 *
 * @param WP_Term|null $term Category.
 * @return array{code:string,location:string,term_id:int,term_name:string}
 */
function sps_term_box( $term ) {
	$empty = array( 'code' => '', 'location' => '', 'term_id' => 0, 'term_name' => '' );
	if ( ! $term ) {
		return $empty;
	}
	$chain = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, SPS_TAX_CAT, 'taxonomy' ) );
	foreach ( $chain as $term_id ) {
		$code = (string) get_term_meta( $term_id, 'sps_box_code', true );
		if ( '' !== $code ) {
			$t = get_term( $term_id, SPS_TAX_CAT );
			return array(
				'code'      => $code,
				'location'  => (string) get_term_meta( $term_id, 'sps_box_location', true ),
				'term_id'   => (int) $term_id,
				'term_name' => $t && ! is_wp_error( $t ) ? $t->name : '',
			);
		}
	}
	return $empty;
}

/**
 * Effective storage location of a part (box override wins over category box).
 *
 * @return array{code:string,location:string,bin:string,from_category:bool}
 */
function sps_part_box( $post_id ) {
	$override = (string) sps_meta( $post_id, 'box_override' );
	$box      = sps_term_box( sps_part_category( $post_id ) );
	return array(
		'code'          => '' !== $override ? $override : $box['code'],
		'location'      => '' !== $override ? '' : $box['location'],
		'bin'           => (string) sps_meta( $post_id, 'bin' ),
		'from_category' => '' === $override,
	);
}

/**
 * URL encoded into QR labels. Managers land on the scan screen, visitors on the public page.
 */
function sps_scan_url( $code ) {
	return add_query_arg( 'sps_scan', rawurlencode( $code ), home_url( '/' ) );
}

/**
 * Whether a part may be shown publicly.
 */
function sps_is_visible( $post_id ) {
	return 'publish' === get_post_status( $post_id ) && (int) get_post_meta( $post_id, '_sps_visible', true ) === 1;
}

/**
 * Format a money value with the configured currency.
 */
function sps_money( $amount ) {
	if ( '' === $amount || null === $amount ) {
		return '';
	}
	$currency = (string) sps_setting( 'currency' );
	$number   = number_format_i18n( (float) $amount, 2 );
	return in_array( $currency, array( '$', '£' ), true ) ? $currency . $number : $number . ' ' . $currency;
}

/**
 * Condition badge HTML.
 */
function sps_condition_badge( $condition ) {
	$conditions = sps_conditions();
	if ( ! isset( $conditions[ $condition ] ) ) {
		return '';
	}
	$c = $conditions[ $condition ];
	return sprintf(
		'<span class="sps-badge sps-badge--%1$s" style="--sps-badge:%2$s" title="%3$s">%4$s</span>',
		esc_attr( $c['group'] ),
		esc_attr( $c['color'] ),
		esc_attr( $c['label'] ),
		esc_html( $c['short'] )
	);
}

/**
 * Part data as an array. "public" context omits private fields.
 *
 * @param int    $post_id Part ID.
 * @param string $context public|admin.
 * @return array<string,mixed>
 */
function sps_part_data( $post_id, $context = 'public' ) {
	$post       = get_post( $post_id );
	$conditions = sps_conditions();
	$statuses   = sps_statuses();
	$flags_all  = sps_condition_flags();
	$category   = sps_part_category( $post_id );
	$condition  = sps_meta( $post_id, 'condition', 'used_b' );
	$status     = sps_meta( $post_id, 'status', 'in_stock' );
	$flags      = (array) sps_meta( $post_id, 'condition_flags', array() );
	$fitment    = wp_get_post_terms( $post_id, SPS_TAX_FIT, array( 'fields' => 'names' ) );
	$gallery    = array_filter( array_map( 'intval', explode( ',', (string) sps_meta( $post_id, 'gallery' ) ) ) );
	$thumb_id   = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		array_unshift( $gallery, $thumb_id );
	}
	$images = array();
	foreach ( array_unique( $gallery ) as $att_id ) {
		$full = wp_get_attachment_image_url( $att_id, 'large' );
		if ( $full ) {
			$images[] = array(
				'id'    => $att_id,
				'thumb' => wp_get_attachment_image_url( $att_id, 'medium' ),
				'full'  => $full,
				'alt'   => (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true ),
			);
		}
	}

	$data = array(
		'id'              => (int) $post_id,
		'name'            => get_the_title( $post ),
		'url'             => get_permalink( $post ),
		'sku'             => (string) sps_meta( $post_id, 'sku' ),
		'part_number'     => (string) sps_meta( $post_id, 'part_number' ),
		'alt_numbers'     => (string) sps_meta( $post_id, 'alt_numbers' ),
		'epc_ref'         => (string) sps_meta( $post_id, 'epc_ref' ),
		'category'        => $category ? array( 'id' => $category->term_id, 'name' => $category->name, 'path' => sps_category_path( $category ), 'slug' => $category->slug ) : null,
		'fitment'         => is_wp_error( $fitment ) ? array() : $fitment,
		'condition'       => $condition,
		'condition_label' => isset( $conditions[ $condition ] ) ? $conditions[ $condition ]['label'] : '',
		'condition_short' => isset( $conditions[ $condition ] ) ? $conditions[ $condition ]['short'] : '',
		'condition_group' => isset( $conditions[ $condition ] ) ? $conditions[ $condition ]['group'] : '',
		'condition_flags' => array_values( array_intersect_key( $flags_all, array_flip( $flags ) ) ),
		'condition_notes' => (string) sps_meta( $post_id, 'condition_notes' ),
		'mileage'         => sps_meta( $post_id, 'mileage' ),
		'status'          => $status,
		'status_label'    => isset( $statuses[ $status ] ) ? $statuses[ $status ]['label'] : '',
		'description'     => $post ? wp_kses_post( wpautop( $post->post_content ) ) : '',
		'images'          => $images,
	);

	if ( sps_setting( 'show_quantity' ) || 'admin' === $context ) {
		$data['quantity'] = (int) sps_meta( $post_id, 'quantity', 0 );
	}
	if ( sps_setting( 'show_prices' ) || 'admin' === $context ) {
		$data['sale_price'] = sps_meta( $post_id, 'sale_price' );
	}
	if ( sps_setting( 'show_location' ) || 'admin' === $context ) {
		$data['box'] = sps_part_box( $post_id );
	}
	if ( 'admin' === $context ) {
		$data += array(
			'edit_url'       => get_edit_post_link( $post_id, 'raw' ),
			'removed_from'   => (string) sps_meta( $post_id, 'removed_from' ),
			'purchase_price' => sps_meta( $post_id, 'purchase_price' ),
			'purchase_date'  => sps_meta( $post_id, 'purchase_date' ),
			'source'         => (string) sps_meta( $post_id, 'source' ),
			'value'          => sps_meta( $post_id, 'value' ),
			'public'         => (int) sps_meta( $post_id, 'public', 0 ),
			'private_notes'  => (string) sps_meta( $post_id, 'private_notes' ),
			'inspections'    => sps_get_inspections( $post_id ),
			'label_printed'  => (string) sps_meta( $post_id, 'label_printed' ),
			'post_status'    => get_post_status( $post_id ),
		);
	}
	return $data;
}

/**
 * Dated condition / inspection log entries, newest first.
 *
 * @return array<int,array{date:string,note:string,condition:string,user:string}>
 */
function sps_get_inspections( $post_id ) {
	$log = get_post_meta( $post_id, '_sps_inspections', true );
	if ( ! is_array( $log ) ) {
		return array();
	}
	usort(
		$log,
		static function ( $a, $b ) {
			return strcmp( (string) $b['date'], (string) $a['date'] );
		}
	);
	return $log;
}

/**
 * Append an inspection / condition note.
 */
function sps_add_inspection( $post_id, $note, $condition = '', $date = '' ) {
	$note = sanitize_textarea_field( (string) $note );
	if ( '' === trim( $note ) ) {
		return;
	}
	$user  = wp_get_current_user();
	$log   = get_post_meta( $post_id, '_sps_inspections', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'date'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ? $date : current_time( 'Y-m-d' ),
		'note'      => $note,
		'condition' => array_key_exists( (string) $condition, sps_conditions() ) ? $condition : '',
		'user'      => $user && $user->exists() ? $user->display_name : '',
	);
	update_post_meta( $post_id, '_sps_inspections', $log );
}

/**
 * Hierarchical <option> list of categories with box codes.
 *
 * @param int    $selected   Selected term ID.
 * @param bool   $show_boxes Append box codes.
 * @param string $value_by   id|slug.
 */
function sps_category_options( $selected = 0, $show_boxes = true, $value_by = 'id', $counts = null ) {
	$terms = get_terms(
		array(
			'taxonomy'   => SPS_TAX_CAT,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return '';
	}
	$by_parent = array();
	foreach ( $terms as $term ) {
		$by_parent[ $term->parent ][] = $term;
	}
	foreach ( $by_parent as &$children ) {
		usort(
			$children,
			static function ( $a, $b ) {
				$oa = (int) get_term_meta( $a->term_id, 'sps_order', true );
				$ob = (int) get_term_meta( $b->term_id, 'sps_order', true );
				return $oa === $ob ? strcasecmp( $a->name, $b->name ) : $oa - $ob;
			}
		);
	}
	unset( $children );
	$html = '';
	$walk = static function ( $parent, $depth ) use ( &$walk, &$html, $by_parent, $selected, $show_boxes, $value_by, $counts ) {
		if ( empty( $by_parent[ $parent ] ) ) {
			return;
		}
		foreach ( $by_parent[ $parent ] as $term ) {
			if ( is_array( $counts ) && empty( $counts[ $term->term_id ] ) ) {
				continue;
			}
			$box   = $show_boxes ? (string) get_term_meta( $term->term_id, 'sps_box_code', true ) : '';
			$value = 'slug' === $value_by ? $term->slug : $term->term_id;
			$label = str_repeat( '— ', $depth ) . $term->name . ( '' !== $box ? ' [' . $box . ']' : '' );
			if ( is_array( $counts ) ) {
				$label .= ' (' . (int) $counts[ $term->term_id ] . ')';
			}
			$inherited = sps_term_box( $term );
			$html     .= sprintf(
				'<option value="%1$s" data-box="%2$s" data-box-location="%3$s"%4$s>%5$s</option>',
				esc_attr( $value ),
				esc_attr( $inherited['code'] ),
				esc_attr( $inherited['location'] ),
				selected( (string) $selected, (string) $value, false ),
				esc_html( $label )
			);
			$walk( $term->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $html;
}

/**
 * Number of visible parts per category, including descendants.
 *
 * @return array<int,int>
 */
function sps_category_counts() {
	$counts = get_transient( 'sps_category_counts' );
	if ( is_array( $counts ) ) {
		return $counts;
	}
	$ids    = get_posts(
		array(
			'post_type'      => SPS_PT,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_key'       => '_sps_visible', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$counts = array();
	if ( $ids ) {
		$rel = wp_get_object_terms( $ids, SPS_TAX_CAT, array( 'fields' => 'all_with_object_id' ) );
		if ( ! is_wp_error( $rel ) ) {
			$seen = array();
			foreach ( $rel as $term ) {
				$chain = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, SPS_TAX_CAT, 'taxonomy' ) );
				foreach ( $chain as $tid ) {
					$key = $tid . ':' . $term->object_id;
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ]   = true;
					$counts[ $tid ] = isset( $counts[ $tid ] ) ? $counts[ $tid ] + 1 : 1;
				}
			}
		}
	}
	set_transient( 'sps_category_counts', $counts, DAY_IN_SECONDS );
	return $counts;
}
