<?php
defined( 'ABSPATH' ) || exit;

function ultp_oxygen_builder() {
	if ( ultimate_post()->get_setting('ultp_oxygen') == 'true' ) {
		if ( class_exists( 'OxygenElement' ) ) {
			require_once ULTP_PATH.'/addons/oxygen/oxygen.php';
		}
	}
}
add_action( 'init', 'ultp_oxygen_builder' );