<?php
/**
 * [git_widget] shortcode. Each use is its own widget, so a page can have several:
 *
 *     [git_widget sections="profile"]
 *     [git_widget sections="calendar,overview" user="octocat" accent="#8250df" layout="grid"]
 *
 * Attributes: sections, user, year, years, accent, theme (auto|light|dark), layout (stack|grid), title, show_link (yes|no).
 *
 * @package GitWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the shortcode.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string HTML.
 */
function gw_shortcode( $atts ) {
	return gw_render( shortcode_atts( gw_defaults(), (array) $atts, 'git_widget' ) );
}
add_shortcode( 'git_widget', 'gw_shortcode' );
