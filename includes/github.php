<?php
/**
 * GitHub data: profile, organisations, contribution calendar, contribution types and achievements.
 *
 * With a token, everything comes from the GraphQL API. Without one, the profile and organisations
 * come from the public REST API and the calendar and achievements from the public profile pages.
 * Every result is cached for the configured number of hours.
 *
 * @package GitWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cached wrapper: return the stored value for $key, or compute, store and return it.
 *
 * @param string   $key  Cache key (username and what is being fetched).
 * @param callable $make Builds the value; return a WP_Error to skip caching.
 * @return mixed|WP_Error
 */
function gw_cached( $key, $make ) {
	$tkey = 'gw_' . md5( $key );
	$hit  = get_transient( $tkey );
	if ( false !== $hit ) {
		return $hit;
	}
	$value = $make();
	if ( ! is_wp_error( $value ) ) {
		set_transient( $tkey, $value, (int) gw_settings()['cache_hours'] * HOUR_IN_SECONDS );
		// Keep a list of keys so "Refresh GitHub data now" can clear them.
		$keys = (array) get_option( 'gw_cache_keys', array() );
		if ( ! in_array( $tkey, $keys, true ) ) {
			$keys[] = $tkey;
			update_option( 'gw_cache_keys', array_slice( $keys, -500 ), false );
		}
	}
	return $value;
}

/**
 * Clear all cached GitHub data (Settings > Git Widget > Refresh GitHub data now).
 *
 * @return void Redirects back.
 */
function gw_flush_cache() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gw_flush' ) ) {
		wp_die( 'Not allowed' );
	}
	foreach ( (array) get_option( 'gw_cache_keys', array() ) as $k ) {
		delete_transient( $k );
	}
	delete_option( 'gw_cache_keys' );
	set_transient( 'gw_notice', array( 'success', 'GitHub data will be fetched fresh on the next page view.' ), 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=git-widget' ) );
	exit;
}
add_action( 'admin_post_gw_flush', 'gw_flush_cache' );

/**
 * HTTP GET with GitHub-friendly headers.
 *
 * @param string $url    Full URL.
 * @param bool   $json   Decode JSON (true) or return the raw body.
 * @param bool   $auth   Send the token, if one is saved.
 * @return mixed|WP_Error Decoded data or body.
 */
function gw_get( $url, $json = true, $auth = true ) {
	$headers = array( 'User-Agent' => 'WordPress-Git-Widget' );
	$token   = gw_settings()['token'];
	if ( $json ) {
		$headers['Accept'] = 'application/vnd.github+json';
	}
	if ( $auth && $token && false !== strpos( $url, 'api.github.com' ) ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}
	$resp = wp_remote_get( $url, array( 'timeout' => 15, 'headers' => $headers ) );
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	if ( 200 !== $code ) {
		return new WP_Error( 'gw_http', 'GitHub returned HTTP ' . $code . ' for ' . $url );
	}
	return $json ? json_decode( $body, true ) : $body;
}

/**
 * Run a GraphQL query (needs a token).
 *
 * @param string $query     GraphQL query.
 * @param array  $variables Variables.
 * @return array|WP_Error The "data" member.
 */
function gw_graphql( $query, $variables ) {
	$token = gw_settings()['token'];
	if ( ! $token ) {
		return new WP_Error( 'gw_no_token', 'A GitHub token is needed for this section.' );
	}
	$resp = wp_remote_post(
		'https://api.github.com/graphql',
		array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'User-Agent' => 'WordPress-Git-Widget', 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'query' => $query, 'variables' => $variables ) ),
		)
	);
	if ( is_wp_error( $resp ) ) {
		return $resp;
	}
	$data = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( ! empty( $data['errors'] ) || empty( $data['data'] ) ) {
		return new WP_Error( 'gw_graphql', $data['errors'][0]['message'] ?? $data['message'] ?? 'GitHub GraphQL request failed.' );
	}
	return $data['data'];
}

/**
 * Valid GitHub username or empty string.
 *
 * @param string $user Raw input.
 * @return string
 */
function gw_clean_user( $user ) {
	$user = trim( (string) $user );
	return preg_match( '/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})$/', $user ) ? $user : '';
}

