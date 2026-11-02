<?php
/**
 * Cache management and extensibility hooks for Manual Related Posts Pro.
 * Completely infrastructure-independent, relying purely on WordPress Core APIs
 * with optional adapters for common caching plugins.
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MRP_Cache {

	/**
	 * Invalidate internal WordPress data cache for affected posts and fire public hooks.
	 *
	 * @param int[] $post_ids Array of affected post IDs.
	 * @return void
	 */
	public static function invalidate_posts( array $post_ids ) {
		if ( empty( $post_ids ) ) {
			return;
		}

		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		// 1. Invalidate WordPress core object/meta/term cache for each affected post.
		foreach ( $post_ids as $post_id ) {
			clean_post_cache( $post_id );
		}

		// 2. Primary public action hook for themes, mu-plugins, and external integrations.
		/**
		 * Action triggered whenever relationships (series or spin-offs) change.
		 *
		 * @param int[] $post_ids Array of affected post IDs.
		 */
		do_action( 'mrp_relationships_updated', $post_ids );

		// 3. Optional third-party cache plugin adapters (safe degradation, purely via public APIs).
		self::trigger_optional_cache_adapters( $post_ids );
	}

	/**
	 * Trigger public invalidation APIs of popular WordPress caching plugins if active.
	 *
	 * @param int[] $post_ids Array of post IDs.
	 * @return void
	 */
	private static function trigger_optional_cache_adapters( array $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			// LiteSpeed Cache
			if ( class_exists( 'LiteSpeed_Cache_API' ) && method_exists( 'LiteSpeed_Cache_API', 'purge_post' ) ) {
				LiteSpeed_Cache_API::purge_post( $post_id );
			}

			// WP Super Cache
			if ( function_exists( 'wpsc_delete_post_cache' ) ) {
				wpsc_delete_post_cache( $post_id );
			}

			// WP Rocket
			if ( function_exists( 'rocket_clean_post' ) ) {
				rocket_clean_post( $post_id );
			}

			// W3 Total Cache
			if ( function_exists( 'w3tc_flush_post' ) ) {
				w3tc_flush_post( $post_id );
			}
		}
	}
}
