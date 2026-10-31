<?php
/**
 * Plugin Name: Git Widget
 * Plugin URI:  https://github.com/DannoPeters/WordPress_Git-Widget
 * Description: Embed your GitHub profile, contribution calendar, activity charts, achievements and organisations anywhere on your site. Works as a Beaver Builder module or the [git_widget] shortcode.
 * Version:     0.1.6
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      Danno Peters
 * License:     GPL-2.0-or-later
 * Text Domain: git-widget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Plugin version, used to version assets. */
define( 'GW_VERSION', '0.1.6' );
/** Absolute path to this plugin's folder, with a trailing slash. */
define( 'GW_DIR', plugin_dir_path( __FILE__ ) );
/** URL of this plugin's folder, with a trailing slash. */
define( 'GW_URL', plugin_dir_url( __FILE__ ) );

require_once GW_DIR . 'includes/settings.php';
require_once GW_DIR . 'includes/github.php';
require_once GW_DIR . 'includes/render.php';
require_once GW_DIR . 'includes/shortcode.php';
require_once GW_DIR . 'includes/beaver-builder.php';
