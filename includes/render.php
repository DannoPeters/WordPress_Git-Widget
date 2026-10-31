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
		'metrics'       => 'awesome',
		'awesome_count' => 6,
		'calendar_period' => '',
		'calendar_picker' => 'off',
		'picker_position' => 'right',
		'cal_small'       => 'scroll',
		'picker_small'    => 'wrap',
		// Overview tiles per screen size (d = desktop, t = tablet, m = mobile).
		'tiles_cols_d'    => 'auto',
		'tiles_cols_t'    => 'auto',
		'tiles_cols_m'    => '2',
		'tiles_size_d'    => 'normal',
		'tiles_size_t'    => 'normal',
		'tiles_size_m'    => 'small',
		'tiles_text_d'    => 'full',
		'tiles_text_t'    => 'full',
		'tiles_text_m'    => 'short',
		'all_layout'      => 'joined',
		'calendar_chart'  => 'off',
		'chart_style'     => 'line',
		'chart_fill'      => 'yes',
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
 * Contribution calendar. "calendar_period" picks the last 12 months, the chosen year, or all time; all time
 * stacks one full-year calendar per year, newest first, all shaded on the same scale so years compare fairly.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (calendar_period: last|year|all, year).
 * @return string HTML.
 */
function gw_section_calendar( $user, $a ) {
	$period = gw_period( array( 'period' => $a['calendar_period'], 'year' => $a['year'] ) );
	$start  = 'all' === $period['kind'] ? 'all' : ( 'year' === $period['kind'] ? 'y' . $period['year'] : 'last' );
	if ( ! in_array( $a['calendar_picker'], array( 'dropdown', 'list' ), true ) ) {
		$html = gw_calendar_panel( $user, $start, $a );
		return '' === $html ? gw_error( __( 'No contribution data found.', 'git-widget' ) ) : $html . gw_calendar_legend();
	}
	return gw_calendar_picker( $user, $start, $a );
}

/**
 * One calendar view: "last" (12 months), "yYYYY" (a year) or "all" (every year), with the optional trend chart
 * above it.
 *
 * @param string $user GitHub username.
 * @param string $view View key.
 * @param array  $a    Widget options (all_layout, calendar_chart, chart_style, chart_fill).
 * @return string HTML without the legend, or "" when there is no data.
 */
function gw_calendar_panel( $user, $view, $a ) {
	if ( 'all' === $view ) {
		return gw_calendar_all_years( $user, $a );
	}
	$year = 'last' === $view ? null : (int) substr( $view, 1 );
	$cal  = gw_calendar( $user, $year );
	if ( is_wp_error( $cal ) || ! $cal['days'] ) {
		return '';
	}
	$title = $year
		? sprintf( _n( '%1$s contribution in %2$d', '%1$s contributions in %2$d', $cal['total'], 'git-widget' ), number_format_i18n( $cal['total'] ), $year )
		: sprintf( _n( '%s contribution in the last year', '%s contributions in the last year', $cal['total'], 'git-widget' ), number_format_i18n( $cal['total'] ) );
	return '<p class="gw-label">' . esc_html( $title ) . '</p>'
		. gw_trend_chart( gw_past_days( $cal['days'] ), $a )
		. gw_calendar_adaptive( $cal['days'], $title, max( 1, max( $cal['days'] ) ), $year, $a );
}

/**
 * A calendar plus, when chosen, a two-row version that takes over on narrow screens.
 *
 * @param array    $days  Date => count.
 * @param string   $title Accessible name.
 * @param int      $max   Count that gets the darkest shade.
 * @param int|null $year  Whole-year grid when set.
 * @param array    $a     Widget options (cal_small: scroll|fused|between).
 * @return string HTML.
 */
function gw_calendar_adaptive( $days, $title, $max, $year, $a ) {
	$wide = '<div class="gw-cal-scroll">' . gw_calendar_svg( $days, $title, $max, $year ) . '</div>';
	$mode = $a['cal_small'] ?? 'scroll';
	if ( ! in_array( $mode, array( 'fused', 'between' ), true ) ) {
		return $wide;
	}
	return '<div class="gw-cal-adapt"><div class="gw-cal-wide">' . $wide . '</div>'
		. '<div class="gw-cal-narrow">' . gw_calendar_svg( $days, $title, $max, $year, $mode ) . '</div></div>';
}

/**
 * Drop days after today (GitHub returns the whole current year).
 *
 * @param array $days Date => count.
 * @return array
 */
