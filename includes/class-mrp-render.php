<?php
/**
 * Dynamic block rendering.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Render {
	public static function render_block( $attributes ) {
		$attributes   = MRP_Helpers::sanitize_block_attributes( $attributes );
		$selected_ids = $attributes['selectedPosts'] ?? array();

		if ( empty( $selected_ids ) ) {
			return '';
		}

		$settings = MRP_Helpers::resolve_settings( $attributes );
		$posts    = get_posts(
			array(
				'post_type'              => MRP_Helpers::get_allowed_post_types(),
				'post__in'               => $selected_ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $selected_ids ),
				'post_status'            => 'publish',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $posts ) ) {
			return '';
		}

		$wrapper_attributes = array(
			'class' => 'mrp-block-wrapper',
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