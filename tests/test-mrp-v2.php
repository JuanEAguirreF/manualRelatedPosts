<?php
/**
 * Comprehensive Test Suite for Manual Related Posts Pro 2.0.0
 * Verifies core functionality, relationship lifecycles, series propagation,
 * unlinking persistence, non-destruction on older chapter edit,
 * mode transitions, conflict blocking, multiple blocks,
 * reciprocal provenance, capability atomicity, and large series support.
 */

// Define WordPress mock environment
define( 'ABSPATH', __DIR__ . '/../' );
define( 'MRP_TESTING', true );

// Global in-memory storage simulating WordPress DB
$GLOBALS['_wp_posts'] = array();
$GLOBALS['_wp_post_meta'] = array();
$GLOBALS['_wp_terms'] = array();
$GLOBALS['_wp_term_relationships'] = array(); // post_id => array( term_id )
$GLOBALS['_wp_options'] = array();
$GLOBALS['_wp_actions'] = array();
$GLOBALS['_wp_filters'] = array();
$GLOBALS['_wp_cleaned_caches'] = array();
$GLOBALS['_wp_triggered_actions'] = array();
$GLOBALS['_wp_current_user_caps'] = array(); // post_id => bool

// Mock WordPress functions
function wp_parse_url( $url ) { return parse_url( $url ); }
function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
function sanitize_hex_color( $color ) { return preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ? $color : ''; }
function sanitize_html_class( $class, $fallback = '' ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $class ) ?: $fallback; }
function sanitize_title( $title ) { return strtolower( preg_replace( '/[^A-Za-z0-9-]+/', '-', $title ) ); }
function absint( $maybeint ) { return abs( (int) $maybeint ); }
function rest_sanitize_boolean( $value ) { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function wp_kses_post( $str ) { return $str; }
function __( $text, $domain = 'default' ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function esc_attr__( $text, $domain = 'default' ) { return $text; }
function wp_generate_uuid4() { return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff) ); }

function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) { $GLOBALS['_wp_actions'][$hook][] = array( 'cb' => $cb, 'priority' => $priority, 'args' => $accepted_args ); }
function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) { $GLOBALS['_wp_filters'][$hook][] = array( 'cb' => $cb, 'priority' => $priority, 'args' => $accepted_args ); }
function do_action( $hook, ...$args ) {
    $GLOBALS['_wp_triggered_actions'][$hook][] = $args;
    if ( ! empty( $GLOBALS['_wp_actions'][$hook] ) ) {
        foreach ( $GLOBALS['_wp_actions'][$hook] as $action ) {
            call_user_func_array( $action['cb'], array_slice( $args, 0, $action['args'] ) );
        }
    }
}
function apply_filters( $hook, $value, ...$args ) {
    if ( ! empty( $GLOBALS['_wp_filters'][$hook] ) ) {
        foreach ( $GLOBALS['_wp_filters'][$hook] as $filter ) {
            $value = call_user_func_array( $filter['cb'], array_merge( array( $value ), $args ) );
        }
    }
    return $value;
}

function get_option( $key, $default = false ) { return $GLOBALS['_wp_options'][$key] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['_wp_options'][$key] = $value; return true; }

function register_taxonomy( $tax, $object_type, $args ) { $GLOBALS['_wp_taxonomies'][$tax] = $args; }
function register_post_meta( $post_type, $meta_key, $args ) { $GLOBALS['_wp_registered_meta'][$post_type][$meta_key] = $args; }
function register_block_type( $path, $args ) { $GLOBALS['_wp_registered_blocks'][$path] = $args; }

function wp_set_object_terms( $post_id, $terms, $taxonomy, $append = false ) {
    if ( ! empty( $GLOBALS['_wp_fail_terms'][$post_id] ) ) {
        return new WP_Error( 'db_error', 'WordPress term assignment failure' );
    }
    $term_id = is_array( $terms ) ? ( ! empty( $terms ) ? reset( $terms ) : null ) : $terms;
    if ( null === $term_id ) {
        $GLOBALS['_wp_term_relationships'][$post_id] = array();
        return array();
    }
    if ( ! $append ) {
        $GLOBALS['_wp_term_relationships'][$post_id] = array( (int) $term_id );
    } else {
        $GLOBALS['_wp_term_relationships'][$post_id][] = (int) $term_id;
    }
    return array( (int) $term_id );
}

function wp_remove_object_terms( $post_id, $terms, $taxonomy ) {
    if ( ! empty( $GLOBALS['_wp_fail_remove_terms'][$post_id] ) ) {
        return new WP_Error( 'db_error', 'WordPress term removal failure' );
    }
    $terms_to_rem = is_array( $terms ) ? $terms : array( $terms );
    $current = $GLOBALS['_wp_term_relationships'][$post_id] ?? array();
    $GLOBALS['_wp_term_relationships'][$post_id] = array_values( array_diff( $current, $terms_to_rem ) );
    return true;
}

function get_the_terms( $post_id, $taxonomy ) {
    $term_ids = $GLOBALS['_wp_term_relationships'][$post_id] ?? array();
    $terms = array();
    foreach ( $term_ids as $tid ) {
        if ( isset( $GLOBALS['_wp_terms'][$tid] ) ) {
            $t = $GLOBALS['_wp_terms'][$tid];
            $terms[] = (object) array(
                'term_id' => $t['term_id'],
                'name'    => $t['name'],
                'slug'    => $t['slug'],
                'count'   => 1,
            );
        }
    }
    return ! empty( $terms ) ? $terms : false;
}

function term_exists( $term, $taxonomy = '' ) {
    foreach ( $GLOBALS['_wp_terms'] as $t ) {
        if ( $t['name'] === $term || $t['slug'] === $term ) {
            return array( 'term_id' => $t['term_id'], 'term_taxonomy_id' => $t['term_id'] );
        }
    }
    return null;
}

function wp_insert_term( $term, $taxonomy, $args = array() ) {
    static $term_counter = 1;
    $id = $term_counter++;
    $GLOBALS['_wp_terms'][$id] = array(
        'term_id' => $id,
        'name'    => $term,
        'slug'    => $args['slug'] ?? sanitize_title( $term ) . '-' . $id,
    );
    return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
}

