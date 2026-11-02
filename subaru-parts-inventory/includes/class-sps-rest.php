<?php
/**
 * REST API: public catalogue + manager endpoints.
 *
 * Public:   GET /subaru-parts/v1/parts, /parts/<id>, /categories, /conditions
 * Managers: GET /lookup?code=…, GET /check-number?number=…, POST /parts/<id>/quick
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_REST {

	const NS = 'subaru-parts/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/parts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_parts' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'category'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'condition' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'fitment'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'orderby'   => array( 'type' => 'string', 'enum' => array( 'newest', 'part_number', 'name', 'price' ), 'default' => 'newest' ),
					'page'      => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
					'format'    => array( 'type' => 'string', 'enum' => array( 'json', 'html' ), 'default' => 'json' ),
					'view'      => array( 'type' => 'string', 'enum' => array( 'grid', 'table' ), 'default' => 'grid' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/parts/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_part' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'categories' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/conditions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					return rest_ensure_response( array( 'conditions' => sps_conditions(), 'flags' => sps_condition_flags(), 'statuses' => sps_statuses() ) );
				},
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/lookup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'lookup' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array( 'code' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/check-number',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'check_number' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'number'  => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'exclude' => array( 'type' => 'integer', 'default' => 0 ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/parts/(?P<id>\d+)/quick',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'quick' ),
				'permission_callback' => static function ( $request ) {
					return current_user_can( 'edit_post', (int) $request['id'] );
				},
				'args'                => array(
					'qty_delta' => array( 'type' => 'integer' ),
					'quantity'  => array( 'type' => 'integer', 'minimum' => 0 ),
					'status'    => array( 'type' => 'string', 'enum' => array_keys( sps_statuses() ) ),
					'condition' => array( 'type' => 'string', 'enum' => array_keys( sps_conditions() ) ),
					'note'      => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
				),
			)
		);
	}

	public static function can_manage() {
		return current_user_can( SPS_EDIT_CAP );
	}

	/**
	 * Build a WP_Query for the public catalogue.
	 *
	 * @param array $args Filters: search, category (id|slug), condition (key|new|used|core, comma list), fitment (slug), orderby, page, per_page.
	 * @return WP_Query
	 */
	public static function query( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'search'    => '',
				'category'  => '',
				'condition' => '',
				'fitment'   => '',
				'orderby'   => 'newest',
				'page'      => 1,
				'per_page'  => (int) sps_setting( 'per_page' ),
			)
		);
		$q = array(
			'post_type'      => SPS_PT,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 100, (int) $args['per_page'] ) ),
			'paged'          => max( 1, (int) $args['page'] ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'visible' => array( 'key' => '_sps_visible', 'value' => 1 ),
			),
			'tax_query'      => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		);

		if ( '' !== $args['category'] ) {
			$field = ctype_digit( (string) $args['category'] ) ? 'term_id' : 'slug';
			$q['tax_query'][] = array( 'taxonomy' => SPS_TAX_CAT, 'field' => $field, 'terms' => $args['category'], 'include_children' => true );
		}
		if ( '' !== $args['fitment'] ) {
			$q['tax_query'][] = array( 'taxonomy' => SPS_TAX_FIT, 'field' => 'slug', 'terms' => $args['fitment'] );
		}
		if ( '' !== $args['condition'] ) {
			$wanted = array();
			foreach ( array_map( 'trim', explode( ',', $args['condition'] ) ) as $c ) {
				if ( in_array( $c, array( 'new', 'used', 'core' ), true ) ) {
					$wanted = array_merge( $wanted, array_keys( wp_list_filter( sps_conditions(), array( 'group' => $c ) ) ) );
				} elseif ( array_key_exists( $c, sps_conditions() ) ) {
					$wanted[] = $c;
				}
			}
			if ( $wanted ) {
				$q['meta_query']['condition'] = array( 'key' => '_sps_condition', 'value' => array_unique( $wanted ), 'compare' => 'IN' );
			}
		}
		if ( '' !== trim( (string) $args['search'] ) ) {
			$term     = trim( (string) $args['search'] );
			$meta_ids = sps_search_meta_ids( $term );
			$text_ids = get_posts(
				array(
					'post_type'      => SPS_PT,
					'post_status'    => 'publish',
					's'              => $term,
					'fields'         => 'ids',
					'posts_per_page' => 1000,
					'no_found_rows'  => true,
				)
			);
			$ids             = array_unique( array_merge( $meta_ids, $text_ids ) );
			$q['post__in']   = $ids ? $ids : array( 0 );
		}
		switch ( $args['orderby'] ) {
			case 'part_number':
				$q['meta_query']['pn'] = array( 'key' => '_sps_part_number', 'compare' => 'EXISTS' );
				$q['orderby']          = array( 'pn' => 'ASC' );
				break;
			case 'name':
				$q['orderby'] = 'title';
				$q['order']   = 'ASC';
				break;
			case 'price':
				$q['meta_query']['price'] = array( 'key' => '_sps_sale_price', 'type' => 'DECIMAL(10,2)', 'compare' => 'EXISTS' );
				$q['orderby']             = array( 'price' => 'ASC' );
				break;
			default:
				$q['orderby'] = 'date';
				$q['order']   = 'DESC';
		}
		return new WP_Query( apply_filters( 'sps_catalog_query_args', $q, $args ) );
	}

	public static function list_parts( WP_REST_Request $request ) {
		$args = array(
			'search'    => (string) $request['search'],
			'category'  => (string) $request['category'],
			'condition' => (string) $request['condition'],
			'fitment'   => (string) $request['fitment'],
			'orderby'   => (string) $request['orderby'],
			'page'      => (int) $request['page'],
			'per_page'  => $request['per_page'] ? (int) $request['per_page'] : (int) sps_setting( 'per_page' ),
		);
		$query = self::query( $args );
		$data  = array(
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'page'  => (int) $args['page'],
		);
		if ( 'html' === $request['format'] ) {
			$data['html']       = SPS_Frontend::render_results( $query, (string) $request['view'] );
			$data['pagination'] = SPS_Frontend::render_pagination( $query, (int) $args['page'] );
			$data['summary']    = SPS_Frontend::render_summary( $query );
		} else {
			$data['items'] = array_map(
				static function ( $post ) {
					return sps_part_data( $post->ID );
				},
				$query->posts
			);
		}
		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $data['total'] );
		$response->header( 'X-WP-TotalPages', (string) $data['pages'] );
		return $response;
	}

	public static function get_part( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( SPS_PT !== get_post_type( $id ) ) {
			return new WP_Error( 'sps_not_found', __( 'Part not found.', 'subaru-parts-inventory' ), array( 'status' => 404 ) );
		}
		if ( current_user_can( 'edit_post', $id ) ) {
			return rest_ensure_response( sps_part_data( $id, 'admin' ) );
		}
		if ( ! sps_is_visible( $id ) ) {
			return new WP_Error( 'sps_not_found', __( 'Part not found.', 'subaru-parts-inventory' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( sps_part_data( $id ) );
	}

	public static function categories() {
		$counts = sps_category_counts();
		$terms  = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false ) );
		$out    = array();
		foreach ( (array) $terms as $term ) {
			$out[] = array(
				'id'     => $term->term_id,
				'name'   => html_entity_decode( $term->name ),
				'slug'   => $term->slug,
				'parent' => $term->parent,
				'count'  => isset( $counts[ $term->term_id ] ) ? (int) $counts[ $term->term_id ] : 0,
				'order'  => (int) get_term_meta( $term->term_id, 'sps_order', true ),
			);
		}
		return rest_ensure_response( $out );
	}

	public static function lookup( WP_REST_Request $request ) {
		$ids = sps_find_parts_by_code( (string) $request['code'], false );
		return rest_ensure_response(
			array_map(
				static function ( $id ) {
					return sps_part_data( $id, 'admin' );
				},
				array_slice( $ids, 0, 50 )
			)
		);
	}

	public static function check_number( WP_REST_Request $request ) {
		$norm = sps_normalize_number( (string) $request['number'] );
		if ( strlen( $norm ) < 4 ) {
			return rest_ensure_response( array() );
		}
		$ids = array_diff( sps_find_parts_by_code( $norm ), array( (int) $request['exclude'] ) );
		$out = array();
		foreach ( array_slice( $ids, 0, 20 ) as $id ) {
			$out[] = array(
				'id'        => $id,
				'name'      => get_the_title( $id ),
				'sku'       => sps_meta( $id, 'sku' ),
				'condition' => sps_meta( $id, 'condition' ),
				'quantity'  => (int) sps_meta( $id, 'quantity', 0 ),
				'status'    => sps_meta( $id, 'status' ),
				'edit_url'  => get_edit_post_link( $id, 'raw' ),
			);
		}
		return rest_ensure_response( $out );
	}

	public static function quick( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( SPS_PT !== get_post_type( $id ) ) {
			return new WP_Error( 'sps_not_found', __( 'Part not found.', 'subaru-parts-inventory' ), array( 'status' => 404 ) );
		}
		$data = array();
		if ( null !== $request['quantity'] ) {
			$data['quantity'] = (int) $request['quantity'];
		} elseif ( null !== $request['qty_delta'] ) {
			$data['quantity'] = max( 0, (int) sps_meta( $id, 'quantity', 0 ) + (int) $request['qty_delta'] );
		}
		if ( $request['status'] ) {
			$data['status'] = $request['status'];
		}
		if ( $request['condition'] ) {
			$data['condition'] = $request['condition'];
		}
		if ( $data ) {
			sps_save_part_meta( $id, $data );
		}
		if ( $request['note'] ) {
			sps_add_inspection( $id, $request['note'], (string) $request['condition'] );
		}
		return rest_ensure_response( sps_part_data( $id, 'admin' ) );
	}
}
