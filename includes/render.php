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
		'calendar'      => __( 'Contribution calendar', 'git-widget' ),
		'overview'      => __( 'Activity overview (totals and streaks)', 'git-widget' ),
		'types'         => __( 'Contribution types (commits, pull requests, issues, reviews)', 'git-widget' ),
		'monthly'       => __( 'Contributions per month (chart)', 'git-widget' ),
		'yearly'        => __( 'Contributions per year (chart)', 'git-widget' ),
		'achievements'  => __( 'Achievements', 'git-widget' ),
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
		'years'     => 6,
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
 * Contribution calendar: one square per day for the last 12 months (or a chosen year), weeks as columns.
 * Drawn as an SVG that scales to its container.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (year).
 * @return string HTML.
 */
function gw_section_calendar( $user, $a ) {
	$year = (int) $a['year'] ? (int) $a['year'] : null;
	$cal  = gw_calendar( $user, $year );
	if ( is_wp_error( $cal ) ) {
		return gw_error( $cal );
	}
	if ( ! $cal['days'] ) {
		return gw_error( __( 'No contribution data found.', 'git-widget' ) );
	}
	$days  = $cal['days'];
	$dates = array_keys( $days );
	$start = strtotime( $dates[0] );
	$start = $start - (int) gmdate( 'w', $start ) * DAY_IN_SECONDS; // Back up to Sunday.
	$max   = max( 1, max( $days ) );
	$cell  = 11;
	$gap   = 3;
	$left  = 28;
	$top   = 16;
	$rects = '';
	$month = '';
	$col   = 0;
	for ( $t = $start; $t <= strtotime( end( $dates ) ); $t += DAY_IN_SECONDS ) {
		$dow  = (int) gmdate( 'w', $t );
		$col  = (int) floor( ( $t - $start ) / WEEK_IN_SECONDS );
		$date = gmdate( 'Y-m-d', $t );
		if ( 0 === $dow && (int) gmdate( 'j', $t ) <= 7 ) {
			$month .= '<text x="' . ( $left + $col * ( $cell + $gap ) ) . '" y="10">' . esc_html( date_i18n( 'M', $t ) ) . '</text>';
		}
		if ( ! isset( $days[ $date ] ) ) {
			continue;
		}
		$n      = $days[ $date ];
		$level  = 0 === $n ? 0 : (int) min( 4, ceil( 4 * $n / $max ) );
		$label  = sprintf( _n( '%1$s contribution on %2$s', '%1$s contributions on %2$s', $n, 'git-widget' ), number_format_i18n( $n ), date_i18n( 'M j, Y', $t ) );
		$rects .= '<rect class="gw-l' . $level . '" x="' . ( $left + $col * ( $cell + $gap ) ) . '" y="' . ( $top + $dow * ( $cell + $gap ) ) . '" width="' . $cell . '" height="' . $cell . '" rx="2"><title>' . esc_html( $label ) . '</title></rect>';
	}
	$width  = $left + ( $col + 1 ) * ( $cell + $gap );
	$height = $top + 7 * ( $cell + $gap );
	$labels = '';
	foreach ( array( 1 => __( 'Mon', 'git-widget' ), 3 => __( 'Wed', 'git-widget' ), 5 => __( 'Fri', 'git-widget' ) ) as $d => $l ) {
		$labels .= '<text x="0" y="' . ( $top + $d * ( $cell + $gap ) + 9 ) . '">' . esc_html( $l ) . '</text>';
	}
	$legend = '';
	for ( $i = 0; $i <= 4; $i++ ) {
		$legend .= '<span class="gw-l' . $i . '"></span>';
	}
	$title = $year
		? sprintf( _n( '%1$s contribution in %2$d', '%1$s contributions in %2$d', $cal['total'], 'git-widget' ), number_format_i18n( $cal['total'] ), $year )
		: sprintf( _n( '%s contribution in the last year', '%s contributions in the last year', $cal['total'], 'git-widget' ), number_format_i18n( $cal['total'] ) );
	return '<p class="gw-label">' . esc_html( $title ) . '</p>'
		. '<div class="gw-cal-scroll"><svg class="gw-cal" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr( $title ) . '">'
		. '<g class="gw-axis">' . $month . $labels . '</g>' . $rects . '</svg></div>'
		. '<p class="gw-legend">' . esc_html__( 'Less', 'git-widget' ) . ' ' . $legend . ' ' . esc_html__( 'More', 'git-widget' ) . '</p>';
}

/**
 * Streaks and other figures from a calendar.
 *
 * @param array $days Date => count, in date order.
 * @return array total, active, current, longest, best_day, best_count.
 */
