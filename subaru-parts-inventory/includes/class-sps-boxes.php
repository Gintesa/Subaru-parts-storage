<?php
/**
 * Storage boxes: code scheme, clean-up and rebuild.
 *
 * Modes:
 *  - "sections":   one box per service-manual section inside each category (EN-FU, EN-IN, SB-BR …),
 *                  categories without sections get one box (AX).
 *  - "categories": one box per parts.subaru.com category (EN, SB, EL …).
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Boxes {

	public static function modes() {
		return array(
			'sections'   => __( 'Sub-boxes by service-manual section (EN-FU, EN-IN, SB-BR …)', 'subaru-parts-inventory' ),
			'categories' => __( 'One box per parts.subaru.com category (EN, SB, EL …)', 'subaru-parts-inventory' ),
		);
	}

	/**
	 * Remove every box code and assign the scheme.
	 *
	 * @return int Number of boxes assigned.
	 */
	public static function assign_codes( $mode = 'sections' ) {
		$mode  = array_key_exists( $mode, self::modes() ) ? $mode : 'sections';
		$terms = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return 0;
		}
		foreach ( $terms as $t ) {
			delete_term_meta( $t->term_id, 'sps_box_code' );
		}
		$n     = 0;
		$taken = array();
		foreach ( $terms as $t ) {
			if ( 0 !== (int) $t->parent ) {
				continue;
			}
			$name = html_entity_decode( $t->name, ENT_QUOTES );
			if ( isset( SPS_Library::BOXES[ $name ] ) ) {
				$code = SPS_Library::BOXES[ $name ];
			} elseif ( 'To Sort (Unidentified)' === $name ) {
				$code = 'TS';
			} else {
				// Your own top-level categories: first letters, made unique.
				$base = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $name ), 0, 3 ) );
				$base = '' !== $base ? $base : 'X';
				$code = $base;
				$i    = 2;
				while ( isset( $taken[ $code ] ) || in_array( $code, SPS_Library::BOXES, true ) || 'TS' === $code ) {
					$code = $base . $i++;
				}
			}
			$taken[ $code ] = true;
			update_term_meta( $t->term_id, 'sps_box_code', $code );
			$n++;
			if ( 'sections' !== $mode ) {
				continue;
			}
			foreach ( get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'parent' => $t->term_id, 'hide_empty' => false ) ) as $child ) {
				$sec = (string) get_term_meta( $child->term_id, 'sps_section', true );
				if ( '' !== $sec ) {
					update_term_meta( $child->term_id, 'sps_box_code', $code . '-' . $sec );
					$n++;
				}
			}
		}
		update_option( 'sps_box_mode', $mode );
		sps_flush_caches();
		return $n;
	}

	/**
	 * Merge categories with the same name under the same parent (keeps the library one / oldest).
	 *
	 * @return int Merged (deleted) duplicates.
	 */
	public static function merge_duplicates() {
		$terms = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC' ) );
		if ( is_wp_error( $terms ) ) {
			return 0;
		}
		$groups = array();
		foreach ( $terms as $t ) {
			$key              = $t->parent . '|' . strtolower( trim( html_entity_decode( $t->name, ENT_QUOTES ) ) );
			$groups[ $key ][] = $t;
		}
		$merged = 0;
		foreach ( $groups as $list ) {
			if ( count( $list ) < 2 ) {
				continue;
			}
			usort(
				$list,
				static function ( $a, $b ) {
					$la = (int) get_term_meta( $a->term_id, 'sps_lib', true );
					$lb = (int) get_term_meta( $b->term_id, 'sps_lib', true );
					return $lb - $la ?: $a->term_id - $b->term_id;
				}
			);
			$keep = array_shift( $list );
			foreach ( $list as $dup ) {
				self::move_term_contents( $dup->term_id, $keep->term_id );
				wp_delete_term( $dup->term_id, SPS_TAX_CAT );
				$merged++;
			}
		}
		return $merged;
	}

	private static function move_term_contents( $from, $to ) {
		$posts = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => SPS_TAX_CAT, 'terms' => $from, 'include_children' => false ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $posts as $pid ) {
			wp_set_object_terms( $pid, array( (int) $to ), SPS_TAX_CAT );
		}
		foreach ( get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'parent' => $from, 'hide_empty' => false ) ) as $child ) {
			wp_update_term( $child->term_id, SPS_TAX_CAT, array( 'parent' => (int) $to ) );
		}
	}

	/**
	 * Delete categories that are not part of the Subaru library and hold no parts.
	 *
	 * @return int Deleted.
	 */
	public static function delete_empty_custom() {
		$terms = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return 0;
		}
		usort(
			$terms,
			static function ( $a, $b ) {
				return count( get_ancestors( $b->term_id, SPS_TAX_CAT, 'taxonomy' ) ) - count( get_ancestors( $a->term_id, SPS_TAX_CAT, 'taxonomy' ) );
			}
		);
		$n = 0;
		foreach ( $terms as $t ) {
			if ( get_term_meta( $t->term_id, 'sps_lib', true ) || 'To Sort (Unidentified)' === html_entity_decode( $t->name ) ) {
				continue;
			}
			if ( get_term_children( $t->term_id, SPS_TAX_CAT ) ) {
				continue;
			}
			$has = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => SPS_TAX_CAT, 'terms' => $t->term_id, 'include_children' => false ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( ! $has ) {
				wp_delete_term( $t->term_id, SPS_TAX_CAT );
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Full clean-up.
	 *
	 * @param array $args mode (sections|categories), delete_empty (bool), unbox (bool), refile (bool).
	 * @return array<string,int>
	 */
	public static function cleanup( array $args ) {
		$args = wp_parse_args( $args, array( 'mode' => 'sections', 'delete_empty' => true, 'unbox' => false, 'refile' => true ) );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$report = array( 'merged' => 0, 'deleted' => 0, 'refiled' => 0, 'boxes' => 0, 'overrides' => 0, 'unboxed' => 0, 'duplicates' => 0 );

		// Rebuild the library tree so every diagram sits in its section.
		if ( SPS_Library::loaded() && file_exists( SPS_Library::bundled_path() ) && ! get_option( 'sps_oem_custom_source' ) ) {
			$data = SPS_Library::read( SPS_Library::bundled_path() );
			if ( ! is_wp_error( $data ) ) {
				SPS_Library::import( $data );
			}
		}
		$report['merged'] = self::merge_duplicates();

		$ids = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		if ( $args['refile'] ) {
			// Put every part with a catalog number into its catalog diagram.
			foreach ( $ids as $id ) {
				$oem = SPS_Library::get( sps_meta( $id, 'part_number' ) );
				if ( ! $oem || ! $oem['term_id'] || ! term_exists( (int) $oem['term_id'], SPS_TAX_CAT ) ) {
					continue;
				}
				$cat = sps_part_category( $id );
				if ( ! $cat || get_term_meta( $cat->term_id, 'sps_lib', true ) || 'To Sort (Unidentified)' === html_entity_decode( $cat->name ) ) {
					if ( ! $cat || (int) $cat->term_id !== (int) $oem['term_id'] ) {
						wp_set_object_terms( $id, array( (int) $oem['term_id'] ), SPS_TAX_CAT );
						$report['refiled']++;
					}
				}
			}
		}
		if ( $args['delete_empty'] ) {
			$report['deleted'] = self::delete_empty_custom();
		}
		$report['boxes'] = self::assign_codes( $args['mode'] );

		// Box overrides pointing at codes that no longer exist are cleared.
		$codes = self::all_codes();
		foreach ( $ids as $id ) {
			$ov = strtoupper( (string) sps_meta( $id, 'box_override' ) );
			if ( '' !== $ov && ! isset( $codes[ $ov ] ) ) {
				update_post_meta( $id, '_sps_box_override', '' );
				$report['overrides']++;
			}
			if ( $args['unbox'] && 'in_stock' === sps_meta( $id, 'status' ) ) {
				update_post_meta( $id, '_sps_status', 'not_boxed' );
				$report['unboxed']++;
			}
		}
		$report['duplicates'] = count( self::duplicate_codes() );
		update_option( 'sps_boxes_cleaned', current_time( 'mysql' ) );
		sps_flush_caches();
		return $report;
	}

	/**
	 * @return array<string,int> code => term_id
	 */
	public static function all_codes() {
		$out   = array();
		$terms = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false, 'meta_key' => 'sps_box_code', 'meta_compare' => '!=', 'meta_value' => '' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( (array) $terms as $t ) {
			$out[ strtoupper( (string) get_term_meta( $t->term_id, 'sps_box_code', true ) ) ] = (int) $t->term_id;
		}
		return $out;
	}

	/**
	 * Box codes used by more than one category.
	 *
	 * @return array<string,int> code => count
	 */
	public static function duplicate_codes() {
		$count = array();
		$terms = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false, 'meta_key' => 'sps_box_code', 'meta_compare' => '!=', 'meta_value' => '' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( (array) $terms as $t ) {
			$c           = strtoupper( (string) get_term_meta( $t->term_id, 'sps_box_code', true ) );
			$count[ $c ] = isset( $count[ $c ] ) ? $count[ $c ] + 1 : 1;
		}
		return array_filter(
			$count,
			static function ( $n ) {
				return $n > 1;
			}
		);
	}
}
