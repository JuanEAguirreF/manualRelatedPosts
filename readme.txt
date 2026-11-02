=== Manual Related Posts Pro ===
Contributors: waylayer, juanaguirre
Tags: related posts, gutenberg, block, editor, cards, series, chapters, episodes
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a lightweight Gutenberg block for manual related posts, episode series groups with automatic bidirectional propagation, and spin-off relationships.

== Description ==

Manual Related Posts Pro adds a dynamic Gutenberg block and relation engine for manually chosen related posts, series chapters, and spin-offs.

Instead of generic algorithmic related posts, editors choose exact entries, group series chapters seamlessly, or link independent spin-offs and sequels. The frontend is server-rendered in PHP using current live post data, so titles, links, excerpts, images, and dates stay up to date with zero client-side JavaScript overhead.

Key features:

* Three distinct modes: Classic Manual, Same Series (Chapters), and Related Content (Spin-offs).
* Same Series Groups: Automatic bidirectional propagation. When publishing a new chapter (e.g. Chapter 7) and linking an existing chapter (e.g. Chapter 6), all previous chapters (1–6) automatically display the new chapter without manual editing.
* Auto-Render for Series: Posts belonging to a series display the chapter card grid automatically without requiring a manual block in each post.
* Episode ordering: Order chapters by episode number or publication date.
* Spin-offs & Sequels: Independent relationships that never merge series chapters or alter numbering.
* Safeguards against accidental mergers: Prevents silent conflation when posts from distinct series are selected.
* Infrastructure Independent: Works 100% on standard WordPress Core APIs. No dependencies on Varnish, Nginx, LiteSpeed, Cloudflare, or specific hosting providers.
* Extensible Cache Hook: Dispatches `do_action( 'mrp_relationships_updated', $affected_post_ids )` for custom cache purge integrations, with optional zero-overhead adapters for popular caching plugins.
* 100% Backwards Compatible: Existing classic blocks remain intact and in classic mode without modifying historical content.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the zip from the WordPress admin plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Go to `Settings > Manual Related Posts` to configure global defaults and series auto-rendering.
4. Edit any post or page and insert the `Manual Related Posts` block.

== Frequently Asked Questions ==

= Does this plugin require a specific hosting or cache setup? =

No. The plugin is completely independent of infrastructure. It operates on standard WordPress APIs and works on any standard WordPress installation.

= What is the difference between Same Series and Related Content? =

Same Series groups chapters into a single family where all episodes know each other bidirectionally and propagate automatically. Related Content links independent works (spin-offs, sequels) without merging their chapters or altering numbering.

= Will my existing blocks break when updating to 2.0.0? =

No. All existing blocks default to Classic mode and continue functioning exactly as before.

== Changelog ==

= 2.0.0 =

* Major release: Introduced Same Series (Chapters) mode with automatic bidirectional propagation.
* Introduced Related Content (Spin-offs & Sequels) mode with independent relationships and optional reciprocity.
* Added episode numbering control and custom ordering.
* Added automatic series rendering on posts without a block via `the_content` with strict deduplication guards.
* Added conflict detection in both backend and Gutenberg editor to prevent accidental series mergers.
* Re-architected caching to be completely infrastructure-independent, dispatching `mrp_relationships_updated`.
* Retained 100% backwards compatibility for classic blocks.

= 1.0.8 =

* Switched the wp-admin preview image to a Picsum placeholder.
* Expanded the preview column so the live demo has more room in the settings screen.

== Upgrade Notice ==

= 2.0.0 =

Adds Same Series chapter groups, spin-off relationships, auto-rendering, and infrastructure-independent caching while preserving full backwards compatibility with classic blocks.
