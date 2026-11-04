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

/** How recently the site must have been viewed for the preloader to do anything (so an idle site is silent). */
const GW_VIEW_WINDOW = 1800; // 30 minutes.

/** Option names. */
const GW_PRELOAD_OPTION = 'gw_preload';       // Settings: enabled, batch.
const GW_PRELOAD_STATE  = 'gw_preload_state'; // Progress: queue, done, cursor, last_run, started.
const GW_PRELOAD_HOOK   = 'gw_preload_tick';  // Cron event.

/**
 * Note that someone just viewed the front end, so the preloader knows the site is in use. Written at most once
 * every couple of minutes to keep it cheap, and never from the admin.
 *
 * @return void
 */
function gw_note_view() {
	if ( is_admin() ) {
		return;
	}
	$now  = time();
	$last = (int) get_option( 'gw_last_view', 0 );
	if ( $now - $last > 120 ) {
		update_option( 'gw_last_view', $now, true );
	}
}
add_action( 'template_redirect', 'gw_note_view' );

/**
 * Whether the site has been viewed recently enough for the preloader to run. This is what keeps an idle site
 * from making any GitHub calls: no recent view, no warming.
 *
 * @return bool
 */
function gw_recently_viewed() {
	return ( time() - (int) get_option( 'gw_last_view', 0 ) ) <= GW_VIEW_WINDOW;
}

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
 * Current progress, with sensible defaults.
 *
 * @return array queue (list of "user\tjob"), cursor, done, total, started, last_run, finished.
 */
function gw_preload_state() {
	return wp_parse_args(
		(array) get_option( GW_PRELOAD_STATE, array() ),
		array( 'queue' => array(), 'cursor' => 0, 'done' => 0, 'total' => 0, 'started' => 0, 'last_run' => 0, 'finished' => 0 )
	);
}

/**
 * Build the full queue of "user<TAB>job" entries across every warmed account. Called once per cycle; the list
 * of years it needs is itself cached, so building the queue is cheap after the first time.
 *
 * @return array
 */
function gw_preload_build_queue() {
	$queue = array();
	foreach ( gw_preload_users() as $user ) {
		foreach ( gw_preload_jobs( $user ) as $job ) {
			$queue[] = $user . "\t" . $job;
		}
	}
	return $queue;
}

/**
 * Warm up to $limit entries, stopping early if GitHub's rate limit kicks in. Safe to call from cron or a
 * button. Builds the queue when there isn't one, and marks the cycle finished when it reaches the end.
 *
 * @param int $limit How many entries to warm this call.
 * @return array The updated state.
 */
function gw_preload_process( $limit ) {
	$state = gw_preload_state();
	$done  = ! empty( $state['queue'] ) && $state['cursor'] >= count( $state['queue'] );
	// How long a warmed cycle stays good: the cache lifetime. Re-warm a little before it lapses.
	$ttl     = max( 1, (int) gw_settings()['cache_hours'] ) * HOUR_IN_SECONDS;
	$refresh = max( 0, $ttl - 15 * MINUTE_IN_SECONDS );
	$start_new = empty( $state['queue'] ) || ( $done && ( time() - (int) $state['started'] ) >= $refresh );
	if ( $start_new ) {
		// Start a fresh cycle.
		$state['queue']    = gw_preload_build_queue();
		$state['cursor']   = 0;
		$state['done']     = 0;
		$state['total']    = count( $state['queue'] );
		$state['started']  = time();
		$state['finished'] = 0;
	} elseif ( $done ) {
		// Finished and not yet due to refresh: nothing to do this run.
		$state['last_run'] = time();
		update_option( GW_PRELOAD_STATE, $state, false );
		return $state;
	}
	$state['last_run'] = time();
	$processed = 0;
	while ( $processed < $limit && $state['cursor'] < count( $state['queue'] ) ) {
		if ( gw_rate_limited() ) {
			break; // Come back after the limit resets.
		}
		list( $user, $job ) = array_pad( explode( "\t", $state['queue'][ $state['cursor'] ], 2 ), 2, '' );
		if ( '' !== $user && '' !== $job ) {
			gw_preload_run_job( $user, $job );
		}
		$state['cursor']++;
		$state['done']++;
		$processed++;
	}
	if ( $state['cursor'] >= count( $state['queue'] ) && $state['total'] > 0 ) {
		$state['finished'] = time();
	}
	update_option( GW_PRELOAD_STATE, $state, false );
	return $state;
}

/**
 * Cron tick: warm one batch, pacing well under GitHub's limits (the default 3 per 15 minutes is 12 an hour,
 * comfortably below the 60 an hour GitHub allows without a token).
 *
 * @return void
 */
function gw_preload_tick() {
	$settings = gw_preload_settings();
	// Only ever warm when the site has actually been visited, and never while rate-limited.
	if ( ! $settings['enabled'] || gw_rate_limited() || ! gw_recently_viewed() ) {
		return;
	}
	gw_preload_process( max( 1, (int) $settings['batch'] ) );
}
add_action( GW_PRELOAD_HOOK, 'gw_preload_tick' );

/**
 * A short, human summary of where the preloader is at, for the settings page.
 *
 * @return array enabled, total, done, percent, finished (ts), last_run (ts), rate_reset (ts).
 */
function gw_preload_status() {
	$s     = gw_preload_settings();
	$state = gw_preload_state();
	$total = (int) $state['total'];
	return array(
		'enabled'    => (bool) $s['enabled'],
		'total'      => $total,
		'done'       => min( (int) $state['cursor'], $total ),
		'percent'    => $total ? (int) round( 100 * min( $state['cursor'], $total ) / $total ) : 0,
		'finished'   => (int) $state['finished'],
		'last_run'   => (int) $state['last_run'],
		'rate_reset' => gw_rate_reset(),
	);
}

/**
 * Stop the schedule when the plugin is deactivated.
 *
 * @return void
 */
function gw_preload_deactivate() {
	wp_clear_scheduled_hook( GW_PRELOAD_HOOK );
}
register_deactivation_hook( GW_FILE, 'gw_preload_deactivate' );

/**
 * "Prime cache now": warm a chunk immediately from the admin. Capped so one click can't run long or blow the
 * rate limit; the rest fills in on visits. This is an explicit admin action, so it ignores the view gate.
 *
 * @return void Redirects back to the settings page.
 */
function gw_preload_prime_now() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gw_prime' ) ) {
		wp_die( 'Not allowed' );
	}
	if ( gw_preload_settings()['enabled'] && ! gw_rate_limited() ) {
		gw_preload_process( 15 );
	}
	$msg = gw_rate_limited() ? 'GitHub is rate-limiting right now; the rest will fill in shortly.' : 'Warmed a chunk of the cache.';
	set_transient( 'gw_notice', array( 'success', $msg ), 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=git-widget' ) );
	exit;
}
add_action( 'admin_post_gw_prime', 'gw_preload_prime_now' );