function update_post_meta( $post_id, $key, $value ) { $GLOBALS['_wp_post_meta'][$post_id][$key] = $value; return true; }
function delete_post_meta( $post_id, $key ) { unset( $GLOBALS['_wp_post_meta'][$post_id][$key] ); return true; }
function get_post_meta( $post_id, $key = '', $single = false ) {
    if ( ! isset( $GLOBALS['_wp_post_meta'][$post_id] ) ) { return $single ? '' : array(); }
    if ( '' === $key ) { return $GLOBALS['_wp_post_meta'][$post_id]; }
    return $GLOBALS['_wp_post_meta'][$post_id][$key] ?? ( $single ? '' : array() );
}

function clean_post_cache( $post_id ) { $GLOBALS['_wp_cleaned_caches'][] = (int) $post_id; }
function get_the_ID() { return $GLOBALS['post']->ID ?? 0; }
function get_permalink( $post ) {
    $id = is_object( $post ) ? $post->ID : $post;
    return 'https://example.com/post-' . $id . '/';
}
function get_the_title( $post = 0 ) {
    $id = is_object( $post ) ? $post->ID : ( $post ?: ( $GLOBALS['post']->ID ?? 0 ) );
    return $GLOBALS['_wp_posts'][$id]->post_title ?? 'Post ' . $id;
}
function get_post_status( $post = 0 ) {
    $id = is_object( $post ) ? $post->ID : $post;
    return $GLOBALS['_wp_posts'][$id]->post_status ?? 'publish';
}
function get_the_date( $format = '', $post = null ) { return '2026-04-01'; }
function has_post_thumbnail( $post = null ) { return false; }
function has_excerpt( $post = null ) { return false; }
function strip_shortcodes( $str ) { return $str; }
function wp_strip_all_tags( $str ) { return strip_tags( $str ); }
function wp_trim_words( $text, $num_words = 55, $more = null ) { return $text; }
function get_the_terms_empty( $post, $tax ) { return false; }
function get_block_wrapper_attributes( $attrs = array() ) {
    $class = $attrs['class'] ?? '';
    $style = $attrs['style'] ?? '';
    return 'class="' . esc_attr( $class ) . '" style="' . esc_attr( $style ) . '"';
}
function is_singular( $post_types = '' ) { return true; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function is_admin() { return false; }
function is_feed() { return false; }
function wp_is_json_request() { return false; }
function is_user_logged_in() { return true; }
function get_current_user_id() { return $GLOBALS['_wp_current_user_id'] ?? 1; }
function current_user_can( $cap, $post_id = 0 ) {
    if ( isset( $GLOBALS['_wp_current_user_caps'][$post_id] ) ) {
        return $GLOBALS['_wp_current_user_caps'][$post_id];
    }
    if ( isset( $GLOBALS['_wp_current_user_caps'][$cap] ) ) {
        return $GLOBALS['_wp_current_user_caps'][$cap];
    }
    return true; // default allowed in tests unless specifically restricted
}
function set_transient( $key, $value, $exp = 0 ) { $GLOBALS['_wp_transients'][$key] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['_wp_transients'][$key] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['_wp_transients'][$key] ); return true; }
function wp_json_encode( $data ) { return json_encode( $data ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
class WP_Error {
    public $message;
    public $code;
    public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
}

if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = array();
        public function __construct( $args = array() ) {
            $matched = array();
            $search = strtolower( trim( (string) ( $args['s'] ?? '' ) ) );
            $post_status = $args['post_status'] ?? 'publish';
            $author = $args['author'] ?? null;

            foreach ( $GLOBALS['_wp_posts'] as $post ) {
                // Status check
                if ( is_array( $post_status ) ) {
                    if ( ! in_array( $post->post_status, $post_status, true ) ) {
                        continue;
                    }
                } elseif ( 'any' !== $post_status && $post->post_status !== $post_status ) {
                    continue;
                }

                // Author check
                if ( null !== $author && (int) ( $post->post_author ?? 1 ) !== (int) $author ) {
                    continue;
                }

                // Search check
                if ( '' !== $search ) {
                    $in_title = false !== stripos( $post->post_title, $search );
                    $in_content = false !== stripos( $post->post_content, $search );
                    if ( ! $in_title && ! $in_content ) {
                        continue;
                    }
                }

                $matched[] = $post;
            }

            $limit = $args['posts_per_page'] ?? -1;
            if ( $limit > 0 ) {
                $matched = array_slice( $matched, 0, $limit );
            }

            $this->posts = $matched;
        }
    }
}

function get_posts( $args = array() ) {
    $results = array();
    foreach ( $GLOBALS['_wp_posts'] as $post ) {
        // Status filter
        $status = $args['post_status'] ?? 'publish';
        if ( is_array( $status ) && ! in_array( $post->post_status, $status, true ) ) {
            continue;
        } elseif ( is_string( $status ) && $post->post_status !== $status ) {
            continue;
        }

        // post__in
        if ( ! empty( $args['post__in'] ) && ! in_array( $post->ID, $args['post__in'], true ) ) {
            continue;
        }

        // post__not_in
        if ( ! empty( $args['post__not_in'] ) && in_array( $post->ID, $args['post__not_in'], true ) ) {
            continue;
        }

        // tax_query
        if ( ! empty( $args['tax_query'] ) ) {
            $tax_match = false;
            foreach ( $args['tax_query'] as $tq ) {
                if ( ! is_array( $tq ) ) continue;
                $terms = $GLOBALS['_wp_term_relationships'][$post->ID] ?? array();
                if ( in_array( $tq['terms'], $terms, true ) ) {
                    $tax_match = true;
                    break;
                }
            }
            if ( ! $tax_match ) {
                continue;
            }
        }

        $results[] = $post;
    }

    if ( ! empty( $args['post__in'] ) && ( $args['orderby'] ?? '' ) === 'post__in' ) {
        $ordered = array();
        $map = array();
        foreach ( $results as $r ) { $map[$r->ID] = $r; }
        foreach ( $args['post__in'] as $id ) {
            if ( isset( $map[$id] ) ) { $ordered[] = $map[$id]; }
        }
        return $ordered;
    }

    return $results;
}

function get_post_types( $args = array(), $output = 'names' ) {
    return array( 'post' => 'post', 'page' => 'page' );
}

function parse_blocks( $content ) {
    $blocks = array();
    if ( preg_match_all( '/<!-- wp:manual-related-posts-pro\/manual-related-posts\s+({.*?})\s+\/-->/s', $content, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $m ) {
            $blocks[] = array(
                'blockName' => 'manual-related-posts-pro/manual-related-posts',
                'attrs'     => json_decode( $m[1], true ) ?: array(),
            );
        }
    }
    return $blocks;
}

function has_blocks( $content ) {
    return strpos( $content, '<!-- wp:' ) !== false;
}

