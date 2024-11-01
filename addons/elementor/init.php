<?php
defined( 'ABSPATH' ) || exit;

add_action('plugins_loaded', 'ultp_elementor_init');
function ultp_elementor_init() {
	if ( ultimate_post()->get_setting('ultp_elementor') == 'true' ) {
		if (did_action( 'elementor/loaded' )) {
			require_once ULTP_PATH.'/addons/elementor/Elementor.php';
			Elementor_ULTP_Extension::instance();
		}
	}
}