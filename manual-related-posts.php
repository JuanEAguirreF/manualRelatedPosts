<?php
/**
 * Plugin Name: Manual Related Posts Pro
 * Plugin URI: https://juan.webmasterpersonal.com
 * Description: Adds a lightweight Gutenberg block for manually selected related posts with dynamic server-side rendering.
 * Version: 1.0.8
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Juan Aguirre
 * Author URI: https://juan.webmasterpersonal.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: manual-related-posts-pro
 *
 * @package ManualRelatedPostsPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MRP_VERSION', '1.0.8' );
define( 'MRP_PLUGIN_FILE', __FILE__ );
define( 'MRP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MRP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MRP_PLUGIN_DIR . 'includes/class-mrp-helpers.php';
require_once MRP_PLUGIN_DIR . 'includes/class-mrp-render.php';
require_once MRP_PLUGIN_DIR . 'includes/class-mrp-settings.php';
require_once MRP_PLUGIN_DIR . 'includes/class-mrp-block.php';

function mrp_boot_plugin() {
	MRP_Settings::init();
	MRP_Block::init();
}

add_action( 'plugins_loaded', 'mrp_boot_plugin' );

/**
 * Add a direct Settings link on the plugins screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function mrp_add_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=mrp-settings' ) ),
		esc_html__( 'Settings', 'manual-related-posts-pro' )
	);

	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( MRP_PLUGIN_FILE ), 'mrp_add_plugin_action_links' );






