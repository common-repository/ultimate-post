<?php
/**
 * Require All Blocks and handle block ajax action
 * @package ULTP\Blocks
 * @since 4.1.11
*/

namespace ULTP;
defined('ABSPATH') || exit;

/*
* Blocks class.
*/
class Blocks {
    
    /*
	 * Setup class.
	 * @since 4.1.11
	*/
    public function __construct() {
        $this->include_all_blocks(); // Include Blocks
        add_action( 'wp_ajax_ultp_next_prev',               array( $this, 'ultp_next_prev_callback' )); // Next Previous AJAX Call
        add_action( 'wp_ajax_nopriv_ultp_next_prev',        array( $this, 'ultp_next_prev_callback' )); // Next Previous AJAX Call Logout User

        add_action( 'wp_ajax_ultp_filter',                  array( $this, 'ultp_filter_callback' )); // Next Previous AJAX Call
        add_action( 'wp_ajax_nopriv_ultp_filter',           array( $this, 'ultp_filter_callback' )); // Next Previous AJAX Call Logout User
        
        add_action( 'wp_ajax_ultp_adv_filter',              array( $this, 'ultp_adv_filter_callback' ) );  
        add_action( 'wp_ajax_nopriv_ultp_adv_filter',       array( $this, 'ultp_adv_filter_callback' ) );  
        
        add_action( 'wp_ajax_ultp_pagination',              array( $this, 'ultp_pagination_callback' )); // Page Number AJAX Call
        add_action( 'wp_ajax_nopriv_ultp_pagination',       array( $this, 'ultp_pagination_callback' )); // Page Number AJAX Call Logout User
    
        add_action( 'wp_ajax_ultp_share_count',             array( $this, 'ultp_shareCount_callback' )); // share Count save
        add_action( 'wp_ajax_nopriv_ultp_share_count',      array( $this, 'ultp_shareCount_callback' )); // share Count save
    }

