<?php
/**
 * Spin-offs, sequels, and related content management for Manual Related Posts Pro.
 * Provides independent relationships with provenance tracking (manual vs. reciprocal),
 * ensuring reciprocal cleanups never erase independent manual links.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Relationships {

	const META_MANUAL_RELATED     = '_mrp_related_spinoff_ids';
	const META_RECIPROCAL_SOURCES = '_mrp_reciprocal_spinoff_ids';
	const META_RECIPROCAL         = '_mrp_related_reciprocal';

	/**
	 * Guard against recursive execution during reciprocal updates.
	 *
	 * @var bool
	 */
	private static $is_syncing = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 6 );
		add_action( 'wp_trash_post', array( __CLASS__, 'cleanup_on_delete' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_on_delete' ) );
	}

	public static function register_meta() {
		$post_types = MRP_Helpers::get_allowed_post_types();

		foreach ( $post_types as $pt ) {
			register_post_meta(
				$pt,
				self::META_MANUAL_RELATED,
				array(
					'show_in_rest'  => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
					'single'        => true,
					'type'          => 'array',
					'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);

			register_post_meta(
				$pt,
				self::META_RECIPROCAL_SOURCES,
				array(
					'show_in_rest'  => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
					'single'        => true,
					'type'          => 'array',
					'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);

			register_post_meta(
				$pt,
				self::META_RECIPROCAL,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'boolean',
					'auth_callback' => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Get manually selected related spin-off IDs for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public static function get_manual_related_ids( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_MANUAL_RELATED, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
	}

	/**
	 * Get IDs of posts that reciprocally linked to this post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public static function get_reciprocal_related_ids( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_RECIPROCAL_SOURCES, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) );
	}

	/**
	 * Get all related spin-off IDs for a post (manual selection + reciprocal links).
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public static function get_related_ids( $post_id ) {
		$manual     = self::get_manual_related_ids( $post_id );
		$reciprocal = self::get_reciprocal_related_ids( $post_id );

		return array_values( array_unique( array_merge( $manual, $reciprocal ) ) );
	}

	/**
	 * Get published WP_Post objects for all related spin-offs.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post[]
	 */
	public static function get_related_posts( $post_id ) {
		$ids = self::get_related_ids( $post_id );
		if ( empty( $ids ) ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'              => MRP_Helpers::get_allowed_post_types(),
				'post__in'               => $ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $ids ),
				'post_status'            => 'publish',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
	}

	/**
	 * Synchronize spin-off relationships for a post with strict provenance.
	 *
	 * @param int   $post_id       The source post ID.
	 * @param int[] $target_ids    Target related post IDs.
	 * @param bool  $is_reciprocal Whether to establish reciprocal link in target posts.
	 * @return array{success: bool, error: string}
	 */
	public static function sync_spinoff_relationships( $post_id, array $target_ids, $is_reciprocal = false ) {
		if ( self::$is_syncing ) {
			return array( 'success' => false, 'error' => 'recursive_call' );
		}

		self::$is_syncing = true;

		$post_id    = absint( $post_id );
		$target_ids = array_values( array_unique( array_filter( array_map( 'absint', $target_ids ) ) ) );
		$target_ids = array_values( array_diff( $target_ids, array( $post_id ) ) );

		// Read previous states BEFORE updating to determine all affected targets.
		$old_manual_targets = self::get_manual_related_ids( $post_id );
		$was_reciprocal     = (bool) get_post_meta( $post_id, self::META_RECIPROCAL, true );

		// Determine all external targets that will have their reciprocal metadata modified.
		$external_targets_to_modify = array();
		if ( $is_reciprocal ) {
			$removed_targets            = array_diff( $old_manual_targets, $target_ids );
			$external_targets_to_modify = array_unique( array_merge( $target_ids, $removed_targets ) );
		} else {
			if ( $was_reciprocal && ! empty( $old_manual_targets ) ) {
				$external_targets_to_modify = $old_manual_targets;
			}
		}
		$external_targets_to_modify = array_values( array_diff( $external_targets_to_modify, array( $post_id ) ) );

		// ATOMIC PERMISSION VALIDATION:
		// Validate edit capability for the source post AND ALL external target posts (both additions and removals!)
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'current_user_can' ) ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				self::$is_syncing = false;
				return array(
					'success' => false,
					'error'   => 'permission_denied',
					'message' => __( 'No tienes permisos para modificar las relaciones de esta publicación.', 'manual-related-posts-pro' ),
				);
			}

			foreach ( $external_targets_to_modify as $tid ) {
				if ( ! current_user_can( 'edit_post', $tid ) ) {
					self::$is_syncing = false;
					return array(
						'success' => false,
						'error'   => 'permission_denied',
						'message' => sprintf(
							__( 'No tienes permisos para modificar las relaciones recíprocas en la publicación ID %d.', 'manual-related-posts-pro' ),
							$tid
						),
					);
				}
			}
		}

		// Update source post's manual targets and reciprocity flag.
		update_post_meta( $post_id, self::META_MANUAL_RELATED, $target_ids );
		update_post_meta( $post_id, self::META_RECIPROCAL, (bool) $is_reciprocal );

		$affected_ids = array( $post_id );

		if ( $is_reciprocal ) {
			// Add $post_id into each target's RECIPROCAL list (never touches their manual list!).
			foreach ( $target_ids as $tid ) {
				$recip = self::get_reciprocal_related_ids( $tid );
				if ( ! in_array( $post_id, $recip, true ) ) {
					$recip[] = $post_id;
					update_post_meta( $tid, self::META_RECIPROCAL_SOURCES, array_values( array_unique( $recip ) ) );
					$affected_ids[] = $tid;
				}
			}

			// For targets that were removed, clean up $post_id from their RECIPROCAL list.
			$removed_targets = array_diff( $old_manual_targets, $target_ids );
			foreach ( $removed_targets as $rem_id ) {
				$recip = self::get_reciprocal_related_ids( $rem_id );
				if ( in_array( $post_id, $recip, true ) ) {
					$recip = array_values( array_diff( $recip, array( $post_id ) ) );
					update_post_meta( $rem_id, self::META_RECIPROCAL_SOURCES, $recip );
					$affected_ids[] = $rem_id;
				}
			}
		} else {
			// If reciprocity was disabled, clean up $post_id from former targets' reciprocal lists.
			if ( $was_reciprocal && ! empty( $old_manual_targets ) ) {
				foreach ( $old_manual_targets as $rem_id ) {
					$recip = self::get_reciprocal_related_ids( $rem_id );
					if ( in_array( $post_id, $recip, true ) ) {
						$recip = array_values( array_diff( $recip, array( $post_id ) ) );
						update_post_meta( $rem_id, self::META_RECIPROCAL_SOURCES, $recip );
						$affected_ids[] = $rem_id;
					}
				}
			}
		}

		MRP_Cache::invalidate_posts( array_values( array_unique( $affected_ids ) ) );

		self::$is_syncing = false;

		return array( 'success' => true, 'error' => '' );
	}

	/**
	 * Remove a deleted post from any other posts' manual and reciprocal lists.
	 *
	 * @param int $deleted_post_id Post ID being trashed or deleted.
	 */
	public static function cleanup_on_delete( $deleted_post_id ) {
		$deleted_post_id = absint( $deleted_post_id );
		if ( ! $deleted_post_id ) {
			return;
		}

		// 1. Posts that this post pointed to manually: clean from their reciprocal list.
		$manual_targets = self::get_manual_related_ids( $deleted_post_id );
		foreach ( $manual_targets as $tid ) {
			$recip = self::get_reciprocal_related_ids( $tid );
			if ( in_array( $deleted_post_id, $recip, true ) ) {
				$recip = array_values( array_diff( $recip, array( $deleted_post_id ) ) );
				update_post_meta( $tid, self::META_RECIPROCAL_SOURCES, $recip );
				MRP_Cache::invalidate_posts( array( $tid ) );
			}
		}

		// 2. Posts that pointed to this post reciprocally: clean from their manual list.
		$reciprocal_sources = self::get_reciprocal_related_ids( $deleted_post_id );
		foreach ( $reciprocal_sources as $sid ) {
			$man = self::get_manual_related_ids( $sid );
			if ( in_array( $deleted_post_id, $man, true ) ) {
				$man = array_values( array_diff( $man, array( $deleted_post_id ) ) );
				update_post_meta( $sid, self::META_MANUAL_RELATED, $man );
				MRP_Cache::invalidate_posts( array( $sid ) );
			}
		}
	}
}