function wp_is_post_revision( $post_id ) { return false; }
function wp_is_post_autosave( $post_id ) { return false; }
function get_post( $id = 0 ) {
    $id = is_object( $id ) ? $id->ID : ( $id ?: ( $GLOBALS['post']->ID ?? 0 ) );
    return $GLOBALS['_wp_posts'][$id] ?? null;
}
function get_bloginfo( $show = '' ) { return 'UTF-8'; }
function get_post_type_object( $pt ) {
    return (object) array( 'labels' => (object) array( 'singular_name' => ucfirst( $pt ) ) );
}
function rest_url( $path = '' ) { return 'https://example.com/wp-json/' . ltrim( $path, '/' ); }
function wp_create_nonce( $action = -1 ) { return 'test-nonce-123'; }
function rest_ensure_response( $response ) { return $response; }

if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private $params = array();
        public function __construct( $params = array() ) { $this->params = $params; }
        public function get_param( $key ) { return $this->params[$key] ?? null; }
        public function set_param( $key, $val ) { $this->params[$key] = $val; }
    }
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
    class WP_REST_Server {
        const READABLE   = 'GET';
        const CREATABLE  = 'POST';
        const EDITABLE   = 'POST, PUT, PATCH';
        const DELETABLE  = 'DELETE';
        const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
    }
}

function register_rest_route( $namespace, $route, $args = array() ) {}
function wp_localize_script( $handle, $name, $data ) {}
function wp_set_script_translations( $handle, $domain ) {}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function wp_add_inline_script( $handle, $data, $position = 'after' ) {}

define( 'MRP_PLUGIN_DIR', __DIR__ . '/../' );
define( 'MRP_PLUGIN_URL', 'https://example.com/wp-content/plugins/manual-related-posts-pro/' );
define( 'MRP_VERSION', '2.0.0' );

// Load plugin code
require_once __DIR__ . '/../includes/class-mrp-helpers.php';
require_once __DIR__ . '/../includes/class-mrp-cache.php';
require_once __DIR__ . '/../includes/class-mrp-series.php';
require_once __DIR__ . '/../includes/class-mrp-relationships.php';
require_once __DIR__ . '/../includes/class-mrp-auto-render.php';
require_once __DIR__ . '/../includes/class-mrp-render.php';
require_once __DIR__ . '/../includes/class-mrp-block.php';

// Helpers to create mock posts
function create_mock_post( $id, $title, $status = 'publish', $content = '', $author = 1 ) {
    $p = (object) array(
        'ID'           => $id,
        'post_title'   => $title,
        'post_status'  => $status,
        'post_content' => $content,
        'post_author'  => $author,
        'post_type'    => 'post',
        'post_date'    => '2026-04-01 10:00:00',
        'post_date_gmt'=> '2026-04-01 10:00:00',
    );
    $GLOBALS['_wp_posts'][$id] = $p;
    return $p;
}

// --- TEST RUNNER ---
$tests_passed = 0;
$tests_failed = 0;

function assert_true( $cond, $name ) {
    global $tests_passed, $tests_failed;
    if ( $cond ) {
        echo "  [PASS] {$name}\n";
        $tests_passed++;
    } else {
        echo "  [FAIL] {$name}\n";
        $tests_failed++;
    }
}

function assert_equals( $expected, $actual, $name ) {
    global $tests_passed, $tests_failed;
    if ( $expected === $actual ) {
        echo "  [PASS] {$name}\n";
        $tests_passed++;
    } else {
        echo "  [FAIL] {$name} (Expected: " . json_encode($expected) . ", Got: " . json_encode($actual) . ")\n";
        $tests_failed++;
    }
}

echo "=== MANUAL RELATED POSTS PRO 2.0.0 - TEST SUITE ===\n\n";

// TEST 1: Classic block backwards compatibility
echo "Test 1: Classic Block Compatibility\n";
create_mock_post( 101, 'Classic Post 1' );
create_mock_post( 102, 'Classic Post 2' );
create_mock_post( 100, 'Container Post', 'publish', '<!-- wp:manual-related-posts-pro/manual-related-posts {"selectedPosts":[101,102]} /-->' );

$rendered_classic = MRP_Render::render_block( array( 'selectedPosts' => array( 101, 102 ) ) );
assert_true( strpos( $rendered_classic, 'Classic Post 1' ) !== false, 'Renders Classic Post 1' );
assert_true( strpos( $rendered_classic, 'Classic Post 2' ) !== false, 'Renders Classic Post 2' );
assert_equals( null, MRP_Series::get_post_series( 100 ), 'Classic block does NOT assign post to series' );
assert_equals( null, MRP_Series::get_post_series( 101 ), 'Classic block does NOT assign target post to series' );

// TEST 2: Create a series selecting chapters 1-5 from chapter 6
echo "\nTest 2: Create Series from Chapter 6 (selecting 1-5)\n";
for ( $i = 1; $i <= 6; $i++ ) {
    create_mock_post( $i, "Capítulo {$i}" );
}
$sync_res = MRP_Series::sync_post_series( 6, array( 1, 2, 3, 4, 5 ), '6', 'Solo Leveling' );
assert_true( $sync_res['success'], 'Sync succeeds' );
assert_true( $sync_res['series_id'] > 0, 'Series term created' );

$series_term = MRP_Series::get_post_series( 6 );
assert_true( null !== $series_term, 'Chapter 6 has series' );
assert_equals( $sync_res['series_id'], (int) $series_term->term_id, 'Chapter 6 series term matches' );

// Verify Chapters 1-5 also belong to this same series!
for ( $i = 1; $i <= 5; $i++ ) {
    $term = MRP_Series::get_post_series( $i );
    assert_true( null !== $term && (int) $term->term_id === (int) $series_term->term_id, "Chapter {$i} belongs to Solo Leveling series" );
    $ep = get_post_meta( $i, MRP_Series::META_EPISODE, true );
    assert_equals( (string) $i, (string) $ep, "Chapter {$i} has episode number {$i}" );
}

// TEST 3: Add Chapter 7 selecting ONLY Chapter 6
echo "\nTest 3: Add Chapter 7 selecting ONLY Chapter 6\n";
create_mock_post( 7, 'Capítulo 7' );
$sync_ch7 = MRP_Series::sync_post_series( 7, array( 6 ), '7' );
assert_true( $sync_ch7['success'], 'Chapter 7 joins series' );
assert_equals( $sync_res['series_id'], $sync_ch7['series_id'], 'Chapter 7 joins existing Solo Leveling series' );

