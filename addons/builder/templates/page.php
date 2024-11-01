<?php
defined( 'ABSPATH' ) || exit;

global $ULTP_HEADER_ID;
global $ULTP_FOOTER_ID;

if ( wp_is_block_theme() ) {
    ?><!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<?php 
		wp_head();
		?>
	</head>
	<body <?php body_class(); ?>>
	<?php wp_body_open();

	if( !$ULTP_HEADER_ID ) {
		ob_start();
        block_template_part('header');
		$header = ob_get_clean();
		echo '<header class="wp-block-template-part">'.$header.'</header>';
    }
} else {
    get_header();
}

do_action( 'ultp_before_content' );

global $ultp_page_builder_id;
if ( $ultp_page_builder_id ) {
    $page_id = $ultp_page_builder_id;
} else {
    $page_id = ultimate_post()->builder_check_conditions('return_p');
}

$width = $page_id ? ( get_post_meta($page_id, '__container_width', true) ? get_post_meta($page_id, '__container_width', true) : '1140' ) : '1140';
$sidebar = $page_id ? get_post_meta($page_id, '__builder_sidebar', true) : '';
$widget_area = $page_id ? get_post_meta($page_id, '__builder_widget_area', true) : '';
$has_widget = ($sidebar && $widget_area != '') ? true : false;

echo '<div class="ultp-builder-container'.(esc_html($has_widget?' ultp-widget-'.$sidebar:'')).(esc_html(' ultp-builderid-'.$page_id)).'" style="margin:0 auto; width: -webkit-fill-available; width: -moz-available; max-width: '.esc_html($width).'px;">';
    if ($has_widget && $sidebar == 'left') {
        echo '<div class="ultp-sidebar-left">';
            if (is_active_sidebar($widget_area)) {
                dynamic_sidebar($widget_area);
            }
        echo '</div>';
    }
    echo '<div class="ultp-builder-wrap">';
        if ($page_id) {
            /* Content Filtering for elementor & divi */
            $builder_type = '';
            $body_class = get_body_class();
            $divi_settings = ultimate_post()->get_setting('ultp_divi');
            $elem_settings = ultimate_post()->get_setting('ultp_elementor');
            if($divi_settings == 'true' && class_exists( 'ET_Builder_Module' ) && in_array('et-fb', $body_class)) { 
                $builder_type = 'divi';
            } else if( did_action( 'elementor/loaded' ) && $elem_settings == 'true' && in_array('elementor-editor-active', $body_class) && in_array('elementor-editor-wp-post', $body_class) ) {
                $builder_type = 'elementor';
            }
            /* Content Filtering for elementor & divi */
            ultimate_post()->get_post_content($page_id, $builder_type);
        } else {
            the_content();
        }
    echo '</div>';
    if ($has_widget && $sidebar == 'right') {
        echo '<div class="ultp-sidebar-right">';
            if (is_active_sidebar($widget_area)) {
                dynamic_sidebar($widget_area);
            }
        echo '</div>';
    }
echo '</div>';

do_action( 'ultp_after_content' );


if ( wp_is_block_theme() ) {
    ?>
	</body>
	</html>
	<?php
	if ( !$ULTP_FOOTER_ID ) {
		ob_start();
        block_template_part('footer');
		$footer = ob_get_clean();
		echo '<footer class="wp-block-template-part">'.$footer.'</footer>';
    }
	wp_head();
	wp_footer();
} else {
    get_footer();
}