/**
 * Profile and organisations.
 *
 * @param string $user GitHub username.
 * @return array|WP_Error name, login, avatar, bio, company, location, blog, twitter, url, followers, following, repos, orgs[], created.
 */
function gw_profile( $user ) {
	return gw_cached(
		"profile:$user",
		function () use ( $user ) {
			$u = gw_get( 'https://api.github.com/users/' . rawurlencode( $user ) );
			if ( is_wp_error( $u ) ) {
				return $u;
			}
			$orgs = gw_get( 'https://api.github.com/users/' . rawurlencode( $user ) . '/orgs' );
			return array(
				'name'      => $u['name'] ?? '',
				'login'     => $u['login'],
				'avatar'    => $u['avatar_url'],
				'bio'       => $u['bio'] ?? '',
				'company'   => $u['company'] ?? '',
				'location'  => $u['location'] ?? '',
				'blog'      => $u['blog'] ?? '',
				'twitter'   => $u['twitter_username'] ?? '',
				'url'       => $u['html_url'],
				'followers' => (int) $u['followers'],
				'following' => (int) $u['following'],
				'repos'     => (int) $u['public_repos'],
				'created'   => $u['created_at'],
				'orgs'      => is_wp_error( $orgs ) ? array() : array_map(
					function ( $o ) {
						return array( 'login' => $o['login'], 'avatar' => $o['avatar_url'], 'url' => 'https://github.com/' . $o['login'], 'description' => $o['description'] ?? '' );
					},
					$orgs
				),
			);
		}
	);
}

/**
 * Contribution calendar for the last year, or for a given year.
 * Uses GraphQL with a token; otherwise reads GitHub's public calendar page.
 *
 * @param string   $user GitHub username.
 * @param int|null $year Calendar year, or null for the last 12 months.
 * @return array|WP_Error days[] (date => count), total.
 */
function gw_calendar( $user, $year = null ) {
	return gw_cached(
		"calendar:$user:" . ( $year ? $year : 'last' ),
		function () use ( $user, $year ) {
			if ( gw_settings()['token'] ) {
				$range = $year ? array( 'from' => "$year-01-01T00:00:00Z", 'to' => "$year-12-31T23:59:59Z" ) : array();
				$data  = gw_graphql(
					'query($login:String!,$from:DateTime,$to:DateTime){user(login:$login){contributionsCollection(from:$from,to:$to){contributionCalendar{totalContributions weeks{contributionDays{date contributionCount}}}}}}',
					array( 'login' => $user ) + $range
				);
				if ( ! is_wp_error( $data ) ) {
					$cal  = $data['user']['contributionsCollection']['contributionCalendar'];
					$days = array();
					foreach ( $cal['weeks'] as $w ) {
						foreach ( $w['contributionDays'] as $d ) {
							$days[ $d['date'] ] = (int) $d['contributionCount'];
						}
					}
					return array( 'days' => $days, 'total' => (int) $cal['totalContributions'] );
				}
			}
			// Public fallback: the same HTML fragment github.com loads on profile pages.
			$url  = 'https://github.com/users/' . rawurlencode( $user ) . '/contributions' . ( $year ? "?from=$year-01-01&to=$year-12-31" : '' );
			$html = gw_get( $url, false, false );
			if ( is_wp_error( $html ) ) {
				return $html;
			}
			return gw_parse_calendar_html( $html );
		}
	);
}

/**
 * Read day counts from GitHub's public contributions HTML.
 *
 * @param string $html Page HTML.
 * @return array days[] (date => count), total.
 */
function gw_parse_calendar_html( $html ) {
	$days = array();
	// Each day cell has data-date and an id; the count is in a matching <tool-tip for="id">.
	preg_match_all( '/<td[^>]*data-date="(\d{4}-\d{2}-\d{2})"[^>]*id="([^"]+)"|<td[^>]*id="([^"]+)"[^>]*data-date="(\d{4}-\d{2}-\d{2})"/', $html, $cells, PREG_SET_ORDER );
	$tips = array();
	if ( preg_match_all( '/<tool-tip[^>]*for="([^"]+)"[^>]*>([^<]*)</', $html, $tm, PREG_SET_ORDER ) ) {
		foreach ( $tm as $t ) {
			$tips[ $t[1] ] = preg_match( '/(\d[\d,]*) contribution/', $t[2], $n ) ? (int) str_replace( ',', '', $n[1] ) : 0;
		}
	}
	foreach ( $cells as $c ) {
		$date          = $c[1] ? $c[1] : $c[4];
		$id            = $c[2] ? $c[2] : $c[3];
		$days[ $date ] = $tips[ $id ] ?? 0;
	}
	ksort( $days );
	return array( 'days' => $days, 'total' => array_sum( $days ) );
}

