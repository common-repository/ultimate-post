<?php
defined( 'ABSPATH' ) || exit;

global $post_id;
$attr['headingStyle'] = sanitize_html_class( $attr['headingStyle'] );
$attr['headingAlign'] = sanitize_html_class( $attr['headingAlign'] );
$allowed_html_tags    = ultimate_post()->ultp_allowed_html_tags();

$finalHeadingText    = $attr['headingText'];
$finalHeadingURL     = $attr['headingURL'];
$finalSubHeadingText = $attr['subHeadingText'];

// Dynamic Content
if ( ultimate_post()->is_dc_active($attr) && isset( $attr['dc'] ) ) {
	[ $text, $url ] = \ULTP\DCService::get_dc_content_for_rich_text( $attr );

	if ( ! empty( $url ) ) {
		$finalHeadingURL = $url;
	}

	$finalHeadingText = \ULTP\DCService::replace( $finalHeadingText, $text );
}

$finalHeadingText    = wp_kses( $finalHeadingText, $allowed_html_tags );
$finalHeadingURL     = esc_url( $finalHeadingURL );
$finalSubHeadingText = wp_kses( $finalSubHeadingText, $allowed_html_tags );

$attr['headingBtnText'] = wp_kses( $attr['headingBtnText'], $allowed_html_tags );
$attr['headingTag']     = in_array( $attr['headingTag'], ultimate_post()->ultp_allowed_block_tags() ) ? $attr['headingTag'] : 'h2';

if ( $attr['headingShow'] ) {
	$new_tab        = isset( $attr['openInTab'] ) && $attr['openInTab'] == true ? 'target="_blank"' : '';
	$wraper_before .= '<div class="ultp-heading-wrap ultp-heading-' . $attr['headingStyle'] . ' ultp-heading-' . $attr['headingAlign'] . '">';
	if ( $finalHeadingURL ) {
		$wraper_before .= '<' . $attr['headingTag'] . ' class="ultp-heading-inner"><a href="' . $finalHeadingURL . '" ' . $new_tab . '><span>' . $finalHeadingText . '</span></a></' . $attr['headingTag'] . '>';
	} else {
		$wraper_before .= '<' . $attr['headingTag'] . ' class="ultp-heading-inner"><span>' . $finalHeadingText . '</span></' . $attr['headingTag'] . '>';
	}
	if ( $attr['headingStyle'] == 'style11' && $finalHeadingURL && $attr['headingBtnText'] ) {
		$wraper_before .= '<a class="ultp-heading-btn" href="' . $finalHeadingURL . '" ' . $new_tab . '>' . $attr['headingBtnText'] . ultimate_post()->get_svg_icon( 'rightAngle2' ) . '</a>';
	}
	if ( $attr['subHeadingShow'] ) {
		$wraper_before .= '<div class="ultp-sub-heading"><div class="ultp-sub-heading-inner">' . $finalSubHeadingText . '</div></div>';
	}
	$wraper_before .= '</div>';
}