    /**
	 * Require Blocks 
     * 
     * @since v.1.0.0
	 * @return NULL
	 */
    public function include_all_blocks() {
        spl_autoload_register( function ( $class ) {
            if ( strpos( $class, 'ULTP\blocks' ) === 0 ) {
                $source = ULTP_PATH . 'blocks/' . explode( '\\', $class )[2] . '.php';
                if ( file_exists( $source ) ) {
                    include_once $source;
                } else {
                    $source = ULTP_PATH . 'addons/builder/blocks/' . explode( '\\', $class )[2] . '.php';
                    if ( file_exists( $source ) ) {
                        include_once $source;
                    }
                }
            }
        } );

        
        $request = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
        $get_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

        if (
            is_admin()  && 
            $request != 'et_fb_ajax_render_shortcode' && // Divi Module Check
            $get_action != 'elementor' && // Elementor Widget Check
            $request != 'elementor_ajax' // Elementor Widget Check
        ) {
            return ;
        }


        $settings = ultimate_post()->get_setting();
        $blocks = array(
            'post_list_1'                   => 'Post_List_1',
            'post_list_2'                   => 'Post_List_2',
            'post_list_3'                   => 'Post_List_3',
            'post_list_4'                   => 'Post_List_4',
            'post_grid_1'                   => 'Post_Grid_1',
            'post_grid_2'                   => 'Post_Grid_2',
            'post_grid_3'                   => 'Post_Grid_3',
            'post_grid_4'                   => 'Post_Grid_4',
            'post_grid_5'                   => 'Post_Grid_5',
            'post_grid_6'                   => 'Post_Grid_6',
            'post_grid_7'                   => 'Post_Grid_7',
            'post_slider_1'                 => 'Post_Slider_1',
            'post_slider_2'                 => 'Post_Slider_2',
            'post_module_1'                 => 'Post_Module_1',
            'post_module_2'                 => 'Post_Module_2',
            'heading'                       => 'Heading',
            'image'                         => 'Image',
            'taxonomy'                      => 'Taxonomy',
            'news_ticker'                   => 'News_Ticker',
            'advanced_search'               => 'Advanced_Search',
            'advanced_filter'               => 'Advanced_Filter',
            'dark_Light'                    => 'Dark_Light',
            'advanced_list'                 => 'Advanced_List',
            'button'                        => 'Button'
        );
        
        foreach ( $blocks as $id => $block ) {
            if ( isset($settings[$id]) && $settings[$id] != 'yes' ) {
            } else {
                $obj = '\ULTP\blocks\\' . $block;
                new $obj();
            }
        }

        if ( isset($settings['ultp_builder']) && $settings['ultp_builder'] == 'true' ) {
            $builder_blocks = array(
                'builder_archive_title'                 => 'Archive_Title',
                'builder_post_title'                    => 'Post_Title',
                'builder_post_content'                  => 'Post_Content',
                'builder_post_featured_image'           => 'Post_Featured_Image',
                'builder_post_breadcrumb'               => 'Post_Breadcrumb',
                'builder_post_tag'                      => 'Post_Tag',
                'builder_post_category'                 => 'Post_Category',
                'builder_post_next_previous'                 => 'Next_Previous',
                'builder_post_excerpt'                  => 'Post_Excerpt',
                'builder_author_box'                    => 'Author_Box',
                'builder_post_comments'                 => 'Post_Comments',
                'builder_post_view_count'               => 'Post_View_Count',
                'builder_post_reading_time'             => 'Post_Reading_Time',
                'builder_post_comment_count'            => 'Post_Comment_Count',
                'builder_post_author_meta'              => 'Post_Author_Meta',
                'builder_post_date_meta'                => 'Post_Date_Meta',
                'builder_post_social_share'             => 'Post_Social_Share',
                'builder_advance_post_meta'             => 'Advance_Post_Meta',
            );
            foreach ( $builder_blocks as $id => $block ) {
                if ( isset($settings[$id]) && $settings[$id] != 'yes' ) {
                } else {
                    $obj = '\ULTP\blocks\\' . $block;
                    new $obj();
                }
            }
        }
    }


