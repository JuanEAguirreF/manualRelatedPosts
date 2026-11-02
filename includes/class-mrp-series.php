<?php
/**
 * Series management for Manual Related Posts Pro.
 * Handles the non-public taxonomy, episode ordering, membership propagation,
 * strict merger blocking, persistent unlinking, and atomic permission checks.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Series {

	const TAXONOMY              = 'mrp_series';
	const META_EPISODE          = '_mrp_episode_number';
	const META_UNLINKED         = '_mrp_unlinked_series';
	const META_HAS_SERIES_BLOCK = '_mrp_has_series_block';
	const META_SYNC_STATUS      = '_mrp_last_sync_status';

	/**
	 * Guard against recursive execution during save hooks.
	 *
	 * @var bool
	 */
	private static $is_syncing = false;

	public static function init() {
		// Register internal taxonomy early on init.
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 6 );

		// Hook save action after meta and terms are updated.
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_after_insert_post' ), 20, 3 );

		// Admin notices for sync errors.
		add_action( 'admin_notices', array( __CLASS__, 'display_admin_sync_notices' ) );

		// Status transitions & deletion invalidation.
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 20, 3 );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_trash_or_delete_post' ), 20 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_trash_or_delete_post' ), 20 );
		add_action( 'untrashed_post', array( __CLASS__, 'on_trash_or_delete_post' ), 20 );
	}

	/**
	 * Register the non-public taxonomy mrp_series.
	 */
	public static function register_taxonomy() {
		$post_types = MRP_Helpers::get_allowed_post_types();

		register_taxonomy(
			self::TAXONOMY,
			$post_types,
			array(
				'labels'            => array(
					'name'          => __( 'MRP Series', 'manual-related-posts-pro' ),
					'singular_name' => __( 'MRP Series', 'manual-related-posts-pro' ),
				),
				'public'            => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_tagcloud'     => false,
				'show_in_rest'      => false,
				'hierarchical'      => false,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/**
	 * Register post meta for episode numbering and persistent unlinking tracking.
	 */
	public static function register_meta() {
		$post_types = MRP_Helpers::get_allowed_post_types();

		foreach ( $post_types as $pt ) {
			register_post_meta(
				$pt,
				self::META_EPISODE,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);

			register_post_meta(
				$pt,
				self::META_UNLINKED,
				array(
					'show_in_rest'  => false,
					'single'        => true,
					'type'          => 'array',
					'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);

			register_post_meta(
				$pt,
				self::META_HAS_SERIES_BLOCK,
				array(
					'show_in_rest'  => false,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);

			register_post_meta(
				$pt,
				self::META_SYNC_STATUS,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Get the single series term associated with a post, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Term|null
	 */
	public static function get_post_series( $post_id ) {
		$terms = get_the_terms( $post_id, self::TAXONOMY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		return reset( $terms );
	}

	/**
	 * Get list of series term IDs from which this post was explicitly unlinked.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public static function get_unlinked_series_ids( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_UNLINKED, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
	}

	/**
	 * Mark a post as explicitly unlinked from a specific series term.
	 *
	 * @param int $post_id Post ID.
	 * @param int $term_id Series term ID.
	 * @return void
	 */
	public static function add_unlinked_series_flag( $post_id, $term_id ) {
		$current = self::get_unlinked_series_ids( $post_id );
		if ( ! in_array( (int) $term_id, $current, true ) ) {
			$current[] = (int) $term_id;
			update_post_meta( $post_id, self::META_UNLINKED, $current );
		}
	}

	/**
	 * Clear unlinked series flags (e.g. when editor explicitly assigns post to that series).
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $term_id Specific term ID or null to clear all.
	 * @return void
	 */
	public static function clear_unlinked_series_flag( $post_id, $term_id = null ) {
		if ( null === $term_id ) {
			delete_post_meta( $post_id, self::META_UNLINKED );
			return;
		}

		$current = self::get_unlinked_series_ids( $post_id );
		$updated = array_values( array_diff( $current, array( (int) $term_id ) ) );
		update_post_meta( $post_id, self::META_UNLINKED, $updated );
	}

	/**
	 * Get all published posts in a series, sorted by episode number and date.
	 * Supports large series without arbitrary 100-limit (configurable via filter).
	 *
	 * @param int $term_id    Series term ID.
	 * @param int $exclude_id Optional post ID to exclude from results.
	 * @return WP_Post[]
	 */
	public static function get_series_posts( $term_id, $exclude_id = 0 ) {
		$term_id = absint( $term_id );
		if ( ! $term_id ) {
			return array();
		}

		$max_posts = apply_filters( 'mrp_max_series_posts', -1 );

		$query_args = array(
			'post_type'              => MRP_Helpers::get_allowed_post_types(),
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $max_posts,
			'tax_query'              => array(
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'ignore_sticky_posts'    => true,
		);

		if ( $exclude_id ) {
			$query_args['post__not_in'] = array( absint( $exclude_id ) );
		}

		$posts = get_posts( $query_args );

		if ( empty( $posts ) ) {
			return array();
		}

		// Sort by episode number (numeric) ASC, fallback to post_date ASC, then ID ASC.
		usort(
			$posts,
			static function ( $a, $b ) {
				$ep_a = get_post_meta( $a->ID, self::META_EPISODE, true );
				$ep_b = get_post_meta( $b->ID, self::META_EPISODE, true );

				$has_a = ( '' !== $ep_a && false !== $ep_a );
				$has_b = ( '' !== $ep_b && false !== $ep_b );

				if ( $has_a && $has_b ) {
					$num_a = (float) $ep_a;
					$num_b = (float) $ep_b;
					if ( $num_a !== $num_b ) {
						return ( $num_a < $num_b ) ? -1 : 1;
					}
				} elseif ( $has_a && ! $has_b ) {
					return -1;
				} elseif ( ! $has_a && $has_b ) {
					return 1;
				}

				// Fallback to post date.
				$time_a = strtotime( $a->post_date_gmt ?: $a->post_date );
				$time_b = strtotime( $b->post_date_gmt ?: $b->post_date );
				if ( $time_a !== $time_b ) {
					return ( $time_a < $time_b ) ? -1 : 1;
				}

				return ( $a->ID < $b->ID ) ? -1 : 1;
			}
		);

		return $posts;
	}

	/**
	 * Detect if a list of post IDs spans multiple distinct series terms.
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @return int[] Array of distinct term IDs found.
	 */
	public static function get_distinct_series_ids( array $post_ids ) {
		$series_ids = array();

		foreach ( $post_ids as $pid ) {
			$series = self::get_post_series( $pid );
			if ( $series && ! in_array( (int) $series->term_id, $series_ids, true ) ) {
				$series_ids[] = (int) $series->term_id;
			}
		}

		return $series_ids;
	}

	/**
	 * Assign a post to a series term and set its episode number.
	 * Validates permissions before altering.
	 *
	 * @param int         $post_id        Post ID.
	 * @param int         $term_id        Series term ID.
	 * @param string|null $episode_number Optional episode number.
	 * @return bool
	 */
	public static function assign_post_to_series( $post_id, $term_id, $episode_number = null ) {
		$post_id = absint( $post_id );
		$term_id = absint( $term_id );

		if ( ! $post_id || ! $term_id ) {
			return false;
		}

		// Security: verify user capability on target post when called in user context.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'current_user_can' ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		// Enforce single series membership per post and strictly verify WordPress term assignment result.
		$term_result = wp_set_object_terms( $post_id, array( $term_id ), self::TAXONOMY, false );
		if ( is_wp_error( $term_result ) || empty( $term_result ) ) {
			return false;
		}

		// Clear unlinked flag for this series term ONLY after wp_set_object_terms succeeded!
		self::clear_unlinked_series_flag( $post_id, $term_id );

		if ( null !== $episode_number && '' !== $episode_number ) {
			update_post_meta( $post_id, self::META_EPISODE, sanitize_text_field( (string) $episode_number ) );
		}

		return true;
	}

	/**
	 * Completely unlink a post from its current series with persistent tracking.
	 *
	 * @param int $post_id Post ID to unlink.
	 * @return bool True if unlinked, false if wasn't in a series.
	 */
	public static function unlink_post_from_series( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		$series = self::get_post_series( $post_id );
		if ( ! $series ) {
			return false;
		}

		$term_id = (int) $series->term_id;

		// Security check: verify user permission.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'current_user_can' ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		// Remove series taxonomy relationship and verify WordPress result.
		if ( function_exists( 'wp_remove_object_terms' ) ) {
			$rem_result = wp_remove_object_terms( $post_id, $term_id, self::TAXONOMY );
		} elseif ( function_exists( 'wp_set_object_terms' ) ) {
			$rem_result = wp_set_object_terms( $post_id, array(), self::TAXONOMY, false );
		}

		if ( is_wp_error( $rem_result ) ) {
			return false;
		}

		// Clear episode number and series block tracking flag.
		delete_post_meta( $post_id, self::META_EPISODE );
		delete_post_meta( $post_id, self::META_HAS_SERIES_BLOCK );

		// Record persistent unlinking flag so re-saving other chapters won't re-add this post!
		self::add_unlinked_series_flag( $post_id, $term_id );

		// Invalidate cache for this post and all former siblings in the series.
		$siblings     = self::get_series_posts( $term_id );
		$affected_ids = array_map( static function ( $p ) { return (int) $p->ID; }, $siblings );
		$affected_ids[] = $post_id;

		MRP_Cache::invalidate_posts( array_values( array_unique( $affected_ids ) ) );

		return true;
	}

	/**
	 * Create a new series term with a unique slug to guarantee series identity.
	 * Two independent series with identical titles NEVER share a group.
	 *
	 * @param string $title          Series title.
	 * @param int    $origin_post_id Originating post ID.
	 * @return int|WP_Error Term ID on success.
	 */
	public static function create_series_term( $title, $origin_post_id = 0 ) {
		$title = trim( $title );
		if ( '' === $title ) {
			$title = sprintf( __( 'Series #%d', 'manual-related-posts-pro' ), $origin_post_id ?: mt_rand( 100, 9999 ) );
		}

		// Unique slug ensures series with identical titles never merge into the same term.
		$base_slug = sanitize_title( $title );
		$unique_slug = $base_slug . '-' . ( $origin_post_id ?: 'series' ) . '-' . substr( md5( wp_generate_uuid4() ), 0, 8 );

		$result = wp_insert_term( $title, self::TAXONOMY, array( 'slug' => $unique_slug ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return (int) $result['term_id'];
	}

	/**
	 * Synchronize series membership when a post with a series block is saved.
	 * Validates permissions upfront atomically: if any affected post cannot be edited,
	 * the operation is rejected before making any modifications.
	 *
	 * Persistent unlinking: If any selected post (or the current post) was previously
	 * unlinked from this series, it is NOT silently ignored; it returns an explicit error
	 * requiring confirmation. If $relink_confirmed is true, it is reincorporated.
	 *
	 * @param int    $post_id          The post being saved.
	 * @param int[]  $selected_ids     Posts selected in the block.
	 * @param string $episode_number   Episode number for this post.
	 * @param string $series_title     Custom series title if creating a new one.
	 * @param bool   $relink_confirmed Explicit confirmation to re-link previously unlinked posts.
	 * @return array{success: bool, series_id: int, affected_ids: int[], conflict: bool, error: string, unlinked_post_ids?: int[], message?: string}
	 */
	public static function sync_post_series( $post_id, array $selected_ids, $episode_number = '', $series_title = '', $relink_confirmed = false ) {
		if ( self::$is_syncing ) {
			return array(
				'success'      => false,
				'series_id'    => 0,
				'affected_ids' => array(),
				'conflict'     => false,
				'error'        => 'recursive_call',
			);
		}

		self::$is_syncing = true;

		$post_id      = absint( $post_id );
		$selected_ids = array_values( array_unique( array_filter( array_map( 'absint', $selected_ids ) ) ) );

		// Remove self from selected_ids to prevent cycle.
		$selected_ids = array_values( array_diff( $selected_ids, array( $post_id ) ) );

		// 1. Check existing series of the current post.
		$current_series  = self::get_post_series( $post_id );
		$current_term_id = $current_series ? (int) $current_series->term_id : 0;

		// 2. Check existing series among all selected posts.
		$selected_series_ids = self::get_distinct_series_ids( $selected_ids );

		// Combine to find all distinct series involved.
		$all_series_ids = array_values( array_unique( array_filter( array_merge(
			$current_term_id ? array( $current_term_id ) : array(),
			$selected_series_ids
		) ) ) );

		// Strict safeguard: If posts from multiple distinct series are selected, merger is BLOCKED.
		if ( count( $all_series_ids ) > 1 ) {
			self::$is_syncing = false;
			return array(
				'success'      => false,
				'series_id'    => $current_term_id ?: $all_series_ids[0],
				'affected_ids' => array(),
				'conflict'     => true,
				'error'        => 'merge_blocked',
			);
		}

		$target_term_id = ! empty( $all_series_ids ) ? (int) $all_series_ids[0] : 0;

		// Target posts to assign: current post + selected posts.
		$posts_to_assign = array_values( array_unique( array_merge( array( $post_id ), $selected_ids ) ) );

		// ATOMIC PERMISSION VALIDATION:
		// Check edit permission for ALL affected posts BEFORE modifying ANY post, term, or metadata!
		// If ANY post cannot be edited, reject immediately with zero side-effects.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'current_user_can' ) ) {
			foreach ( $posts_to_assign as $pid ) {
				if ( ! current_user_can( 'edit_post', $pid ) ) {
					self::$is_syncing = false;
					return array(
						'success'      => false,
						'series_id'    => 0,
						'affected_ids' => array(),
						'conflict'     => false,
						'error'        => 'permission_denied',
						'message'      => sprintf(
							__( 'Permiso denegado: no tienes autorización para modificar las relaciones del capítulo ID %d.', 'manual-related-posts-pro' ),
							$pid
						),
					);
				}
			}
		}

		// Persistent Unlinking Safeguard:
		// Check if the current post or any selected post was previously unlinked from this target series!
		if ( $target_term_id ) {
			$unlinked_found = array();
			if ( in_array( $target_term_id, self::get_unlinked_series_ids( $post_id ), true ) ) {
				$unlinked_found[] = $post_id;
			}
			foreach ( $selected_ids as $sid ) {
				if ( in_array( $target_term_id, self::get_unlinked_series_ids( $sid ), true ) ) {
					$unlinked_found[] = $sid;
				}
			}
			$unlinked_found = array_values( array_unique( $unlinked_found ) );

			if ( ! empty( $unlinked_found ) && ! $relink_confirmed ) {
				// NEVER return success: true when unlinked posts are omitted or requested without confirmation!
				self::$is_syncing = false;
				return array(
					'success'           => false,
					'series_id'         => $target_term_id,
					'affected_ids'      => array(),
					'conflict'          => false,
					'error'             => 'unlinked_posts_detected',
					'unlinked_post_ids' => $unlinked_found,
					'message'           => sprintf(
						__( 'Uno o más capítulos (%s) fueron desvinculados previamente de esta serie. Se requiere confirmación explícita para reincorporarlos.', 'manual-related-posts-pro' ),
						implode( ', ', $unlinked_found )
					),
				);
			}
		}

		if ( empty( $target_term_id ) ) {
			// No series exists yet. Create a new series term with guaranteed unique identity.
			$title = ! empty( $series_title ) ? $series_title : get_the_title( $post_id );
			$created = self::create_series_term( $title, $post_id );
			if ( is_wp_error( $created ) || ! $created ) {
				self::$is_syncing = false;
				return array(
					'success'      => false,
					'series_id'    => 0,
					'affected_ids' => array(),
					'conflict'     => false,
					'error'        => 'term_creation_failed',
					'message'      => __( 'Error al registrar el grupo de serie en WordPress.', 'manual-related-posts-pro' ),
				);
			}
			$target_term_id = (int) $created;
		}

		// Record existing members before assignment to enable atomic rollback if any assignment fails.
		$previous_members    = self::get_series_posts( $target_term_id );
		$previous_member_ids = array_map( static function ( $p ) { return (int) $p->ID; }, $previous_members );

		$assigned_ids = array();
		$failed_ids   = array();

		foreach ( $posts_to_assign as $index => $pid ) {
			$post_ep = ( $pid === $post_id ) ? $episode_number : null;

			// If a selected post doesn't have an episode number yet, assign a 1-based index based on selection order.
			if ( null === $post_ep ) {
				$existing_ep = get_post_meta( $pid, self::META_EPISODE, true );
				if ( '' === $existing_ep || false === $existing_ep ) {
					$sel_index = array_search( $pid, $selected_ids, true );
					$post_ep   = ( false !== $sel_index ) ? (string) ( $sel_index + 1 ) : (string) ( $index + 1 );
				}
			}

			$assigned = self::assign_post_to_series( $pid, $target_term_id, $post_ep );
			if ( ! $assigned ) {
				$failed_ids[] = $pid;
			} else {
				$assigned_ids[] = $pid;
			}
		}

		// Check for WordPress assignment errors.
		if ( ! empty( $failed_ids ) ) {
			// Rollback: unassign any posts that were newly assigned in this batch to prevent corrupt partial states.
			foreach ( $assigned_ids as $aid ) {
				if ( ! in_array( $aid, $previous_member_ids, true ) ) {
					if ( function_exists( 'wp_remove_object_terms' ) ) {
						wp_remove_object_terms( $aid, $target_term_id, self::TAXONOMY );
					} elseif ( function_exists( 'wp_set_object_terms' ) ) {
						wp_set_object_terms( $aid, array(), self::TAXONOMY, false );
					}
					// Restore unlinked flag if it was an unlinked post that was partially modified
					if ( isset( $unlinked_found ) && in_array( $aid, $unlinked_found, true ) ) {
						self::add_unlinked_series_flag( $aid, $target_term_id );
					}
				}
			}

			self::$is_syncing = false;
			return array(
				'success'         => false,
				'series_id'       => $target_term_id,
				'affected_ids'    => array(),
				'conflict'        => false,
				'error'           => 'term_assignment_failed',
				'failed_post_ids' => $failed_ids,
				'message'         => sprintf(
					__( 'Error al vincular los capítulos a la serie en WordPress (IDs: %s). La operación fue cancelada para preservar la integridad de datos.', 'manual-related-posts-pro' ),
					implode( ', ', $failed_ids )
				),
			);
		}

		// Ensure the post holding the series block tracks this state.
		update_post_meta( $post_id, self::META_HAS_SERIES_BLOCK, '1' );

		// Also get any previous members of this series to ensure their cache is purged.
		$existing_members = self::get_series_posts( $target_term_id );
		foreach ( $existing_members as $m ) {
			$assigned_ids[] = (int) $m->ID;
		}

		$affected_ids = array_values( array_unique( $assigned_ids ) );

		// Invalidate internal WordPress data cache and trigger public hook.
		MRP_Cache::invalidate_posts( $affected_ids );

		self::$is_syncing = false;

		return array(
			'success'      => true,
			'series_id'    => $target_term_id,
			'affected_ids' => $affected_ids,
			'conflict'     => false,
			'error'        => '',
			'message'      => __( 'Serie sincronizada correctamente.', 'manual-related-posts-pro' ),
		);
	}

	/**
	 * Hooked to wp_after_insert_post to detect, sync, or unlink series blocks.
	 * Distinguishes auto-linked posts (which never had their own series block)
	 * from posts whose series block was explicitly removed or changed mode.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public static function on_after_insert_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, MRP_Helpers::get_allowed_post_types(), true ) ) {
			return;
		}

		$blocks = ( ! empty( $post->post_content ) && has_blocks( $post->post_content ) )
			? parse_blocks( $post->post_content )
			: array();

		// Collect all MRP blocks in the content (including nested).
		$mrp_blocks = self::find_all_mrp_blocks( $blocks );

		$has_series_block   = false;
		$series_block_data  = null;
		$has_spinoff_block  = false;
		$spinoff_ids        = array();
		$spinoff_reciprocal = false;

		foreach ( $mrp_blocks as $block ) {
			$attrs = $block['attrs'] ?? array();
			$mode  = $attrs['mode'] ?? 'classic';

			if ( 'series' === $mode ) {
				$has_series_block  = true;
				$series_block_data = $attrs;
			} elseif ( 'spinoff' === $mode ) {
				$has_spinoff_block = true;
				$sel = is_array( $attrs['selectedPosts'] ?? null ) ? $attrs['selectedPosts'] : array();
				$spinoff_ids = array_merge( $spinoff_ids, $sel );
				if ( ! empty( $attrs['isReciprocal'] ) ) {
					$spinoff_reciprocal = true;
				}
			}
		}

		// 1. Handle Series:
		$had_series_block = ( '1' === get_post_meta( $post_id, self::META_HAS_SERIES_BLOCK, true ) );

		if ( $has_series_block && null !== $series_block_data ) {
			update_post_meta( $post_id, self::META_HAS_SERIES_BLOCK, '1' );

			$selected_ids     = is_array( $series_block_data['selectedPosts'] ?? null ) ? $series_block_data['selectedPosts'] : array();
			$episode_number   = isset( $series_block_data['episodeNumber'] ) ? (string) $series_block_data['episodeNumber'] : '';
			$series_title     = isset( $series_block_data['seriesTitle'] ) ? sanitize_text_field( (string) $series_block_data['seriesTitle'] ) : '';
			$relink_confirmed = ! empty( $series_block_data['relinkConfirmed'] );

			$sync_result = self::sync_post_series( $post_id, $selected_ids, $episode_number, $series_title, $relink_confirmed );

			if ( ! $sync_result['success'] ) {
				// Record error status in post meta (accessible by REST API / Gutenberg) and transient (for admin notices).
				update_post_meta( $post_id, self::META_SYNC_STATUS, wp_json_encode( $sync_result ) );
				if ( function_exists( 'set_transient' ) ) {
					set_transient( 'mrp_sync_error_' . $post_id, $sync_result, 120 );
				}
			} else {
				// Clear any previous error on success.
				delete_post_meta( $post_id, self::META_SYNC_STATUS );
				if ( function_exists( 'delete_transient' ) ) {
					delete_transient( 'mrp_sync_error_' . $post_id );
				}
			}
		} else {
			// Current post does NOT have a series block in content now.
			if ( $had_series_block ) {
				// The editor previously inserted a series block and has now explicitly removed it or changed mode.
				self::unlink_post_from_series( $post_id );
				delete_post_meta( $post_id, self::META_HAS_SERIES_BLOCK );
				delete_post_meta( $post_id, self::META_SYNC_STATUS );
			}
			// If $had_series_block was false, this post never had its own series block.
			// It was auto-linked to a series by another chapter, and may contain classic or spinoff blocks.
			// We MUST NOT touch its series membership!
		}

		// 2. Handle Spin-offs:
		$had_spinoff_block = ( '1' === get_post_meta( $post_id, '_mrp_has_spinoff_block', true ) );

		if ( $has_spinoff_block ) {
			update_post_meta( $post_id, '_mrp_has_spinoff_block', '1' );
			$spinoff_ids = array_values( array_unique( array_filter( array_map( 'absint', $spinoff_ids ) ) ) );
			MRP_Relationships::sync_spinoff_relationships( $post_id, $spinoff_ids, $spinoff_reciprocal );
		} else {
			if ( $had_spinoff_block ) {
				MRP_Relationships::sync_spinoff_relationships( $post_id, array(), false );
				delete_post_meta( $post_id, '_mrp_has_spinoff_block' );
			}
		}
	}

	/**
	 * Find all MRP blocks recursively in a parsed blocks tree.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array
	 */
	public static function find_all_mrp_blocks( array $blocks ) {
		$found = array();

		foreach ( $blocks as $block ) {
			if ( 'manual-related-posts-pro/manual-related-posts' === ( $block['blockName'] ?? '' ) ) {
				$found[] = $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_found = self::find_all_mrp_blocks( $block['innerBlocks'] );
				if ( ! empty( $inner_found ) ) {
					$found = array_merge( $found, $inner_found );
				}
			}
		}

		return $found;
	}

	/**
	 * Invalidate cache when post status changes (e.g. published, unpublished, trashed).
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_transition_post_status( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status ) {
			return;
		}

		$series = self::get_post_series( $post->ID );
		if ( ! $series ) {
			return;
		}

		$posts = self::get_series_posts( $series->term_id );
		$ids   = array_map( static function ( $p ) { return (int) $p->ID; }, $posts );
		$ids[] = (int) $post->ID;

		MRP_Cache::invalidate_posts( $ids );
	}

	/**
	 * Invalidate cache when a post in a series is trashed, untrashed, or deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_trash_or_delete_post( $post_id ) {
		$series = self::get_post_series( $post_id );
		if ( ! $series ) {
			return;
		}

		$posts = self::get_series_posts( $series->term_id, $post_id );
		$ids   = array_map( static function ( $p ) { return (int) $p->ID; }, $posts );
		$ids[] = absint( $post_id );

		MRP_Cache::invalidate_posts( $ids );
	}

	/**
	 * Display admin notice in post editor if synchronization failed.
	 */
	public static function display_admin_sync_notices() {
		global $post;
		if ( ! $post || empty( $post->ID ) ) {
			return;
		}

		$transient_key = 'mrp_sync_error_' . $post->ID;
		$error_data    = function_exists( 'get_transient' ) ? get_transient( $transient_key ) : null;
		if ( ! $error_data || empty( $error_data['message'] ) ) {
			return;
		}

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $transient_key );
		}

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
			esc_html__( 'Manual Related Posts - Error al sincronizar serie', 'manual-related-posts-pro' ),
			esc_html( $error_data['message'] )
		);
	}
}
