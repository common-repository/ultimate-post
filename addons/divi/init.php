<?php
defined( 'ABSPATH' ) || exit;

function ultp_divi_builder() {
	if ( ultimate_post()->get_setting('ultp_divi') == 'true' ) {
		if ( class_exists( 'ET_Builder_Module' ) ) {
			require_once ULTP_PATH.'/addons/divi/divi.php';
			
			$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended	
			$post_id = isset($_GET['post']) ? sanitize_text_field($_GET['post']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended	
			if ($action && $post_id) {
				if (get_post_type($post_id) == 'ultp_templates') {
					add_filter( 'et_builder_enable_classic_editor', '__return_false' );
				}
			}
		}
	}
}
add_action( 'init', 'ultp_divi_builder' );