    /**
	 * Blocks Content Start
     * 
     * @since v.1.0.0
	 * @return STRING
	 */
    public function pagination_content_return( $blocks, $paged, $blockId, $blockRaw, $blockName, $builder, $postId, $filterValue, $filterType, $ultp_uniqueIds=[], $ultp_current_unique_posts=[] , $widgetBlockId='', $exclude_post_id = '', $adv_filter_data=[] ) {
        foreach ( $blocks as $key => $value ) {
            if ($blockName == $value['blockName']) {
                if ( $value['attrs']['blockId'] == $blockId ) {
                    $objName = str_replace(' ', '_', ucwords(str_replace( ["ultimate-post/", "-"], ['', ' '], $blockName)) );
                    $new_obj = '\ULTP\blocks\\' . $objName;
                    $objectBlock = new $new_obj();
                    $attr = $objectBlock->get_attributes(true); 

                    // Fix for grid blocks that do not support load more by default (pagination block)
                    if (isset($adv_filter_data['notFirstLoad']) && $adv_filter_data['notFirstLoad']) {
                        $attr['notFirstLoad'] = $adv_filter_data['notFirstLoad'];
                    }
                    
                    $value['attrs']['paged'] = $paged;
                    if ( $builder ) {
                        $value['attrs']['builder'] = $builder;
                    }
                    if ( $postId ) {
                        $attr['current_post'] = $postId;
                        if( get_post_type($postId) == 'ultp_builder' && !$builder ){
                            $attr['current_post'] = $exclude_post_id;
                        }
                    }
                    if ( isset($value['attrs']['queryUnique']) && $value['attrs']['queryUnique'] ) {
                        $value['attrs']['loadMoreQueryUnique'] = $ultp_uniqueIds;
                        $ultp_uniqueIds[$value['attrs']['queryUnique']] = array_diff( $ultp_uniqueIds[$value['attrs']['queryUnique']], $ultp_current_unique_posts );
                        $value['attrs']['savedQueryUnique'] = $ultp_uniqueIds;
                        $value['attrs']['ultp_current_unique_posts'] = $ultp_current_unique_posts;
                    }
                    if( isset($value['attrs']['queryUnique']) && $value['attrs']['queryUnique'] && ( $value['attrs']['paginationType'] == 'loadMore' || $value['attrs']['paginationType'] == 'navigation') && isset($ultp_uniqueIds) && !isset($ultp_current_unique_posts) ) {
                        die();
                    }

                    if( $filterValue ) {
                        $value['attrs']['queryTaxValue'] = $adv_filter_data["is_adv"] ? wp_json_encode($filterValue) : wp_json_encode(array($filterValue));
                        $value['attrs']['queryTax'] = $filterType;
                        $value['attrs']['checkFilter'] = true;
                        $value['attrs']['filterShow'] = $adv_filter_data["filterShow"];
                        $value['attrs']['queryAuthor'] = $adv_filter_data["author"];
                        $value['attrs']['queryOrderBy'] = $adv_filter_data["orderby"];
                        $value['attrs']['queryOrder'] = $adv_filter_data["order"];
                        $value['attrs']['querySearch'] = $adv_filter_data["search"];
                        $value['attrs']['queryQuick'] = $adv_filter_data["adv_sort"];

                        if ($adv_filter_data["is_adv"]) {
                            $value['attrs']['queryRelation'] = 'AND';
                        }
                    }
                    // Exclude Current Post From Pagination
                    if( $exclude_post_id ){
                        $queryArr = json_decode( $value['attrs']['queryExclude']);
                        $queryArr[] = array(
                            'value' => $exclude_post_id,
                            'title' => ''
                        );
                        $value['attrs']['queryExclude'] = wp_json_encode($queryArr);
                    }
                    $attr = array_merge($attr, $value['attrs']);
                    echo  $objectBlock->content($attr, true); //phpcs:ignore
                    die();
                }
            }
            if ( !empty($value['innerBlocks']) ) {
                $this->pagination_content_return($value['innerBlocks'], $paged, $blockId, $blockRaw, $blockName, $builder, $postId, $filterValue, $filterType, $ultp_uniqueIds, $ultp_current_unique_posts, $widgetBlockId, $exclude_post_id, $adv_filter_data);
            }
        }
    }