// Querying series for Chapter 1: should return chapters 2 through 7 (excluding 1)
$ch1_siblings = MRP_Series::get_series_posts( $series_term->term_id, 1 );
$sibling_ids = array_map( function( $p ) { return (int) $p->ID; }, $ch1_siblings );
assert_equals( array( 2, 3, 4, 5, 6, 7 ), $sibling_ids, 'Chapter 1 automatically sees chapters 2-7 in proper episode order without manual editing' );

// Querying series for Chapter 6: should return 1, 2, 3, 4, 5, 7
$ch6_siblings = MRP_Series::get_series_posts( $series_term->term_id, 6 );
$ch6_sibling_ids = array_map( function( $p ) { return (int) $p->ID; }, $ch6_siblings );
assert_equals( array( 1, 2, 3, 4, 5, 7 ), $ch6_sibling_ids, 'Chapter 6 sees 1-5 and Chapter 7' );

// TEST 4: Auto-render on posts without a block
echo "\nTest 4: Auto-render on Posts without a block\n";
$GLOBALS['post'] = $GLOBALS['_wp_posts'][1]; // Chapter 1 has no block
$auto_content = MRP_Auto_Render::filter_content( '<p>Contenido del capítulo 1.</p>' );
assert_true( strpos( $auto_content, '<p>Contenido del capítulo 1.</p>' ) !== false, 'Original content preserved' );
assert_true( strpos( $auto_content, 'mrp-block-wrapper' ) !== false, 'Auto-renders grid wrapper' );
assert_true( strpos( $auto_content, 'Capítulo 2' ) !== false, 'Shows Chapter 2' );
assert_true( strpos( $auto_content, 'Capítulo 7' ) !== false, 'Shows Chapter 7' );
assert_true( strpos( $auto_content, 'href="https://example.com/post-7/"' ) !== false, 'HTML links are crawlable' );

// TEST 5: Deduplication check (post already has series block)
echo "\nTest 5: Deduplication (Post already has series block)\n";
$GLOBALS['post'] = create_mock_post( 6, 'Capítulo 6', 'publish', '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"series","selectedPosts":[1,2,3,4,5]} /-->' );
$content_with_block = $GLOBALS['post']->post_content;
$filtered = MRP_Auto_Render::filter_content( $content_with_block );
assert_equals( $content_with_block, $filtered, 'Auto-render does NOT duplicate grid when series block is present' );

// TEST 6: Spin-offs (Tipo B) Independence & Reciprocal Visibility
echo "\nTest 6: Spin-offs (Tipo B) Independence & Reciprocal Visibility\n";
create_mock_post( 201, 'Spin-off Ragnarok 1' );
MRP_Relationships::sync_spinoff_relationships( 201, array( 1 ), true ); // Reciprocal
$ragnarok_related = MRP_Relationships::get_related_ids( 201 );
assert_equals( array( 1 ), $ragnarok_related, 'Spin-off links to Chapter 1' );
$ch1_related = MRP_Relationships::get_related_ids( 1 );
assert_equals( array( 201 ), $ch1_related, 'Chapter 1 reciprocal links to Spin-off' );

// Crucial: Spin-off 201 must NOT have Solo Leveling series!
assert_equals( null, MRP_Series::get_post_series( 201 ), 'Spin-off does NOT inherit series taxonomy' );
// Chapter 1 series must NOT include 201
$series_posts_check = MRP_Series::get_series_posts( $series_term->term_id );
$series_post_ids = array_map( function( $p ) { return (int) $p->ID; }, $series_posts_check );
assert_true( ! in_array( 201, $series_post_ids, true ), 'Series chapters do not include spin-off post' );

// Reciprocal visibility: Post 1 auto-renders both series AND spin-off cards!
$GLOBALS['post'] = $GLOBALS['_wp_posts'][1];
$content_ch1_auto = MRP_Auto_Render::filter_content( '<p>Texto del capítulo 1</p>' );
assert_true( strpos( $content_ch1_auto, 'Spin-off Ragnarok 1' ) !== false, 'Chapter 1 auto-renders reciprocal Spin-off card' );
assert_true( strpos( $content_ch1_auto, 'Capítulo 2' ) !== false, 'Chapter 1 also shows series chapters' );

// Post 201 (the spin-off) auto-renders Chapter 1 as related content!
$GLOBALS['post'] = $GLOBALS['_wp_posts'][201];
$content_spinoff_auto = MRP_Auto_Render::filter_content( '<p>Texto del spin-off</p>' );
assert_true( strpos( $content_spinoff_auto, 'Capítulo 1' ) !== false, 'Spin-off auto-renders reciprocal Chapter 1' );

// TEST 7: Strict Merger Blocker
echo "\nTest 7: Strict Merger Blocker\n";
create_mock_post( 301, 'Another Manga 1' );
create_mock_post( 302, 'Another Manga 2' );
MRP_Series::sync_post_series( 302, array( 301 ), '2', 'Another Manga' );
$series_b_term = MRP_Series::get_post_series( 301 );

create_mock_post( 401, 'New Post Linking Both' );
$conflict_attempt = MRP_Series::sync_post_series( 401, array( 1, 301 ), '1' );
assert_true( $conflict_attempt['conflict'], 'Conflict detected' );
assert_true( ! $conflict_attempt['success'], 'Sync aborted - mergers are strictly blocked' );
assert_equals( 'merge_blocked', $conflict_attempt['error'], 'Specific merge_blocked error returned' );
assert_equals( $series_term->term_id, MRP_Series::get_post_series( 1 )->term_id, 'Series A untouched' );
assert_equals( $series_b_term->term_id, MRP_Series::get_post_series( 301 )->term_id, 'Series B untouched' );

// TEST 8: Infrastructure Independence & Cache Hooks
echo "\nTest 8: Infrastructure Independence & Cache Hooks\n";
$GLOBALS['_wp_cleaned_caches'] = array();
$hook_fired_with = array();
add_action( 'mrp_relationships_updated', function( $ids ) use ( &$hook_fired_with ) {
    $hook_fired_with = $ids;
} );

MRP_Cache::invalidate_posts( array( 1, 2, 7 ) );
assert_equals( array( 1, 2, 7 ), $GLOBALS['_wp_cleaned_caches'], 'clean_post_cache called for affected IDs' );
assert_equals( array( 1, 2, 7 ), $hook_fired_with, 'mrp_relationships_updated fired with affected IDs' );

