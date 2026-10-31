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
		'languages'     => __( 'Top languages (donut chart)', 'git-widget' ),
		'repositories'  => __( 'Top repositories', 'git-widget' ),
		'weekdays'      => __( 'Activity by day of the week', 'git-widget' ),
		'cumulative'    => __( 'Contributions over time (running total)', 'git-widget' ),
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
		'types_style' => 'axes',
		'bio_breaks'  => 'yes',
		'count'       => 6,
		'metrics'     => 'contributions,active_days,current_streak,longest_streak',
		'period'      => '',
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
		. ( $p['bio'] ? '<p class="gw-bio">' . gw_bio_html( $p['bio'], 'no' !== $a['bio_breaks'] ) . '</p>' : '' )
		. ( $meta ? '<p class="gw-meta">' . implode( ' · ', $meta ) . '</p>' : '' )
		. '<p class="gw-counts"><span><strong>' . esc_html( gw_num( $p['followers'] ) ) . '</strong> ' . esc_html__( 'followers', 'git-widget' ) . '</span>'
		. '<span><strong>' . esc_html( gw_num( $p['following'] ) ) . '</strong> ' . esc_html__( 'following', 'git-widget' ) . '</span>'
		. '<span><strong>' . esc_html( gw_num( $p['repos'] ) ) . '</strong> ' . esc_html__( 'public repositories', 'git-widget' ) . '</span></p>'
		. '</div></div>';
}

/**
 * Bio text as HTML. GitHub bios are a single line, so people pad them with runs of underscores to push
 * text onto the next line. When $breaks is on, any run of two or more underscores (__ or __________) becomes
 * one line break.
 *
 * @param string $bio    Raw bio.
 * @param bool   $breaks Turn underscore runs into line breaks.
 * @return string Escaped HTML.
 */