    /**
	 * Next Preview Callback of the Blocks
     * 
     * @since v.1.0.0
	 * @return STRING
	 */
    public function ultp_next_prev_callback() {
        if ( ! (isset($_REQUEST['wpnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST['wpnonce'])), 'ultp-nonce')) ) {
            return ;
        }

        $paged      = isset($_POST['paged'])?sanitize_text_field($_POST['paged']):'';
        $blockId    = isset($_POST['blockId'])? sanitize_text_field($_POST['blockId']):'';
        $postId     = isset($_POST['postId'])? sanitize_text_field($_POST['postId']):'';
        $blockRaw   = isset($_POST['blockName'])? sanitize_text_field($_POST['blockName']):'';
        $builder    = isset($_POST['builder']) ? sanitize_text_field($_POST['builder']) : '';
        $blockName  = str_replace('_','/', $blockRaw);
        $widgetBlockId  = isset($_POST['widgetBlockId'])?sanitize_text_field($_POST['widgetBlockId']):'';
        $exclude_post_id = isset($_POST['exclude']) ? sanitize_text_field($_POST['exclude']) : '';

        $is_adv = isset($_POST['isAdv'])? ultimate_post()->ultp_rest_sanitize_params($_POST['isAdv']) : false;
        $filterValue = isset($_POST['filterValue']) ? 
            (
                is_array($_POST['filterValue']) ?
                    ultimate_post()->ultp_rest_sanitize_params( $_POST['filterValue'] ) :
                    sanitize_text_field($_POST['filterValue'])
            ) :
            '';

        $filterType  = isset($_POST['filterType'])? sanitize_text_field($_POST['filterType']):'';
        $filterShow  = isset($_POST['filterShow']) ? sanitize_text_field($_POST['filterShow']) : false;
        $checkFilter = isset($_POST['checkFilter']) ? sanitize_text_field($_POST['checkFilter']) : false;
        $author      = isset($_POST['author']) ? sanitize_text_field( $_POST['author'] ) : false;
        $orderby     = isset($_POST['orderby']) ? sanitize_text_field( $_POST['orderby'] ) : 'date';
        $order       = isset($_POST['order']) ? sanitize_text_field( $_POST['order'] ) : 'DESC';
        $search      = isset($_POST['search']) ? sanitize_text_field( $_POST['search'] ) : '';
        $adv_sort    = isset($_POST['adv_sort']) ? sanitize_text_field( $_POST['adv_sort'] ) : '';

        $adv_filter_data = array(
            "is_adv" => filter_var($is_adv, FILTER_VALIDATE_BOOLEAN),
            "filterShow" => filter_var($filterShow, FILTER_VALIDATE_BOOLEAN),
            "checkFilter" => filter_var($checkFilter, FILTER_VALIDATE_BOOLEAN),
            "author" => $author ? wp_json_encode($author) : false,
            "orderby" => $orderby,
            "order" => $order,
            "search" => $search,
            "adv_sort" => $adv_sort,
            "notFirstLoad" => true
        );

        $ultp_uniqueIds = isset($_POST['ultpUniqueIds']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpUniqueIds'])), true) : [];
        $ultp_current_unique_posts = isset($_POST['ultpCurrentUniquePosts']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpCurrentUniquePosts'])), true) : [];

        if( $widgetBlockId ) {
            $blocks = parse_blocks(get_option('widget_block')[$widgetBlockId]['content']);
            $this->pagination_content_return($blocks, $paged, $blockId, $blockRaw, $blockName, $builder, '', $filterValue, $filterType, $ultp_uniqueIds, $ultp_current_unique_posts, $widgetBlockId, '', $adv_filter_data);
        }elseif ($paged && $blockId && $postId && $blockName ) {
            $post = get_post($postId); 
            if( has_blocks($post->post_content) ) {
                $blocks = parse_blocks($post->post_content);
                $this->pagination_content_return($blocks, $paged, $blockId, $blockRaw, $blockName, $builder, $postId, $filterValue, $filterType, $ultp_uniqueIds, $ultp_current_unique_posts, '', $exclude_post_id, $adv_filter_data);
            }
        }
    }

    /**
	 * Filter Callback of the Blocks
     * 
     * @since v.1.0.0
	 * @return STRING
	 */
    public function ultp_filter_callback() {
        if ( ! (isset($_REQUEST['wpnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST['wpnonce'])), 'ultp-nonce')) ) {
            return ;
        }
     
        $taxtype    = isset($_POST['taxtype'])? sanitize_text_field($_POST['taxtype']):'';
        if ( $taxtype ) {
            $blockId    = isset($_POST['blockId'])? sanitize_text_field($_POST['blockId']):'';
            $postId     = isset($_POST['postId'])?sanitize_text_field($_POST['postId']):'';
            $taxonomy   = isset($_POST['taxonomy'])?sanitize_text_field($_POST['taxonomy']):'';
            $blockRaw   = isset($_POST['blockName'])?sanitize_text_field($_POST['blockName']):'';
            $blockName  = str_replace('_','/', $blockRaw);
            $post = get_post($postId); 
            $widgetBlockId  = isset($_POST['widgetBlockId'])? sanitize_text_field($_POST['widgetBlockId']):'';
            $ultp_uniqueIds = isset($_POST['ultpUniqueIds']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpUniqueIds'])), true) : [];
            $ultp_current_unique_posts = isset($_POST['ultpCurrentUniquePosts']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpCurrentUniquePosts'])), true) : [];
            $toReturn = [];
            
            if( $widgetBlockId ) {
                $blocks = parse_blocks(get_option('widget_block')[$widgetBlockId]['content']);
                $data = $this->filter_content_return($blocks, $blockId, $blockRaw, $blockName, $taxtype, $taxonomy, $postId, $toReturn, $widgetBlockId, [], $ultp_uniqueIds, $ultp_current_unique_posts);
            }elseif ( has_blocks($post->post_content) ) {
                $blocks = parse_blocks($post->post_content);
                $data = $this->filter_content_return($blocks, $blockId, $blockRaw, $blockName, $taxtype, $taxonomy, $postId, $toReturn, '', [], $ultp_uniqueIds, $ultp_current_unique_posts);
            }
            return wp_send_json_success( [
                'filteredData' => $data
            ] );
        }
    }

    /**
	 * Advanced Filter Callback of the Blocks
     * 
     * @since v.3.2.4
	 * @return string
	 */
    public function ultp_adv_filter_callback() {
        if ( ! (isset($_REQUEST['wpnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST['wpnonce'])), 'ultp-nonce')) ) {
            return ;
        }
     
        $blockId    = isset($_POST['blockId'])? sanitize_text_field($_POST['blockId']):'';
        $postId     = isset($_POST['postId'])?sanitize_text_field($_POST['postId']):'';

        $taxonomy   = isset($_POST['taxonomy']) ? ultimate_post()->ultp_rest_sanitize_params( $_POST['taxonomy'] ) : '[]';

        $author     = isset($_POST['author']) ? ultimate_post()->ultp_rest_sanitize_params( $_POST['author'] ) : false;
        $orderby    = isset($_POST['orderby']) ? sanitize_text_field( $_POST['orderby'] ) : 'date';
        $order      = isset($_POST['order']) ? sanitize_text_field( $_POST['order'] ) : 'DESC';
        $search     = isset($_POST['search']) ? sanitize_text_field( $_POST['search'] ) : '';
        $adv_sort   = isset($_POST['adv_sort']) ? sanitize_text_field( $_POST['adv_sort'] ) : '';

        $adv_filter_data = array(
            "is_adv" => true,
            "filterShow" => true,
            "checkFilter" => true,
            "author" => $author ? wp_json_encode($author) : false,
            "orderby" => $orderby,
            "order" => $order,
            "search" => $search,
            "adv_sort" => $adv_sort
        );

        $blockRaw   = isset($_POST['blockName'])?sanitize_text_field($_POST['blockName']):'';
        $blockName  = str_replace('_','/', $blockRaw);
        $post = get_post($postId); 
        $widgetBlockId  = isset($_POST['widgetBlockId'])? sanitize_text_field($_POST['widgetBlockId']):'';
        $toReturn = [];

        $ultp_uniqueIds = isset($_POST['ultpUniqueIds']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpUniqueIds'])), true) : [];
        $ultp_current_unique_posts = isset($_POST['ultpCurrentUniquePosts']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpCurrentUniquePosts'])), true) : [];

        if( $widgetBlockId ) {
            $blocks = parse_blocks(get_option('widget_block')[$widgetBlockId]['content']);
            $data = $this->filter_content_return($blocks, $blockId, $blockRaw, $blockName, 'multiTaxonomy', $taxonomy, $postId, $toReturn, $widgetBlockId, $adv_filter_data, $ultp_uniqueIds, $ultp_current_unique_posts);
        }elseif ( has_blocks($post->post_content) ) {
            $blocks = parse_blocks($post->post_content);
            $data = $this->filter_content_return($blocks, $blockId, $blockRaw, $blockName, 'multiTaxonomy', $taxonomy, $postId, $toReturn, '', $adv_filter_data, $ultp_uniqueIds, $ultp_current_unique_posts);
        }
        return wp_send_json_success( [
            'filteredData' => $data
        ] );
    }

    /**
	 * Pagination of the Blocks
     * 
     * @since v.1.0.0
	 * @return STRING
	 */
    public function ultp_pagination_callback() {
        if (! (isset($_REQUEST['wpnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST['wpnonce'])), 'ultp-nonce'))) {
            return ;
        }

        $paged      = isset($_POST['paged'])? sanitize_text_field($_POST['paged']):'';
        if ( $paged ) {
            $blockId    = isset($_POST['blockId'])? sanitize_text_field($_POST['blockId']):'';
            $postId     = isset($_POST['postId'])?sanitize_text_field($_POST['postId']):'';
            $blockRaw   = isset($_POST['blockName'])?sanitize_text_field($_POST['blockName']):'';
            $builder    = isset($_POST['builder']) ? sanitize_text_field($_POST['builder']) : '';
            $blockName  = str_replace('_','/', $blockRaw);
            $post = get_post($postId);
            $widgetBlockId  = isset($_POST['widgetBlockId'])? sanitize_text_field($_POST['widgetBlockId']):'';
            $exclude_post_id = isset($_POST['exclude']) ? sanitize_text_field($_POST['exclude']) : '';

            $is_adv = isset($_POST['isAdv'])? ultimate_post()->ultp_rest_sanitize_params($_POST['isAdv']) : false;
            $filterValue = isset($_POST['filterValue']) ? 
                (
                    is_array($_POST['filterValue']) ?
                        ultimate_post()->ultp_rest_sanitize_params( $_POST['filterValue'] ) :
                        sanitize_text_field($_POST['filterValue'])
                ) :
                '';

            $filterType  = isset($_POST['filterType'])? sanitize_text_field($_POST['filterType']):'';
            $filterShow  = isset($_POST['filterShow']) ? sanitize_text_field($_POST['filterShow']) : false;
            $checkFilter = isset($_POST['checkFilter']) ? sanitize_text_field($_POST['checkFilter']) : false;
            $author      = isset($_POST['author']) ? sanitize_text_field( $_POST['author'] ) : false;
            $orderby     = isset($_POST['orderby']) ? sanitize_text_field( $_POST['orderby'] ) : 'date';
            $order       = isset($_POST['order']) ? sanitize_text_field( $_POST['order'] ) : 'DESC';
            $search      = isset($_POST['search']) ? sanitize_text_field( $_POST['search'] ) : '';
            $adv_sort    = isset($_POST['adv_sort']) ? sanitize_text_field( $_POST['adv_sort'] ) : '';

            $adv_filter_data = array(
                "is_adv" => filter_var($is_adv, FILTER_VALIDATE_BOOLEAN),
                "filterShow" => filter_var($filterShow, FILTER_VALIDATE_BOOLEAN),
                "checkFilter" => filter_var($checkFilter, FILTER_VALIDATE_BOOLEAN),
                "author" => $author ? wp_json_encode($author) : false,
                "orderby" => $orderby,
                "order" => $order,
                "search" => $search,
                "adv_sort" => $adv_sort,
            );


            $ultp_uniqueIds = isset($_POST['ultpUniqueIds']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpUniqueIds'])), true) : [];
            $ultp_current_unique_posts = isset($_POST['ultpCurrentUniquePosts']) ? json_decode(stripslashes(sanitize_text_field($_POST['ultpCurrentUniquePosts'])), true) : [];
            
            if( $widgetBlockId ) {
                $blocks = parse_blocks(get_option('widget_block')[$widgetBlockId]['content']);
                $this->pagination_content_return($blocks, $paged, $blockId, $blockRaw, $blockName, $builder, '', $filterValue, $filterType, $ultp_uniqueIds, $ultp_current_unique_posts, $widgetBlockId, '', $adv_filter_data);
            }elseif( has_blocks($post->post_content) ) {
                $blocks = parse_blocks($post->post_content);
                $this->pagination_content_return($blocks, $paged, $blockId, $blockRaw, $blockName, $builder, $postId, $filterValue, $filterType, $ultp_uniqueIds, $ultp_current_unique_posts, '', $exclude_post_id, $adv_filter_data);
            }
        }
    }

    /**
	 * share Count callback
     * 
     * @since v.1.0.0
	 * @return STRING
	 */
    public function ultp_shareCount_callback() {
        if ( ! (isset($_REQUEST['wpnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_REQUEST['wpnonce'])), 'ultp-nonce')) ) {
            return ;
        }
            $id =isset($_POST['postId'])? sanitize_text_field($_POST['postId']):'';
            $count = isset($_POST['shareCount'])? sanitize_text_field($_POST['shareCount']):'';
            $post_id = $id;
            $new_count = $count+1; 
            update_post_meta($post_id, 'share_count', $new_count);
    }

    /**
	 * Filter Callback of the Blocks
     * 
     * @since v.1.0.0
	 * @return string
	 */
    public function filter_content_return( $blocks, $blockId, $blockRaw, $blockName, $taxtype, $taxonomy, $postId, &$toReturn, $widgetBlockId='', $adv_filter_data=[], $ultp_uniqueIds=[], $ultp_current_unique_posts=[] ) {
        foreach ( $blocks as $key => $value ) {
            if ( $blockName == $value['blockName'] ) {
                if ( $value['attrs']['blockId'] == $blockId ) {
                    $objName = str_replace(' ', '_', ucwords(str_replace( ["ultimate-post/", "-"], ['', ' '], $blockName)) );
                    $new_obj = '\ULTP\blocks\\' . $objName;
                    $objectBlock = new $new_obj();
                    $attr = $objectBlock->get_attributes(true);
                    if ( $taxonomy ) {

                        if (isset($adv_filter_data['is_adv']) && $adv_filter_data['is_adv']) {
                            $value['attrs']['queryTaxValue'] = wp_json_encode($taxonomy);
                            $value['attrs']['queryRelation'] = 'AND';
                            $value['attrs']['queryAuthor'] = $adv_filter_data['author'];
                            $value['attrs']['queryOrderBy'] = $adv_filter_data['orderby'];
                            $value['attrs']['queryOrder'] = $adv_filter_data['order'];
                            $value['attrs']['querySearch'] = $adv_filter_data['search'];
                            $value['attrs']['queryQuick'] = $adv_filter_data['adv_sort'];
                        } else {
                            $value['attrs']['queryTaxValue'] = wp_json_encode(array($taxonomy));
                        }

                        $value['attrs']['queryTax'] = $taxtype;
                        $value['attrs']['ajaxCall'] = true;
                    }
                    if ( isset($value['attrs']['queryNumber']) ) {
                        $value['attrs']['queryNumber'] = $value['attrs']['queryNumber'];
                    }

                    if ( isset($value['attrs']['queryUnique']) && $value['attrs']['queryUnique'] ) {
                        $value['attrs']['loadMoreQueryUnique'] = $ultp_uniqueIds;
                        $ultp_uniqueIds[$value['attrs']['queryUnique']] = array_diff( $ultp_uniqueIds[$value['attrs']['queryUnique']], $ultp_current_unique_posts );
                        $value['attrs']['savedQueryUnique'] = $ultp_uniqueIds;
                        $value['attrs']['ultp_current_unique_posts'] = $ultp_current_unique_posts;
                    }

                    $attr = array_merge($attr, $value['attrs']);

                    $filter_attributes = [];

                    $filter_attributes['isAdv'] = isset($adv_filter_data['is_adv']) ? $adv_filter_data['is_adv'] : false;
                    $filter_attributes['queryTaxValue'] = $filter_attributes['isAdv'] ? wp_json_encode($taxonomy) : $taxonomy;
                    $filter_attributes['queryTax'] = $taxtype;

                    if ($filter_attributes['isAdv']) {
                        $filter_attributes['queryAuthor'] = $adv_filter_data['author'];
                        $filter_attributes['queryOrderBy'] = $adv_filter_data['orderby'];
                        $filter_attributes['queryOrder'] = $adv_filter_data['order'];
                        $filter_attributes['querySearch'] = $adv_filter_data['search'];
                        $filter_attributes['queryQuick'] = $adv_filter_data['adv_sort'];
                    }

                    $toReturn = [
                        'blocks' => $objectBlock->content($attr, true),
                        'pagination' => $this->pagination_for_filter($attr, $postId, $blockRaw, $filter_attributes),
                        'paginationType' => $attr['paginationType'],
                        'paginationShow' => $attr['paginationShow']
                    ];
                }
            }
            if ( !empty($value['innerBlocks']) ) {
                $this->filter_content_return($value['innerBlocks'], $blockId, $blockRaw, $blockName, $taxtype, $taxonomy, $postId, $toReturn, $widgetBlockId, $adv_filter_data, $ultp_uniqueIds, $ultp_current_unique_posts);
            }
        }
        return $toReturn;
    }

    /**
	 * Pagination for filter cal'back
     * 
     * @since v.2.8.9
	 * @return STRING
	 */
    public function pagination_for_filter( $attr, $postId, $blockRaw, $filter_attributes ) {
        $attr['queryNumber'] = ultimate_post()->get_post_number(4, $attr['queryNumber'], $attr['queryNumPosts']);
        $recent_posts = new \WP_Query( ultimate_post()->get_query( $attr ) );
        $pageNum = ultimate_post()->get_page_number($attr, $recent_posts->found_posts);

        $datasets  = ultimate_post()->get_adv_data_attrs(null, $filter_attributes);
        $datasets .= ' data-for="ultp-block-' . sanitize_html_class($attr['blockId']) . '" ';

        $wraper_after = '';
        $style = $pageNum == 1 ? 'style="display:none"' : '';

        if( $attr['paginationType'] == 'loadMore' ) {
            $wraper_after .= '<div '.$style.' class="ultp-loadmore "'.'>';
                $wraper_after .= '<span class="ultp-loadmore-action" tabindex="0" role="button" data-pages="'.$pageNum.'" data-pagenum="1" data-blockid="'.$attr['blockId'].'" data-blockname="'.$blockRaw.'" data-postid="'.$postId.'" '.ultimate_post()->get_builder_attr($attr['queryType']). $datasets.'>'.( isset($attr['loadMoreText']) ? $attr['loadMoreText'] : 'Load More' ).' <span class="ultp-spin">'.ultimate_post()->get_svg_icon('refresh').'</span></span>';
            $wraper_after .= '</div>';
        }
        else if( $attr['paginationType'] == 'navigation' ) {
            $wraper_after .= '<div '.$style.'  class="ultp-next-prev-wrap" data-pages="'.$pageNum.'" data-pagenum="1" data-blockid="'.$attr['blockId'].'" data-blockname="'.$blockRaw.'" data-postid="'.$postId.'" '.ultimate_post()->get_builder_attr($attr['queryType']). $datasets .'>';
                $wraper_after .= ultimate_post()->next_prev();
            $wraper_after .= '</div>';
        }
        else if( $attr['paginationType'] == 'pagination' ) {
            $wraper_after .= '<div class="ultp-pagination-wrap'.($attr["paginationAjax"] ? " ultp-pagination-ajax-action" : "").'" data-paged="1" data-blockid="'.$attr['blockId'].'" data-postid="'.$postId.'" data-pages="'.$pageNum.'" data-blockname="'.$blockRaw.'" '.ultimate_post()->get_builder_attr($attr['queryType']). $datasets .'>';
                $wraper_after .= ultimate_post()->pagination($pageNum, $attr['paginationNav'], $attr['paginationText'], $attr["paginationAjax"], isset($_SERVER['HTTP_REFERER'])?esc_url_raw($_SERVER['HTTP_REFERER']):'');
            $wraper_after .= '</div>';
        }
        wp_reset_query();

        return $wraper_after;
    }
}