// TEST 9: Trashed Post Exclusion
echo "\nTest 9: Trashed Post Exclusion\n";
$GLOBALS['_wp_posts'][3]->post_status = 'trash';
$series_after_trash = MRP_Series::get_series_posts( $series_term->term_id );
$ids_after_trash = array_map( function( $p ) { return (int) $p->ID; }, $series_after_trash );
assert_true( ! in_array( 3, $ids_after_trash, true ), 'Trashed chapter 3 is excluded from active series display' );

// TEST 10: Crucial - Editing an auto-linked chapter without a block MUST NOT unlink it!
echo "\nTest 10: Editing Auto-linked Chapter Without Block Must NOT Unlink\n";
$post_2_obj = $GLOBALS['_wp_posts'][2];
// Chapter 2 has content with NO MRP blocks
$post_2_obj->post_content = '<p>Edición de texto para corregir sinopsis del capítulo 2.</p>';
MRP_Series::on_after_insert_post( 2, $post_2_obj, true );

// Verification: Chapter 2 MUST STILL belong to the series!
assert_true( null !== MRP_Series::get_post_series( 2 ), 'Chapter 2 still belongs to series after editing text without a block' );
assert_equals( '2', get_post_meta( 2, MRP_Series::META_EPISODE, true ), 'Chapter 2 episode number preserved' );

// TEST 11: Explicit Unlinking & Persistence across re-saves
echo "\nTest 11: Persistent Unlinking Across Re-saves\n";
// Create Chapter 50 in Solo Leveling
create_mock_post( 50, 'Capítulo 50' );
MRP_Series::assign_post_to_series( 50, $series_term->term_id, '50' );
assert_true( null !== MRP_Series::get_post_series( 50 ), 'Chapter 50 joined series' );

// Explicitly unlink chapter 50
$unlinked = MRP_Series::unlink_post_from_series( 50 );
assert_true( $unlinked, 'unlink_post_from_series returned true' );
assert_equals( null, MRP_Series::get_post_series( 50 ), 'Post 50 unlinked from series' );

// Re-saving post 50 with new text must NOT re-link it
$post_50_obj = $GLOBALS['_wp_posts'][50];
$post_50_obj->post_content = '<p>Texto actualizado de capítulo 50</p>';
MRP_Series::on_after_insert_post( 50, $post_50_obj, true );
assert_equals( null, MRP_Series::get_post_series( 50 ), 'Post 50 remains unlinked after being saved again' );

// Re-saving Chapter 6 (whose selectedPosts still contains 50) must NOT re-add 50!
$post_6_obj = $GLOBALS['_wp_posts'][6];
$post_6_obj->post_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"series","selectedPosts":[1,2,4,5,50]} /-->';
MRP_Series::on_after_insert_post( 6, $post_6_obj, true );
assert_equals( null, MRP_Series::get_post_series( 50 ), 'Chapter 50 is NOT re-added even if another post still has it in selectedPosts' );

// TEST 12: Changing mode away from series (Series -> Classic) unlinks
echo "\nTest 12: Mode Change (Series -> Classic) Unlinks\n";
create_mock_post( 52, 'Capítulo 52' );
$post_52_obj = $GLOBALS['_wp_posts'][52];
$post_52_obj->post_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"series","selectedPosts":[1,2],"episodeNumber":"52"} /-->';
MRP_Series::on_after_insert_post( 52, $post_52_obj, true );
assert_true( null !== MRP_Series::get_post_series( 52 ), 'Chapter 52 is in series with series block' );

$post_52_obj->post_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"classic","selectedPosts":[101]} /-->';
MRP_Series::on_after_insert_post( 52, $post_52_obj, true );
assert_equals( null, MRP_Series::get_post_series( 52 ), 'Switching block mode to classic unlinks post from series' );

// TEST 13: Reciprocal Provenance - Disabling reciprocity does NOT delete independent links
echo "\nTest 13: Reciprocal Provenance Isolation\n";
create_mock_post( 501, 'Main Story' );
create_mock_post( 502, 'Independent Spin-off' );
create_mock_post( 503, 'Reciprocal Spin-off' );

// 502 manually links to 501
MRP_Relationships::sync_spinoff_relationships( 502, array( 501 ), false );
assert_equals( array( 501 ), MRP_Relationships::get_manual_related_ids( 502 ), '502 manually links to 501' );

// 503 links to 502 reciprocally
MRP_Relationships::sync_spinoff_relationships( 503, array( 502 ), true );
// 502 now has 501 (manual) and 503 (reciprocal)
assert_equals( array( 501, 503 ), MRP_Relationships::get_related_ids( 502 ), '502 has both manual and reciprocal spin-offs' );

// Now 503 turns OFF reciprocity or unlinks 502
MRP_Relationships::sync_spinoff_relationships( 503, array(), false );
// Crucial: 502 MUST still have 501! Its manual link was NOT touched!
assert_equals( array( 501 ), MRP_Relationships::get_related_ids( 502 ), 'Disabling reciprocity in 503 did NOT erase 502 manual link to 501' );

// TEST 14: Series Identity - Two series with identical titles NEVER merge
echo "\nTest 14: Series Identity with Identical Titles\n";
create_mock_post( 601, 'Manga A Cap 1' );
create_mock_post( 602, 'Manga A Cap 2' );
create_mock_post( 701, 'Manga B Cap 1' );
create_mock_post( 702, 'Manga B Cap 2' );

// Both created with the identical name "Especial"
$sync_manga_a = MRP_Series::sync_post_series( 602, array( 601 ), '2', 'Especial' );
$sync_manga_b = MRP_Series::sync_post_series( 702, array( 701 ), '2', 'Especial' );

assert_true( $sync_manga_a['series_id'] !== $sync_manga_b['series_id'], 'Two series with the exact same title receive distinct term IDs' );
$manga_a_posts = MRP_Series::get_series_posts( $sync_manga_a['series_id'] );
$manga_a_ids = array_map( function( $p ) { return (int) $p->ID; }, $manga_a_posts );
assert_equals( array( 601, 602 ), $manga_a_ids, 'Series A only contains Manga A chapters' );

$manga_b_posts = MRP_Series::get_series_posts( $sync_manga_b['series_id'] );
$manga_b_ids = array_map( function( $p ) { return (int) $p->ID; }, $manga_b_posts );
assert_equals( array( 701, 702 ), $manga_b_ids, 'Series B only contains Manga B chapters' );

// TEST 15: Atomic Permission Check
echo "\nTest 15: Atomic Permission Validation\n";
create_mock_post( 801, 'Permission Post 1' );
create_mock_post( 802, 'Permission Post 2 (No Permission)' );
create_mock_post( 803, 'Permission Post 3' );

