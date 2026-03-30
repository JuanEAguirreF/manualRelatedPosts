# Manual Related Posts Pro

Lightweight WordPress plugin that adds a Gutenberg block for manually selected related posts.

## Architecture

- Frontend rendering is dynamic in PHP, so cards always use current live post titles, links, images, excerpts, and taxonomy data.
- Block content stores only selected post IDs plus per-block settings.
- Global defaults use the WordPress Settings API and resolve with this precedence:
  1. block-level value
  2. global plugin default
  3. internal hardcoded fallback
- Editor search and preview use a small custom REST endpoint that only returns the post records needed by the block UI.
- Frontend styles use CSS variables on the wrapper, which avoids generating per-block stylesheets.

## Setup

1. Copy this folder into `wp-content/plugins/manual-related-posts-pro`.
2. Activate **Manual Related Posts Pro** in WordPress admin.
3. Open `Settings > Manual Related Posts` and set the global defaults you want.
4. Add the **Manual Related Posts** block inside any post or page.
5. Search posts by title, add them, reorder them, and configure block-level overrides in the sidebar.

## Rebuilding Editor Assets

The plugin ships with a ready-to-load `build/index.js`, so it can run without a local Node build step.

If you want to rebuild the editor bundle:

1. Run `npm install`
2. Run `npm run build`

## v1 Scope

Included:
- Manual post search by title
- Post ID storage
- Selected post preview in Gutenberg
- Reordering and removal controls
- Dynamic PHP frontend render
- Responsive grid output
- Per-block style overrides
- Global defaults page
- Minimal shared frontend/editor CSS

Left for later:
- Optional add-by-URL helper
- Drag-and-drop sorting
- Advanced caching or fragment persistence
- More extensive style presets or layout variants