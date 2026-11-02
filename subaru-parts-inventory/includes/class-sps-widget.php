<?php
/**
 * Sidebar widget: part search, stock stats, categories and latest parts.
 *
 * @package SubaruPartsInventory
 */

defined( 'ABSPATH' ) || exit;

class SPS_Widget extends WP_Widget {

	public static function init() {
		add_action(
			'widgets_init',
			static function () {
				register_widget( __CLASS__ );
			}
		);
	}

	public function __construct() {
		parent::__construct(
			'sps_parts_widget',
			__( 'Subaru Parts Inventory', 'subaru-parts-inventory' ),
			array(
				'description'                 => __( 'Search box, stock counters, categories and latest parts from your Subaru parts inventory.', 'subaru-parts-inventory' ),
				'customize_selective_refresh' => true,
				'show_instance_in_rest'       => true,
			)
		);
	}

	private function defaults() {
		return array(
			'title'      => __( 'Subaru parts', 'subaru-parts-inventory' ),
			'search'     => 1,
			'stats'      => 1,
			'categories' => 1,
			'latest'     => 5,
		);
	}

	public function widget( $args, $instance ) {
		$i = wp_parse_args( (array) $instance, $this->defaults() );
		wp_enqueue_style( 'sps-frontend' );
		$catalog = (int) sps_setting( 'catalog_page_id' ) ? get_permalink( (int) sps_setting( 'catalog_page_id' ) ) : get_post_type_archive_link( SPS_PT );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $i['title'] ) {
			echo $args['before_title'] . esc_html( apply_filters( 'widget_title', $i['title'], $i, $this->id_base ) ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="sps-widget">';

		if ( $i['search'] && $catalog ) {
			$uses_page = (bool) (int) sps_setting( 'catalog_page_id' );
			echo '<form class="sps-widget__search" method="get" action="' . esc_url( $catalog ) . '">';
			echo '<input type="search" name="' . ( $uses_page ? 'sps_q' : 's' ) . '" placeholder="' . esc_attr__( 'Part number or name…', 'subaru-parts-inventory' ) . '" />';
			if ( ! $uses_page ) {
				echo '<input type="hidden" name="post_type" value="' . esc_attr( SPS_PT ) . '" />';
			}
			echo '<button type="submit">' . esc_html__( 'Find', 'subaru-parts-inventory' ) . '</button></form>';
		}

		if ( $i['stats'] ) {
			$counts = $this->public_counts();
			echo '<ul class="sps-widget__stats">';
			echo '<li><strong>' . esc_html( number_format_i18n( $counts['all'] ) ) . '</strong> ' . esc_html__( 'parts', 'subaru-parts-inventory' ) . '</li>';
			echo '<li><strong>' . esc_html( number_format_i18n( $counts['new'] ) ) . '</strong> ' . esc_html__( 'new', 'subaru-parts-inventory' ) . '</li>';
			echo '<li><strong>' . esc_html( number_format_i18n( $counts['used'] ) ) . '</strong> ' . esc_html__( 'used', 'subaru-parts-inventory' ) . '</li>';
			echo '</ul>';
		}

		if ( $i['categories'] ) {
			$counts = sps_category_counts();
			$tops   = get_terms( array( 'taxonomy' => SPS_TAX_CAT, 'parent' => 0, 'hide_empty' => false ) );
			echo '<ul class="sps-widget__cats">';
			foreach ( (array) $tops as $term ) {
				if ( empty( $counts[ $term->term_id ] ) ) {
					continue;
				}
				$link = (int) sps_setting( 'catalog_page_id' ) ? add_query_arg( 'sps_cat', $term->slug, $catalog ) : get_term_link( $term );
				printf( '<li><a href="%1$s">%2$s</a> <span>%3$d</span></li>', esc_url( $link ), esc_html( $term->name ), (int) $counts[ $term->term_id ] );
			}
			echo '</ul>';
		}

		if ( (int) $i['latest'] > 0 ) {
			$latest = new WP_Query(
				array(
					'post_type'      => SPS_PT,
					'post_status'    => 'publish',
					'posts_per_page' => (int) $i['latest'],
					'no_found_rows'  => true,
					'meta_key'       => '_sps_visible', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			if ( $latest->have_posts() ) {
				echo '<h4 class="sps-widget__subtitle">' . esc_html__( 'Latest parts', 'subaru-parts-inventory' ) . '</h4><ul class="sps-widget__latest">';
				foreach ( $latest->posts as $p ) {
					printf(
						'<li><a href="%1$s">%2$s<span class="sps-widget__pn">%3$s</span> <span class="sps-widget__name">%4$s</span></a> %5$s</li>',
						esc_url( get_permalink( $p ) ),
						get_the_post_thumbnail( $p, array( 40, 40 ) ), // phpcs:ignore WordPress.Security.EscapeOutput
						esc_html( sps_meta( $p->ID, 'part_number' ) ),
						esc_html( get_the_title( $p ) ),
						sps_condition_badge( sps_meta( $p->ID, 'condition' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
					);
				}
				echo '</ul>';
			}
		}
		if ( $catalog ) {
			echo '<p class="sps-widget__all"><a href="' . esc_url( $catalog ) . '">' . esc_html__( 'Browse all parts →', 'subaru-parts-inventory' ) . '</a></p>';
		}
		echo '</div>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private function public_counts() {
		$cached = get_transient( 'sps_public_counts' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$out = array( 'all' => 0, 'new' => 0, 'used' => 0 );
		$ids = get_posts( array( 'post_type' => SPS_PT, 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_sps_visible', 'meta_value' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$c   = sps_conditions();
		foreach ( $ids as $id ) {
			$out['all']++;
			$g = $c[ sps_meta( $id, 'condition', 'used_b' ) ]['group'] ?? 'used';
			$out[ 'new' === $g ? 'new' : 'used' ]++;
		}
		set_transient( 'sps_public_counts', $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	public function form( $instance ) {
		$i = wp_parse_args( (array) $instance, $this->defaults() );
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'subaru-parts-inventory' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $i['title'] ); ?>" /></p>
		<?php foreach ( array( 'search' => __( 'Show search box', 'subaru-parts-inventory' ), 'stats' => __( 'Show stock counters', 'subaru-parts-inventory' ), 'categories' => __( 'Show categories', 'subaru-parts-inventory' ) ) as $key => $label ) : ?>
			<p><label><input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" value="1" <?php checked( $i[ $key ], 1 ); ?> /> <?php echo esc_html( $label ); ?></label></p>
		<?php endforeach; ?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'latest' ) ); ?>"><?php esc_html_e( 'Latest parts to show', 'subaru-parts-inventory' ); ?></label>
		<input type="number" min="0" max="20" class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'latest' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'latest' ) ); ?>" value="<?php echo (int) $i['latest']; ?>" /></p>
		<?php
	}

	public function update( $new, $old ) {
		return array(
			'title'      => sanitize_text_field( $new['title'] ?? '' ),
			'search'     => empty( $new['search'] ) ? 0 : 1,
			'stats'      => empty( $new['stats'] ) ? 0 : 1,
			'categories' => empty( $new['categories'] ) ? 0 : 1,
			'latest'     => max( 0, min( 20, (int) ( $new['latest'] ?? 5 ) ) ),
		);
	}
}
