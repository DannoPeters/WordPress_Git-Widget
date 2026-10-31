<?php
/**
 * Rendering: one function per section, combined by gw_render().
 *
 * Every widget instance is independent: its own user, sections and options, and a unique id so several
 * can sit on the same page.
 *
 * @package GitWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sections a widget can show, in their default order.
 *
 * @return array Key => label.
 */
function gw_sections() {
	return array(
		'profile'      => __( 'Profile (photo, name, bio, links)', 'git-widget' ),
		'organisations' => __( 'Organisations', 'git-widget' ),
	);
}

/**
 * Default options for a widget instance.
 *
 * @return array
 */
function gw_defaults() {
	return array(
		'user'      => '',
		'sections'  => 'profile',
		'year'      => '',
		'accent'    => '#2da44e',
		'theme'     => 'auto',
		'layout'    => 'stack',
		'title'     => '',
		'show_link' => 'yes',
	);
}

/**
 * Render a widget.
 *
 * @param array $atts See gw_defaults(). sections is a comma-separated list of gw_sections() keys.
 * @return string HTML.
 */
function gw_render( $atts ) {
	$a    = wp_parse_args( array_filter( (array) $atts, 'strlen' ), gw_defaults() );
	$user = gw_clean_user( $a['user'] ? $a['user'] : gw_settings()['username'] );
	if ( ! $user ) {
		return current_user_can( 'manage_options' ) ? '<p class="gw-note">' . esc_html__( 'Git Widget: set a GitHub username in Settings > Git Widget or on the widget.', 'git-widget' ) . '</p>' : '';
	}
	gw_enqueue();
	$sections = array_intersect( array_map( 'trim', explode( ',', $a['sections'] ) ), array_keys( gw_sections() ) );
	$id       = 'gw-' . wp_unique_id();
	$classes  = array( 'gw', 'gw-theme-' . sanitize_html_class( $a['theme'] ), 'gw-layout-' . sanitize_html_class( $a['layout'] ) );
	$style    = '--gw-accent:' . ( sanitize_hex_color( $a['accent'] ) ? sanitize_hex_color( $a['accent'] ) : '#2da44e' );

	$out = '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( $style ) . '">';
	if ( '' !== $a['title'] ) {
		$out .= '<h3 class="gw-title">' . esc_html( $a['title'] ) . '</h3>';
	}
	$out .= '<div class="gw-sections">';
	foreach ( $sections as $section ) {
		$fn   = 'gw_section_' . $section;
		$html = function_exists( $fn ) ? $fn( $user, $a ) : '';
		if ( '' !== $html ) {
			$out .= '<section class="gw-section gw-' . esc_attr( $section ) . '">' . $html . '</section>';
		}
	}
	$out .= '</div>';
	if ( 'yes' === $a['show_link'] ) {
		$out .= '<p class="gw-footer"><a href="' . esc_url( 'https://github.com/' . $user ) . '" target="_blank" rel="noopener">' . esc_html( sprintf( __( 'View %s on GitHub', 'git-widget' ), '@' . $user ) ) . '</a></p>';
	}
	return $out . '</div>';
}

/**
 * A small notice shown in place of a section that can't load.
 *
 * @param WP_Error|string $err Error or message.
 * @return string HTML, visible to editors only.
 */
function gw_error( $err ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return '<p class="gw-note">' . esc_html( is_wp_error( $err ) ? $err->get_error_message() : $err ) . '</p>';
}

/**
 * Format a number compactly (1.2k).
 *
 * @param int $n Number.
 * @return string
 */
function gw_num( $n ) {
	return $n >= 10000 ? round( $n / 1000 ) . 'k' : ( $n >= 1000 ? round( $n / 1000, 1 ) . 'k' : number_format_i18n( $n ) );
}

/**
 * Profile: photo, name, username, bio, company, location, website, followers, repositories.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options.
 * @return string HTML.
 */
function gw_section_profile( $user, $a ) {
	$p = gw_profile( $user );
	if ( is_wp_error( $p ) ) {
		return gw_error( $p );
	}
	$meta = array();
	if ( $p['company'] ) {
		$meta[] = esc_html( $p['company'] );
	}
	if ( $p['location'] ) {
		$meta[] = esc_html( $p['location'] );
	}
	if ( $p['blog'] ) {
		$url    = preg_match( '#^https?://#', $p['blog'] ) ? $p['blog'] : 'https://' . $p['blog'];
		$meta[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://#', '', rtrim( $p['blog'], '/' ) ) ) . '</a>';
	}
	return '<div class="gw-profile">'
		. '<a class="gw-avatar" href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $p['avatar'] ) . '&s=240" alt="' . esc_attr( $p['name'] ? $p['name'] : $p['login'] ) . '" width="120" height="120" loading="lazy"></a>'
		. '<div class="gw-profile-text">'
		. ( $p['name'] ? '<p class="gw-name">' . esc_html( $p['name'] ) . '</p>' : '' )
		. '<p class="gw-login"><a href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener">@' . esc_html( $p['login'] ) . '</a></p>'
		. ( $p['bio'] ? '<p class="gw-bio">' . esc_html( $p['bio'] ) . '</p>' : '' )
		. ( $meta ? '<p class="gw-meta">' . implode( ' · ', $meta ) . '</p>' : '' )
		. '<p class="gw-counts"><span><strong>' . esc_html( gw_num( $p['followers'] ) ) . '</strong> ' . esc_html__( 'followers', 'git-widget' ) . '</span>'
		. '<span><strong>' . esc_html( gw_num( $p['following'] ) ) . '</strong> ' . esc_html__( 'following', 'git-widget' ) . '</span>'
		. '<span><strong>' . esc_html( gw_num( $p['repos'] ) ) . '</strong> ' . esc_html__( 'public repositories', 'git-widget' ) . '</span></p>'
		. '</div></div>';
}

/**
 * Organisations the user publicly belongs to.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options.
 * @return string HTML, empty when there are none.
 */
function gw_section_organisations( $user, $a ) {
	$p = gw_profile( $user );
	if ( is_wp_error( $p ) ) {
		return gw_error( $p );
	}
	if ( ! $p['orgs'] ) {
		return '';
	}
	$out = '<p class="gw-label">' . esc_html__( 'Organisations', 'git-widget' ) . '</p><ul class="gw-orgs">';
	foreach ( $p['orgs'] as $o ) {
		$out .= '<li><a href="' . esc_url( $o['url'] ) . '" target="_blank" rel="noopener" title="' . esc_attr( $o['description'] ? $o['description'] : $o['login'] ) . '">'
			. '<img src="' . esc_url( $o['avatar'] ) . '&s=64" alt="" width="32" height="32" loading="lazy"><span>' . esc_html( $o['login'] ) . '</span></a></li>';
	}
	return $out . '</ul>';
}

/**
 * Load the stylesheet once per page.
 *
 * @return void
 */
function gw_enqueue() {
	wp_enqueue_style( 'git-widget', GW_URL . 'assets/git-widget.css', array(), GW_VERSION );
}
