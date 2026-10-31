<?php
/**
 * Development preview: renders widgets with sample data outside WordPress.
 * Usage: php dev/preview.php > preview.html
 *
 * @package GitWidget
 */

// Minimal stand-ins for the WordPress functions the plugin uses.
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'YEAR_IN_SECONDS', 31536000 );
$GLOBALS['gw_t'] = array();
function __( $s ) { return $s; }
function esc_html__( $s ) { return htmlspecialchars( $s ); }
function esc_attr__( $s ) { return htmlspecialchars( $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_html_class( $s ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $s ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#[0-9a-f]{3,6}$/i', $c ) ? $c : null; }
function sanitize_title( $s ) { return strtolower( trim( preg_replace( '/[^A-Za-z0-9]+/', '-', $s ), '-' ) ); }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function wp_unique_id() { static $i = 0; return ++$i; }
function current_user_can() { return true; }
function get_option( $k, $d = false ) { return 'gw_settings' === $k ? array( 'username' => 'octocat', 'token' => getenv( 'GW_TOKEN' ) ? 'x' : '', 'cache_hours' => 6 ) : $d; }
function update_option() {}
function get_transient( $k ) { return $GLOBALS['gw_t'][ $k ] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['gw_t'][ $k ] = $v; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error { public $m; function __construct( $c, $m ) { $this->m = $m; } function get_error_message() { return $this->m; } }
function _n( $s, $p, $n ) { return 1 == $n ? $s : $p; }
function number_format_i18n( $n ) { return number_format( $n ); }
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function add_action() {}
function add_shortcode() {}
function shortcode_atts( $d, $a ) { return array_merge( $d, array_intersect_key( $a, $d ) ); }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return ''; }
function date_i18n( $f, $t ) { return gmdate( $f, $t ); }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }

require dirname( __DIR__ ) . '/git-widget.php';

// Sample data, cached under the same keys the plugin uses.
$sample = json_decode( file_get_contents( __DIR__ . '/sample.json' ), true );
foreach ( $sample as $key => $value ) {
	set_transient( 'gw_' . md5( $key ), $value );
}

$css = file_get_contents( dirname( __DIR__ ) . '/assets/git-widget.css' );
echo "<!doctype html><meta charset=utf-8><title>Git Widget preview</title><style>body{font-family:system-ui;margin:24px;max-width:1100px}"
	. ".pair{display:grid;grid-template-columns:1fr 1fr;gap:24px}.dark{background:#1e1e1e;color:#ddd;padding:16px}{$css}</style>";
echo '<script>' . file_get_contents( dirname( __DIR__ ) . '/assets/git-widget.js' ) . '</script>';
$cases = json_decode( file_get_contents( __DIR__ . '/cases.json' ), true );
foreach ( $cases as $label => $atts ) {
	echo '<h2>' . esc_html( $label ) . '</h2><div class="pair"><div>' . gw_render( $atts ) . '</div><div class="dark">' . gw_render( $atts ) . '</div></div>';
}
echo '<h2>Shortcodes side by side</h2><div class="pair"><div>' . gw_shortcode( array( 'sections' => 'profile,organisations' ) ) . '</div><div>'
	. gw_shortcode( array( 'sections' => 'overview,calendar', 'theme' => 'dark', 'accent' => '#8250df', 'title' => 'This year on GitHub' ) ) . '</div></div>';
