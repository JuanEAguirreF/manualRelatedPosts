<?php
/**
 * Block registration and editor REST support for Manual Related Posts Pro.
 * Includes capability and security checks to prevent unauthorized data exposure or modification.
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
				'defaults'        => MRP_Helpers::get_plugin_settings() + MRP_Helpers::get_hardcoded_defaults(),
				'restUrl'         => esc_url_raw( rest_url( 'mrp/v1/posts' ) ),
				'seriesCheckUrl'  => esc_url_raw( rest_url( 'mrp/v1/series-check' ) ),
				'unlinkSeriesUrl' => esc_url_raw( rest_url( 'mrp/v1/unlink-series' ) ),
				'relinkSeriesUrl' => esc_url_raw( rest_url( 'mrp/v1/relink-series' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'postTypes'       => MRP_Helpers::get_allowed_post_types(),
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
		wp_enqueue_style( 'mrp-style' );
		wp_enqueue_style( 'mrp-admin-settings', MRP_PLUGIN_URL . 'assets/css/admin-settings.css', array( 'wp-color-picker', 'mrp-style' ), MRP_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'mrp-admin-preview', MRP_PLUGIN_URL . 'assets/js/admin-settings-preview.js', array( 'jquery', 'wp-color-picker' ), MRP_VERSION, true );
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

		register_rest_route(
			'mrp/v1',
			'/series-check',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_series_check_endpoint' ),
					'permission_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
					'args'                => array(
						'ids' => array( 'type' => 'array', 'required' => true ),
					),
				),
			)
		);

		register_rest_route(
			'mrp/v1',
			'/unlink-series',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_unlink_series_endpoint' ),
					'permission_callback' => static function ( WP_REST_Request $request ) {
						$post_id = absint( $request->get_param( 'id' ) );
						return current_user_can( 'edit_post', $post_id );
					},
					'args'                => array(
						'id' => array( 'type' => 'integer', 'required' => true ),
					),
				),
			)
		);

		register_rest_route(
			'mrp/v1',
			'/relink-series',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_relink_series_endpoint' ),
					'permission_callback' => static function ( WP_REST_Request $request ) {
						$ids = $request->get_param( 'ids' );
						if ( ! is_array( $ids ) ) {
							$single_id = absint( $request->get_param( 'id' ) );
							$ids = $single_id ? array( $single_id ) : array();
						}
						foreach ( $ids as $id ) {
							if ( ! current_user_can( 'edit_post', absint( $id ) ) ) {
								return false;
							}
						}
						return ! empty( $ids );
					},
					'args'                => array(
						'ids'       => array( 'type' => 'array', 'required' => false ),
						'id'        => array( 'type' => 'integer', 'required' => false ),
						'series_id' => array( 'type' => 'integer', 'required' => true ),
					),
				),
			)
		);
	}

	public static function handle_posts_endpoint( WP_REST_Request $request ) {
		$search  = trim( (string) $request->get_param( 'search' ) );
		$ids     = $request->get_param( 'ids' );
		$results = array();

		// Security: Restrict statuses so authors cannot see other users' private posts or drafts.
		$can_edit_others = current_user_can( 'edit_others_posts' );
		$allowed_statuses = $can_edit_others
			? array( 'publish', 'draft', 'pending', 'future', 'private' )
			: array( 'publish' );

		if ( '' !== $search ) {
			$query_args = array(
				'post_type'              => MRP_Helpers::get_allowed_post_types(),
				's'                      => $search,
				'posts_per_page'         => 10,
				'post_status'            => $allowed_statuses,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			);

			// If author without edit_others_posts, also include their own drafts.
			if ( ! $can_edit_others && is_user_logged_in() ) {
				$query_args['author'] = get_current_user_id();
				$query_args['post_status'] = array( 'publish', 'draft', 'pending' );
			}

			$query = new WP_Query( $query_args );

			foreach ( $query->posts as $post ) {
				$results[] = self::prepare_post_response_item( $post );
			}
		}

		$selected = array();
		if ( is_array( $ids ) && ! empty( $ids ) ) {
			$sanitized_ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
			if ( ! empty( $sanitized_ids ) ) {
				$posts = get_posts(
					array(
						'post_type'              => MRP_Helpers::get_allowed_post_types(),
						'post__in'               => $sanitized_ids,
						'orderby'                => 'post__in',
						'posts_per_page'         => count( $sanitized_ids ),
						'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
						'no_found_rows'          => true,
						'ignore_sticky_posts'    => true,
						'update_post_meta_cache' => true,
						'update_post_term_cache' => true,
					)
				);

				foreach ( $posts as $post ) {
					// Verify view capability for non-published posts.
					if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
						continue;
					}
					$selected[] = self::prepare_post_response_item( $post );
				}
			}
		}

		return rest_ensure_response(
			array(
				'results'  => $results,
				'selected' => $selected,
			)
		);
	}

	public static function handle_series_check_endpoint( WP_REST_Request $request ) {
		$ids             = $request->get_param( 'ids' );
		$current_post_id = absint( $request->get_param( 'postId' ) );

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return rest_ensure_response(
				array(
					'detected'      => false,
					'seriesList'    => array(),
					'hasConflict'   => false,
					'members'       => array(),
					'unlinkedPosts' => array(),
				)
			);
		}

		$sanitized_ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$series_map    = array();

		foreach ( $sanitized_ids as $pid ) {
			$series = MRP_Series::get_post_series( $pid );
			if ( $series ) {
				$term_id = (int) $series->term_id;
				if ( ! isset( $series_map[ $term_id ] ) ) {
					$series_map[ $term_id ] = array(
						'id'       => $term_id,
						'name'     => $series->name,
						'postIds'  => array( $pid ),
					);
				} else {
					$series_map[ $term_id ]['postIds'][] = $pid;
				}
			}
		}

		$series_list    = array_values( $series_map );
		$has_conflict   = count( $series_list ) > 1;
		$members        = array();
		$unlinked_posts = array();

		if ( 1 === count( $series_list ) ) {
			$detected_term_id = $series_list[0]['id'];
			$all_in_series    = MRP_Series::get_series_posts( $detected_term_id );
			foreach ( $all_in_series as $p ) {
				$members[] = self::prepare_post_response_item( $p );
			}

			// Check if current post or any selected post was previously unlinked from this detected series.
			$posts_to_check = $sanitized_ids;
			if ( $current_post_id ) {
				$posts_to_check[] = $current_post_id;
			}
			$posts_to_check = array_values( array_unique( $posts_to_check ) );

			foreach ( $posts_to_check as $pid ) {
				$unlinked_from = MRP_Series::get_unlinked_series_ids( $pid );
				if ( in_array( $detected_term_id, $unlinked_from, true ) ) {
					$post_obj = get_post( $pid );
					$unlinked_posts[] = array(
						'id'    => $pid,
						'title' => $post_obj ? html_entity_decode( get_the_title( $post_obj ), ENT_QUOTES, get_bloginfo( 'charset' ) ) : sprintf( __( 'Capítulo #%d', 'manual-related-posts-pro' ), $pid ),
					);
				}
			}
		}

		return rest_ensure_response(
			array(
				'detected'      => ! empty( $series_list ),
				'seriesList'    => $series_list,
				'hasConflict'   => $has_conflict,
				'members'       => $members,
				'unlinkedPosts' => $unlinked_posts,
			)
		);
	}

	public static function handle_unlink_series_endpoint( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'id' ) );
		if ( ! $post_id ) {
			return new WP_Error( 'invalid_id', __( 'Invalid post ID.', 'manual-related-posts-pro' ), array( 'status' => 400 ) );
		}

		$success = MRP_Series::unlink_post_from_series( $post_id );

		return rest_ensure_response(
			array(
				'success' => $success,
				'message' => $success
					? __( 'Post unlinked from series successfully.', 'manual-related-posts-pro' )
					: __( 'Post is not part of any series.', 'manual-related-posts-pro' ),
			)
		);
	}

	public static function handle_relink_series_endpoint( WP_REST_Request $request ) {
		$series_id = absint( $request->get_param( 'series_id' ) );
		$ids       = $request->get_param( 'ids' );
		if ( ! is_array( $ids ) ) {
			$single_id = absint( $request->get_param( 'id' ) );
			$ids = $single_id ? array( $single_id ) : array();
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) || ! $series_id ) {
			return new WP_Error( 'invalid_params', __( 'Parámetros inválidos.', 'manual-related-posts-pro' ), array( 'status' => 400 ) );
		}

		foreach ( $ids as $pid ) {
			MRP_Series::clear_unlinked_series_flag( $pid, $series_id );
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'relinked_ids' => $ids,
				'message'      => __( 'Capítulos listos para ser reincorporados a la serie.', 'manual-related-posts-pro' ),
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

		$series = MRP_Series::get_post_series( $post->ID );
		$ep     = get_post_meta( $post->ID, MRP_Series::META_EPISODE, true );

		return array(
			'id'            => (int) $post->ID,
			'title'         => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'status'        => $post->post_status,
			'type'          => get_post_type_object( $post->post_type )->labels->singular_name ?? $post->post_type,
			'date'          => get_the_date( '', $post ),
			'permalink'     => get_permalink( $post ),
			'excerpt'       => wp_trim_words( has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 18, '...' ),
			'image'         => $thumbnail,
			'series'        => $series ? array(
				'id'   => (int) $series->term_id,
				'name' => $series->name,
			) : null,
			'episodeNumber' => ( '' !== $ep && false !== $ep ) ? (string) $ep : '',
		);
	}
}