function gw_past_days( $days ) {
	$today = gmdate( 'Y-m-d' );
	return array_filter(
		$days,
		function ( $d ) use ( $today ) {
			return $d <= $today;
		},
		ARRAY_FILTER_USE_KEY
	);
}

/**
 * Optional chart above the calendar: contributions per year, month or day, as a line (optionally filled
 * underneath) or as bars.
 *
 * @param array $days Date => count, in date order.
 * @param array $a    Widget options (calendar_chart: off|year|month|day, chart_style: line|bar, chart_fill: yes|no).
 * @return string SVG, or "" when switched off.
 */
function gw_trend_chart( $days, $a ) {
	$unit = $a['calendar_chart'];
	if ( ! in_array( $unit, array( 'year', 'month', 'day' ), true ) || ! $days ) {
		return '';
	}
	$len = array( 'year' => 4, 'month' => 7, 'day' => 10 )[ $unit ];
	$sum = array();
	foreach ( $days as $date => $n ) {
		$k         = substr( $date, 0, $len );
		$sum[ $k ] = ( $sum[ $k ] ?? 0 ) + $n;
	}
	if ( 'year' === $unit && count( $sum ) < 2 ) {
		// One year on its own says little; show its months instead.
		return gw_trend_chart( $days, array( 'calendar_chart' => 'month' ) + $a );
	}
	$keys   = array_keys( $sum );
	$vals   = array_values( $sum );
	$n      = count( $vals );
	$max    = max( 1, max( $vals ) );
	// A wide canvas keeps the text small when the chart is stretched across a full-width page.
	$w      = 1000;
	$h      = 170;
	$bottom = 20;
	$top    = 16;
	$plot   = $h - $bottom - $top;
	$fmt    = function ( $k ) use ( $unit ) {
		return 'year' === $unit ? $k : date_i18n( 'month' === $unit ? 'M Y' : 'M j, Y', strtotime( 'month' === $unit ? $k . '-01' : $k ) );
	};
	$names  = array( 'year' => __( 'Contributions per year', 'git-widget' ), 'month' => __( 'Contributions per month', 'git-widget' ), 'day' => __( 'Contributions per day', 'git-widget' ) );
	$svg    = '<line class="gw-baseline" x1="0" x2="' . $w . '" y1="' . ( $h - $bottom ) . '" y2="' . ( $h - $bottom ) . '"/>';
	$step   = $w / max( 1, 'bar' === $a['chart_style'] ? $n : $n - 1 );
	if ( 'bar' === $a['chart_style'] ) {
		$bw = max( 1, $step * ( $n > 60 ? 0.8 : 0.62 ) );
		foreach ( $vals as $i => $v ) {
			$bh   = $plot * $v / $max;
			$svg .= '<rect class="gw-trend-bar" x="' . round( $i * $step + ( $step - $bw ) / 2, 2 ) . '" y="' . round( $h - $bottom - $bh, 2 ) . '" width="' . round( $bw, 2 ) . '" height="' . round( $bh, 2 ) . '"><title>' . esc_html( $fmt( $keys[ $i ] ) . ': ' . number_format_i18n( $v ) ) . '</title></rect>';
		}
	} else {
		$pts = array();
		foreach ( $vals as $i => $v ) {
			$pts[] = round( $n > 1 ? $i * $step : $w / 2, 2 ) . ',' . round( $h - $bottom - $plot * $v / $max, 2 );
		}
		if ( 'no' !== $a['chart_fill'] ) {
			$svg .= '<polygon class="gw-trend-fill" points="0,' . ( $h - $bottom ) . ' ' . implode( ' ', $pts ) . ' ' . $w . ',' . ( $h - $bottom ) . '"/>';
		}
		$svg .= '<polyline class="gw-trend-line" points="' . implode( ' ', $pts ) . '"/>';
		if ( $n <= 60 ) {
			foreach ( $pts as $i => $p ) {
				list( $x, $y ) = explode( ',', $p );
				$svg          .= '<circle class="gw-trend-dot" cx="' . $x . '" cy="' . $y . '" r="3"><title>' . esc_html( $fmt( $keys[ $i ] ) . ': ' . number_format_i18n( $vals[ $i ] ) ) . '</title></circle>';
			}
		}
	}
	// Up to about 12 labels along the bottom, wherever a new year/month starts.
	$every = max( 1, (int) ceil( $n / 12 ) );
	foreach ( $keys as $i => $k ) {
		$new = 'day' === $unit ? '01' === substr( $k, 8, 2 ) && ( (int) substr( $k, 5, 2 ) - 1 ) % max( 1, (int) ceil( $n / 365 ) ) === 0 : 0 === $i % $every;
		if ( ! $new ) {
			continue;
		}
		$x     = 'bar' === $a['chart_style'] ? $i * $step + $step / 2 : ( $n > 1 ? $i * $step : $w / 2 );
		$label = 'year' === $unit ? $k : date_i18n( ( 'month' === $unit && $n > 13 ) || ( 'day' === $unit && $n > 400 ) ? 'M y' : 'M', strtotime( 'month' === $unit ? $k . '-01' : $k ) );
		$svg  .= '<text class="gw-lab" x="' . round( $x, 1 ) . '" y="' . ( $h - 5 ) . '">' . esc_html( $label ) . '</text>';
	}
	$svg .= '<text class="gw-val" x="' . ( $w - 2 ) . '" y="10" style="text-anchor:end">' . esc_html( sprintf( __( 'peak %s', 'git-widget' ), number_format_i18n( $max ) ) ) . '</text>';
	return '<svg class="gw-chart gw-trend" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr( $names[ $unit ] ) . '">' . $svg . '</svg>';
}

