<?php
defined( 'ABSPATH' ) || exit;

add_action('init', 'ultp_templates_init');
function ultp_templates_init() {
	if ( ultimate_post()->get_setting('ultp_templates') == 'true' ) {
		require_once ULTP_PATH.'/addons/templates/Saved_Templates.php';
		require_once ULTP_PATH.'/addons/templates/Shortcode.php';
		new \ULTP\Saved_Templates();
		new \ULTP\Shortcode();
	}
}