<?php
/**
 * Auto-render chapter cards and related spin-offs in posts without requiring a manual block.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Auto_Render {

	public static function init() {
		// Priority 20 ensures formatting (wpautop, shortcodes) has run.
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
	}

	/**
	 * Append series chapter grid and/or related spin-offs grid to post content if applicable.
	 *
	 * @param string $content Original post content.
	 * @return string Modified content.
	 */
	public static function filter_content( $content ) {
		// Guard against admin, feeds, REST requests, and XML-RPC.
		if ( is_admin() || is_feed() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return $content;
		}

		// Ensure we are inside the main query loop of a singular view of an allowed post type.
		if ( ! is_singular( MRP_Helpers::get_allowed_post_types() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		global $post;
		if ( ! $post || empty( $post->ID ) ) {
			return $content;
		}

		$global_settings = MRP_Helpers::get_plugin_settings();

		// Parse blocks to detect existing series or spin-off blocks in post_content.
		$has_series_block  = false;
		$has_spinoff_block = false;

		if ( has_blocks( $post->post_content ) ) {
			$blocks = parse_blocks( $post->post_content );
			$mrp_blocks = MRP_Series::find_all_mrp_blocks( $blocks );
			foreach ( $mrp_blocks as $b ) {
				$mode = $b['attrs']['mode'] ?? 'classic';
				if ( 'series' === $mode ) {
					$has_series_block = true;
				} elseif ( 'spinoff' === $mode ) {
					$has_spinoff_block = true;
				}
			}
		}

		$extra_html = '';

		// 1. Auto-render Same Series chapters if post belongs to a series and has no series block.
		$auto_render_series = isset( $global_settings['autoRenderSeries'] ) ? (bool) $global_settings['autoRenderSeries'] : true;
		if ( $auto_render_series && ! $has_series_block ) {
			$series = MRP_Series::get_post_series( $post->ID );
			if ( $series ) {
				$sibling_posts = MRP_Series::get_series_posts( $series->term_id, $post->ID );
				if ( ! empty( $sibling_posts ) ) {
					$custom_title = ! empty( $global_settings['seriesSectionTitle'] )
						? $global_settings['seriesSectionTitle']
						: sprintf( __( 'Episodes of %s', 'manual-related-posts-pro' ), $series->name );

					$render_attributes = array(
						'mode'         => 'series',
						'sectionTitle' => $custom_title,
					);

					$series_html = MRP_Render::render_posts_list( $sibling_posts, $render_attributes );
					if ( ! empty( $series_html ) ) {
						$series_html = apply_filters( 'mrp_auto_render_series_html', $series_html, $post, $series, $sibling_posts );
						$extra_html .= "\n\n" . $series_html;
					}
				}
			}
		}

		// 2. Auto-render Related Content (Spin-offs) if post has related items and no spinoff block.
		$auto_render_spinoffs = isset( $global_settings['autoRenderSpinoffs'] ) ? (bool) $global_settings['autoRenderSpinoffs'] : true;
		if ( $auto_render_spinoffs && ! $has_spinoff_block ) {
			$related_posts = MRP_Relationships::get_related_posts( $post->ID );
			if ( ! empty( $related_posts ) ) {
				$spinoff_title = ! empty( $global_settings['spinoffSectionTitle'] )
					? $global_settings['spinoffSectionTitle']
					: __( 'Related Content & Spin-offs', 'manual-related-posts-pro' );

				$render_attributes = array(
					'mode'         => 'spinoff',
					'sectionTitle' => $spinoff_title,
				);

				$spinoff_html = MRP_Render::render_posts_list( $related_posts, $render_attributes );
				if ( ! empty( $spinoff_html ) ) {
					$spinoff_html = apply_filters( 'mrp_auto_render_spinoff_html', $spinoff_html, $post, $related_posts );
					$extra_html .= "\n\n" . $spinoff_html;
				}
			}
		}

		if ( empty( $extra_html ) ) {
			return $content;
		}

		$extra_html = apply_filters( 'mrp_auto_render_html', $extra_html, $post );

		return $content . $extra_html;
	}
}
