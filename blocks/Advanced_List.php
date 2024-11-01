<?php
namespace ULTP\blocks;

defined( 'ABSPATH' ) || exit;

class Advanced_List {
	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		register_block_type(
			'ultimate-post/list',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	public function render( $attr, $content ) {

		if ( ultimate_post()->is_dc_active( $attr ) && isset( $attr['dc'] ) ) {

			[ $text, $url ] = \ULTP\DCService::get_dc_content_for_rich_text( $attr );

			if ( ! empty( $url ) ) {
				$text = '<a href="' . esc_url( $url ) . '">' . wp_kses( $text, ultimate_post()->ultp_allowed_html_tags() ) . '</a>';
			}

			$content = \ULTP\DCService::replace( $content, $text );
		}

		return $content;
	}
}
