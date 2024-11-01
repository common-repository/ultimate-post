<?php
defined( 'ABSPATH' ) || exit;

function ultp_wpbakery_builder() {
	if ( ultimate_post()->get_setting('ultp_wpbakery') == 'true' ) {
		if (defined( 'WPB_VC_VERSION' )) {
			require_once ULTP_PATH.'/addons/wpbakery/wpbakery.php';
		}
	}
}

add_action( 'init', 'ultp_wpbakery_builder' );