// Deny permission on 802
$GLOBALS['_wp_current_user_caps'][802] = false;

// Attempt to sync 801, 802, 803
$perm_sync = MRP_Series::sync_post_series( 801, array( 802, 803 ), '1', 'Permission Series' );
assert_true( ! $perm_sync['success'], 'Sync fails atomically when one target post cannot be edited' );
assert_equals( 'permission_denied', $perm_sync['error'], 'Error is permission_denied' );
assert_equals( null, MRP_Series::get_post_series( 801 ), '0 posts modified: Post 801 was not assigned' );
assert_equals( null, MRP_Series::get_post_series( 803 ), '0 posts modified: Post 803 was not assigned' );

// Reset permission
unset( $GLOBALS['_wp_current_user_caps'][802] );

// TEST 16: Multiple Blocks in Single Post
echo "\nTest 16: Multiple Blocks in Single Post\n";
create_mock_post( 60, 'Manga with Series and Spinoffs' );
$multi_block_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"series","selectedPosts":[1,2],"episodeNumber":"60"} /-->';
$multi_block_content .= "\n<p>Some manga text</p>\n";
$multi_block_content .= '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"spinoff","selectedPosts":[201,301],"isReciprocal":false} /-->';

$post_60_obj = $GLOBALS['_wp_posts'][60];
$post_60_obj->post_content = $multi_block_content;
MRP_Series::on_after_insert_post( 60, $post_60_obj, true );

assert_true( null !== MRP_Series::get_post_series( 60 ), 'Post 60 has series synced from first block' );
assert_equals( array( 201, 301 ), MRP_Relationships::get_manual_related_ids( 60 ), 'Post 60 has spinoffs synced from second block' );

// Deduplication on multiple blocks: since post 60 has both, auto-render must not add duplicates of either!
$GLOBALS['post'] = $post_60_obj;
$multi_filtered = MRP_Auto_Render::filter_content( $multi_block_content );
assert_equals( $multi_block_content, $multi_filtered, 'Auto-render injects nothing when both series and spinoff blocks are present' );

// TEST 17: Large Series Support (> 100 chapters)
echo "\nTest 17: Large Series Support (> 100 chapters)\n";
$large_series_res = wp_insert_term( 'Mega Series', 'mrp_series' );
$large_term_id = $large_series_res['term_id'];

for ( $k = 1001; $k <= 1150; $k++ ) {
    create_mock_post( $k, "Mega Cap {$k}" );
    MRP_Series::assign_post_to_series( $k, $large_term_id, (string) ($k - 1000) );
}

$large_posts = MRP_Series::get_series_posts( $large_term_id );
assert_equals( 150, count( $large_posts ), 'Retrieved all 150 chapters without being truncated by 100 limit' );
assert_equals( '1', get_post_meta( $large_posts[0]->ID, MRP_Series::META_EPISODE, true ), 'First post is Episode 1' );
assert_equals( '150', get_post_meta( end($large_posts)->ID, MRP_Series::META_EPISODE, true ), 'Last post is Episode 150' );

// TEST 18: Auto-linked Chapter with Existing Classic Block Retains Series on Save (Audit Scenario 1)
echo "\nTest 18: Auto-linked Chapter with Classic Block Retains Series on Save\n";
create_mock_post( 53, 'Capítulo 53 (Auto-vinculado con bloque clásico)' );
MRP_Series::assign_post_to_series( 53, $series_term->term_id, '53' );
assert_true( null !== MRP_Series::get_post_series( 53 ), 'Chapter 53 assigned to series' );

// Chapter 53 has a classic block and editor updates text
$post_53_obj = $GLOBALS['_wp_posts'][53];
$post_53_obj->post_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"classic","selectedPosts":[101,102]} /--><p>Texto editado</p>';
MRP_Series::on_after_insert_post( 53, $post_53_obj, true );

assert_true( null !== MRP_Series::get_post_series( 53 ), 'Auto-linked chapter with classic block RETAINS series membership' );
assert_equals( '53', get_post_meta( 53, MRP_Series::META_EPISODE, true ), 'Chapter 53 retains its episode number' );

// TEST 19: Deleting Last Series Block from Post Unlinks Post (Audit Scenario 1 Part 2)
echo "\nTest 19: Deleting Last Series Block from Post Unlinks Post\n";
create_mock_post( 54, 'Capítulo 54' );
$post_54_obj = $GLOBALS['_wp_posts'][54];
$post_54_obj->post_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"series","selectedPosts":[1,2],"episodeNumber":"54"} /-->';
MRP_Series::on_after_insert_post( 54, $post_54_obj, true );
assert_true( null !== MRP_Series::get_post_series( 54 ), 'Chapter 54 joined series via block' );

// Editor removes the series block completely (post now has standard paragraphs only)
$post_54_obj->post_content = '<p>Texto sin bloques MRP</p>';
MRP_Series::on_after_insert_post( 54, $post_54_obj, true );
assert_equals( null, MRP_Series::get_post_series( 54 ), 'Deleting series block successfully unlinks post from series' );

// TEST 20: Persistent Unlink with Series Block Still in Content Blocked from Auto-Rejoin (Audit Scenario 2)
echo "\nTest 20: Persistent Unlink with Series Block in Content Blocked from Auto-Rejoin\n";
create_mock_post( 55, 'Capítulo 55' );
$post_55_obj = $GLOBALS['_wp_posts'][55];
$post_55_obj->post_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"series","selectedPosts":[1,2],"episodeNumber":"55"} /-->';
MRP_Series::on_after_insert_post( 55, $post_55_obj, true );
assert_true( null !== MRP_Series::get_post_series( 55 ), 'Chapter 55 joined series' );

// Editor explicitly unlinks chapter 55
MRP_Series::unlink_post_from_series( 55 );
assert_equals( null, MRP_Series::get_post_series( 55 ), 'Chapter 55 unlinked' );

// Post 55 is saved again while still containing the series block in content, without relink confirmation
MRP_Series::on_after_insert_post( 55, $post_55_obj, true );
assert_equals( null, MRP_Series::get_post_series( 55 ), 'Re-saving post 55 does NOT re-join series without explicit confirmation' );

