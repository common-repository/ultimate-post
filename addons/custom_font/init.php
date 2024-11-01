<?php
defined( 'ABSPATH' ) || exit;

add_action('init', 'ultp_custom_font_init');
function ultp_custom_font_init() {
	if ( ultimate_post()->get_setting('ultp_custom_font') == 'true' ) {
		if ( current_user_can('manage_options') ) {
            require_once ULTP_PATH.'/addons/custom_font/Custom_Font.php';
			new \ULTP\Custom_Font();
        }
	}
}