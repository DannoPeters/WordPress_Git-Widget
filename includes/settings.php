<?php
/**
 * Settings > Git Widget: default GitHub username, optional token and cache time.
 *
 * @package GitWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Name of the option that stores the plugin settings. */
const GW_OPTION = 'gw_settings';

/**
 * Saved settings merged with defaults.
 *
 * @return array username, token, cache_hours.
 */
function gw_settings() {
	return wp_parse_args(
		get_option( GW_OPTION, array() ),
		array(
			'username'    => '',
			'token'       => '',
			'cache_hours' => 6,
		)
	);
}

/**
 * Register the settings page.
 *
 * @return void
 */
function gw_settings_menu() {
	add_options_page( 'Git Widget', 'Git Widget', 'manage_options', 'git-widget', 'gw_settings_page' );
}
add_action( 'admin_menu', 'gw_settings_menu' );

/**
 * Draw the settings page.
 *
 * @return void
 */
function gw_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s      = gw_settings();
	$notice = get_transient( 'gw_notice' );
	delete_transient( 'gw_notice' );
	?>
	<div class="wrap">
		<h1>Git Widget</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?>"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gw_save' ); ?>
			<input type="hidden" name="action" value="gw_save">
			<input type="hidden" name="gw_section" value="main">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="gw-username">GitHub username</label></th>
					<td><input id="gw-username" name="username" type="text" class="regular-text" value="<?php echo esc_attr( $s['username'] ); ?>">
						<p class="description">Used by every widget unless a widget sets its own.</p></td>
				</tr>
				<tr>
					<th><label for="gw-token">GitHub token (optional)</label></th>
					<td><input id="gw-token" name="token" type="password" class="regular-text" autocomplete="new-password" data-lpignore="true" data-1p-ignore
						placeholder="<?php echo $s['token'] ? esc_attr__( 'Saved (leave blank to keep)', 'git-widget' ) : 'github_pat_…'; ?>">
						<p class="description">Needed for contribution type statistics and yearly charts. A fine-grained token with <strong>no permissions</strong> is enough: it only reads public data. Without one, the profile, organisations, calendar and achievements still work.</p></td>
				</tr>
				<tr>
					<th><label for="gw-cache">Refresh every</label></th>
					<td><input id="gw-cache" name="cache_hours" type="number" min="1" max="168" value="<?php echo (int) $s['cache_hours']; ?>"> hours
						<p class="description">GitHub data is cached so pages stay fast.</p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2><?php esc_html_e( 'Preload GitHub data', 'git-widget' ); ?></h2>
		<?php $gw_pl = gw_preload_settings(); $gw_st = gw_preload_status(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gw_save' ); ?>
			<input type="hidden" name="action" value="gw_save">
			<input type="hidden" name="gw_section" value="preload">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Preload in the background', 'git-widget' ); ?></th>
					<td><label><input type="checkbox" name="preload_enabled" value="1" <?php checked( $gw_pl['enabled'] ); ?>> <?php esc_html_e( 'Keep every year and section ready in the cache', 'git-widget' ); ?></label>
						<p class="description"><?php esc_html_e( 'Warms a few entries when the site is visited, so pages never wait on GitHub and switching the year is instant. An idle site makes no calls, and it stays gentle on GitHub\'s rate limit.', 'git-widget' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gw-batch"><?php esc_html_e( 'Entries per run', 'git-widget' ); ?></label></th>
					<td><input id="gw-batch" name="preload_batch" type="number" min="1" max="20" value="<?php echo (int) $gw_pl['batch']; ?>">
						<p class="description"><?php esc_html_e( 'Without a GitHub token, GitHub allows about 60 requests an hour. 3 per run (every 15 minutes) stays well under that.', 'git-widget' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'git-widget' ); ?></th>
					<td><p class="description">
						<?php
						if ( ! $gw_st['enabled'] ) {
							esc_html_e( 'Off.', 'git-widget' );
						} elseif ( $gw_st['total'] && $gw_st['finished'] ) {
							printf( esc_html__( 'Ready: all %d entries are cached. It re-checks automatically.', 'git-widget' ), (int) $gw_st['total'] );
						} elseif ( $gw_st['total'] ) {
							printf( esc_html__( 'Warming: %1$d of %2$d entries (%3$d%%).', 'git-widget' ), (int) $gw_st['done'], (int) $gw_st['total'], (int) $gw_st['percent'] );
						} else {
							esc_html_e( 'Starting on the next run.', 'git-widget' );
						}
						if ( $gw_st['rate_reset'] ) {
							echo ' ' . esc_html__( 'Waiting for GitHub\'s rate limit to reset.', 'git-widget' );
						}
						?>
					</p></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save preloading', 'git-widget' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:-1em">
			<?php wp_nonce_field( 'gw_prime' ); ?>
			<input type="hidden" name="action" value="gw_prime">
			<?php submit_button( __( 'Prime cache now', 'git-widget' ), 'secondary', 'submit', false ); ?>
			<p class="description"><?php esc_html_e( 'Warms a chunk right away; the rest keeps filling in as people visit the site.', 'git-widget' ); ?></p>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gw_flush' ); ?>
			<input type="hidden" name="action" value="gw_flush">
			<?php submit_button( 'Refresh GitHub data now', 'secondary' ); ?>
		</form>
	</div>
	<?php
}

/**
 * Save the settings form.
 *
 * @return void Redirects back to the page.
 */
function gw_save_settings() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'gw_save' ) ) {
		wp_die( 'Not allowed' );
	}
	$section = sanitize_key( wp_unslash( $_POST['gw_section'] ?? 'main' ) );
	if ( 'preload' === $section ) {
		update_option(
			GW_PRELOAD_OPTION,
			array(
				'enabled' => ! empty( $_POST['preload_enabled'] ),
				'batch'   => max( 1, min( 20, (int) ( $_POST['preload_batch'] ?? 3 ) ) ),
			),
			false
		);
		gw_preload_sync_cron();
		set_transient( 'gw_notice', array( 'success', 'Preloading saved.' ), 60 );
	} else {
		$s                = gw_settings();
		$s['username']    = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		$s['cache_hours'] = max( 1, min( 168, (int) ( $_POST['cache_hours'] ?? 6 ) ) );
		$token            = trim( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
		// Only accept values shaped like a GitHub token, so browser auto-fill can't overwrite it.
		if ( preg_match( '/^(github_pat_|ghp_)[A-Za-z0-9_]{20,}$/', $token ) ) {
			$s['token'] = $token;
		}
		update_option( GW_OPTION, $s, false );
		set_transient( 'gw_notice', array( 'success', 'Settings saved.' ), 60 );
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=git-widget' ) );
	exit;
}
add_action( 'admin_post_gw_save', 'gw_save_settings' );
