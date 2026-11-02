<?php
/**
 * Dynamic block rendering for Manual Related Posts Pro.
 * Handles Classic, Same Series, and Related Content modes.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Render {

	/**
	 * Render callback for the Gutenberg block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public static function render_block( $attributes ) {
		$attributes = MRP_Helpers::sanitize_block_attributes( $attributes );
		$mode       = $attributes['mode'] ?? 'classic';
		$current_id = get_the_ID();
		$posts      = array();

		if ( 'series' === $mode ) {
			// In Same Series mode, always fetch live group members.
			$series = $current_id ? MRP_Series::get_post_series( $current_id ) : null;
			if ( $series ) {
				$posts = MRP_Series::get_series_posts( $series->term_id, $current_id );
			}

			// If not yet assigned or during block editor preview, fallback to selectedPosts.
			if ( empty( $posts ) && ! empty( $attributes['selectedPosts'] ) ) {
				$fallback_ids = array_diff( $attributes['selectedPosts'], $current_id ? array( $current_id ) : array() );
				$posts        = self::get_posts_by_ids( $fallback_ids );
			}
		} elseif ( 'spinoff' === $mode ) {
			// In Related Content mode, fetch independent spin-offs.
			if ( $current_id ) {
				$posts = MRP_Relationships::get_related_posts( $current_id );
			}
			if ( empty( $posts ) && ! empty( $attributes['selectedPosts'] ) ) {
				$fallback_ids = array_diff( $attributes['selectedPosts'], $current_id ? array( $current_id ) : array() );
				$posts        = self::get_posts_by_ids( $fallback_ids );
			}
		} else {
			// Classic mode: exactly the original selected posts behavior.
			$selected_ids = $attributes['selectedPosts'] ?? array();
			if ( ! empty( $selected_ids ) ) {
				$posts = self::get_posts_by_ids( $selected_ids );
			}
		}

		if ( empty( $posts ) ) {
			return '';
		}

		return self::render_posts_list( $posts, $attributes );
	}

	/**
	 * Retrieve published posts by IDs preserving order.
	 *
	 * @param int[] $ids Post IDs.
	 * @return WP_Post[]
	 */
	public static function get_posts_by_ids( array $ids ) {
		$sanitized_ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $sanitized_ids ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'              => MRP_Helpers::get_allowed_post_types(),
				'post__in'               => $sanitized_ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $sanitized_ids ),
				'post_status'            => 'publish',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
	}

	/**
	 * Render a responsive grid of post cards.
	 *
	 * @param WP_Post[] $posts      List of posts to display.
	 * @param array     $attributes Block/render attributes.
	 * @return string HTML output.
	 */
	public static function render_posts_list( array $posts, array $attributes ) {
		if ( empty( $posts ) ) {
			return '';
		}

		$settings           = MRP_Helpers::resolve_settings( $attributes );
		$wrapper_attributes = array(
			'class' => 'mrp-block-wrapper mrp-mode-' . sanitize_html_class( $settings['mode'] ?? 'classic' ),
			'style' => MRP_Helpers::build_wrapper_style( $settings ),
		);
		$link_target        = ! empty( $settings['openInNewTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
		$heading_tag        = in_array( $settings['sectionTitleTag'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), true ) ? $settings['sectionTitleTag'] : 'h2';

		ob_start();
		?>
		<section <?php echo get_block_wrapper_attributes( $wrapper_attributes ); ?>>
			<?php if ( ! empty( $settings['sectionTitle'] ) || ! empty( $settings['sectionSubtitle'] ) ) : ?>
				<header class="mrp-block-header">
					<?php if ( ! empty( $settings['sectionTitle'] ) ) : ?>
						<?php printf( '<%1$s class="mrp-block-heading">%2$s</%1$s>', esc_html( $heading_tag ), esc_html( $settings['sectionTitle'] ) ); ?>
					<?php endif; ?>
					<?php if ( ! empty( $settings['sectionSubtitle'] ) ) : ?>
						<p class="mrp-block-subtitle"><?php echo esc_html( $settings['sectionSubtitle'] ); ?></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<div class="mrp-grid" role="list">
				<?php foreach ( $posts as $post ) : ?>
					<?php
					$permalink = get_permalink( $post );
					$title     = get_the_title( $post );

					if ( ! $permalink || '' === $title ) {
						continue;
					}

					$excerpt = '';
					if ( ! empty( $settings['showExcerpt'] ) ) {
						$excerpt = wp_trim_words(
							has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) ),
							max( 5, absint( $settings['excerptLength'] ) ),
							'...'
						);
					}

					$categories = '';
					if ( ! empty( $settings['showCategory'] ) ) {
						$terms = get_the_terms( $post, 'category' );
						if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
							$categories = implode(
								', ',
								array_map(
									static function ( $term ) {
										return $term->name;
									},
									array_slice( $terms, 0, 2 )
								)
							);
						}
					}
					?>
					<article class="mrp-card<?php echo ! empty( $settings['fullCardLink'] ) ? ' is-clickable' : ''; ?>" role="listitem">
						<?php if ( ! empty( $settings['fullCardLink'] ) ) : ?>
							<a class="mrp-card-link-overlay" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>"<?php echo wp_kses_post( $link_target ); ?>></a>
						<?php endif; ?>

						<?php if ( ! empty( $settings['showImage'] ) && has_post_thumbnail( $post ) ) : ?>
							<div class="mrp-card-image">
								<a href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true"<?php echo wp_kses_post( $link_target ); ?>>
									<?php echo get_the_post_thumbnail( $post, $settings['imageSize'], array( 'loading' => 'lazy' ) ); ?>
								</a>
							</div>
						<?php endif; ?>

						<div class="mrp-card-body">
							<?php if ( ! empty( $settings['showDate'] ) || ! empty( $categories ) ) : ?>
								<div class="mrp-card-meta">
									<?php if ( ! empty( $settings['showDate'] ) ) : ?>
										<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time>
									<?php endif; ?>
									<?php if ( ! empty( $categories ) ) : ?>
										<span><?php echo esc_html( $categories ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<h3 class="mrp-card-title">
								<a href="<?php echo esc_url( $permalink ); ?>"<?php echo wp_kses_post( $link_target ); ?>><?php echo esc_html( $title ); ?></a>
							</h3>

							<?php if ( ! empty( $excerpt ) ) : ?>
								<p class="mrp-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>

							<?php if ( ! empty( $settings['showButton'] ) && ! empty( $settings['buttonText'] ) ) : ?>
								<div class="mrp-card-actions">
									<a class="mrp-card-button" href="<?php echo esc_url( $permalink ); ?>"<?php echo wp_kses_post( $link_target ); ?>>
										<?php echo esc_html( $settings['buttonText'] ); ?>
									</a>
								</div>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
}
