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

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 15 );
	}

	public static function menu() {
		add_submenu_page( 'edit.php?post_type=' . SPS_PT, __( 'Boxes', 'subaru-parts-inventory' ), __( 'Boxes & images', 'subaru-parts-inventory' ), SPS_CAP, 'sps-boxes', array( __CLASS__, 'admin_page' ) );
	}

	/**
	 * wp-admin page: box clean-up / rebuild + catalog image download.
	 */
	public static function admin_page() {
		$codes = self::all_codes();
		ksort( $codes, SORT_NATURAL );
		?>
		<div class="wrap sps-wrap sps-boxes-admin">
			<h1><?php esc_html_e( 'Boxes & catalog images', 'subaru-parts-inventory' ); ?></h1>
			<div class="sps-columns">
				<div class="sps-panel">
					<?php echo self::panel_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SPS_PT . '&page=sps-labels' ) ); ?>"><?php esc_html_e( 'Print box labels →', 'subaru-parts-inventory' ); ?></a></p>
				</div>
				<div class="sps-panel">
					<h3><?php esc_html_e( 'Catalog images', 'subaru-parts-inventory' ); ?></h3>
					<p><?php esc_html_e( 'Copies all part pictures and diagrams from the parts.subaru.com image server to this website, a few at a time. Keep this page open until it finishes; you can stop and continue later.', 'subaru-parts-inventory' ); ?></p>
					<div class="sps-progress"><i data-img-bar></i></div>
					<p data-img-status></p>
					<p><button type="button" class="button button-primary" data-action="img-fetch"><?php esc_html_e( 'Download catalog images to this site', 'subaru-parts-inventory' ); ?></button>
					<button type="button" class="button" data-action="img-stop" hidden><?php esc_html_e( 'Stop', 'subaru-parts-inventory' ); ?></button>
					<button type="button" class="button" data-action="img-retry" hidden><?php esc_html_e( 'Retry not-available images', 'subaru-parts-inventory' ); ?></button></p>
					<ul data-img-errors></ul>
				</div>
			</div>
			<h2><?php esc_html_e( 'Current boxes', 'subaru-parts-inventory' ); ?></h2>
			<table class="widefat striped" style="max-width:900px"><thead><tr><th><?php esc_html_e( 'Box', 'subaru-parts-inventory' ); ?></th><th><?php esc_html_e( 'Category', 'subaru-parts-inventory' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $codes as $code => $tid ) : $term = get_term( $tid, SPS_TAX_CAT ); ?>
				<tr><td><strong class="sps-box-code"><?php echo esc_html( $code ); ?></strong></td><td><?php echo esc_html( html_entity_decode( sps_category_path( $term ) ) ); ?></td>
				<td><a target="_blank" href="<?php echo esc_url( SPS_Labels::print_url( array( $tid ), 'box' ) ); ?>"><?php esc_html_e( 'Box label', 'subaru-parts-inventory' ); ?></a></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<style>.sps-boxes-admin .box-mode label{display:block;margin:4px 0}.sps-boxes-admin .checkline{display:block;margin:6px 0}.sps-progress{height:10px;background:#f0f0f1;border-radius:5px;overflow:hidden}.sps-progress i{display:block;height:100%;width:0;background:#2271b1;transition:width .3s}</style>
		<script>
		(function () {
			var rest = <?php echo wp_json_encode( esc_url_raw( rest_url( SPS_REST::NS . '/' ) ) ); ?>, nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
			var T = <?php echo wp_json_encode( array(
				'confirm' => __( 'Remove all box codes and rebuild them? Box labels must be reprinted.', 'subaru-parts-inventory' ),
				'working' => __( 'Working… this can take a minute.', 'subaru-parts-inventory' ),
				'report'  => __( 'Done: %1$d boxes, %2$d duplicates merged, %3$d empty categories deleted, %4$d parts re-filed, %5$d old box overrides cleared, %6$d parts set to Not boxed.', 'subaru-parts-inventory' ),
				'status'  => __( '%1$d of %2$d images on this site · %3$d not available on the image server', 'subaru-parts-inventory' ),
				'dupes'   => __( 'Duplicate box codes found:', 'subaru-parts-inventory' ),
			) ); ?>;
			function api(path, method, body) {
				return fetch(rest + path, { method: method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined })
					.then(function (r) { return r.json().then(function (d) { if (!r.ok) { throw new Error(d.message || r.status); } return d; }); });
			}
			var q = function (s) { return document.querySelector('.sps-boxes-admin ' + s); };
			api('boxes/cleanup').then(function (st) {
				var d = Object.keys(st.duplicates || {});
				if (d.length) { var w = q('[data-box-dupes]'); w.hidden = false; w.textContent = T.dupes + ' ' + d.map(function (k) { return k + ' ×' + st.duplicates[k]; }).join(', '); }
			});
			q('[data-action="box-cleanup"]').addEventListener('click', function () {
				if (!confirm(T.confirm)) { return; }
				var b = this; b.disabled = true; q('[data-box-report]').textContent = T.working;
				var mode = (document.querySelector('.sps-boxes-admin [name="sps_box_mode"]:checked') || {}).value || 'sections';
				api('boxes/cleanup', 'POST', { mode: mode, delete_empty: q('[data-box-opt="delete_empty"]').checked, unbox: q('[data-box-opt="unbox"]').checked }).then(function (r) {
					q('[data-box-report]').textContent = T.report.replace('%1$d', r.boxes).replace('%2$d', r.merged).replace('%3$d', r.deleted).replace('%4$d', r.refiled).replace('%5$d', r.overrides).replace('%6$d', r.unboxed);
					setTimeout(function () { location.reload(); }, 3000);
				}).catch(function (e) { q('[data-box-report]').textContent = e.message; }).then(function () { b.disabled = false; });
			});
			var run = false;
			function show(st) {
				q('[data-img-bar]').style.width = (st.total ? Math.round(100 * st.local / st.total) : 0) + '%';
				q('[data-img-status]').textContent = T.status.replace('%1$d', st.local).replace('%2$d', st.total).replace('%3$d', st.failed);
				q('[data-action="img-retry"]').hidden = !st.failed || run;
			}
			function stop() { run = false; q('[data-action="img-fetch"]').hidden = false; q('[data-action="img-stop"]').hidden = true; }
			function step(retry) {
				if (!run) { return; }
				api('library/images/fetch', 'POST', retry ? { retry: 1 } : {}).then(function (r) {
					show(r.status);
					q('[data-img-errors]').innerHTML = (r.errors || []).slice(0, 5).map(function (e) { var li = document.createElement('li'); li.textContent = e; return li.outerHTML; }).join('');
					if (r.network) { stop(); return; }
					if (r.status.remaining > 0 && (r.fetched > 0 || (r.errors || []).length)) { step(false); } else { stop(); }
				}).catch(function (e) { q('[data-img-status]').textContent = e.message; stop(); });
			}
			api('library/images').then(show);
			q('[data-action="img-fetch"]').addEventListener('click', function () { run = true; this.hidden = true; q('[data-action="img-stop"]').hidden = false; step(false); });
			q('[data-action="img-stop"]').addEventListener('click', stop);
			q('[data-action="img-retry"]').addEventListener('click', function () { run = true; this.hidden = true; q('[data-action="img-fetch"]').hidden = true; q('[data-action="img-stop"]').hidden = false; step(true); });
		})();
		</script>
		<?php
	}

	public static function modes() {
		return array(
			'sections'   => __( 'Sub-boxes: service-manual sections (EN-FU, EN-IN, SB-BR …), Accessories and your own categories by sub-category (AX-AM, AX-STI …)', 'subaru-parts-inventory' ),
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
			$children = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'parent' => $t->term_id, 'hide_empty' => false ) );
			$has_sec  = false;
			foreach ( (array) $children as $child ) {
				$sec = (string) get_term_meta( $child->term_id, 'sps_section', true );
				if ( '' !== $sec ) {
					$has_sec = true;
					update_term_meta( $child->term_id, 'sps_box_code', $code . '-' . $sec );
					$n++;
				}
			}
			if ( $has_sec || 'To Sort (Unidentified)' === $name ) {
				continue;
			}
			// Categories without service-manual sections (Accessories, your own "extra" categories):
			// one sub-box per sub-category, e.g. AX-AM (Audio Media), AX-STI (STI Brand).
			$used = array();
			foreach ( (array) $children as $child ) {
				$sub = self::initials( html_entity_decode( $child->name, ENT_QUOTES ) );
				$try = $sub;
				$i   = 2;
				while ( isset( $used[ $try ] ) ) {
					$try = $sub . $i++;
				}
				$used[ $try ] = true;
				update_term_meta( $child->term_id, 'sps_box_code', $code . '-' . $try );
				$n++;
			}
		}
		update_option( 'sps_box_mode', $mode );
		sps_flush_caches();
		return $n;
	}

	/**
	 * "Audio Media" → AM, "Protection and Security" → PS, "STI Brand" → STI.
	 */
	public static function initials( $name ) {
		$words = array_values(
			array_filter(
				preg_split( '/[^A-Za-z0-9]+/', $name ),
				static function ( $w ) {
					return '' !== $w && ! in_array( strtolower( $w ), array( 'and', 'or', 'of', 'the', 'for', 'with' ), true );
				}
			)
		);
		if ( ! $words ) {
			return 'X';
		}
		foreach ( $words as $w ) {
			if ( strlen( $w ) >= 2 && strlen( $w ) <= 4 && ctype_upper( $w ) ) {
				return $w; // Acronym wins: "STI Brand" → STI.
			}
		}
		if ( 1 === count( $words ) ) {
			return strtoupper( substr( $words[0], 0, 3 ) );
		}
		$out = '';
		foreach ( $words as $w ) {
			$out .= ctype_upper( $w ) && strlen( $w ) <= 4 ? $w : strtoupper( $w[0] );
		}
		return substr( $out, 0, 5 );
	}

	/**
	 * Box setup panel (shared by the parts app and the admin Boxes page).
	 */
	public static function panel_html( $context = 'app' ) {
		if ( ! current_user_can( SPS_CAP ) ) {
			return '<p class="hint">' . esc_html__( 'Box setup needs an administrator.', 'subaru-parts-inventory' ) . '</p>';
		}
		$mode = get_option( 'sps_box_mode', 'sections' );
		$html = '<div class="tool-box" data-box-tool><h3>' . esc_html__( 'Box setup: clean up & rebuild boxes', 'subaru-parts-inventory' ) . '</h3>'
			. '<p class="hint">' . esc_html__( 'Removes all box codes, merges duplicate categories, deletes empty leftover categories, files every part into its catalog diagram and creates a fresh, unique box scheme. Box labels must be reprinted afterwards.', 'subaru-parts-inventory' ) . '</p>'
			. '<div class="warn" data-box-dupes hidden></div><div class="svc-pick box-mode">';
		foreach ( self::modes() as $k => $label ) {
			$html .= '<label' . ( $mode === $k ? ' class="on"' : '' ) . '><input type="radio" name="sps_box_mode" value="' . esc_attr( $k ) . '"' . checked( $mode, $k, false ) . '>' . esc_html( $label ) . '</label>';
		}
		$html .= '</div><label class="checkline"><input type="checkbox" data-box-opt="delete_empty" checked> ' . esc_html__( 'Delete empty categories that are not from the Subaru catalog', 'subaru-parts-inventory' ) . '</label>'
			. '<label class="checkline"><input type="checkbox" data-box-opt="unbox"> ' . esc_html__( 'Mark all boxed parts as "Not boxed yet" (I will re-sort them)', 'subaru-parts-inventory' ) . '</label>'
			. '<div class="row-btns"><button type="button" class="btn danger mini button" data-action="box-cleanup">' . esc_html__( 'Clean up & rebuild boxes', 'subaru-parts-inventory' ) . '</button></div><div class="hint" data-box-report></div></div>';
		return $html;
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
