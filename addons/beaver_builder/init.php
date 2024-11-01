<?php
defined( 'ABSPATH' ) || exit;
function ultp_postx_beaver_builder() {
	if ( ultimate_post()->get_setting('ultp_beaver_builder') == 'true' ) {
		if ( class_exists( 'FLBuilder' ) ) {
			require_once ULTP_PATH.'/addons/beaver_builder/beaverbuilder.php';
		}
	}
}
add_action( 'init', 'ultp_postx_beaver_builder' );