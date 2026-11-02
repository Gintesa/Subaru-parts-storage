<?php
/**
 * Front end: catalogue shortcode/block, single part page, QR scan redirect.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Frontend {

	public static function init() {
		add_shortcode( 'subaru_parts', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_filter( 'the_content', array( __CLASS__, 'single_content' ) );
		add_action( 'template_redirect', array( __CLASS__, 'scan_redirect' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_for_parts_pages' ) );
	}

	public static function register_assets() {
		wp_register_style( 'sps-frontend', SPS_URL . 'assets/css/frontend.css', array(), SPS_VERSION );
		wp_register_script( 'sps-frontend', SPS_URL . 'assets/js/frontend.js', array(), SPS_VERSION, true );
		wp_localize_script(
			'sps-frontend',
			'SPSFront',
			array(
				'rest' => esc_url_raw( rest_url( SPS_REST::NS . '/parts' ) ),
				'i18n' => array(
					'loading' => __( 'Loading…', 'subaru-parts-inventory' ),
					'error'   => __( 'Could not load parts. Please try again.', 'subaru-parts-inventory' ),
				),
			)
		);
	}

	public static function enqueue_for_parts_pages() {
		if ( is_singular( SPS_PT ) || is_post_type_archive( SPS_PT ) || is_tax( array( SPS_TAX_CAT, SPS_TAX_FIT ) ) ) {
			wp_enqueue_style( 'sps-frontend' );
			wp_enqueue_script( 'sps-frontend' );
		}
	}

	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			'sps-block',
			SPS_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			SPS_VERSION,
			true
		);
		$cats = array( array( 'value' => '', 'label' => __( 'All categories', 'subaru-parts-inventory' ) ) );
		if ( is_admin() ) {
			foreach ( get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'hide_empty' => false, 'parent' => 0 ) ) as $t ) {
				$cats[] = array( 'value' => $t->slug, 'label' => html_entity_decode( $t->name ) );
			}
		}
		wp_localize_script( 'sps-block', 'SPSBlock', array( 'categories' => $cats ) );
		register_block_type(
			'subaru-parts/catalog',
			array(
				'api_version'     => 2,
				'editor_script'   => 'sps-block',
				'style'           => 'sps-frontend',
				'editor_style'    => 'sps-app',
				'render_callback' => array( __CLASS__, 'shortcode' ),
				'attributes'      => array(
					'category'    => array( 'type' => 'string', 'default' => '' ),
					'condition'   => array( 'type' => 'string', 'default' => '' ),
					'per_page'    => array( 'type' => 'number', 'default' => 0 ),
					'view'        => array( 'type' => 'string', 'default' => 'grid' ),
					'filters'     => array( 'type' => 'boolean', 'default' => true ),
					'orderby'     => array( 'type' => 'string', 'default' => 'newest' ),
					'layout'      => array( 'type' => 'string', 'default' => 'app' ),
					'title'       => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}

	/**
	 * [subaru_parts category="engine-auxiliaries" condition="used" per_page="24" view="grid|table" filters="yes" orderby="newest"]
	 *
	 * @param array|string $atts Attributes.
	 */
	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'category'  => '',
				'condition' => '',
				'per_page'  => 0,
				'view'      => 'grid',
				'filters'   => true,
				'orderby'   => 'newest',
				'layout'    => 'app',
				'title'     => '',
			),
			is_array( $atts ) ? $atts : array(),
			'subaru_parts'
		);
		if ( 'grid' !== $atts['layout'] && 'table' !== $atts['layout'] ) {
			return SPS_App::render( $atts );
		}
		if ( 'table' === $atts['layout'] ) {
			$atts['view'] = 'table';
		}
		$show_filters = ! in_array( (string) $atts['filters'], array( '0', 'no', 'false', '' ), true ) || true === $atts['filters'];
		$per_page     = (int) $atts['per_page'] ? (int) $atts['per_page'] : (int) sps_setting( 'per_page' );
		$view         = 'table' === $atts['view'] ? 'table' : 'grid';

		// Shareable state from the URL.
		// phpcs:disable WordPress.Security.NonceVerification
		$state = array(
			'search'    => isset( $_GET['sps_q'] ) ? sanitize_text_field( wp_unslash( $_GET['sps_q'] ) ) : '',
			'category'  => isset( $_GET['sps_cat'] ) ? sanitize_title( wp_unslash( $_GET['sps_cat'] ) ) : (string) $atts['category'],
			'condition' => isset( $_GET['sps_cond'] ) ? sanitize_text_field( wp_unslash( $_GET['sps_cond'] ) ) : (string) $atts['condition'],
			'orderby'   => isset( $_GET['sps_sort'] ) ? sanitize_key( $_GET['sps_sort'] ) : (string) $atts['orderby'],
			'page'      => isset( $_GET['sps_page'] ) ? max( 1, absint( $_GET['sps_page'] ) ) : 1,
			'per_page'  => $per_page,
		);
		// phpcs:enable
		if ( ! in_array( $state['orderby'], array( 'newest', 'part_number', 'name', 'price' ), true ) ) {
			$state['orderby'] = 'newest';
		}
		$query = SPS_REST::query( $state );

		wp_enqueue_style( 'sps-frontend' );
		wp_enqueue_script( 'sps-frontend' );

		$fixed = array(
			'per_page' => $per_page,
			'view'     => $view,
		);
		$counts = sps_category_counts();
		ob_start();
		?>
		<div class="sps-catalog sps-catalog--<?php echo esc_attr( $view ); ?>" data-sps-catalog data-fixed="<?php echo esc_attr( wp_json_encode( $fixed ) ); ?>">
			<?php if ( $show_filters ) : ?>
			<form class="sps-filters" role="search" data-sps-filters>
				<div class="sps-filters__search">
					<?php $sps_qid = wp_unique_id( 'sps-q-' ); ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( $sps_qid ); ?>"><?php esc_html_e( 'Search parts', 'subaru-parts-inventory' ); ?></label>
					<input type="search" id="<?php echo esc_attr( $sps_qid ); ?>" name="search" value="<?php echo esc_attr( $state['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search part number or name…', 'subaru-parts-inventory' ); ?>" autocomplete="off" />
				</div>
				<select name="category" aria-label="<?php esc_attr_e( 'Category', 'subaru-parts-inventory' ); ?>">
					<option value=""><?php esc_html_e( 'All categories', 'subaru-parts-inventory' ); ?></option>
					<?php echo sps_category_options( $state['category'], false, 'slug', $counts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</select>
				<select name="condition" aria-label="<?php esc_attr_e( 'Condition', 'subaru-parts-inventory' ); ?>">
					<option value=""><?php esc_html_e( 'New & used', 'subaru-parts-inventory' ); ?></option>
					<?php foreach ( sps_conditions() as $key => $c ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $state['condition'], $key ); ?>><?php echo esc_html( $c['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="orderby" aria-label="<?php esc_attr_e( 'Sort by', 'subaru-parts-inventory' ); ?>">
					<option value="newest" <?php selected( $state['orderby'], 'newest' ); ?>><?php esc_html_e( 'Newest first', 'subaru-parts-inventory' ); ?></option>
					<option value="part_number" <?php selected( $state['orderby'], 'part_number' ); ?>><?php esc_html_e( 'Part number', 'subaru-parts-inventory' ); ?></option>
					<option value="name" <?php selected( $state['orderby'], 'name' ); ?>><?php esc_html_e( 'Name', 'subaru-parts-inventory' ); ?></option>
					<?php if ( sps_setting( 'show_prices' ) ) : ?>
						<option value="price" <?php selected( $state['orderby'], 'price' ); ?>><?php esc_html_e( 'Price', 'subaru-parts-inventory' ); ?></option>
					<?php endif; ?>
				</select>
				<button type="submit" class="sps-filters__submit"><?php esc_html_e( 'Search', 'subaru-parts-inventory' ); ?></button>
			</form>
			<?php endif; ?>
			<div class="sps-summary" data-sps-summary aria-live="polite"><?php echo self::render_summary( $query ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="sps-results" data-sps-results><?php echo self::render_results( $query, $view ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<nav class="sps-pagination" data-sps-pagination aria-label="<?php esc_attr_e( 'Parts pages', 'subaru-parts-inventory' ); ?>"><?php echo self::render_pagination( $query, $state['page'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></nav>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_summary( WP_Query $query ) {
		/* translators: %s: number of parts */
		return esc_html( sprintf( _n( '%s part', '%s parts', (int) $query->found_posts, 'subaru-parts-inventory' ), number_format_i18n( (int) $query->found_posts ) ) );
	}

	/**
	 * Result cards or table rows.
	 */
	public static function render_results( WP_Query $query, $view = 'grid' ) {
		if ( ! $query->have_posts() ) {
			return '<p class="sps-empty">' . esc_html__( 'No parts match your search.', 'subaru-parts-inventory' ) . '</p>';
		}
		$html = 'table' === $view
			? '<table class="sps-table"><thead><tr><th></th><th>' . esc_html__( 'Part number', 'subaru-parts-inventory' ) . '</th><th>' . esc_html__( 'Name', 'subaru-parts-inventory' ) . '</th><th>' . esc_html__( 'Condition', 'subaru-parts-inventory' ) . '</th><th>' . esc_html__( 'Category', 'subaru-parts-inventory' ) . '</th>' . ( sps_setting( 'show_quantity' ) ? '<th>' . esc_html__( 'Qty', 'subaru-parts-inventory' ) . '</th>' : '' ) . ( sps_setting( 'show_prices' ) ? '<th>' . esc_html__( 'Price', 'subaru-parts-inventory' ) . '</th>' : '' ) . '</tr></thead><tbody>'
			: '<ul class="sps-grid">';
		foreach ( $query->posts as $post ) {
			$d    = sps_part_data( $post->ID );
			$img  = $d['images'] ? '<img src="' . esc_url( $d['images'][0]['thumb'] ) . '" alt="' . esc_attr( $d['images'][0]['alt'] ? $d['images'][0]['alt'] : $d['name'] ) . '" loading="lazy" />' : ( ! empty( $d['catalog_image'] ) ? '<img class="sps-catalog-img" src="' . esc_url( $d['catalog_image'] ) . '" alt="' . esc_attr( $d['name'] ) . '" loading="lazy" />' : '<span class="sps-noimg" aria-hidden="true"></span>' );
			$cond = sps_condition_badge( $d['condition'] );
			if ( 'table' === $view ) {
				$html .= '<tr><td class="sps-table__img"><a href="' . esc_url( $d['url'] ) . '" tabindex="-1">' . $img . '</a></td>'
					. '<td class="sps-table__pn"><a href="' . esc_url( $d['url'] ) . '">' . esc_html( $d['part_number'] ? $d['part_number'] : '—' ) . '</a></td>'
					. '<td>' . esc_html( $d['name'] ) . '</td><td>' . $cond . '</td>'
					. '<td>' . esc_html( $d['category'] ? html_entity_decode( $d['category']['path'] ) : '' ) . '</td>'
					. ( isset( $d['quantity'] ) ? '<td>' . (int) $d['quantity'] . '</td>' : '' )
					. ( sps_setting( 'show_prices' ) ? '<td>' . esc_html( sps_money( $d['sale_price'] ) ) . '</td>' : '' )
					. '</tr>';
				continue;
			}
			$html .= '<li class="sps-card"><a class="sps-card__link" href="' . esc_url( $d['url'] ) . '">'
				. '<div class="sps-card__img">' . $img . '</div>'
				. '<div class="sps-card__body">'
				. '<div class="sps-card__top">' . $cond . ( isset( $d['quantity'] ) && $d['quantity'] > 1 ? '<span class="sps-card__qty">× ' . (int) $d['quantity'] . '</span>' : '' ) . '</div>'
				. '<div class="sps-card__pn">' . esc_html( $d['part_number'] ? $d['part_number'] : '—' ) . '</div>'
				. '<h3 class="sps-card__name">' . esc_html( $d['name'] ) . '</h3>'
				. ( $d['category'] ? '<div class="sps-card__cat">' . esc_html( html_entity_decode( $d['category']['path'] ) ) . '</div>' : '' )
				. ( sps_setting( 'show_prices' ) && '' !== $d['sale_price'] ? '<div class="sps-card__price">' . esc_html( sps_money( $d['sale_price'] ) ) . '</div>' : '' )
				. '</div></a></li>';
		}
		$html .= 'table' === $view ? '</tbody></table>' : '</ul>';
		return $html;
	}

	public static function render_pagination( WP_Query $query, $current ) {
		$pages = (int) $query->max_num_pages;
		if ( $pages < 2 ) {
			return '';
		}
		$html  = '';
		$range = array_unique( array_filter( array( 1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $pages ), static function ( $p ) use ( $pages ) {
			return $p >= 1 && $p <= $pages;
		} ) );
		sort( $range );
		$prev = 0;
		foreach ( $range as $p ) {
			if ( $prev && $p > $prev + 1 ) {
				$html .= '<span class="sps-page-gap">…</span>';
			}
			$html .= $p === (int) $current
				? '<span class="sps-page-num is-current" aria-current="page">' . (int) $p . '</span>'
				: '<a class="sps-page-num" data-page="' . (int) $p . '" href="' . esc_url( add_query_arg( 'sps_page', $p ) ) . '">' . (int) $p . '</a>';
			$prev = $p;
		}
		return $html;
	}

	/**
	 * Single part page: specs, condition and gallery added around the description.
	 *
	 * @param string $content Post content.
	 */
	public static function single_content( $content ) {
		$id = (int) get_the_ID();
		if ( ! is_singular( SPS_PT ) || $id !== (int) get_queried_object_id() || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		$d  = sps_part_data( $id );
		$rows = array(
			__( 'Part number', 'subaru-parts-inventory' )     => '<strong class="sps-pn">' . esc_html( $d['part_number'] ? $d['part_number'] : '—' ) . '</strong>',
			__( 'Alternate numbers', 'subaru-parts-inventory' ) => esc_html( $d['alt_numbers'] ),
			__( 'Condition', 'subaru-parts-inventory' )       => sps_condition_badge( $d['condition'] ) . ' ' . esc_html( $d['condition_label'] ),
			__( 'Category', 'subaru-parts-inventory' )        => $d['category'] ? '<a href="' . esc_url( get_term_link( $d['category']['id'], SPS_TAX_CAT ) ) . '">' . esc_html( html_entity_decode( $d['category']['path'] ) ) . '</a>' : '',
			__( 'Fits', 'subaru-parts-inventory' )            => esc_html( implode( ', ', $d['fitment'] ) ),
			__( 'Availability', 'subaru-parts-inventory' )    => esc_html( $d['status_label'] ),
		);
		if ( isset( $d['quantity'] ) ) {
			$rows[ __( 'Quantity', 'subaru-parts-inventory' ) ] = (int) $d['quantity'];
		}
		if ( '' !== (string) $d['mileage'] && 'new' !== $d['condition_group'] ) {
			$rows[ __( 'Mileage', 'subaru-parts-inventory' ) ] = esc_html( number_format_i18n( (int) $d['mileage'] ) . ' ' . sps_setting( 'distance_unit' ) );
		}
		if ( isset( $d['sale_price'] ) && '' !== $d['sale_price'] ) {
			$rows[ __( 'Price', 'subaru-parts-inventory' ) ] = '<strong>' . esc_html( sps_money( $d['sale_price'] ) ) . '</strong>';
		}
		if ( isset( $d['box'] ) && $d['box']['code'] ) {
			$rows[ __( 'Storage', 'subaru-parts-inventory' ) ] = esc_html( trim( $d['box']['code'] . ' ' . $d['box']['location'] ) );
		}
		$rows[ __( 'Reference', 'subaru-parts-inventory' ) ] = '<code>' . esc_html( $d['sku'] ) . '</code>';

		ob_start();
		if ( ! $d['images'] && ! empty( $d['catalog_image'] ) ) {
			$d['images'] = array( array( 'full' => $d['catalog_image'], 'thumb' => $d['catalog_image'], 'alt' => $d['name'] ) );
		}
		echo '<div class="sps-single' . ( $d['images'] ? '' : ' sps-single--noimg' ) . '">';
		if ( $d['images'] ) {
			echo '<div class="sps-gallery-view" data-sps-gallery><div class="sps-gallery-view__main"><img src="' . esc_url( $d['images'][0]['full'] ) . '" alt="' . esc_attr( $d['name'] ) . '" /></div>';
			if ( count( $d['images'] ) > 1 ) {
				echo '<ul class="sps-gallery-view__thumbs">';
				foreach ( $d['images'] as $i => $img ) {
					printf( '<li><button type="button" data-full="%1$s" class="%3$s"><img src="%2$s" alt="" loading="lazy" /></button></li>', esc_url( $img['full'] ), esc_url( $img['thumb'] ), 0 === $i ? 'is-active' : '' );
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		echo '<table class="sps-specs"><tbody>';
		foreach ( $rows as $label => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		}
		echo '</tbody></table>';
		if ( $d['condition_flags'] || $d['condition_notes'] ) {
			echo '<div class="sps-condition-box sps-condition-box--' . esc_attr( $d['condition_group'] ) . '"><h2>' . esc_html__( 'Condition notes', 'subaru-parts-inventory' ) . '</h2>';
			if ( $d['condition_flags'] ) {
				echo '<ul class="sps-flags-list"><li>' . implode( '</li><li>', array_map( 'esc_html', $d['condition_flags'] ) ) . '</li></ul>';
			}
			if ( $d['condition_notes'] ) {
				echo wp_kses_post( wpautop( esc_html( $d['condition_notes'] ) ) );
			}
			echo '</div>';
		}
		echo '</div>';
		$extra = ob_get_clean();
		return $extra . ( trim( $content ) ? '<div class="sps-description">' . $content . '</div>' : '' );
	}

	/**
	 * QR labels encode /?sps_scan=<label ID>. Managers go to the scan screen,
	 * visitors to the public page (if the part is public).
	 */
	public static function scan_redirect() {
		if ( ! isset( $_GET['sps_scan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code = sanitize_text_field( wp_unslash( $_GET['sps_scan'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( current_user_can( SPS_EDIT_CAP ) ) {
			wp_safe_redirect( add_query_arg( array( 'post_type' => SPS_PT, 'page' => 'sps-scan', 'code' => rawurlencode( $code ) ), admin_url( 'edit.php' ) ) );
			exit;
		}
		$ids = 0 === stripos( $code, 'BOX:' ) ? array() : sps_find_parts_by_code( $code );
		foreach ( $ids as $id ) {
			if ( sps_is_visible( $id ) ) {
				wp_safe_redirect( get_permalink( $id ) );
				exit;
			}
		}
		// Not public: ask the owner to log in, then continue to the scan screen.
		wp_safe_redirect( wp_login_url( add_query_arg( 'sps_scan', rawurlencode( $code ), home_url( '/' ) ) ) );
		exit;
	}
}