function gw_bio_html( $bio, $breaks = true ) {
	if ( ! $breaks ) {
		return esc_html( $bio );
	}
	$lines = preg_split( '/\s*_{2,}\s*|\r?\n/', trim( $bio ) );
	return implode( '<br>', array_map( 'esc_html', array_filter( array_map( 'trim', $lines ), 'strlen' ) ) );
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
 * Metrics the activity overview can show, in display order.
 *
 * @return array Key => label.
 */
function gw_overview_metrics() {
	return array(
		'contributions'  => __( 'Contributions', 'git-widget' ),
		'active_days'    => __( 'Active days', 'git-widget' ),
		'current_streak' => __( 'Current streak', 'git-widget' ),
		'longest_streak' => __( 'Longest streak', 'git-widget' ),
		'daily_average'  => __( 'Average per active day', 'git-widget' ),
		'busiest_day'    => __( 'Busiest day', 'git-widget' ),
		'commits'        => __( 'Commits', 'git-widget' ),
		'pull_requests'  => __( 'Pull requests opened', 'git-widget' ),
		'merged_prs'     => __( 'Pull requests merged', 'git-widget' ),
		'issues_opened'  => __( 'Issues opened', 'git-widget' ),
		'reviews'        => __( 'Code reviews', 'git-widget' ),
		'stars'          => __( 'Stars received', 'git-widget' ),
		'forks'          => __( 'Forks of my repositories', 'git-widget' ),
		'repositories'   => __( 'Public repositories', 'git-widget' ),
		'languages'      => __( 'Languages used', 'git-widget' ),
		'followers'      => __( 'Followers', 'git-widget' ),
		'following'      => __( 'Following', 'git-widget' ),
		'organisations'  => __( 'Organisations', 'git-widget' ),
		'member_since'   => __( 'Years on GitHub', 'git-widget' ),
	);
}

/**
 * The time frame a widget covers.
 *
 * @param array $a Widget options (period: last, year, all; year).
 * @return array kind (last|year|all), year, range (search date range or ""), label.
 */
function gw_period( $a ) {
	$kind = in_array( $a['period'], array( 'last', 'year', 'all' ), true ) ? $a['period'] : ( (int) $a['year'] ? 'year' : 'last' );
	$year = (int) $a['year'] ? (int) $a['year'] : (int) gmdate( 'Y' );
	if ( 'all' === $kind ) {
		return array( 'kind' => 'all', 'year' => null, 'range' => '', 'label' => __( 'all time', 'git-widget' ) );
	}
	if ( 'year' === $kind ) {
		return array( 'kind' => 'year', 'year' => $year, 'range' => "$year-01-01..$year-12-31", 'label' => (string) $year );
	}
	return array( 'kind' => 'last', 'year' => null, 'range' => gmdate( 'Y-m-d', strtotime( '-1 year +1 day' ) ) . '..' . gmdate( 'Y-m-d' ), 'label' => __( 'last 12 months', 'git-widget' ) );
}

/**
 * Activity overview: a row of tiles. Which metrics (option "metrics") and the time frame (option "period":
 * last 12 months, a year, or all time) are configurable. Repository and profile figures are always current.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (metrics, period, year).
 * @return string HTML.
 */
function gw_section_overview( $user, $a ) {
	$period = gw_period( $a );
	$all    = gw_overview_metrics();
	$want   = array_values( array_intersect( array_map( 'trim', explode( ',', $a['metrics'] ) ), array_keys( $all ) ) );
	if ( ! $want ) {
		$want = array( 'contributions', 'active_days', 'current_streak', 'longest_streak' );
	}
	$cal_keys = array( 'contributions', 'active_days', 'current_streak', 'longest_streak', 'daily_average', 'busiest_day' );
	$st       = null;
	if ( array_intersect( $want, $cal_keys ) ) {
		$cal = 'all' === $period['kind'] ? gw_calendar_all( $user ) : gw_calendar( $user, $period['year'] );
		if ( is_wp_error( $cal ) ) {
			return gw_error( $cal );
		}
		$st = gw_calendar_stats( $cal['days'] );
	}
	$repos   = array_intersect( $want, array( 'stars', 'forks', 'languages' ) ) ? gw_repos( $user ) : array();
	$profile = array_intersect( $want, array( 'repositories', 'followers', 'following', 'organisations', 'member_since' ) ) ? gw_profile( $user ) : array();
	$tiles   = array();
	foreach ( $want as $m ) {
		$value = null;
		$sub   = '';
		switch ( $m ) {
			case 'contributions':
				$value = $st['total'];
				break;
			case 'active_days':
				$value = $st['active'];
				break;
			case 'current_streak':
				$value = $st['current'];
				$sub   = _n( 'day', 'days', $st['current'], 'git-widget' );
				break;
			case 'longest_streak':
				$value = $st['longest'];
				$sub   = _n( 'day', 'days', $st['longest'], 'git-widget' );
				break;
			case 'daily_average':
				$value = $st['active'] ? round( $st['total'] / $st['active'], 1 ) : 0;
				break;
			case 'busiest_day':
				$value = $st['best_count'];
				$sub   = $st['best_day'] ? date_i18n( 'M j, Y', strtotime( $st['best_day'] ) ) : '';
				break;
			case 'commits':
			case 'pull_requests':
			case 'merged_prs':
			case 'issues_opened':
			case 'reviews':
				$n     = gw_search_count( $user, $m, $period['range'] );
				$value = is_wp_error( $n ) ? null : $n;
				break;
			case 'stars':
			case 'forks':
				if ( ! is_wp_error( $repos ) ) {
					$value = array_sum( array_column( $repos, $m ) );
				}
				break;
			case 'languages':
				if ( ! is_wp_error( $repos ) ) {
					$value = count( array_unique( array_filter( array_column( $repos, 'language' ) ) ) );
				}
				break;
			case 'repositories':
			case 'followers':
			case 'following':
				if ( ! is_wp_error( $profile ) ) {
					$value = $profile[ 'repositories' === $m ? 'repos' : $m ];
				}
				break;
			case 'organisations':
				if ( ! is_wp_error( $profile ) ) {
					$value = count( $profile['orgs'] );
				}
				break;
			case 'member_since':
				if ( ! is_wp_error( $profile ) ) {
					$value = max( 0, (int) floor( ( time() - strtotime( $profile['created'] ) ) / YEAR_IN_SECONDS ) );
					$sub   = sprintf( __( 'since %s', 'git-widget' ), date_i18n( 'Y', strtotime( $profile['created'] ) ) );
				}
				break;
		}
		if ( null !== $value ) {
			$tiles[] = array( is_float( $value ) ? number_format_i18n( $value, 1 ) : gw_num( (int) $value ), $all[ $m ], $sub );
		}
	}
	if ( ! $tiles ) {
		return gw_error( __( 'None of the chosen metrics could be loaded.', 'git-widget' ) );
	}
	$out = '<p class="gw-label">' . esc_html( sprintf( __( 'Activity overview, %s', 'git-widget' ), $period['label'] ) ) . '</p><div class="gw-tiles">';
	foreach ( $tiles as $t ) {
		$out .= '<div class="gw-tile"><strong>' . esc_html( $t[0] ) . '</strong><span>' . esc_html( $t[1] ) . '</span>'
			. ( '' !== $t[2] ? '<em>' . esc_html( $t[2] ) . '</em>' : '' ) . '</div>';
	}
	return $out . '</div>';
}

/**
 * Contribution types: share of commits, pull requests, issues and code reviews.
 * With a token: exact counts from GraphQL. Without one (or if that fails): the percentages GitHub shows in
 * the profile's activity overview. Drawn as GitHub's cross-shaped chart ("axes") or as a bar.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (year, types_style).
 * @return string HTML.
 */
function gw_section_types( $user, $a ) {
	$year   = (int) $a['year'] ? (int) $a['year'] : null;
	$kinds  = array(
		'commits'       => __( 'Commits', 'git-widget' ),
		'pull_requests' => __( 'Pull requests', 'git-widget' ),
		'issues'        => __( 'Issues', 'git-widget' ),
		'reviews'       => __( 'Code review', 'git-widget' ),
	);
	$counts = gw_settings()['token'] ? gw_contribution_types( $user, $year ) : new WP_Error( 'gw_no_token', '' );
	if ( is_wp_error( $counts ) ) {
		$pct = gw_activity_percentages( $user, $year );
		if ( is_wp_error( $pct ) ) {
			return gw_error( $pct );
		}
		$counts = null;
	} else {
		$total = max( 1, array_sum( array_intersect_key( $counts, $kinds ) ) );
		$pct   = array();
		foreach ( $kinds as $k => $l ) {
			$pct[ $k ] = 100 * $counts[ $k ] / $total;
		}
	}
	$title = $year ? sprintf( __( 'Contribution types in %d', 'git-widget' ), $year ) : __( 'Contribution types, last 12 months', 'git-widget' );
	$out   = '<p class="gw-label">' . esc_html( $title ) . '</p>';
	if ( 'bar' === $a['types_style'] ) {
		$bar  = '';
		$rows = '';
		$i    = 0;
		foreach ( $kinds as $k => $label ) {
			$p     = round( $pct[ $k ], 1 );
			$bar  .= '<span class="gw-k' . $i . '" style="width:' . esc_attr( $p ) . '%" title="' . esc_attr( $label . ': ' . round( $p ) . '%' ) . '"></span>';
			$rows .= '<li><span class="gw-dot gw-k' . $i . '"></span><span class="gw-kind">' . esc_html( $label ) . '</span><strong>' . ( $counts ? esc_html( number_format_i18n( $counts[ $k ] ) ) : '' ) . '</strong><span class="gw-pct">' . esc_html( round( $p ) . '%' ) . '</span></li>';
			$i++;
		}
		$out .= '<div class="gw-bar" role="img" aria-label="' . esc_attr( $title ) . '">' . $bar . '</div><ul class="gw-types">' . $rows . '</ul>';
	} else {
		$out .= gw_axes_chart( $pct, $kinds, $title );
	}
	if ( $counts && $counts['private'] ) {
		$out .= '<p class="gw-small">' . esc_html( sprintf( __( 'Plus %s contributions to private repositories.', 'git-widget' ), number_format_i18n( $counts['private'] ) ) ) . '</p>';
	}
	return $out;
}

/**
 * GitHub's activity overview chart: four axes (code review up, issues right, pull requests down,
 * commits left), a point on each at its percentage, and the shape between them filled.
 *
 * @param array  $pct   Kind => percent.
 * @param array  $kinds Kind => label.
 * @param string $title Accessible name.
 * @return string SVG.
 */
function gw_axes_chart( $pct, $kinds, $title ) {
	$cx  = 170;
	$cy  = 120;
	$len = 90;
	$dir = array( 'reviews' => array( 0, -1 ), 'issues' => array( 1, 0 ), 'pull_requests' => array( 0, 1 ), 'commits' => array( -1, 0 ) );
	$pts = array();
	$svg = '';
	foreach ( $dir as $k => $d ) {
		$svg  .= '<line class="gw-axis-line" x1="' . $cx . '" y1="' . $cy . '" x2="' . ( $cx + $d[0] * $len ) . '" y2="' . ( $cy + $d[1] * $len ) . '"/>';
		$r     = max( 2, $len * $pct[ $k ] / 100 );
		$pts[] = ( $cx + $d[0] * $r ) . ',' . ( $cy + $d[1] * $r );
	}
	$svg .= '<polygon class="gw-axis-shape" points="' . implode( ' ', $pts ) . '"/>';
	foreach ( $pts as $p ) {
		list( $x, $y ) = explode( ',', $p );
		$svg          .= '<circle class="gw-axis-dot" cx="' . $x . '" cy="' . $y . '" r="4"/>';
	}
	$place = array( 'reviews' => array( $cx, $cy - $len - 12, 'middle' ), 'issues' => array( $cx + $len + 16, $cy - 2, 'start' ), 'pull_requests' => array( $cx, $cy + $len + 18, 'middle' ), 'commits' => array( $cx - $len - 16, $cy - 2, 'end' ) );
	foreach ( $place as $k => $p ) {
		$share = round( $pct[ $k ] );
		$svg  .= '<text class="gw-axis-label" x="' . $p[0] . '" y="' . $p[1] . '" text-anchor="' . $p[2] . '">'
			. ( $share ? '<tspan x="' . $p[0] . '" dy="0">' . esc_html( $share . '%' ) . '</tspan><tspan x="' . $p[0] . '" dy="1.2em">' : '<tspan x="' . $p[0] . '" dy="0.6em">' )
			. esc_html( $kinds[ $k ] ) . '</tspan></text>';
	}
	return '<svg class="gw-axes" viewBox="0 0 340 250" role="img" aria-label="' . esc_attr( $title ) . '">' . $svg . '</svg>';
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
 * GitHub's colour for common languages, so the donut matches what people see on GitHub.
 *
 * @param string $lang Language name.
 * @param int    $i    Position, used to pick a fallback colour.
 * @return string Hex colour.
 */
function gw_lang_colour( $lang, $i ) {
	$known = array( 'Python' => '#3572A5', 'JavaScript' => '#f1e05a', 'TypeScript' => '#3178c6', 'PHP' => '#4F5D95', 'C' => '#555555', 'C++' => '#f34b7d',
		'C#' => '#178600', 'Java' => '#b07219', 'Go' => '#00ADD8', 'Rust' => '#dea584', 'Ruby' => '#701516', 'Shell' => '#89e051', 'HTML' => '#e34c26',
		'CSS' => '#663399', 'Jupyter Notebook' => '#DA5B0B', 'MATLAB' => '#e16737', 'Arduino' => '#bd79d1', 'Kotlin' => '#A97BFF', 'Swift' => '#F05138',
		'Dart' => '#00B4AB', 'Vue' => '#41b883', 'Lua' => '#000080', 'R' => '#198CE7', 'Dockerfile' => '#384d54', 'Processing' => '#0096D8', 'G-code' => '#D08CF2' );
	$spare = array( '#2da44e', '#8250df', '#bf8700', '#0969da', '#cf222e', '#1b7c83', '#9a6700', '#6e7781' );
	return $known[ $lang ] ?? $spare[ $i % count( $spare ) ];
}

/**
 * Top languages across public repositories, as a donut with a legend. Each repository counts once for its
 * main language, weighted by its size, so one huge repository doesn't hide the rest entirely.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (count = how many languages before "Other").
 * @return string HTML.
 */
function gw_section_languages( $user, $a ) {
	$repos = gw_repos( $user );
	if ( is_wp_error( $repos ) ) {
		return gw_error( $repos );
	}
	$weight = array();
	foreach ( $repos as $r ) {
		if ( $r['language'] ) {
			$weight[ $r['language'] ] = ( $weight[ $r['language'] ] ?? 0 ) + sqrt( max( 1, $r['size'] ) );
		}
	}
	if ( ! $weight ) {
		return '';
	}
	arsort( $weight );
	$top   = array_slice( $weight, 0, max( 1, min( 10, (int) $a['count'] ) ), true );
	$other = array_sum( $weight ) - array_sum( $top );
	if ( $other > 0 ) {
		$top[ __( 'Other', 'git-widget' ) ] = $other;
	}
	$total  = array_sum( $top );
	$r      = 60;
	$circ   = 2 * M_PI * $r;
	$offset = 0;
	$arcs   = '';
	$legend = '';
	$i      = 0;
	foreach ( $top as $lang => $w ) {
		$share   = $w / $total;
		$colour  = gw_lang_colour( $lang, $i );
		$arcs   .= '<circle r="' . $r . '" cx="80" cy="80" fill="none" stroke="' . esc_attr( $colour ) . '" stroke-width="26" stroke-dasharray="' . round( $share * $circ, 2 ) . ' ' . round( $circ, 2 ) . '" stroke-dashoffset="' . round( -$offset, 2 ) . '"><title>' . esc_html( $lang . ': ' . round( 100 * $share ) . '%' ) . '</title></circle>';
		$legend .= '<li><span class="gw-dot" style="background:' . esc_attr( $colour ) . '"></span>' . esc_html( $lang ) . '<span class="gw-pct">' . esc_html( round( 100 * $share ) . '%' ) . '</span></li>';
		$offset += $share * $circ;
		$i++;
	}
	$title = __( 'Top languages', 'git-widget' );
	return '<p class="gw-label">' . esc_html( $title ) . '</p><div class="gw-donut-wrap">'
		. '<svg class="gw-donut" viewBox="0 0 160 160" role="img" aria-label="' . esc_attr( $title ) . '"><g transform="rotate(-90 80 80)">' . $arcs . '</g>'
		. '<text x="80" y="76" class="gw-donut-n">' . count( $repos ) . '</text><text x="80" y="96" class="gw-donut-t">' . esc_html__( 'repos', 'git-widget' ) . '</text></svg>'
		. '<ul class="gw-legend-list">' . $legend . '</ul></div>';
}

/**
 * Top repositories by stars (then most recently updated), as cards.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (count).
 * @return string HTML.
 */
function gw_section_repositories( $user, $a ) {
	$repos = gw_repos( $user );
	if ( is_wp_error( $repos ) ) {
		return gw_error( $repos );
	}
	usort(
		$repos,
		function ( $x, $y ) {
			return array( $y['stars'], $y['forks'], $y['pushed'] ) <=> array( $x['stars'], $x['forks'], $x['pushed'] );
		}
	);
	$out = '<p class="gw-label">' . esc_html__( 'Top repositories', 'git-widget' ) . '</p><div class="gw-repos">';
	foreach ( array_slice( $repos, 0, max( 1, min( 24, (int) $a['count'] ) ) ) as $i => $r ) {
		$out .= '<a class="gw-repo" href="' . esc_url( $r['url'] ) . '" target="_blank" rel="noopener">'
			. '<strong>' . esc_html( $r['name'] ) . '</strong>'
			. ( $r['description'] ? '<span class="gw-repo-desc">' . esc_html( $r['description'] ) . '</span>' : '' )
			. '<span class="gw-repo-meta">'
			. ( $r['language'] ? '<span><span class="gw-dot" style="background:' . esc_attr( gw_lang_colour( $r['language'], $i ) ) . '"></span>' . esc_html( $r['language'] ) . '</span>' : '' )
			. '<span title="' . esc_attr__( 'Stars', 'git-widget' ) . '">&#9733; ' . esc_html( gw_num( $r['stars'] ) ) . '</span>'
			. '<span title="' . esc_attr__( 'Forks', 'git-widget' ) . '">&#8916; ' . esc_html( gw_num( $r['forks'] ) ) . '</span>'
			. '<span>' . esc_html( sprintf( __( 'updated %s', 'git-widget' ), date_i18n( 'M Y', strtotime( $r['pushed'] ) ) ) ) . '</span>'
			. '</span></a>';
	}
	return $out . '</div>';
}

/**
 * Contributions by day of the week (Monday first), from the calendar: shows when you tend to work.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (year).
 * @return string HTML.
 */
function gw_section_weekdays( $user, $a ) {
	$year = (int) $a['year'] ? (int) $a['year'] : null;
	$cal  = gw_calendar( $user, $year );
	if ( is_wp_error( $cal ) ) {
		return gw_error( $cal );
	}
	$sum = array_fill( 1, 7, 0 );
	foreach ( $cal['days'] as $date => $n ) {
		$sum[ (int) gmdate( 'N', strtotime( $date ) ) ] += $n;
	}
	$labels = array();
	for ( $d = 1; $d <= 7; $d++ ) {
		$labels[] = date_i18n( 'D', strtotime( 'monday this week +' . ( $d - 1 ) . ' days' ) );
	}
	$title = __( 'Activity by day of the week', 'git-widget' );
	$best  = array_search( max( $sum ), $sum, true );
	return '<p class="gw-label">' . esc_html( $title ) . '</p>' . gw_bar_chart( $labels, array_values( $sum ), $title )
		. ( max( $sum ) ? '<p class="gw-small">' . esc_html( sprintf( __( 'Most active on %s.', 'git-widget' ), date_i18n( 'l', strtotime( 'monday this week +' . ( $best - 1 ) . ' days' ) ) ) ) . '</p>' : '' );
}

/**
 * Running total of contributions across the calendar period, as an area chart.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (year).
 * @return string HTML.
 */
function gw_section_cumulative( $user, $a ) {
	$year = (int) $a['year'] ? (int) $a['year'] : null;
	$cal  = gw_calendar( $user, $year );
	if ( is_wp_error( $cal ) ) {
		return gw_error( $cal );
	}
	$days = $cal['days'];
	if ( ! $days ) {
		return '';
	}
	$w     = 600;
	$h     = 160;
	$n     = count( $days );
	$max   = max( 1, array_sum( $days ) );
	$run   = 0;
	$pts   = array();
	$i     = 0;
	$ticks = '';
	foreach ( $days as $date => $c ) {
		$run  += $c;
		$x     = round( $w * $i / max( 1, $n - 1 ), 1 );
		$pts[] = $x . ',' . round( $h - 20 - ( $h - 34 ) * $run / $max, 1 );
		if ( '01' === substr( $date, 8, 2 ) ) {
			$ticks .= '<line class="gw-baseline" x1="' . $x . '" x2="' . $x . '" y1="' . ( $h - 20 ) . '" y2="' . ( $h - 16 ) . '"/><text class="gw-lab" x="' . $x . '" y="' . ( $h - 4 ) . '">' . esc_html( date_i18n( 'M', strtotime( $date ) ) ) . '</text>';
		}
		$i++;
	}
	$title = $year ? sprintf( __( 'Contributions through %d', 'git-widget' ), $year ) : __( 'Contributions over the last year', 'git-widget' );
	$line  = implode( ' ', $pts );
	return '<p class="gw-label">' . esc_html( $title ) . '</p>'
		. '<svg class="gw-chart gw-area" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr( $title . ': ' . number_format_i18n( $max ) ) . '">'
		. '<polygon points="0,' . ( $h - 20 ) . ' ' . $line . ' ' . $w . ',' . ( $h - 20 ) . '"/><polyline points="' . $line . '"/>'
		. '<line class="gw-baseline" x1="0" x2="' . $w . '" y1="' . ( $h - 20 ) . '" y2="' . ( $h - 20 ) . '"/>' . $ticks
		. '<text class="gw-val" x="' . ( $w - 4 ) . '" y="10" style="text-anchor:end">' . esc_html( number_format_i18n( $max ) ) . '</text></svg>';
}

/**
 * Load the stylesheet once per page.
 *
 * @return void
 */
function gw_enqueue() {
	wp_enqueue_style( 'git-widget', GW_URL . 'assets/git-widget.css', array(), GW_VERSION );
}
