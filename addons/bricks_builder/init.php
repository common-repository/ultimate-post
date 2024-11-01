<?php
defined( 'ABSPATH' ) || exit;

function ultp_postx_bricks_builder() {
	if ( ultimate_post()->get_setting('ultp_bricks_builder') == 'true' ) {
		if ( defined( 'BRICKS_VERSION' ) ) {
			\Bricks\Elements::register_element( ULTP_PATH . '/addons/bricks_builder/bricksbuilder.php' );
		}
	}
}
add_action( 'init', 'ultp_postx_bricks_builder', 11 );