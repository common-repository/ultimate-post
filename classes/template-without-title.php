<?php
defined( 'ABSPATH' ) || exit;

$is_block_theme = wp_is_block_theme();

global $ULTP_HEADER_ID;
global $ULTP_FOOTER_ID;
$builder_active = ultimate_post()->get_setting('ultp_builder') != 'false';
$has_header = $builder_active ? $ULTP_HEADER_ID : '';
$has_footer = $builder_active ? $ULTP_FOOTER_ID : '';

if( $is_block_theme ) {
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

	if( !$has_header ) {
		ob_start();
        block_template_part('header');
		$header = ob_get_clean();
		echo '<header class="wp-block-template-part">'.$header.'</header>';
    }
} else {
	get_header();
}

do_action( 'ultp_before_content' );

$width = ultimate_post()->get_setting( 'container_width' );
$width = $width ? $width : '1140';
?>
<div class="ultp-template-container" style="margin:0 auto;max-width:<?php echo esc_attr( $width ); ?>px; padding: 0 15px; width: -webkit-fill-available; width: -moz-available;">
	<?php
		while ( have_posts() ) : the_post();
			the_content();
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
	?>
</div>

<?php
do_action( 'ultp_after_content' );
if( $is_block_theme ) {
	?>
	</body>
	</html>
	<?php
	if ( !$has_footer ) {
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