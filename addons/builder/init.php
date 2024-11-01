<?php
defined( 'ABSPATH' ) || exit;

add_action('init', 'ultp_new_builder_init');
function ultp_new_builder_init() {
	if ( ultimate_post()->get_setting('ultp_builder') == 'true' ) {
		require_once ULTP_PATH.'/addons/builder/Builder.php';
		require_once ULTP_PATH.'/addons/builder/RequestAPI.php';
		new \ULTP\Builder();
		new \ULTP\RequestAPI();
	}
}