/**
 * Totals by contribution type for one year or the last 12 months (needs a token).
 *
 * @param string   $user GitHub username.
 * @param int|null $year Year, or null for the last 12 months.
 * @return array|WP_Error commits, issues, pull_requests, reviews, repositories, private.
 */
function gw_contribution_types( $user, $year = null ) {
	return gw_cached(
		"types:$user:" . ( $year ? $year : 'last' ),
		function () use ( $user, $year ) {
			$range = $year ? array( 'from' => "$year-01-01T00:00:00Z", 'to' => "$year-12-31T23:59:59Z" ) : array();
			$data  = gw_graphql(
				'query($login:String!,$from:DateTime,$to:DateTime){user(login:$login){contributionsCollection(from:$from,to:$to){totalCommitContributions totalIssueContributions totalPullRequestContributions totalPullRequestReviewContributions totalRepositoryContributions restrictedContributionsCount}}}',
				array( 'login' => $user ) + $range
			);
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$c = $data['user']['contributionsCollection'];
			return array(
				'commits'       => (int) $c['totalCommitContributions'],
				'pull_requests' => (int) $c['totalPullRequestContributions'],
				'issues'        => (int) $c['totalIssueContributions'],
				'reviews'       => (int) $c['totalPullRequestReviewContributions'],
				'repositories'  => (int) $c['totalRepositoryContributions'],
				'private'       => (int) $c['restrictedContributionsCount'],
			);
		}
	);
}

/**
 * Years the user has contributions in, newest first (needs a token; falls back to account age).
 *
 * @param string $user GitHub username.
 * @return int[]
 */
function gw_contribution_years( $user ) {
	$years = gw_cached(
		"years:$user",
		function () use ( $user ) {
			$data = gw_graphql( 'query($login:String!){user(login:$login){contributionsCollection{contributionYears}}}', array( 'login' => $user ) );
			return is_wp_error( $data ) ? $data : array_map( 'intval', $data['user']['contributionsCollection']['contributionYears'] );
		}
	);
	if ( is_wp_error( $years ) ) {
		$p     = gw_profile( $user );
		$first = is_wp_error( $p ) ? (int) gmdate( 'Y' ) : (int) substr( $p['created'], 0, 4 );
		$years = range( (int) gmdate( 'Y' ), $first );
	}
	return $years;
}

/**
 * Achievements from the public profile page (GitHub has no API for them).
 *
 * @param string $user GitHub username.
 * @return array|WP_Error List of name, image, count, url.
 */
function gw_achievements( $user ) {
	return gw_cached(
		"achievements:$user",
		function () use ( $user ) {
			$html = gw_get( 'https://github.com/' . rawurlencode( $user ) . '?tab=achievements', false, false );
			if ( is_wp_error( $html ) ) {
				return $html;
			}
			return gw_parse_achievements_html( $html, $user );
		}
	);
}

/**
 * Read achievement badges from profile HTML.
 *
 * @param string $html Page HTML.
 * @param string $user GitHub username, for links.
 * @return array List of name, image, count, url.
 */
function gw_parse_achievements_html( $html, $user ) {
	$out = array();
	if ( preg_match_all( '/<img[^>]+alt="Achievement: ([^"]+)"[^>]+src="([^"]+)"[^>]*>(.{0,600})/s', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $a ) {
			$name = html_entity_decode( $a[1] );
			if ( isset( $out[ $name ] ) ) {
				continue;
			}
			$count        = preg_match( '/achievement-tier-label[^>]*>\s*x(\d+)/', $a[3], $c ) ? (int) $c[1] : 1;
			$slug         = sanitize_title( $name );
			$out[ $name ] = array(
				'name'  => $name,
				'image' => $a[2],
				'count' => $count,
				'url'   => 'https://github.com/' . rawurlencode( $user ) . '?achievement=' . str_replace( '-', '-', $slug ) . '&tab=achievements',
			);
		}
	}
	return array_values( $out );
}