// TEST 21: Selecting Previously Unlinked Chapter Returns Explicit Error (Audit Scenario 3 Part 1)
echo "\nTest 21: Selecting Previously Unlinked Chapter Returns Error without Confirmation\n";
create_mock_post( 56, 'Capítulo 56' );
// Chapter 56 selects Chapter 1 and the previously unlinked Chapter 55 without confirmation
$sync_unlinked_attempt = MRP_Series::sync_post_series( 56, array( 1, 55 ), '56', '', false );
assert_true( ! $sync_unlinked_attempt['success'], 'Sync fails when selecting an unlinked chapter without confirmation (NEVER returns success: true)' );
assert_equals( 'unlinked_posts_detected', $sync_unlinked_attempt['error'], 'Error code is unlinked_posts_detected' );
assert_true( in_array( 55, $sync_unlinked_attempt['unlinked_post_ids'], true ), 'Unlinked ID 55 is identified in error payload' );
assert_equals( null, MRP_Series::get_post_series( 56 ), 'Post 56 was not assigned to series' );

// TEST 22: Reincorporating Unlinked Chapter with Confirmation Clears Exclusion (Audit Scenario 3 Part 2)
echo "\nTest 22: Reincorporating Unlinked Chapter with Confirmation\n";
$sync_relink_ok = MRP_Series::sync_post_series( 56, array( 1, 55 ), '56', '', true );
assert_true( $sync_relink_ok['success'], 'Sync succeeds when relink confirmation is true' );
assert_true( null !== MRP_Series::get_post_series( 55 ), 'Post 55 is successfully reincorporated' );
assert_true( null !== MRP_Series::get_post_series( 56 ), 'Post 56 is in series' );
assert_true( ! in_array( $series_term->term_id, MRP_Series::get_unlinked_series_ids( 55 ), true ), 'Unlinked flag for Post 55 is cleared' );

// TEST 23: Atomic Permission Validation on Reciprocal Cleanup Target (Audit Scenario 4)
echo "\nTest 23: Atomic Permission Validation on Reciprocal Cleanup Target\n";
create_mock_post( 901, 'Source Post' );
create_mock_post( 902, 'Target Post A' );
create_mock_post( 903, 'Target Post B' );

// Initial sync: 901 reciprocally links to 902 and 903
$init_spinoff = MRP_Relationships::sync_spinoff_relationships( 901, array( 902, 903 ), true );
assert_true( $init_spinoff['success'], 'Initial reciprocal spinoff sync succeeds' );
assert_true( in_array( 901, MRP_Relationships::get_reciprocal_related_ids( 902 ), true ), '902 has reciprocal link from 901' );
assert_true( in_array( 901, MRP_Relationships::get_reciprocal_related_ids( 903 ), true ), '903 has reciprocal link from 901' );

// Deny permission on 902
$GLOBALS['_wp_current_user_caps'][902] = false;

// 901 attempts to remove 902 from targets while keeping 903
$recip_rem_fail = MRP_Relationships::sync_spinoff_relationships( 901, array( 903 ), true );
assert_true( ! $recip_rem_fail['success'], 'Reciprocal removal fails atomically when user lacks edit_post on removed target' );
assert_equals( 'permission_denied', $recip_rem_fail['error'], 'Error code is permission_denied' );
// Verify atomic rollback: 902 still has 901 (no partial corruption)
assert_true( in_array( 901, MRP_Relationships::get_reciprocal_related_ids( 902 ), true ), 'No partial update: 902 reciprocal list unchanged' );

// 901 attempts to disable reciprocity completely while lacking permission on 902
$recip_off_fail = MRP_Relationships::sync_spinoff_relationships( 901, array(), false );
assert_true( ! $recip_off_fail['success'], 'Disabling reciprocity fails atomically when lacking permission on cleanup target' );
assert_equals( 'permission_denied', $recip_off_fail['error'], 'Error code is permission_denied' );

// Restore permission
unset( $GLOBALS['_wp_current_user_caps'][902] );

// TEST 24: REST API Endpoints Integration (/series-check, /unlink-series, /relink-series)
echo "\nTest 24: REST API Endpoints Integration\n";
create_mock_post( 950, 'REST Test Chapter' );
MRP_Series::assign_post_to_series( 950, $series_term->term_id, '950' );

// 1. series-check
$req_check = new WP_REST_Request( array( 'ids' => array( 1, 950 ), 'postId' => 951 ) );
$res_check = MRP_Block::handle_series_check_endpoint( $req_check );
assert_true( $res_check['detected'], 'REST series-check detected series' );
assert_equals( $series_term->term_id, $res_check['seriesList'][0]['id'], 'REST series-check matched series ID' );
assert_true( count( $res_check['members'] ) > 0, 'REST series-check returned series members' );

// 2. unlink-series via REST
$req_unlink = new WP_REST_Request( array( 'id' => 950 ) );
$res_unlink = MRP_Block::handle_unlink_series_endpoint( $req_unlink );
assert_true( $res_unlink['success'], 'REST unlink-series returned success' );
assert_equals( null, MRP_Series::get_post_series( 950 ), 'Post 950 unlinked from series via REST' );

// 3. series-check detects Post 950 as unlinked
$req_check2 = new WP_REST_Request( array( 'ids' => array( 1, 950 ), 'postId' => 951 ) );
$res_check2 = MRP_Block::handle_series_check_endpoint( $req_check2 );
assert_true( count( $res_check2['unlinkedPosts'] ) > 0, 'REST series-check detected unlinked chapter' );
assert_equals( 950, $res_check2['unlinkedPosts'][0]['id'], 'Unlinked post ID in check response is 950' );

// 4. relink-series via REST
$req_relink = new WP_REST_Request( array( 'ids' => array( 950 ), 'series_id' => $series_term->term_id ) );
$res_relink = MRP_Block::handle_relink_series_endpoint( $req_relink );
assert_true( $res_relink['success'], 'REST relink-series returned success' );
assert_true( ! in_array( $series_term->term_id, MRP_Series::get_unlinked_series_ids( 950 ), true ), 'Post 950 unlinked flag cleared via REST relink' );

// TEST 25: Fallo 1 - Reincorporation Permission Denial MUST NOT Erase Unlinked Flag
echo "\nTest 25: Reincorporation Permission Denial Preserves Unlinked Flag (Fallo 1 Fix)\n";
create_mock_post( 99, 'Capítulo 99 (Desvinculado)' );
// Assign to series first, then unlink to establish unlinked flag
MRP_Series::assign_post_to_series( 99, $series_term->term_id, '99' );
MRP_Series::unlink_post_from_series( 99 );
assert_true( in_array( $series_term->term_id, MRP_Series::get_unlinked_series_ids( 99 ), true ), 'Post 99 has unlinked flag before test' );

// Deny permission on post 99
$GLOBALS['_wp_current_user_caps'][99] = false;

