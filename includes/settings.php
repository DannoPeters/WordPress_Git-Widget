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
 * The saved site style merged with the built-in look.
 *
 * @return array accent, theme, layout, types_style.
 */
function gw_site_style_admin() {
	return wp_parse_args(
		(array) get_option( 'gw_style', array() ),
		array( 'accent' => '', 'theme' => 'auto', 'layout' => 'stack', 'types_style' => 'axes' )
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
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	$s      = gw_settings();
	$st     = gw_site_style_admin();
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
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Site style', 'git-widget' ); ?></th>
					<td><p class="description"><?php esc_html_e( 'Used by any widget set to "Match the site style". Leave as-is to use the built-in look.', 'git-widget' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="gw-accent"><?php esc_html_e( 'Accent colour', 'git-widget' ); ?></label></th>
					<td><input id="gw-accent" name="style_accent" type="text" class="regular-text gw-colour" value="<?php echo esc_attr( $st['accent'] ); ?>" placeholder="#2da44e"></td>
				</tr>
				<tr>
					<th><label for="gw-theme"><?php esc_html_e( 'Background', 'git-widget' ); ?></label></th>
					<td><select id="gw-theme" name="style_theme">
						<?php foreach ( array( 'auto' => __( 'Match the page', 'git-widget' ), 'light' => __( 'Light card', 'git-widget' ), 'dark' => __( 'Dark card', 'git-widget' ) ) as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $st['theme'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr>
					<th><label for="gw-layout"><?php esc_html_e( 'Layout', 'git-widget' ); ?></label></th>
					<td><select id="gw-layout" name="style_layout">
						<?php foreach ( array( 'stack' => __( 'Stacked', 'git-widget' ), 'grid' => __( 'Side by side when there is room', 'git-widget' ) ) as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $st['layout'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr>
					<th><label for="gw-types-style"><?php esc_html_e( 'Contribution types chart', 'git-widget' ); ?></label></th>
					<td><select id="gw-types-style" name="style_types_style">
						<?php foreach ( array( 'axes' => __( 'Cross chart (like GitHub)', 'git-widget' ), 'bar' => __( 'Bar with counts', 'git-widget' ) ) as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $st['types_style'], $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save settings', 'git-widget' ), 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gw_flush' ); ?>
			<input type="hidden" name="action" value="gw_flush">
			<?php submit_button( 'Refresh GitHub data now', 'secondary' ); ?>
		</form>
		<script>jQuery(function($){$('.gw-colour').wpColorPicker();});</script>
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
	$s                = gw_settings();
	$s['username']    = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
	$s['cache_hours'] = max( 1, min( 168, (int) ( $_POST['cache_hours'] ?? 6 ) ) );
	$token            = trim( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) );
	// Only accept values shaped like a GitHub token, so browser auto-fill can't overwrite it.
	if ( preg_match( '/^(github_pat_|ghp_)[A-Za-z0-9_]{20,}$/', $token ) ) {
		$s['token'] = $token;
	}
	update_option( GW_OPTION, $s, false );
	$accent = ltrim( trim( sanitize_text_field( wp_unslash( $_POST['style_accent'] ?? '' ) ) ), '#' );
	update_option(
		'gw_style',
		array(
			'accent'      => preg_match( '/^[0-9a-fA-F]{3,8}$/', $accent ) ? '#' . $accent : '',
			'theme'       => in_array( $_POST['style_theme'] ?? '', array( 'auto', 'light', 'dark' ), true ) ? $_POST['style_theme'] : 'auto',
			'layout'      => in_array( $_POST['style_layout'] ?? '', array( 'stack', 'grid' ), true ) ? $_POST['style_layout'] : 'stack',
			'types_style' => in_array( $_POST['style_types_style'] ?? '', array( 'axes', 'bar' ), true ) ? $_POST['style_types_style'] : 'axes',
		),
		false
	);
	set_transient( 'gw_notice', array( 'success', 'Settings saved.' ), 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=git-widget' ) );
	exit;
}
add_action( 'admin_post_gw_save', 'gw_save_settings' );
