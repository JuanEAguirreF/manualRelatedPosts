<?php
/**
 * Block registration and editor REST support.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Block {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function register_block() {
		wp_register_style( 'mrp-style', MRP_PLUGIN_URL . 'assets/css/style.css', array(), MRP_VERSION );
		wp_register_style( 'mrp-editor-style', MRP_PLUGIN_URL . 'assets/css/editor.css', array( 'mrp-style', 'wp-edit-blocks' ), MRP_VERSION );

		wp_register_script(
			'mrp-editor-script',
			MRP_PLUGIN_URL . 'build/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
			MRP_VERSION,
			true
		);

		wp_set_script_translations( 'mrp-editor-script', 'manual-related-posts-pro' );
		wp_localize_script(
			'mrp-editor-script',
			'mrpBlockData',
			array(
				'defaults'  => MRP_Helpers::get_plugin_settings() + MRP_Helpers::get_hardcoded_defaults(),
				'restUrl'   => esc_url_raw( rest_url( 'mrp/v1/posts' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'postTypes' => MRP_Helpers::get_allowed_post_types(),
			)
		);

		register_block_type(
			MRP_PLUGIN_DIR . 'block.json',
			array(
				'render_callback' => array( 'MRP_Render', 'render_block' ),
			)
		);
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_mrp-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".mrp-color-field").wpColorPicker();});' );
	}

	public static function register_rest_routes() {
		register_rest_route(
			'mrp/v1',
			'/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_posts_endpoint' ),
					'permission_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
					'args'                => array(
						'search' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
						'ids'    => array( 'type' => 'array' ),
					),
				),
			)
		);
	}

	public static function handle_posts_endpoint( WP_REST_Request $request ) {
		$search  = trim( (string) $request->get_param( 'search' ) );
		$ids     = $request->get_param( 'ids' );
		$results = array();

		if ( '' !== $search ) {
			$query = new WP_Query(
				array(
					'post_type'              => MRP_Helpers::get_allowed_post_types(),
					's'                      => $search,
					'posts_per_page'         => 10,
					'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $query->posts as $post ) {
				$results[] = self::prepare_post_response_item( $post );
			}
		}

		$selected = array();
		if ( is_array( $ids ) && ! empty( $ids ) ) {
			$sanitized_ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
			$posts         = get_posts(
				array(
					'post_type'              => MRP_Helpers::get_allowed_post_types(),
					'post__in'               => $sanitized_ids,
					'orderby'                => 'post__in',
					'posts_per_page'         => count( $sanitized_ids ),
					'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $posts as $post ) {
				$selected[] = self::prepare_post_response_item( $post );
			}
		}

		return rest_ensure_response(
			array(
				'results'  => $results,
				'selected' => $selected,
			)
		);
	}

	private static function prepare_post_response_item( $post ) {
		$thumbnail = '';
		if ( has_post_thumbnail( $post ) ) {
			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'thumbnail' );
			if ( is_array( $image ) ) {
				$thumbnail = $image[0];
			}
		}

		return array(
			'id'        => (int) $post->ID,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'status'    => $post->post_status,
			'type'      => get_post_type_object( $post->post_type )->labels->singular_name ?? $post->post_type,
			'date'      => get_the_date( '', $post ),
			'permalink' => get_permalink( $post ),
			'excerpt'   => wp_trim_words( has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 18, '...' ),
			'image'     => $thumbnail,
		);
	}
}