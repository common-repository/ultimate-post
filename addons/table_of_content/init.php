<?php
defined( 'ABSPATH' ) || exit;

add_filter( 'rank_math/researches/toc_plugins', function( $toc_plugins ) {
	if ( has_block( 'ultimate-post/table-of-content' ) ) {
		$toc_plugins['ultimate-post/ultimate-post.php'] = 'PostX';
	}
 	return $toc_plugins;
});