/**
 * Calendar with a year picker visitors can use: every view is rendered up front and a small script shows the
 * chosen one, so switching is instant and needs no extra requests.
 *
 * @param string $user  GitHub username.
 * @param string $start View shown first.
 * @param array  $a     Widget options (calendar_picker: dropdown|list, picker_position: left|right|bottom, ...).
 * @return string HTML.
 */
function gw_calendar_picker( $user, $start, $a ) {
	$style    = $a['calendar_picker'];
	$position = $a['picker_position'];
	$views = array( 'last' => __( 'Last 12 months', 'git-widget' ) );
	foreach ( gw_contribution_years( $user ) as $y ) {
		$views[ 'y' . (int) $y ] = (string) (int) $y;
	}
	$views['all'] = __( 'All years', 'git-widget' );
	$uid    = 'gw-cal-' . wp_unique_id();
	$panels = '';
	$nav    = '';
	foreach ( $views as $key => $label ) {
		$html = gw_calendar_panel( $user, $key, $a );
		if ( '' === $html ) {
			unset( $views[ $key ] );
			continue;
		}
		$on      = $key === $start;
		$panels .= '<div class="gw-cal-panel" id="' . esc_attr( "$uid-$key" ) . '" role="tabpanel" data-view="' . esc_attr( $key ) . '"' . ( $on ? '' : ' hidden' ) . '>' . $html . '</div>';
		if ( 'list' === $style ) {
			$nav .= '<button type="button" role="tab" aria-selected="' . ( $on ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( "$uid-$key" ) . '" data-view="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
		} else {
			$nav .= '<option value="' . esc_attr( $key ) . '"' . ( $on ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
	}
	if ( ! $views ) {
		return gw_error( __( 'No contribution data found.', 'git-widget' ) );
	}
	if ( ! isset( $views[ $start ] ) ) {
		// The chosen first view had no data: show the first one that does.
		$panels = preg_replace( '/ hidden>/', '>', $panels, 1 );
	}
	gw_enqueue_script();
	$position = in_array( $position, array( 'left', 'right', 'bottom' ), true ) ? $position : 'right';
	if ( 'list' === $style ) {
		$small  = in_array( $a['picker_small'], array( 'wrap', 'scroll', 'dropdown' ), true ) ? $a['picker_small'] : 'wrap';
		$picker = '<div class="gw-cal-years-nav" role="tablist" aria-label="' . esc_attr__( 'Choose a year', 'git-widget' ) . '">' . $nav . '</div>';
		if ( 'dropdown' === $small ) {
			// A dropdown twin that takes over on narrow screens; the script keeps both in step.
			$options = '';
			foreach ( $views as $key => $label ) {
				$options .= '<option value="' . esc_attr( $key ) . '"' . ( $key === $start ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
			}
			$picker .= '<label class="gw-cal-select gw-cal-select-small"><span class="screen-reader-text">' . esc_html__( 'Choose a year', 'git-widget' ) . '</span><select>' . $options . '</select></label>';
		}
		$class = 'gw-cal-picker gw-pick-list gw-pick-' . $position . ' gw-small-' . $small;
	} else {
		$picker = '<label class="gw-cal-select"><span class="screen-reader-text">' . esc_html__( 'Choose a year', 'git-widget' ) . '</span><select>' . $nav . '</select></label>';
		$class  = 'gw-cal-picker gw-pick-dropdown';
	}
	return '<div class="' . esc_attr( $class ) . '" data-gw-picker>' . $picker . '<div class="gw-cal-panels">' . $panels . '</div></div>' . gw_calendar_legend();
}

/**
 * All-time view: one calendar per contribution year, newest first, with each year's total.
 *
 * @param string $user GitHub username.
 * @return string HTML.
 */
function gw_calendar_all_years( $user, $a = array() ) {
	$a     = wp_parse_args( $a, gw_defaults() );
	$years = array();
	foreach ( gw_contribution_years( $user ) as $y ) {
		$cal = gw_calendar( $user, (int) $y );
		if ( ! is_wp_error( $cal ) && $cal['days'] ) {
			$years[ (int) $y ] = gw_past_days( $cal['days'] );
		}
	}
	if ( ! $years ) {
		return '';
	}
	krsort( $years );
	$every = array();
	foreach ( $years as $days ) {
		$every += $days;
	}
	ksort( $every );
	$total = array_sum( $every );
	// One shading scale for every year: the busiest days overall, ignoring rare extreme spikes.
	$busy = array_values( array_filter( $every ) );
	sort( $busy );
	$max   = $busy ? max( 1, $busy[ (int) floor( 0.98 * ( count( $busy ) - 1 ) ) ] ) : 1;
	$title = sprintf( _n( '%1$s contribution since %2$d', '%1$s contributions since %2$d', $total, 'git-widget' ), number_format_i18n( $total ), min( array_keys( $years ) ) );
	$out   = '<p class="gw-label">' . esc_html( $title ) . '</p>' . gw_trend_chart( $every, $a );
	if ( 'separate' === $a['all_layout'] ) {
		$out .= '<div class="gw-cal-years">';
		foreach ( $years as $y => $days ) {
			$label = sprintf( _n( '%s contribution', '%s contributions', array_sum( $days ), 'git-widget' ), number_format_i18n( array_sum( $days ) ) );
			$out  .= '<div class="gw-cal-year"><p class="gw-cal-year-head"><strong>' . (int) $y . '</strong><span>' . esc_html( $label ) . '</span></p>'
				. gw_calendar_adaptive( $days, $y . ': ' . $label, $max, (int) $y, $a ) . '</div>';
		}
		return $out . '</div>';
	}
	return $out . '<div class="gw-cal-scroll">' . gw_calendar_joined_svg( $years, $max, $title ) . '</div>';
}

/**
 * Every year joined into one block: a band of seven day-rows per year (newest on top), weekdays on the left,
 * months once along the top and each year's label and total on the right.
 *
 * @param array  $years Year => (date => count), newest first.
 * @param int    $max   Count that gets the darkest shade.
 * @param string $title Accessible name.
 * @return string SVG markup.
 */
function gw_calendar_joined_svg( $years, $max, $title ) {
	$cell  = 11;
	$gap   = 3;
	$band  = 7 * ( $cell + $gap );
	$space = 5; // A hairline between years so the bands still read as separate.
	$left  = 28;
	$top   = 16;
	$right = 92;
	$cols  = 54;
	$svg   = '';
	// Month labels from a reference year: columns shift by at most one between years.
	$ref   = (int) array_key_first( $years );
	$jan1  = strtotime( "$ref-01-01" );
	$start = $jan1 - (int) gmdate( 'w', $jan1 ) * DAY_IN_SECONDS;
	for ( $m = 1; $m <= 12; $m++ ) {
		$t    = strtotime( sprintf( '%d-%02d-01', $ref, $m ) );
		$col  = (int) floor( ( $t - $start ) / WEEK_IN_SECONDS );
		$svg .= '<text class="gw-axis-t" x="' . ( $left + $col * ( $cell + $gap ) ) . '" y="10">' . esc_html( date_i18n( 'M', $t ) ) . '</text>';
	}
	$row = 0;
	foreach ( $years as $y => $days ) {
		$y0    = $top + $row * ( $band + $space );
		$jan1  = strtotime( "$y-01-01" );
		$start = $jan1 - (int) gmdate( 'w', $jan1 ) * DAY_IN_SECONDS;
		foreach ( $days as $date => $n ) {
			if ( 0 !== strpos( $date, (string) $y ) ) {
				continue; // Only this year's days belong in its band.
			}
			$t      = strtotime( $date );
			$col    = (int) floor( ( $t - $start ) / WEEK_IN_SECONDS );
			$dow    = (int) gmdate( 'w', $t );
			$level  = 0 === $n ? 0 : (int) min( 4, ceil( 4 * $n / $max ) );
			$label  = sprintf( _n( '%1$s contribution on %2$s', '%1$s contributions on %2$s', $n, 'git-widget' ), number_format_i18n( $n ), date_i18n( 'M j, Y', $t ) );
			$svg   .= '<rect class="gw-l' . $level . '" x="' . ( $left + $col * ( $cell + $gap ) ) . '" y="' . ( $y0 + $dow * ( $cell + $gap ) ) . '" width="' . $cell . '" height="' . $cell . '" rx="2"><title>' . esc_html( $label ) . '</title></rect>';
		}
		foreach ( array( 1 => __( 'Mon', 'git-widget' ), 3 => __( 'Wed', 'git-widget' ), 5 => __( 'Fri', 'git-widget' ) ) as $d => $l ) {
			$svg .= '<text class="gw-axis-t" x="0" y="' . ( $y0 + $d * ( $cell + $gap ) + 9 ) . '">' . esc_html( $l ) . '</text>';
		}
		$mid  = $y0 + $band / 2;
		$svg .= '<text class="gw-year-t" x="' . ( $left + $cols * ( $cell + $gap ) + 8 ) . '" y="' . ( $mid - 2 ) . '">' . (int) $y . '</text>'
			. '<text class="gw-axis-t" x="' . ( $left + $cols * ( $cell + $gap ) + 8 ) . '" y="' . ( $mid + 12 ) . '">' . esc_html( sprintf( _n( '%s contribution', '%s contributions', array_sum( $days ), 'git-widget' ), number_format_i18n( array_sum( $days ) ) ) ) . '</text>';
		$row++;
	}
	$width  = $left + $cols * ( $cell + $gap ) + $right;
	$height = $top + $row * ( $band + $space );
	return '<svg class="gw-cal gw-cal-joined wp-dark-mode-ignore" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr( $title ) . '"><g class="gw-axis">' . $svg . '</g></svg>';
}

/**
 * Draw one calendar grid: weeks as columns, days as rows (Sunday first), month labels on top.
 *
 * With $split the weeks are cut into two rows of about six months each, for narrow screens:
 * "fused" puts the rows right together with the first half's months on top and the second half's underneath;
 * "between" gives each row its months on top, so the second row's labels sit between the two.
 *
 * @param array       $days  Date => count, in date order.
 * @param string      $title Accessible name.
 * @param int         $max   Count that gets the darkest shade.
 * @param int|null    $year  When set, the grid spans the whole year (Jan 1 to Dec 31) even past today.
 * @param string|null $split null, "fused" or "between".
 * @return string SVG markup.
 */
function gw_calendar_svg( $days, $title, $max, $year = null, $split = null ) {
	$dates = array_keys( $days );
	$first = $year ? strtotime( "$year-01-01" ) : strtotime( $dates[0] );
	$last  = $year ? strtotime( "$year-12-31" ) : strtotime( end( $dates ) );
	$start = $first - (int) gmdate( 'w', $first ) * DAY_IN_SECONDS; // Back up to Sunday.
	$cell  = 11;
	$gap   = 3;
	$step  = $cell + $gap;
	$left  = 28;
	$band  = 7 * $step;
	$cols  = (int) floor( ( $last - $start ) / WEEK_IN_SECONDS ) + 1;
	$half  = $split ? (int) ceil( $cols / 2 ) : $cols;
	// Where each row of weeks starts, and where its month labels go.
	if ( 'fused' === $split ) {
		$tops   = array( 16, 16 + $band + 6 );
		$labels = array( 10, 16 + 2 * $band + 6 + 9 );
		$height = 16 + 2 * $band + 6 + 14;
	} elseif ( 'between' === $split ) {
		$tops   = array( 16, 16 + $band + 18 );
		$labels = array( 10, 16 + $band + 12 );
		$height = 16 + 2 * $band + 18;
	} else {
		$tops   = array( 16 );
		$labels = array( 10 );
		$height = 16 + $band;
	}
	$rects = '';
	$month = '';
	for ( $t = $start; $t <= $last; $t += DAY_IN_SECONDS ) {
		$dow  = (int) gmdate( 'w', $t );
		$col  = (int) floor( ( $t - $start ) / WEEK_IN_SECONDS );
		$row  = $col >= $half ? 1 : 0;
		$x    = $left + ( $col - $row * $half ) * $step;
		$date = gmdate( 'Y-m-d', $t );
		if ( 0 === $dow && (int) gmdate( 'j', $t ) <= 7 && $t >= $first ) {
			$month .= '<text x="' . $x . '" y="' . $labels[ $row ] . '">' . esc_html( date_i18n( 'M', $t ) ) . '</text>';
		}
		if ( ! isset( $days[ $date ] ) ) {
			continue;
		}
		$n      = $days[ $date ];
		$level  = 0 === $n ? 0 : (int) min( 4, ceil( 4 * $n / $max ) );
		$label  = sprintf( _n( '%1$s contribution on %2$s', '%1$s contributions on %2$s', $n, 'git-widget' ), number_format_i18n( $n ), date_i18n( 'M j, Y', $t ) );
		$rects .= '<rect class="gw-l' . $level . '" x="' . $x . '" y="' . ( $tops[ $row ] + $dow * $step ) . '" width="' . $cell . '" height="' . $cell . '" rx="2"><title>' . esc_html( $label ) . '</title></rect>';
	}
	$width  = $left + $half * $step;
	$wdays = '';
	foreach ( $tops as $top ) {
		foreach ( array( 1 => __( 'Mon', 'git-widget' ), 3 => __( 'Wed', 'git-widget' ), 5 => __( 'Fri', 'git-widget' ) ) as $d => $l ) {
			$wdays .= '<text x="0" y="' . ( $top + $d * $step + 9 ) . '">' . esc_html( $l ) . '</text>';
		}
	}
	return '<svg class="gw-cal' . ( $split ? ' gw-cal-split' : '' ) . ' wp-dark-mode-ignore" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . esc_attr( $title ) . '">'
		. '<g class="gw-axis">' . $month . $wdays . '</g>' . $rects . '</svg>';
}

/**
 * The Less ... More legend under a calendar.
 *
 * @return string HTML.
 */
function gw_calendar_legend() {
	$legend = '';
	for ( $i = 0; $i <= 4; $i++ ) {
		$legend .= '<span class="gw-l' . $i . '"></span>';
	}
	return '<p class="gw-legend wp-dark-mode-ignore">' . esc_html__( 'Less', 'git-widget' ) . ' ' . $legend . ' ' . esc_html__( 'More', 'git-widget' ) . '</p>';
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
 * Short names for the overview tiles, for small screens.
 *
 * @return array Metric key => short label.
 */
function gw_overview_short_labels() {
	return array(
		'contributions'  => __( 'Contribs', 'git-widget' ),
		'active_days'    => __( 'Active days', 'git-widget' ),
		'current_streak' => __( 'Streak', 'git-widget' ),
		'longest_streak' => __( 'Best streak', 'git-widget' ),
		'daily_average'  => __( 'Avg / day', 'git-widget' ),
		'busiest_day'    => __( 'Best day', 'git-widget' ),
		'commits'        => __( 'Commits', 'git-widget' ),
		'pull_requests'  => __( 'PRs', 'git-widget' ),
		'merged_prs'     => __( 'Merged', 'git-widget' ),
		'issues_opened'  => __( 'Issues', 'git-widget' ),
		'reviews'        => __( 'Reviews', 'git-widget' ),
		'stars'          => __( 'Stars', 'git-widget' ),
		'forks'          => __( 'Forks', 'git-widget' ),
		'repositories'   => __( 'Repos', 'git-widget' ),
		'languages'      => __( 'Languages', 'git-widget' ),
		'followers'      => __( 'Followers', 'git-widget' ),
		'following'      => __( 'Following', 'git-widget' ),
		'organisations'  => __( 'Orgs', 'git-widget' ),
		'member_since'   => __( 'Years', 'git-widget' ),
	);
}

/**
 * Classes that set the overview tiles' columns, text size and labels on desktop, tablet and mobile.
 *
 * @param array $a Widget options (tiles_cols_*, tiles_size_*, tiles_text_*).
 * @return string Space-separated classes.
 */
function gw_tile_classes( $a ) {
	$allowed = array(
		'cols' => array( 'auto', '1', '2', '3', '4', '6' ),
		'size' => array( 'normal', 'small', 'smaller' ),
		'text' => array( 'full', 'short', 'none' ),
	);
	$classes = array();
	foreach ( array( 'd', 't', 'm' ) as $dev ) {
		foreach ( $allowed as $what => $ok ) {
			$v = (string) ( $a[ "tiles_{$what}_{$dev}" ] ?? '' );
			if ( in_array( $v, $ok, true ) ) {
				$classes[] = "gw-$dev-$what-$v";
			}
		}
	}
	return implode( ' ', $classes );
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
 * Work out one overview metric. Data is loaded lazily and shared through $ctx, so each source is fetched
 * at most once per widget.
 *
 * @param string $m      Metric key from gw_overview_metrics().
 * @param string $user   GitHub username.
 * @param array  $period From gw_period().
 * @param array  $ctx    Shared cache for this widget (by reference).
 * @return array|null [value (int|float), sub-line] or null when unavailable.
 */
function gw_overview_value( $m, $user, $period, &$ctx ) {
	$cal = function () use ( $user, $period, &$ctx ) {
		if ( ! array_key_exists( 'st', $ctx ) ) {
			$c         = 'all' === $period['kind'] ? gw_calendar_all( $user ) : gw_calendar( $user, $period['year'] );
			$ctx['st'] = is_wp_error( $c ) ? null : gw_calendar_stats( $c['days'] );
		}
		return $ctx['st'];
	};
	$repos = function () use ( $user, &$ctx ) {
		if ( ! array_key_exists( 'repos', $ctx ) ) {
			$r            = gw_repos( $user );
			$ctx['repos'] = is_wp_error( $r ) ? null : $r;
		}
		return $ctx['repos'];
	};
	$profile = function () use ( $user, &$ctx ) {
		if ( ! array_key_exists( 'profile', $ctx ) ) {
			$p              = gw_profile( $user );
			$ctx['profile'] = is_wp_error( $p ) ? null : $p;
		}
		return $ctx['profile'];
	};
	switch ( $m ) {
		case 'contributions':
		case 'active_days':
		case 'current_streak':
		case 'longest_streak':
		case 'daily_average':
		case 'busiest_day':
			$st = $cal();
			if ( ! $st ) {
				return null;
			}
			$map = array(
				'contributions'  => array( $st['total'], '' ),
				'active_days'    => array( $st['active'], '' ),
				'current_streak' => array( $st['current'], _n( 'day', 'days', $st['current'], 'git-widget' ) ),
				'longest_streak' => array( $st['longest'], _n( 'day', 'days', $st['longest'], 'git-widget' ) ),
				'daily_average'  => array( $st['active'] ? round( $st['total'] / $st['active'], 1 ) : 0, '' ),
				'busiest_day'    => array( $st['best_count'], $st['best_day'] ? date_i18n( 'M j, Y', strtotime( $st['best_day'] ) ) : '' ),
			);
			return $map[ $m ];
		case 'commits':
		case 'pull_requests':
		case 'merged_prs':
		case 'issues_opened':
		case 'reviews':
			$n = gw_search_count( $user, $m, $period['range'] );
			return is_wp_error( $n ) ? null : array( $n, '' );
		case 'stars':
		case 'forks':
			$r = $repos();
			return null === $r ? null : array( array_sum( array_column( $r, $m ) ), '' );
		case 'languages':
			$r = $repos();
			return null === $r ? null : array( count( array_unique( array_filter( array_column( $r, 'language' ) ) ) ), '' );
		case 'repositories':
		case 'followers':
		case 'following':
			$p = $profile();
			return null === $p ? null : array( $p[ 'repositories' === $m ? 'repos' : $m ], '' );
		case 'organisations':
			$p = $profile();
			return null === $p ? null : array( count( $p['orgs'] ), '' );
		case 'member_since':
			$p = $profile();
			return null === $p ? null : array( max( 0, (int) floor( ( time() - strtotime( $p['created'] ) ) / YEAR_IN_SECONDS ) ), sprintf( __( 'since %s', 'git-widget' ), date_i18n( 'Y', strtotime( $p['created'] ) ) ) );
	}
	return null;
}

/**
 * What counts as a strong number for each metric, used by Awesome Mode. A value equal to its benchmark scores
 * 1; the highest scores win. Benchmarks are for the last 12 months; all-time figures are scaled up.
 *
 * @return array Metric => benchmark.
 */
function gw_awesome_benchmarks() {
	return array(
		'contributions'  => 1000,
		'active_days'    => 200,
		'current_streak' => 14,
		'longest_streak' => 30,
		'daily_average'  => 6,
		'busiest_day'    => 30,
		'commits'        => 600,
		'pull_requests'  => 60,
		'merged_prs'     => 50,
		'issues_opened'  => 40,
		'reviews'        => 40,
		'stars'          => 100,
		'forks'          => 30,
		'repositories'   => 30,
		'languages'      => 6,
		'followers'      => 100,
		'following'      => 1000000, // Never chosen automatically.
		'organisations'  => 3,
		'member_since'   => 6,
	);
}

/**
 * Awesome Mode: score every metric against its benchmark and keep the best ones, always leading with
 * contributions when there are any.
 *
 * @param string $user   GitHub username.
 * @param array  $period From gw_period().
 * @param array  $ctx    Shared cache (by reference).
 * @param int    $how    How many tiles to show.
 * @return array Metric => [value, sub-line], in catalogue order.
 */
function gw_awesome_metrics( $user, $period, &$ctx, $how = 6 ) {
	$bench  = gw_awesome_benchmarks();
	$scale  = 'all' === $period['kind'] ? 5 : 1;
	$timed  = array( 'contributions', 'active_days', 'commits', 'pull_requests', 'merged_prs', 'issues_opened', 'reviews' );
	$values = array();
	$scores = array();
	foreach ( array_keys( gw_overview_metrics() ) as $m ) {
		$v = gw_overview_value( $m, $user, $period, $ctx );
		if ( null === $v || ! $v[0] ) {
			continue;
		}
		$values[ $m ] = $v;
		$scores[ $m ] = $v[0] / ( $bench[ $m ] * ( in_array( $m, $timed, true ) ? $scale : 1 ) );
	}
	arsort( $scores );
	$pick = array_slice( array_keys( $scores ), 0, $how );
	if ( isset( $values['contributions'] ) && ! in_array( 'contributions', $pick, true ) ) {
		array_pop( $pick );
		array_unshift( $pick, 'contributions' );
	}
	// Show them in the usual order rather than by score, so related tiles sit together.
	return array_intersect_key( $values, array_flip( $pick ) );
}

/**
 * Activity overview: a row of tiles. "Awesome Mode" (the default) picks your most impressive figures
 * automatically; otherwise the metrics listed in "metrics" are shown. The time frame ("period") can be the
 * last 12 months, a year, or all time. Repository and profile figures are always current.
 *
 * @param string $user GitHub username.
 * @param array  $a    Widget options (metrics: "awesome" or a list, period, year, awesome_count).
 * @return string HTML.
 */
function gw_section_overview( $user, $a ) {
	$period = gw_period( $a );
	$all    = gw_overview_metrics();
	$ctx    = array();
	$want   = array_values( array_intersect( array_map( 'trim', explode( ',', $a['metrics'] ) ), array_keys( $all ) ) );
	$tiles  = array();
	if ( ! $want ) {
		$tiles = gw_awesome_metrics( $user, $period, $ctx, max( 2, min( 12, (int) $a['awesome_count'] ) ) );
	} else {
		foreach ( $want as $m ) {
			$v = gw_overview_value( $m, $user, $period, $ctx );
			if ( null !== $v ) {
				$tiles[ $m ] = $v;
			}
		}
	}
	if ( ! $tiles ) {
		return gw_error( __( 'None of the chosen metrics could be loaded.', 'git-widget' ) );
	}
	$out = '<p class="gw-label">' . esc_html( sprintf( __( 'Activity overview, %s', 'git-widget' ), $period['label'] ) ) . '</p><div class="gw-tiles ' . esc_attr( gw_tile_classes( $a ) ) . '">';
	$short = gw_overview_short_labels();
	foreach ( $tiles as $m => $t ) {
		$shown = is_float( $t[0] ) ? number_format_i18n( $t[0], 1 ) : gw_num( (int) $t[0] );
		// Both names go in the page; CSS picks one per screen size. The full name stays as a tooltip.
		$out  .= '<div class="gw-tile" title="' . esc_attr( $all[ $m ] ) . '"><strong>' . esc_html( $shown ) . '</strong>'
			. '<span class="gw-full">' . esc_html( $all[ $m ] ) . '</span><span class="gw-short">' . esc_html( $short[ $m ] ?? $all[ $m ] ) . '</span>'
			. ( '' !== $t[1] ? '<em>' . esc_html( $t[1] ) . '</em>' : '' ) . '</div>';
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
 * Load the small script that powers the calendar year picker (once per page, in the footer).
 *
 * @return void
 */
function gw_enqueue_script() {
	wp_enqueue_script( 'git-widget', GW_URL . 'assets/git-widget.js', array(), GW_VERSION, true );
}

/**
 * Load the stylesheet once per page.
 *
 * @return void
 */
function gw_enqueue() {
	wp_enqueue_style( 'git-widget', GW_URL . 'assets/git-widget.css', array(), GW_VERSION );
}