function gw_calendar_stats( $days ) {
	$current = 0;
	$longest = 0;
	$run     = 0;
	$best    = '';
	foreach ( $days as $date => $n ) {
		$run     = $n > 0 ? $run + 1 : 0;
		$longest = max( $longest, $run );
		if ( '' === $best || $n > $days[ $best ] ) {
			$best = $date;
		}
	}
	// The current streak may include today even if today has no contributions yet.
	$rev = array_reverse( $days, true );
	$i   = 0;
	foreach ( $rev as $n ) {
		if ( $n > 0 ) {
			$current++;
		} elseif ( 0 !== $i ) {
			break;
		}
		$i++;
	}
	return array(
		'total'      => array_sum( $days ),
		'active'     => count( array_filter( $days ) ),
		'current'    => $current,
		'longest'    => $longest,
		'best_day'   => $best,
		'best_count' => $best ? $days[ $best ] : 0,
	);
}

/**
 * Activity overview: contributions, active days, current and longest streak, busiest day.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (year).
 * @return string HTML.
 */
function gw_section_overview( $user, $a ) {
	$year = (int) $a['year'] ? (int) $a['year'] : null;
	$cal  = gw_calendar( $user, $year );
	if ( is_wp_error( $cal ) ) {
		return gw_error( $cal );
	}
	$st    = gw_calendar_stats( $cal['days'] );
	$tiles = array(
		array( gw_num( $st['total'] ), $year ? sprintf( __( 'contributions in %d', 'git-widget' ), $year ) : __( 'contributions in the last year', 'git-widget' ) ),
		array( gw_num( $st['active'] ), __( 'active days', 'git-widget' ) ),
		array( $st['current'], __( 'day current streak', 'git-widget' ) ),
		array( $st['longest'], __( 'day longest streak', 'git-widget' ) ),
	);
	$out = '<p class="gw-label">' . esc_html__( 'Activity overview', 'git-widget' ) . '</p><div class="gw-tiles">';
	foreach ( $tiles as $t ) {
		$out .= '<div class="gw-tile"><strong>' . esc_html( $t[0] ) . '</strong><span>' . esc_html( $t[1] ) . '</span></div>';
	}
	$out .= '</div>';
	if ( $st['best_count'] ) {
		$out .= '<p class="gw-small">' . esc_html( sprintf( __( 'Busiest day: %1$s, with %2$s contributions.', 'git-widget' ), date_i18n( 'F j, Y', strtotime( $st['best_day'] ) ), number_format_i18n( $st['best_count'] ) ) ) . '</p>';
	}
	return $out;
}

/**
 * Contribution types: share of commits, pull requests, issues and code reviews.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (year).
 * @return string HTML.
 */
function gw_section_types( $user, $a ) {
	$year = (int) $a['year'] ? (int) $a['year'] : null;
	$t    = gw_contribution_types( $user, $year );
	if ( is_wp_error( $t ) ) {
		return gw_error( $t );
	}
	$kinds = array(
		'commits'       => __( 'Commits', 'git-widget' ),
		'pull_requests' => __( 'Pull requests', 'git-widget' ),
		'issues'        => __( 'Issues', 'git-widget' ),
		'reviews'       => __( 'Code reviews', 'git-widget' ),
	);
	$total = max( 1, array_sum( array_intersect_key( $t, $kinds ) ) );
	$bar   = '';
	$rows  = '';
	$i     = 0;
	foreach ( $kinds as $k => $label ) {
		$pct   = round( 100 * $t[ $k ] / $total, 1 );
		$bar  .= '<span class="gw-k' . $i . '" style="width:' . esc_attr( $pct ) . '%" title="' . esc_attr( $label . ': ' . $pct . '%' ) . '"></span>';
		$rows .= '<li><span class="gw-dot gw-k' . $i . '"></span><span class="gw-kind">' . esc_html( $label ) . '</span><strong>' . esc_html( number_format_i18n( $t[ $k ] ) ) . '</strong><span class="gw-pct">' . esc_html( round( $pct ) . '%' ) . '</span></li>';
		$i++;
	}
	$out = '<p class="gw-label">' . esc_html( $year ? sprintf( __( 'Contribution types in %d', 'git-widget' ), $year ) : __( 'Contribution types, last 12 months', 'git-widget' ) ) . '</p>'
		. '<div class="gw-bar" role="img" aria-label="' . esc_attr__( 'Share of contribution types', 'git-widget' ) . '">' . $bar . '</div><ul class="gw-types">' . $rows . '</ul>';
	if ( $t['private'] ) {
		$out .= '<p class="gw-small">' . esc_html( sprintf( __( 'Plus %s contributions to private repositories.', 'git-widget' ), number_format_i18n( $t['private'] ) ) ) . '</p>';
	}
	return $out;
}

/**
 * A simple bar chart as SVG, with values above the bars and labels below.
 *
 * @param string[] $labels Bar labels.
 * @param int[]    $values Bar values.
 * @param string   $title  Accessible name.
 * @return string SVG markup.
 */
