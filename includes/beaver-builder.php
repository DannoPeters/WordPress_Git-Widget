<?php
/**
 * Beaver Builder integration: registers the "GitHub Widget" module when Beaver Builder is active.
 *
 * @package GitWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the module once Beaver Builder is ready.
 *
 * @return void
 */
function gw_bb_load() {
	if ( class_exists( 'FLBuilder' ) ) {
		require_once GW_DIR . 'modules/gw-github/gw-github.php';
	}
}
add_action( 'init', 'gw_bb_load' );
