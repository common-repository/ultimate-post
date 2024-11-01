<?php
namespace ULTP;

defined('ABSPATH') || exit;

class Shortcode {
    public function __construct() {
        add_shortcode('gutenberg_post_blocks', array($this, 'shortcode_callback'));
        add_shortcode('postx_template', array($this, 'shortcode_callback'));
    }

    // Shortcode Callback
    function shortcode_callback( $atts = array(), $content = null ) {
        extract(shortcode_atts(array(
         'id' => ''
        ), $atts));

        $id = is_numeric( $id ) ? (float) $id : false;

        if ($id) {
            $content = '';
            $pre_content = '';
            $content_post = get_post($id);
            if ($content_post) {
                if ($content_post->post_status == 'publish' && $content_post->post_password == '') {
                    do_action('ultp_enqueue_postx_block_css',
                        [ 'post_id' => $id, 'css' => '', ]
                    );
                    // Breakdance builder support for its shortcode render
                    $current_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url( $_SERVER['REQUEST_URI'] ) : '';
                    if ( 
                        !empty($_GET['_breakdance_doing_ajax']) ||
                        strpos( $current_url, 'bricks/v1/render_element' ) !== false 
                    ) {
                        
                        if ( !empty($_GET['_breakdance_doing_ajax']) && get_template() == 'bricks' ) {
                            get_header();
                        }
                        $pre_content .= ultimate_post()->build_css_for_inline_print( $id, true );
                    }

                    $content .= $content_post->post_content;
                    $content = do_blocks($content);
                    $content = do_shortcode($content);
                    $content = str_replace(']]>', ']]&gt;', $content);
                    $content = preg_replace('%<p>&nbsp;\s*</p>%', '', $content);
                    $content = preg_replace('/^(?:<br\s*\/?>\s*)+/', '', $content);
                    
                    return $pre_content.'<div class="ultp-shortcode" data-postid="'.$id.'">' . $content . '</div>';
                }
            }
        }
        return '';
    }
    
}