// Attempt to reincorporate post 99 with confirmation
create_mock_post( 100, 'Capítulo 100' );
$failed_relink = MRP_Series::sync_post_series( 100, array( 1, 99 ), '100', '', true );
assert_true( ! $failed_relink['success'], 'Sync fails with permission_denied' );
assert_equals( 'permission_denied', $failed_relink['error'], 'Error is permission_denied' );

// CRUCIAL AUDIT CHECK: _mrp_unlinked_series MUST NOT have been erased!
$unlinked_after = MRP_Series::get_unlinked_series_ids( 99 );
assert_true( in_array( $series_term->term_id, $unlinked_after, true ), 'Audit Fallo 1 Fix: _mrp_unlinked_series was NOT erased when permission was denied' );

// Restore permission on 99
unset( $GLOBALS['_wp_current_user_caps'][99] );

// TEST 26: Fallo 2 - WordPress Term Assignment Failure and Atomic Rollback
echo "\nTest 26: WordPress Term Assignment Failure and Atomic Rollback (Fallo 2 Fix)\n";
create_mock_post( 88, 'Capítulo 88' );
create_mock_post( 89, 'Capítulo 89' );

// Simulate WordPress failure on post 89
$GLOBALS['_wp_fail_terms'][89] = true;

$term_fail_sync = MRP_Series::sync_post_series( 88, array( 89 ), '88', 'Fail Series' );
assert_true( ! $term_fail_sync['success'], 'Sync fails when WordPress rejects wp_set_object_terms' );
assert_equals( 'term_assignment_failed', $term_fail_sync['error'], 'Error is term_assignment_failed' );
assert_true( in_array( 89, $term_fail_sync['failed_post_ids'], true ), 'Post 89 is reported in failed_post_ids' );

// Atomic rollback: verify Post 88 was NOT left assigned in DB!
assert_equals( null, MRP_Series::get_post_series( 88 ), 'Audit Fallo 2 Fix: Post 88 was rolled back and not left assigned' );
assert_equals( null, MRP_Series::get_post_series( 89 ), 'Post 89 was not assigned' );

// Clear failure mock
unset( $GLOBALS['_wp_fail_terms'][89] );

// TEST 27: Error Handling on wp_remove_object_terms
echo "\nTest 27: Error Handling on wp_remove_object_terms\n";
create_mock_post( 77, 'Capítulo 77' );
MRP_Series::assign_post_to_series( 77, $series_term->term_id, '77' );

// Simulate WordPress removal failure on 77
$GLOBALS['_wp_fail_remove_terms'][77] = true;
$unlink_res = MRP_Series::unlink_post_from_series( 77 );
assert_true( false === $unlink_res, 'unlink_post_from_series returns false on WordPress failure' );
assert_true( null !== MRP_Series::get_post_series( 77 ), 'Post 77 remains in series when removal fails' );

// Clear removal failure mock
unset( $GLOBALS['_wp_fail_remove_terms'][77] );

// TEST 28: Editor Error Communication via Meta & Transient
echo "\nTest 28: Editor Error Communication via Meta & Transient\n";
create_mock_post( 910, 'Save Fail Post' );
// Deny permission on post 911
create_mock_post( 911, 'Forbidden Post' );
$GLOBALS['_wp_current_user_caps'][911] = false;

$post_910_obj = $GLOBALS['_wp_posts'][910];
$post_910_obj->post_content = '<!-- wp:manual-related-posts-pro/manual-related-posts {"mode":"series","selectedPosts":[911],"episodeNumber":"910"} /-->';
MRP_Series::on_after_insert_post( 910, $post_910_obj, true );

// Check meta _mrp_last_sync_status
$meta_status_raw = get_post_meta( 910, MRP_Series::META_SYNC_STATUS, true );
assert_true( ! empty( $meta_status_raw ), 'Post meta _mrp_last_sync_status is saved on failure' );
$meta_status = json_decode( $meta_status_raw, true );
assert_equals( 'permission_denied', $meta_status['error'], 'Meta recorded error code permission_denied' );

// Check transient for admin notices
$transient_status = get_transient( 'mrp_sync_error_910' );
assert_true( ! empty( $transient_status ), 'Transient mrp_sync_error_910 is set for admin notices' );
assert_equals( 'permission_denied', $transient_status['error'], 'Transient recorded error code' );

// Check REST series-check endpoint passes lastSyncError
$req_check_err = new WP_REST_Request( array( 'ids' => array( 1 ), 'postId' => 910 ) );
$res_check_err = MRP_Block::handle_series_check_endpoint( $req_check_err );
assert_true( ! empty( $res_check_err['lastSyncError'] ), 'REST series-check exposes lastSyncError to Gutenberg' );
assert_equals( 'permission_denied', $res_check_err['lastSyncError']['error'], 'Gutenberg receives permission_denied error' );

// Restore permission on 911
unset( $GLOBALS['_wp_current_user_caps'][911] );

// TEST 29: Multi-Author Post Search Security and Accessibility
echo "\nTest 29: Multi-Author Post Search\n";
// Create posts by Author B (user ID 20)
create_mock_post( 1201, 'Capítulo Especial de Autor B', 'publish', '', 20 );
create_mock_post( 1202, 'Borrador Privado de Autor B', 'draft', '', 20 );

// Current user is Author A (user ID 10) who CANNOT edit others posts
$GLOBALS['_wp_current_user_id'] = 10;
$GLOBALS['_wp_current_user_caps']['edit_others_posts'] = false;
create_mock_post( 1203, 'Mi Borrador Propio de Autor A', 'draft', '', 10 );

// Search for 'Autor'
$search_req = new WP_REST_Request( array( 'search' => 'Autor' ) );
$search_res = MRP_Block::handle_posts_endpoint( $search_req );
$found_ids = array_map( function( $p ) { return $p['id']; }, $search_res['results'] );

assert_true( in_array( 1201, $found_ids, true ), 'Author A can find published post 1201 written by Author B' );
assert_true( in_array( 1203, $found_ids, true ), 'Author A can find their own draft 1203' );
assert_true( ! in_array( 1202, $found_ids, true ), 'Author A CANNOT see Author B private draft 1202' );

// Reset user context
unset( $GLOBALS['_wp_current_user_id'] );
unset( $GLOBALS['_wp_current_user_caps']['edit_others_posts'] );

// SUMMARY
echo "\n=====================================\n";
echo "TEST RESULTS: {$tests_passed} passed, {$tests_failed} failed.\n";
echo "=====================================\n";

if ( $tests_failed > 0 ) {
    exit( 1 );
}
