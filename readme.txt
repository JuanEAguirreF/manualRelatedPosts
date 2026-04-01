=== Manual Related Posts Pro ===
Contributors: waylayer
Tags: related posts, gutenberg, block, editor, cards
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a lightweight Gutenberg block that lets editors manually select related posts and display them as a responsive card grid.

== Description ==

Manual Related Posts Pro adds a dynamic Gutenberg block for manually chosen related posts.

Instead of automatic “related posts” logic, editors search posts by title, select the exact entries they want, and display them in a responsive grid. The frontend is rendered in PHP using current live post data, so titles, links, excerpts, images, and dates stay up to date.

Key features:

* Manual post selection by title search
* Stores post IDs instead of URLs
* Dynamic frontend rendering in PHP
* Responsive card grid
* Section title and subtitle controls
* Per-block style overrides
* Global defaults in wp-admin
* Lightweight frontend with minimal assets

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip from the WordPress admin plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Go to `Settings > Manual Related Posts` to configure global defaults.
4. Edit any post or page and insert the `Manual Related Posts` block.

== Frequently Asked Questions ==

= Does this plugin generate related posts automatically? =

No. Editors manually choose which posts to display.

= Does it store post URLs? =

No. The block stores post IDs for better stability and safer rendering.

= Does it load heavy frontend JavaScript? =

No. The frontend is server-rendered in PHP and uses only lightweight CSS.

== Screenshots ==

1. Gutenberg block with manual post search and selected post list.
2. Responsive frontend card grid output.
3. Settings page for global defaults.

== Changelog ==

= 1.0.8 =

* Switched the wp-admin preview image to a Picsum placeholder.
* Expanded the preview column so the live demo has more room in the settings screen.

== Upgrade Notice ==

= 1.0.8 =

Refines the wp-admin preview with a better placeholder image and a wider demo column.






