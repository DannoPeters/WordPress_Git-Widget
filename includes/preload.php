<?php
/**
 * Background preloader: keep every data selection a visitor could choose already in the cache, so pages never
 * wait on GitHub and switching the year is instant.
 *
 * Day 1 lays the groundwork: the full list of things to warm, the WP-Cron schedule, and an enable switch. The
 * batched runner and the rate-limit backoff come next.
 *
 * @package GitWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option names. */
const GW_PRELOAD_OPTION = 'gw_preload';       // Settings: enabled, batch.
const GW_PRELOAD_STATE  = 'gw_preload_state'; // Progress: queue, done, cursor, last_run, started.
const GW_PRELOAD_HOOK   = 'gw_preload_tick';  // Cron event.

/**
 * Preloader settings merged with defaults.
 *
 * @return array enabled (bool), batch (how many entries to warm per run).
 */
function gw_preload_settings() {
	return wp_parse_args(
		(array) get_option( GW_PRELOAD_OPTION, array() ),
		array(
			'enabled' => false,
			'batch'   => 3,
		)
	);
}

/**
 * The GitHub accounts to keep warm. The configured username to start; a filter lets Git Widget Pro add the
 * organisations and repositories it reports on.
 *
 * @return array List of usernames.
 */
function gw_preload_users() {
	$user  = gw_clean_user( gw_settings()['username'] );
	$users = $user ? array( $user ) : array();
	/**
	 * Filters the accounts the preloader warms.
	 *
	 * @param array $users Usernames.
	 */
	return array_values( array_unique( (array) apply_filters( 'gw_preload_users', $users ) ) );
}

/**
 * Every cache selection to warm for one user, as a flat, ordered list of job descriptors. Each descriptor is
 * handled by gw_preload_run_job(); cheaper, broadly-used fetches (profile, years, repos) come first because
 * the rest build on them.
 *
 * Descriptors:
 *   profile | years | repos | achievements
 *   calendar:last | calendar:all | calendar:<year>
 *   percent:all | percent:<year>
 *   search:<kind>:<range>   (range is "" for last 12 months, "all", or a YYYY-01-01..YYYY-12-31 range)
 *
 * @param string $user GitHub username.
 * @return array Job descriptors.
 */
function gw_preload_jobs( $user ) {
	$jobs  = array( 'profile', 'years', 'repos', 'achievements' );
	$years = gw_contribution_years( $user );
	$years = is_wp_error( $years ) ? array() : array_map( 'intval', $years );

	// Calendars: the last 12 months, every year, and the joined all-years view.
	$jobs[] = 'calendar:last';
	$jobs[] = 'calendar:all';
	foreach ( $years as $y ) {
		$jobs[] = "calendar:$y";
	}

	// Contribution-type percentages: all time and per year.
	$jobs[] = 'percent:all';
	foreach ( $years as $y ) {
		$jobs[] = "percent:$y";
	}

	// Overview figures that come from the search API: each kind across each time frame.
	$kinds  = array( 'commits', 'pull_requests', 'merged_prs', 'issues_opened', 'reviews' );
	$ranges = array( '', 'all' );
	foreach ( $years as $y ) {
		$ranges[] = "$y-01-01..$y-12-31";
	}
	foreach ( $kinds as $kind ) {
		foreach ( $ranges as $range ) {
			$jobs[] = "search:$kind:$range";
		}
	}
	return $jobs;
}

/**
 * Warm one entry by calling the function that computes it; gw_cached() stores the result, so there is no
 * second copy to keep in step. The return value is discarded on purpose: the point is the primed cache.
 *
 * @param string $user GitHub username.
 * @param string $job  Descriptor from gw_preload_jobs().
 * @return void
 */
function gw_preload_run_job( $user, $job ) {
	$parts = explode( ':', $job, 2 );
	$what  = $parts[0];
	$arg   = $parts[1] ?? '';
	switch ( $what ) {
		case 'profile':
			gw_profile( $user );
			break;
		case 'years':
			gw_contribution_years( $user );
			break;
		case 'repos':
			gw_repos( $user );
			break;
		case 'achievements':
			gw_achievements( $user );
			break;
		case 'calendar':
			if ( 'all' === $arg ) {
				gw_calendar_all( $user );
			} else {
				gw_calendar( $user, 'last' === $arg ? null : (int) $arg );
			}
			break;
		case 'percent':
			gw_activity_percentages( $user, 'all' === $arg ? null : (int) $arg );
			break;
		case 'search':
			$sp = explode( ':', $arg, 2 );
			if ( isset( $sp[1] ) ) {
				gw_search_count( $user, $sp[0], $sp[1] );
			}
			break;
	}
}

/**
 * Add a short recurring schedule for the preloader.
 *
 * @param array $schedules Existing cron schedules.
 * @return array
 */
function gw_preload_schedule( $schedules ) {
	$schedules['gw_fifteen_minutes'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 15 minutes (Git Widget preloader)', 'git-widget' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'gw_preload_schedule' );

/**
 * Keep the cron event scheduled while the preloader is on, and cleared while it is off.
 *
 * @return void
 */
function gw_preload_sync_cron() {
	$on        = gw_preload_settings()['enabled'];
	$scheduled = (bool) wp_next_scheduled( GW_PRELOAD_HOOK );
	if ( $on && ! $scheduled ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'gw_fifteen_minutes', GW_PRELOAD_HOOK );
	} elseif ( ! $on && $scheduled ) {
		wp_clear_scheduled_hook( GW_PRELOAD_HOOK );
	}
}
add_action( 'init', 'gw_preload_sync_cron' );

/**
 * Cron tick. The batched runner is added next; for now it only records that the schedule is alive, so turning
 * the preloader on is safe and does no fetching yet.
 *
 * @return void
 */
function gw_preload_tick() {
	if ( ! gw_preload_settings()['enabled'] ) {
		return;
	}
	$state             = (array) get_option( GW_PRELOAD_STATE, array() );
	$state['last_run'] = time();
	update_option( GW_PRELOAD_STATE, $state, false );
}
add_action( GW_PRELOAD_HOOK, 'gw_preload_tick' );

/**
 * Stop the schedule when the plugin is deactivated.
 *
 * @return void
 */
function gw_preload_deactivate() {
	wp_clear_scheduled_hook( GW_PRELOAD_HOOK );
}
register_deactivation_hook( GW_FILE, 'gw_preload_deactivate' );
