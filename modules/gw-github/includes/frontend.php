<?php
/**
 * Module output. $settings is provided by Beaver Builder.
 *
 * @package GitWidget
 */

echo gw_render( GW_BB_Module::to_atts( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- gw_render() escapes its output.