function gw_bar_chart( $labels, $values, $title ) {
	$n      = max( 1, count( $values ) );
	$max    = max( 1, max( $values ) );
	$w      = 600;
	$h      = 180;
	$bottom = 22;
	$top    = 16;
	$slot   = $w / $n;
	$bar    = min( 46, $slot * 0.62 );
	$out    = '<svg class="gw-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr( $title ) . '">'
		. '<line class="gw-baseline" x1="0" x2="' . $w . '" y1="' . ( $h - $bottom ) . '" y2="' . ( $h - $bottom ) . '"/>';
	foreach ( array_values( $values ) as $i => $v ) {
		$bh   = ( $h - $bottom - $top ) * $v / $max;
		$x    = $i * $slot + ( $slot - $bar ) / 2;
		$y    = $h - $bottom - $bh;
		$out .= '<g><title>' . esc_html( $labels[ $i ] . ': ' . number_format_i18n( $v ) ) . '</title>'
			. '<rect x="' . round( $x, 1 ) . '" y="' . round( $y, 1 ) . '" width="' . round( $bar, 1 ) . '" height="' . round( max( $bh, $v ? 1 : 0 ), 1 ) . '" rx="3"/>'
			. ( $v ? '<text class="gw-val" x="' . round( $x + $bar / 2, 1 ) . '" y="' . round( $y - 4, 1 ) . '">' . esc_html( gw_num( $v ) ) . '</text>' : '' )
			. '<text class="gw-lab" x="' . round( $x + $bar / 2, 1 ) . '" y="' . ( $h - 6 ) . '">' . esc_html( $labels[ $i ] ) . '</text></g>';
	}
	return $out . '</svg>';
}

/**
 * Contributions per month for the last 12 months (or a chosen year).
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (year).
 * @return string HTML.
 */
function gw_section_monthly( $user, $a ) {
	$year = (int) $a['year'] ? (int) $a['year'] : null;
	$cal  = gw_calendar( $user, $year );
	if ( is_wp_error( $cal ) ) {
		return gw_error( $cal );
	}
	$months = array();
	foreach ( $cal['days'] as $date => $n ) {
		$key            = substr( $date, 0, 7 );
		$months[ $key ] = ( $months[ $key ] ?? 0 ) + $n;
	}
	$months = array_slice( $months, -12, 12, true );
	$labels = array_map(
		function ( $m ) {
			return date_i18n( 'M', strtotime( $m . '-01' ) );
		},
		array_keys( $months )
	);
	$title  = $year ? sprintf( __( 'Contributions per month in %d', 'git-widget' ), $year ) : __( 'Contributions per month', 'git-widget' );
	return '<p class="gw-label">' . esc_html( $title ) . '</p>' . gw_bar_chart( $labels, array_values( $months ), $title );
}

/**
 * Contributions per year for the most recent years (option "years", default 6).
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (years).
 * @return string HTML.
 */
function gw_section_yearly( $user, $a ) {
	$years  = array_slice( gw_contribution_years( $user ), 0, max( 1, min( 20, (int) $a['years'] ) ) );
	$years  = array_reverse( $years );
	$values = array();
	foreach ( $years as $y ) {
		$t        = gw_year_total( $user, $y );
		$values[] = is_wp_error( $t ) ? 0 : $t;
	}
	if ( ! array_sum( $values ) ) {
		return gw_error( __( 'No yearly contribution data found.', 'git-widget' ) );
	}
	$title = __( 'Contributions per year', 'git-widget' );
	return '<p class="gw-label">' . esc_html( $title ) . '</p>' . gw_bar_chart( array_map( 'strval', $years ), $values, $title );
}

/**
 * Achievement badges with their tier count (x2, x3...).
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options.
 * @return string HTML, empty when there are none.
 */
function gw_section_achievements( $user, $a ) {
	$list = gw_achievements( $user );
	if ( is_wp_error( $list ) ) {
		return gw_error( $list );
	}
	if ( ! $list ) {
		return '';
	}
	$out = '<p class="gw-label">' . esc_html__( 'Achievements', 'git-widget' ) . '</p><ul class="gw-badges">';
	foreach ( $list as $b ) {
		$out .= '<li><a href="' . esc_url( $b['url'] ) . '" target="_blank" rel="noopener" title="' . esc_attr( $b['name'] ) . '">'
			. '<img src="' . esc_url( $b['image'] ) . '" alt="' . esc_attr( $b['name'] ) . '" width="64" height="64" loading="lazy">'
			. ( $b['count'] > 1 ? '<span class="gw-tier">x' . (int) $b['count'] . '</span>' : '' )
			. '<span class="gw-badge-name">' . esc_html( $b['name'] ) . '</span></a></li>';
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
