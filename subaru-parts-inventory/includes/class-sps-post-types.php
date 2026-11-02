<?php
/**
 * Part post type, taxonomies, capabilities and visibility rules.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Post_Types {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'public_query_visibility' ) );
		add_action( 'template_redirect', array( __CLASS__, 'protect_hidden_parts' ) );
		add_action( 'save_post_' . SPS_PT, array( __CLASS__, 'after_save' ), 99, 2 );
		foreach ( array( 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'maybe_flush' ) );
		}
		add_action( 'add_option_sps_settings', static function () {
			sps_setting( null, true );
		} );
	}

	public static function register() {
		$labels = array(
			'name'               => __( 'Subaru Parts', 'subaru-parts-inventory' ),
			'singular_name'      => __( 'Part', 'subaru-parts-inventory' ),
			'menu_name'          => __( 'Subaru Parts', 'subaru-parts-inventory' ),
			'all_items'          => __( 'All Parts', 'subaru-parts-inventory' ),
			'add_new'            => __( 'Add Part', 'subaru-parts-inventory' ),
			'add_new_item'       => __( 'Add New Part', 'subaru-parts-inventory' ),
			'edit_item'          => __( 'Edit Part', 'subaru-parts-inventory' ),
			'new_item'           => __( 'New Part', 'subaru-parts-inventory' ),
			'view_item'          => __( 'View Part', 'subaru-parts-inventory' ),
			'search_items'       => __( 'Search parts, part numbers, SKUs', 'subaru-parts-inventory' ),
			'not_found'          => __( 'No parts found.', 'subaru-parts-inventory' ),
			'not_found_in_trash' => __( 'No parts found in Trash.', 'subaru-parts-inventory' ),
			'featured_image'     => __( 'Main photo', 'subaru-parts-inventory' ),
			'set_featured_image' => __( 'Set main photo', 'subaru-parts-inventory' ),
		);

		register_post_type(
			SPS_PT,
			array(
				'labels'          => $labels,
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 26,
				'menu_icon'       => 'dashicons-car',
				'has_archive'     => 'parts',
				'rewrite'         => array( 'slug' => 'parts/item', 'with_front' => false ),
				'supports'        => array( 'title', 'editor', 'thumbnail', 'revisions' ),
				'capability_type' => array( 'sps_part', 'sps_parts' ),
				'map_meta_cap'    => true,
				'show_in_rest'    => false,
			)
		);

		register_taxonomy(
			SPS_TAX_CAT,
			SPS_PT,
			array(
				'labels'             => array(
					'name'          => __( 'Part Categories', 'subaru-parts-inventory' ),
					'singular_name' => __( 'Part Category', 'subaru-parts-inventory' ),
					'menu_name'     => __( 'Categories & Boxes', 'subaru-parts-inventory' ),
					'add_new_item'  => __( 'Add Category / Box', 'subaru-parts-inventory' ),
					'edit_item'     => __( 'Edit Category / Box', 'subaru-parts-inventory' ),
					'parent_item'   => __( 'Parent category', 'subaru-parts-inventory' ),
					'search_items'  => __( 'Search categories', 'subaru-parts-inventory' ),
				),
				'hierarchical'       => true,
				'public'             => true,
				'show_admin_column'  => false,
				'show_in_quick_edit' => false,
				'meta_box_cb'        => false,
				'rewrite'            => array( 'slug' => 'parts/category', 'with_front' => false, 'hierarchical' => true ),
				'capabilities'       => array(
					'manage_terms' => SPS_CAP,
					'edit_terms'   => SPS_CAP,
					'delete_terms' => SPS_CAP,
					'assign_terms' => SPS_EDIT_CAP,
				),
			)
		);

		register_taxonomy(
			SPS_TAX_FIT,
			SPS_PT,
			array(
				'labels'            => array(
					'name'                       => __( 'Fitment (vehicles)', 'subaru-parts-inventory' ),
					'singular_name'              => __( 'Vehicle', 'subaru-parts-inventory' ),
					'menu_name'                  => __( 'Fitment', 'subaru-parts-inventory' ),
					'add_new_item'               => __( 'Add vehicle', 'subaru-parts-inventory' ),
					'separate_items_with_commas' => __( 'e.g. 2017 Subaru WRX 2.0L 6MT Limited, 2015-2021 WRX', 'subaru-parts-inventory' ),
					'choose_from_most_used'      => __( 'Choose from most used vehicles', 'subaru-parts-inventory' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'parts/fits', 'with_front' => false ),
				'capabilities'      => array(
					'manage_terms' => SPS_CAP,
					'edit_terms'   => SPS_CAP,
					'delete_terms' => SPS_CAP,
					'assign_terms' => SPS_EDIT_CAP,
				),
			)
		);

		foreach ( array( 'sps_box_code', 'sps_box_location', 'sps_order' ) as $key ) {
			register_term_meta( SPS_TAX_CAT, $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );
		}
	}

	/**
	 * Grant capabilities to administrators and create a "Parts Manager" role.
	 */
	public static function add_capabilities() {
		$caps = array(
			'edit_sps_part',
			'read_sps_part',
			'delete_sps_part',
			'edit_sps_parts',
			'edit_others_sps_parts',
			'publish_sps_parts',
			'read_private_sps_parts',
			'delete_sps_parts',
			'delete_private_sps_parts',
			'delete_published_sps_parts',
			'delete_others_sps_parts',
			'edit_private_sps_parts',
			'edit_published_sps_parts',
			'create_sps_parts',
			SPS_CAP,
		);
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}
		$manager = get_role( 'sps_parts_manager' );
		if ( ! $manager ) {
			$manager = add_role( 'sps_parts_manager', __( 'Parts Manager', 'subaru-parts-inventory' ), array( 'read' => true, 'upload_files' => true ) );
		}
		if ( $manager ) {
			foreach ( $caps as $cap ) {
				$manager->add_cap( $cap );
			}
		}
	}

	/**
	 * Keep SKU / visibility meta consistent however a part is saved.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function after_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
			return;
		}
		sps_refresh_derived_meta( $post_id );
	}

	public static function maybe_flush( $post_id ) {
		if ( SPS_PT === get_post_type( $post_id ) ) {
			sps_flush_caches();
		}
	}

	public static function disable_block_editor( $use, $post_type ) {
		return SPS_PT === $post_type ? false : $use;
	}

	/**
	 * Archives / taxonomy pages / site search only list visible parts.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function public_query_visibility( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$is_parts = $query->is_post_type_archive( SPS_PT ) || $query->is_tax( array( SPS_TAX_CAT, SPS_TAX_FIT ) );
		if ( ! $is_parts ) {
			return;
		}
		$meta   = (array) $query->get( 'meta_query' );
		$meta[] = array(
			'key'   => '_sps_visible',
			'value' => 1,
		);
		$query->set( 'meta_query', $meta );
		$query->set( 'posts_per_page', (int) sps_setting( 'per_page' ) );
	}

	/**
	 * Hidden / sold parts 404 for visitors; managers can still preview them.
	 */
	public static function protect_hidden_parts() {
		if ( ! is_singular( SPS_PT ) ) {
			return;
		}
		$id = get_queried_object_id();
		if ( sps_is_visible( $id ) || current_user_can( 'edit_post', $id ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
