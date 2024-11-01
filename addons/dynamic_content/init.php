<?php

defined( 'ABSPATH' ) || exit;

function ultp_postx_addon_dynamic_content() {
	if ( ultimate_post()->get_setting( 'ultp_dynamic_content' ) == 'true' ) {
		require_once ULTP_PATH . '/addons/dynamic_content/includes/DCController.php';
		new \ULTP\DCController();
	}
}
add_action( 'init', 'ultp_postx_addon_dynamic_content' );
