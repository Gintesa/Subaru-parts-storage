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
		add_action( 'rest_api_init', array( __CLASS__, 'manage_routes' ) );
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
					'orderby'   => array( 'type' => 'string', 'enum' => array( 'newest', 'oldest', 'part_number', 'name', 'price', 'updated' ), 'default' => 'newest' ),
					'page'      => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
					'per_page'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
					'format'    => array( 'type' => 'string', 'enum' => array( 'json', 'html' ), 'default' => 'json' ),
					'view'      => array( 'type' => 'string', 'enum' => array( 'grid', 'table' ), 'default' => 'grid' ),
					'context'   => array( 'type' => 'string', 'enum' => array( 'view', 'manage' ), 'default' => 'view' ),
					'status'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'instock' ),
					'label'     => array( 'type' => 'string', 'enum' => array( '', 'yes', 'no' ), 'default' => '' ),
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
					'put_away'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
					'force'     => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
	}

	/**
	 * Write / management routes used by the front-end parts app.
	 */
	public static function manage_routes() {
		register_rest_route(
			self::NS,
			'/parts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_part' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/parts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'save_part' ),
					'permission_callback' => static function ( $request ) {
						return current_user_can( 'edit_post', (int) $request['id'] );
					},
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'trash_part' ),
					'permission_callback' => static function ( $request ) {
						return current_user_can( 'delete_post', (int) $request['id'] );
					},
				),
			)
		);
		register_rest_route(
			self::NS,
			'/parts/(?P<id>\d+)/photos',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'upload_photos' ),
				'permission_callback' => static function ( $request ) {
					return current_user_can( 'edit_post', (int) $request['id'] ) && current_user_can( 'upload_files' );
				},
			)
		);
		register_rest_route(
			self::NS,
			'/parts/(?P<id>\d+)/photos/(?P<att>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'remove_photo' ),
				'permission_callback' => static function ( $request ) {
					return current_user_can( 'edit_post', (int) $request['id'] );
				},
			)
		);
		register_rest_route(
			self::NS,
			'/identify',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $request ) {
					return rest_ensure_response( SPS_Catalog::identify( (string) $request['number'] ) );
				},
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array( 'number' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'stats' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/boxes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'boxes' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/boxes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'save_box' ),
				'permission_callback' => static function () {
					return current_user_can( SPS_CAP );
				},
			)
		);
		register_rest_route(
			self::NS,
			'/activity',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'activity' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	/**
	 * Create or update a part from the front-end form.
	 */
	public static function save_part( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || ! $params ) {
			$params = $request->get_body_params();
		}
		$params = is_array( $params ) ? $params : array();
		if ( $id && SPS_PT !== get_post_type( $id ) ) {
			return new WP_Error( 'sps_not_found', __( 'Part not found.', 'subaru-parts-inventory' ), array( 'status' => 404 ) );
		}
		$name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$pn   = isset( $params['part_number'] ) ? sps_sanitize_field( 'part_number', $params['part_number'] ) : '';
		if ( ! $id && '' === $name && '' === $pn ) {
			return new WP_Error( 'sps_missing', __( 'Enter a part number or a name.', 'subaru-parts-inventory' ), array( 'status' => 400 ) );
		}
		$ident = null;
		if ( ! $id && '' !== $pn && ( '' === $name || empty( $params['category_id'] ) ) ) {
			$ident = SPS_Catalog::identify( $pn );
			if ( '' === $name && '' !== $ident['name'] ) {
				$name = $ident['name'];
			}
			if ( empty( $params['category_id'] ) && $ident['category_id'] ) {
				$params['category_id'] = $ident['category_id'];
			}
		}
		$postarr = array( 'post_type' => SPS_PT );
		if ( '' !== $name || ! $id ) {
			$postarr['post_title'] = '' !== $name ? $name : $pn;
		}
		if ( isset( $params['description'] ) ) {
			$postarr['post_content'] = wp_kses_post( $params['description'] );
		}
		if ( ! $id ) {
			$postarr['post_status'] = current_user_can( 'publish_sps_parts' ) ? 'publish' : 'pending';
			$result                 = wp_insert_post( $postarr, true );
		} else {
			$postarr['ID'] = $id;
			$result        = wp_update_post( $postarr, true );
		}
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 500 ) );
			return $result;
		}
		$id      = (int) $result;
		$created = ! $request['id'];

		$before = sps_meta( $id, 'condition' );
		$meta   = array_intersect_key( $params, sps_meta_fields() );
		if ( $created && ! isset( $meta['status'] ) ) {
			$meta['status'] = 'not_boxed';
		}
		if ( array_key_exists( 'condition_flags', $params ) && ! is_array( $params['condition_flags'] ) && '' === (string) $params['condition_flags'] ) {
			$meta['condition_flags'] = array();
		}
		sps_save_part_meta( $id, $meta );

		if ( array_key_exists( 'category_id', $params ) ) {
			$term_id = absint( $params['category_id'] );
			if ( ! $term_id || ! term_exists( $term_id, SPS_TAX_CAT ) ) {
				$term_id = SPS_Categories::term_from_path( 'To Sort (Unidentified)' );
			}
			wp_set_object_terms( $id, $term_id ? array( $term_id ) : array(), SPS_TAX_CAT );
		} elseif ( $created ) {
			$term_id = SPS_Categories::term_from_path( 'To Sort (Unidentified)' );
			wp_set_object_terms( $id, $term_id ? array( $term_id ) : array(), SPS_TAX_CAT );
		}
		if ( array_key_exists( 'fitment', $params ) ) {
			$fit = is_array( $params['fitment'] ) ? $params['fitment'] : explode( ',', (string) $params['fitment'] );
			wp_set_object_terms( $id, array_values( array_filter( array_map( 'trim', array_map( 'sanitize_text_field', $fit ) ) ) ), SPS_TAX_FIT );
		}
		if ( ! empty( $params['note'] ) ) {
			sps_add_inspection( $id, $params['note'], isset( $meta['condition'] ) ? sps_sanitize_field( 'condition', $meta['condition'] ) : '' );
		} elseif ( $before && isset( $meta['condition'] ) && $before !== $meta['condition'] ) {
			$c = sps_conditions();
			$n = sps_sanitize_field( 'condition', $meta['condition'] );
			/* translators: 1: old grade, 2: new grade */
			sps_add_inspection( $id, sprintf( __( 'Condition changed: %1$s → %2$s', 'subaru-parts-inventory' ), $c[ $before ]['short'] ?? $before, $c[ $n ]['short'] ?? $n ), $n );
		} elseif ( $created ) {
			sps_add_inspection( $id, __( 'Added to inventory.', 'subaru-parts-inventory' ), sps_meta( $id, 'condition' ) );
		}
		sps_refresh_derived_meta( $id );

		$data              = sps_part_data( $id, 'admin' );
		$data['created']   = $created;
		$data['print_url'] = SPS_Labels::print_url( array( $id ) );
		return rest_ensure_response( $data );
	}

	/**
	 * Mark a part as boxed in the given box. If the box differs from the part's
	 * category box, refuse (409) unless $force, which stores it there as an override.
	 */
	private static function put_away( $id, $code, $force ) {
		$box      = sps_part_box( $id );
		$cat_box  = sps_term_box( sps_part_category( $id ) );
		$expected = strtoupper( (string) $box['code'] );
		if ( '' !== $expected && $expected !== $code && ! $force ) {
			return new WP_Error(
				'sps_wrong_box',
				/* translators: 1: expected box, 2: scanned box */
				sprintf( __( 'This part belongs in %1$s, not %2$s.', 'subaru-parts-inventory' ), $expected, $code ),
				array( 'status' => 409, 'expected' => $expected, 'scanned' => $code, 'part' => sps_part_data( $id, 'admin' ) )
			);
		}
		$data = array( 'status' => 'in_stock' );
		if ( strtoupper( (string) $cat_box['code'] ) === $code ) {
			$data['box_override'] = '';
		} elseif ( $expected !== $code ) {
			$data['box_override'] = $code;
		}
		sps_save_part_meta( $id, $data );
		/* translators: %s: box code */
		sps_add_inspection( $id, sprintf( __( 'Put away in %s.', 'subaru-parts-inventory' ), $code ) );
		return rest_ensure_response( sps_part_data( $id, 'admin' ) );
	}

	public static function trash_part( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( SPS_PT !== get_post_type( $id ) ) {
			return new WP_Error( 'sps_not_found', __( 'Part not found.', 'subaru-parts-inventory' ), array( 'status' => 404 ) );
		}
		wp_trash_post( $id );
		return rest_ensure_response( array( 'trashed' => true, 'id' => $id ) );
	}

	/**
	 * Upload one or more photos (multipart field "photos[]"). First photo becomes the main photo if none is set.
	 */
	public static function upload_photos( WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( SPS_PT !== get_post_type( $id ) ) {
			return new WP_Error( 'sps_not_found', __( 'Part not found.', 'subaru-parts-inventory' ), array( 'status' => 404 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$files = $request->get_file_params();
		$list  = array();
		foreach ( $files as $field ) {
			if ( is_array( $field['name'] ) ) {
				foreach ( array_keys( $field['name'] ) as $i ) {
					$list[] = array(
						'name'     => $field['name'][ $i ],
						'type'     => $field['type'][ $i ],
						'tmp_name' => $field['tmp_name'][ $i ],
						'error'    => $field['error'][ $i ],
						'size'     => $field['size'][ $i ],
					);
				}
			} else {
				$list[] = $field;
			}
		}
		if ( ! $list ) {
			return new WP_Error( 'sps_no_files', __( 'No photos received.', 'subaru-parts-inventory' ), array( 'status' => 400 ) );
		}
		$gallery = array_filter( array_map( 'intval', explode( ',', (string) sps_meta( $id, 'gallery' ) ) ) );
		$errors  = array();
		foreach ( $list as $file ) {
			$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			if ( empty( $type['type'] ) || 0 !== strpos( $type['type'], 'image/' ) ) {
				$errors[] = $file['name'] . ': ' . __( 'not an image.', 'subaru-parts-inventory' );
				continue;
			}
			$att = media_handle_sideload( $file, $id, get_the_title( $id ) );
			if ( is_wp_error( $att ) ) {
				$errors[] = $file['name'] . ': ' . $att->get_error_message();
				continue;
			}
			if ( ! has_post_thumbnail( $id ) ) {
				set_post_thumbnail( $id, $att );
			} else {
				$gallery[] = (int) $att;
			}
		}
		update_post_meta( $id, '_sps_gallery', implode( ',', array_unique( $gallery ) ) );
		$data           = sps_part_data( $id, 'admin' );
		$data['errors'] = $errors;
		return rest_ensure_response( $data );
	}

	public static function remove_photo( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$att = (int) $request['att'];
		if ( (int) get_post_thumbnail_id( $id ) === $att ) {
			delete_post_thumbnail( $id );
			$gallery = array_filter( array_map( 'intval', explode( ',', (string) sps_meta( $id, 'gallery' ) ) ) );
			if ( $gallery ) {
				set_post_thumbnail( $id, array_shift( $gallery ) );
				update_post_meta( $id, '_sps_gallery', implode( ',', $gallery ) );
			}
		} else {
			$gallery = array_diff( array_filter( array_map( 'intval', explode( ',', (string) sps_meta( $id, 'gallery' ) ) ) ), array( $att ) );
			update_post_meta( $id, '_sps_gallery', implode( ',', $gallery ) );
		}
		return rest_ensure_response( sps_part_data( $id, 'admin' ) );
	}

	public static function stats() {
		if ( current_user_can( SPS_EDIT_CAP ) ) {
			$s = SPS_Admin::stats();
			return rest_ensure_response(
				array(
					'manager'    => true,
					'parts'      => $s['parts'],
					'items'      => $s['items'],
					'new'        => $s['by_condition']['new'],
					'used'       => $s['parts'] - $s['by_condition']['new'],
					'by_condition' => $s['by_condition'],
					'not_boxed'  => $s['by_status']['not_boxed'],
					'value'      => $s['value'],
					'value_fmt'  => sps_money( $s['value'] ),
					'cost_fmt'   => sps_money( $s['cost'] ),
					'unlabelled' => $s['unlabelled'],
					'no_photo'   => $s['no_photo'],
					'by_status'  => $s['by_status'],
				)
			);
		}
		$c = sps_public_counts();
		return rest_ensure_response( array( 'manager' => false, 'parts' => $c['all'], 'new' => $c['new'], 'used' => $c['used'] ) );
	}

	/**
	 * Storage boxes: categories with their own box code, plus part counts per box.
	 */
	public static function boxes() {
		$manager = current_user_can( SPS_EDIT_CAP );
		$counts  = array();
		$args    = array(
			'post_type'      => SPS_PT,
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);
		if ( $manager ) {
			$args['post_status'] = array( 'publish', 'draft', 'private', 'pending' );
			$args['meta_query']  = array( array( 'key' => '_sps_status', 'value' => array_keys( wp_list_filter( sps_statuses(), array( 'in_stock' => true ) ) ), 'compare' => 'IN' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		} else {
			$args['post_status'] = 'publish';
			$args['meta_key']    = '_sps_visible'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value']  = 1; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		foreach ( get_posts( $args ) as $pid ) {
			$box = sps_part_box( $pid );
			$key = '' !== $box['code'] ? $box['code'] : '—';
			$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
		}
		$out   = array();
		$terms = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false, 'meta_key' => 'sps_box_code', 'meta_compare' => '!=', 'meta_value' => '' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( (array) $terms as $term ) {
			$code     = (string) get_term_meta( $term->term_id, 'sps_box_code', true );
			$children = array();
			foreach ( get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'parent' => $term->term_id, 'hide_empty' => false ) ) as $child ) {
				$own = (string) get_term_meta( $child->term_id, 'sps_box_code', true );
				if ( '' === $own || $own === $code ) {
					$children[] = html_entity_decode( $child->name );
				}
			}
			$out[] = array(
				'id'        => $term->term_id,
				'slug'      => $term->slug,
				'code'      => $code,
				'name'      => html_entity_decode( $term->name ),
				'path'      => html_entity_decode( sps_category_path( $term ) ),
				'location'  => $manager || sps_setting( 'show_location' ) ? (string) get_term_meta( $term->term_id, 'sps_box_location', true ) : '',
				'count'     => isset( $counts[ $code ] ) ? $counts[ $code ] : 0,
				'children'  => $children,
				'order'     => (int) get_term_meta( $term->term_id, 'sps_order', true ),
				'depth'     => count( get_ancestors( $term->term_id, SPS_TAX_CAT, 'taxonomy' ) ),
				'label_url' => $manager ? SPS_Labels::print_url( array( $term->term_id ), 'box' ) : '',
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['code'], $b['code'] );
			}
		);
		return rest_ensure_response( $out );
	}

	public static function save_box( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$term = get_term( $id, SPS_TAX_CAT );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'sps_not_found', __( 'Category not found.', 'subaru-parts-inventory' ), array( 'status' => 404 ) );
		}
		if ( null !== $request['code'] ) {
			update_term_meta( $id, 'sps_box_code', strtoupper( sanitize_text_field( $request['code'] ) ) );
		}
		if ( null !== $request['location'] ) {
			update_term_meta( $id, 'sps_box_location', sanitize_text_field( $request['location'] ) );
		}
		sps_flush_caches();
		return self::boxes();
	}

	/**
	 * Recent condition-log entries across parts.
	 */
	public static function activity() {
		$posts = get_posts(
			array(
				'post_type'      => SPS_PT,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 40,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$items = array();
		foreach ( $posts as $p ) {
			foreach ( array_slice( sps_get_inspections( $p->ID ), 0, 5 ) as $entry ) {
				$items[] = array(
					'date'        => $entry['date'],
					'note'        => $entry['note'],
					'condition'   => $entry['condition'],
					'user'        => $entry['user'],
					'part_id'     => $p->ID,
					'part_number' => (string) sps_meta( $p->ID, 'part_number' ),
					'sku'         => (string) sps_meta( $p->ID, 'sku' ),
					'name'        => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
					'modified'    => $p->post_modified,
				);
			}
		}
		usort(
			$items,
			static function ( $a, $b ) {
				$c = strcmp( $b['date'], $a['date'] );
				return 0 !== $c ? $c : strcmp( $b['modified'], $a['modified'] );
			}
		);
		return rest_ensure_response( array_slice( $items, 0, 30 ) );
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
		$manage = ! empty( $args['manage'] );
		$q      = array(
			'post_type'      => SPS_PT,
			'post_status'    => $manage ? array( 'publish', 'draft', 'private', 'pending' ) : 'publish',
			'posts_per_page' => max( 1, min( 100, (int) $args['per_page'] ) ),
			'paged'          => max( 1, (int) $args['page'] ),
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
			'tax_query'      => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		if ( ! $manage ) {
			$q['meta_query']['visible'] = array( 'key' => '_sps_visible', 'value' => 1 );
		} else {
			$status = isset( $args['status'] ) ? (string) $args['status'] : 'instock';
			if ( 'instock' === $status || '' === $status ) {
				$q['meta_query']['status'] = array( 'key' => '_sps_status', 'value' => array_keys( wp_list_filter( sps_statuses(), array( 'in_stock' => true ) ) ), 'compare' => 'IN' );
			} elseif ( array_key_exists( $status, sps_statuses() ) ) {
				$q['meta_query']['status'] = array( 'key' => '_sps_status', 'value' => $status );
			}
			if ( ! empty( $args['label'] ) ) {
				$q['meta_query']['label'] = 'yes' === $args['label']
					? array( 'key' => '_sps_label_printed', 'compare' => 'EXISTS' )
					: array( 'key' => '_sps_label_printed', 'compare' => 'NOT EXISTS' );
			}
		}

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
				if ( '' !== $c ) {
					$wanted[] = sps_condition_key( $c, $c );
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
					'post_status'    => $q['post_status'],
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
			case 'oldest':
				$q['orderby'] = 'date';
				$q['order']   = 'ASC';
				break;
			case 'updated':
				$q['orderby'] = 'modified';
				$q['order']   = 'DESC';
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
		$manage = 'manage' === $request['context'] && current_user_can( SPS_EDIT_CAP );
		if ( $manage ) {
			$args['manage'] = true;
			$args['status'] = (string) $request['status'];
			$args['label']  = (string) $request['label'];
		}
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
				static function ( $post ) use ( $manage ) {
					return sps_part_data( $post->ID, $manage ? 'admin' : 'public' );
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
		if ( $request['put_away'] ) {
			return self::put_away( $id, strtoupper( trim( preg_replace( '/^BOX:/i', '', (string) $request['put_away'] ) ) ), (bool) $request['force'] );
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
