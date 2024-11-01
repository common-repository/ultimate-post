<?php
/**
 * REST API Action.
 * 
 * @package ULTP\REST_API
 * @since v.1.0.0
*/
namespace ULTP;
defined('ABSPATH') || exit;

/**
 * REST_API class.
*/
class REST_API {
    
    /**
	 * Setup class.
	 *
	 * @since v.1.0.0
	*/
    public function __construct() {
        add_action( 'rest_api_init', array($this, 'ultp_register_route') );
    }

    /**
	 * REST API Action
     * 
     * @since v.1.0.0
	 * @return NULL
	*/
    public function ultp_register_route() {
        register_rest_route( 'ultp', 'common_data', array(
                'methods' => \WP_REST_Server::READABLE,
                'args' => array('wpnonce' => []),
                'callback' => array($this,'ultp_route_common_data'),
                'permission_callback' =>  function () {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
        register_rest_route(
			'ultp',
			'/fetch_posts/',
			array(
				array(
					'methods'  => 'POST',
                    'args' => array(),
                    'callback' => array($this, 'ultp_route_post_data'),
                    'permission_callback' =>  function () {
						return current_user_can( 'edit_posts' );
					},
				)
			)
		);
        register_rest_route(
			'ultp',
			'/specific_taxonomy/',
			array(
				array(
					'methods'  => 'POST',
                    'args' => array(),
                    'callback' => array($this, 'ultp_route_taxonomy_info_data'),
                    'permission_callback' =>  function () {
						return current_user_can( 'edit_others_posts' );
					},
				)
			)
		);
        register_rest_route(
			'ultp/v1',
			'/search/',
			array(
				array(
					'methods'  => 'POST',
					'callback' => array($this, 'search_settings_action'),
					'permission_callback' => function () {
						return current_user_can('edit_others_posts');
					},
					'args' => array()
				)
			)
		);
        register_rest_route(
			'ultp/v2',
			'/premade_wishlist_save/',
			array(
				array(
					'methods'  => 'POST',
					'callback' => array($this, 'premade_wishlist_save'),
					'permission_callback' => function () {
						return current_user_can('edit_others_posts');
					},
					'args' => array()
				)
			)
		);
        register_rest_route(
			'ultp',
			'/ultp_search_data/',
			array(
				array(
					'methods'  => 'POST',
					'callback' => array($this, 'ultp_search_result'),
                    'permission_callback' => '__return_true'
				)
			)
		);
        register_rest_route(
			'ultp/v2', 
			'/custom_tax/',
			array(
				array(
					'methods'  => 'POST', 
					'callback' => array( $this, 'custom_tax_callback'),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'args' => array()
				)
			)
        );
        register_rest_route(
			'ultp/v2', 
			'/init_site_dark_logo/',
			array(
				array(
					'methods'  => 'POST', 
					'callback' => array( $this, 'init_site_dark_logo_callback'),
					'permission_callback' => function () {
						return current_user_can( 'edit_others_posts' );
					},
					'args' => array()
				)
			)
        );
        register_rest_route(
			'ultp/v2', 
			'/get_ultp_image_size/',
			array(
				array(
					'methods'  => 'POST', 
					'callback' => array( $this, 'get_custom_image_size'),
                    'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
					'args' => array()
				)
			)
        );
    }


    /**
	 * Save and get premade_wishlist_save
     * 
     * @since v.3.0.0
     * @param STRING
	 * @return ARRAY | Inserted Post Url 
	*/
    public function premade_wishlist_save($server) {
        $post = $server->get_params();
        $id = isset($post['id'])? ultimate_post()->ultp_rest_sanitize_params($post['id']):'';
        $action = isset($post['action'])? ultimate_post()->ultp_rest_sanitize_params($post['action']):'';
        $wishListArr = get_option('ultp_premade_wishlist', []);
        $request_type = isset($post['type'])?ultimate_post()->ultp_rest_sanitize_params($post['type']):'';

        if ($id && $request_type != 'fetchData') {
            if($action == 'remove') {
                $index = array_search($id, $wishListArr);
                if ($index !== false) {
                    unset($wishListArr[$index]);
                }
            } else {
                if (!in_array($id, $wishListArr)) {
                    array_push($wishListArr,  $id );
                }
            }
            update_option('ultp_premade_wishlist', $wishListArr);
        }
        return rest_ensure_response([
            'success' => true, 
            'message' => $action == 'remove' ? __('Item has been removed from wishlist.', 'ultimate-post') : __('Item added to wishlist.', 'ultimate-post'),
            'wishListArr' => wp_json_encode($wishListArr)]
        );
    }
    
    

    public function search_settings_action($server) {
		global $wpdb;
        $post = $server->get_params();
        $request_type = isset($post['type'])?ultimate_post()->ultp_rest_sanitize_params($post['type']):'';
        $condition_type = isset($post['condition'])?ultimate_post()->ultp_rest_sanitize_params($post['condition']):'';
        $term_type = isset($post['term'])?ultimate_post()->ultp_rest_sanitize_params( $post['term'] ):'';
        switch ($request_type) {
            case 'posts':
            case 'allpost':
            case 'postExclude':
                $post_type = array('post');
                if ($request_type == 'allpost') {
                    $post_type = array_keys(ultimate_post()->get_post_type());
                } else if ($request_type == 'postExclude') {
                    $post_type = array($condition_type);
                }
                $args = array(
                    'post_type'         => $post_type,
                    'post_status'       => 'publish',
                    'posts_per_page'    => 10,
                );
                if (is_numeric($term_type)) {
                    $args['p'] = $term_type;
                } else {
                    $args['s'] = $term_type;
                }

                $post_results = new \WP_Query($args);
                $data = [];
                if (!empty($post_results)) {
                    while ( $post_results->have_posts() ) {
                        $post_results->the_post();
                        $id = get_the_ID();
                        $title = html_entity_decode(get_the_title());
                        $data[] = array('value'=>$id, 'title'=>($title?'[ID: '.$id.'] '.$title:('[ID: '.$id.']')));
                    }
                    wp_reset_postdata();
                }
                return ['success' => true, 'data' => $data];
                break;

            case 'author':
                $term = '%'. $wpdb->esc_like( $term_type ) .'%';
                $post_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare(
                        "SELECT ID, display_name 
                        FROM $wpdb->users 
                        WHERE user_login LIKE %s OR ID LIKE %s OR user_nicename LIKE %s OR user_email LIKE %s OR display_name LIKE %s LIMIT 10", $term, $term, $term, $term, $term 
                    )
                );
                $data = [];
                if (!empty($post_results)) {
                    foreach ($post_results as $key => $val) {
                        $data[] = array('value'=>$val->ID, 'title'=>'[ID: '.$val->ID.'] '.$val->display_name);
                    }
                }
                return ['success' => true, 'data' => $data];
                break;

            case 'taxvalue':
                $split = explode('###', $condition_type);
                $condition = $split[1] != 'multiTaxonomy' ? array($split[1]) : get_object_taxonomies($split[0]);
                $args = array(
                    'taxonomy'  => $condition,
                    'fields'    => 'all',
                    'orderby'   => 'id', 
                    'order'     => 'ASC',
                    'name__like'=> $term_type
                );
                if (is_numeric($term_type)) {
                    unset($args['name__like']);
                    $args['include'] = array($term_type);
                }

                $post_results = get_terms( $args );
                $data = [];
                if (!empty($post_results)) {
                    foreach ($post_results as $key => $val) {
                        if ($split[1] == 'multiTaxonomy') {
                            $data[] = array('value'=>$val->taxonomy.'###'.$val->slug, 'title'=> '[ID: '.$val->term_id.'] '.$val->taxonomy.': '.$val->name);
                        } else {
                            $data[] = array('value'=>urldecode($val->slug), 'title'=>'[ID: '.$val->term_id.'] '.$val->name, 'live_title'=> $val->name);
                        }
                    }
                }
                return ['success' => true, 'data' => $data];
                break;

            case 'taxExclude':
                $condition = get_object_taxonomies($condition_type);
                $args = array(
                    'taxonomy'  => $condition,
                    'fields'    => 'all',
                    'orderby'   => 'id', 
                    'order'     => 'ASC',
                    'name__like'=> $term_type
                ); 
                if (is_numeric($term_type)) {
                    unset($args['name__like']);
                    $args['include'] = array($term_type);
                }
                $post_results = get_terms( $args );
                $data = [];
                if (!empty($post_results)) {
                    foreach ($post_results as $key => $val) {
                        $data[] = array('value'=>$val->taxonomy.'###'.$val->slug, 'title'=> '[ID: '.$val->term_id.'] '.$val->taxonomy.': '.$val->name);
                    }
                }
                return ['success' => true, 'data' => $data];
                break;
                // allPostType
            case 'allPostType': 
                $all_types = array_values(get_post_types( array( 'public' => true ), 'names' ));
                $postType = array();
                foreach($all_types as $type){
                    $postType[] = array(
                        'title' => $type,
                        'value' => $type,
                    );
                };

                return ['success' => true, 'data' => $postType ];
            default:
                return ['success' => true, 'data' => [['value'=>'', 'title'=>'- Select -']]];
                break;
        }
	}

    /**
	 * Post Data Response of REST API
     * 
     * @since v.1.0.0
     * @param MIXED | Pram (ARRAY), Local (BOOLEAN)
	 * @return ARRAY | Response Image Size as Array
	*/
    public function ultp_route_post_data($prams) {
        $prams = $prams->get_params();
        if ( !(isset($prams['wpnonce']) && wp_verify_nonce( sanitize_key(wp_unslash($prams['wpnonce'])), 'ultp-nonce' )) ) {
			die();
		}
        $data = [];
        $loop = new \WP_Query( ultimate_post()->get_query(ultimate_post()->ultp_rest_sanitize_params($prams)) );
        $max_tax = isset($prams['maxTaxonomy']) && $prams['maxTaxonomy'] ? ( ultimate_post()->ultp_rest_sanitize_params($prams['maxTaxonomy']) == '0' ? 0 : ultimate_post()->ultp_rest_sanitize_params($prams['maxTaxonomy']) ) : 30 ;

        if ($loop->have_posts()) {
            while($loop->have_posts()) {
                $loop->the_post(); 
                $var                = array();
                $post_id            = get_the_ID();
                $user_id            = get_the_author_meta('ID');
                $content_data       = get_the_content();
                $var['ID']          = $post_id;
                $var['title']       = get_the_title();
                $var['permalink']   = get_permalink();
                $var['seo_meta']    = ultimate_post()->get_excerpt($post_id, 1);
                $var['excerpt']     = wp_strip_all_tags(get_the_excerpt());
                $var['excerpt_full']= wp_strip_all_tags(get_the_excerpt());
                $var['time']        = (int)get_the_date('U')*1000;
                $var['timeModified']= (int)get_the_modified_date('U')*1000;
                $var['post_time']   = human_time_diff(get_the_time('U'),current_time('U'));
                $var['view']        = get_post_meta(get_the_ID(),'__post_views_count', true);
                $var['comments']    = get_comments_number();
                $var['author_link'] = get_author_posts_url($user_id);
                $var['avatar_url']  = get_avatar_url($user_id);
                $var['display_name']= get_the_author_meta('display_name');
                $var['reading_time']= ceil(strlen($content_data)/1200);
                $var['acf']         = null;

                if (function_exists('get_field_objects')) {
                    $var['acf']         = get_field_objects();
                }

                $post_video = get_post_meta($post_id, '__builder_feature_video', true);
                // Video 
                if ($post_video) {
                    $var['has_video'] = ultimate_post()->get_youtube_id($post_video);
                }
                // image
                $image_sizes = ultimate_post()->get_image_size();
                $image_src = array();
                if (has_post_thumbnail()) {
                    $thumb_id = get_post_thumbnail_id($post_id);
                    foreach ($image_sizes as $key => $value) {
                        $image_src[$key] = wp_get_attachment_image_src($thumb_id, $key, false)[0];
                    }
                    $var['image'] = $image_src;
                } elseif(isset($prams['fallbackImg']['id'])) {
                    foreach ($image_sizes as $key => $value) {
                        $image_src[$key] = wp_get_attachment_image_src(esc_attr($prams['fallbackImg']['id']), $key, false)[0];
                    }
                    $var['image'] = $image_src;
                    $var['is_fallback'] = true;
                }

                // tag
                $tag = get_the_terms($post_id, (isset($prams['tag'])?esc_attr($prams['tag']):'post_tag'));
                if (!empty($tag)) {
                    $v = array();
                    foreach ($tag as $k => $val) {
                        if ($k >= $max_tax) { break; }
                        $v[] = array('slug' => $val->slug, 'name' => $val->name, 'url' => get_term_link($val->term_id));
                    }
                    $var['tag'] = $v;
                }

                // Taxonomy
                $cat = get_the_terms($post_id, (isset($prams['taxonomy'])?esc_attr($prams['taxonomy']):'category'));

                if(!empty($cat)){
                    $v = array();
                    foreach ($cat as $k => $val) {
                        if ($k >= $max_tax) { break; }
                        $v[] = array('slug' => $val->slug, 'name' => $val->name, 'url' => get_term_link($val->term_id), 'color' => get_term_meta($val->term_id, 'ultp_category_color', true));
                    }
                    $var['category'] = $v;
                }
                $data[] = $var;
            }
            wp_reset_postdata();
        }
        return rest_ensure_response( $data);
    }


    /**
	 * Taxonomy Data Response of REST API
     * 
     * @since v.1.0.0
     * @param ARRAY | Parameter (ARRAY)
	 * @return ARRAY | Response Taxonomy List as Array
	*/
    public function ultp_route_common_data($prams) {
        if ( ! (isset($_REQUEST['wpnonce']) && wp_verify_nonce( sanitize_key(wp_unslash($_REQUEST['wpnonce'])), 'ultp-nonce' )) ) {
            return rest_ensure_response([]);
		}
        
        $all_post_type = ultimate_post()->get_post_type();
        $data = array();
        foreach ($all_post_type as $post_type_slug => $post_type ) {
            $data_term = array();
            $taxonomies = get_object_taxonomies($post_type_slug);
            foreach ($taxonomies as $key => $taxonomy_slug) {
                $taxonomy_value = get_terms(array(
                    'taxonomy' => $taxonomy_slug,
                    'hide_empty' => false
                ));
                if (!is_wp_error($taxonomy_value)) {
                    $data_tax = array();
                    foreach ($taxonomy_value as $k => $taxonomy) {
                        $data_tax[urldecode_deep($taxonomy->slug)] = $taxonomy->name;
                    }
                    if (count($data_tax) > 0) {
                        $data_term[$taxonomy_slug] = $data_tax;
                    }
                }
            }
            $data[$post_type_slug] = $data_term;
        }
        // Global Customizer
        $global = get_option('postx_global', []);
        // Image Size
        $image_sizes = ultimate_post()->get_image_size();

        return rest_ensure_response(['taxonomy' => $data, 'global' => $global, 'image' => wp_json_encode($image_sizes), 'posttype' => wp_json_encode($all_post_type)]);
    }

    /**
	 * Specific Taxonomy Data Response of REST API
     * 
     * @since v.1.0.0
     * @param ARRAY | Parameter (ARRAY)
	 * @return ARRAY | Response Taxonomy List as Array
	 */
    public function ultp_route_taxonomy_info_data($prams) {
        $prams = $prams->get_params();
        if ( ! (isset($prams['wpnonce']) && wp_verify_nonce( sanitize_key(wp_unslash($prams['wpnonce'])), 'ultp-nonce' )) ) {
            return rest_ensure_response([]);
		}
        $taxValue = isset($prams['taxValue'])?ultimate_post()->ultp_rest_sanitize_params($prams['taxValue']):'';
        $queryNumber = isset($prams['queryNumber'])?ultimate_post()->ultp_rest_sanitize_params($prams['queryNumber']):'';
        $taxType = isset($prams['taxType'])?ultimate_post()->ultp_rest_sanitize_params($prams['taxType']):'';
        $taxSlug = isset($prams['taxSlug'])?ultimate_post()->ultp_rest_sanitize_params($prams['taxSlug']):'';
        $archiveBuilder = isset($prams['archiveBuilder'])?ultimate_post()->ultp_rest_sanitize_params($prams['archiveBuilder']):'';

        return rest_ensure_response( ultimate_post()->get_category_data(json_decode($taxValue), $queryNumber, $taxType, $taxSlug,  $archiveBuilder) );
    }

    /**
	 * Get Taxonomies for Custom Post Type
     * 
     * @since v.3.2.8
     * @param array $params
	 * @return array
	 */
    public function custom_tax_callback($prams) {

        $post_types = isset($prams['postTypes']) ? ultimate_post()->ultp_rest_sanitize_params($prams['postTypes']) : array();

        $data = array(
            array(
                'id' => '_all',
                'name' => __('All', 'ultimate-post')
            )
        );

        foreach ($post_types as $post_type) {
            $taxonomies = get_object_taxonomies($post_type);
            foreach ($taxonomies as $taxonomy) {
                $terms = get_terms(array(
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false
                ));
                foreach ($terms as $term) {
                    $data[] = array(
                        'id' => $term->slug,
                        'name' => $term->name
                    );
                }
            }
        }

        return rest_ensure_response($data);
    }

    /**
	 * Search Block Data Showing
     * 
     * @since v.2.9.9
     * @param STRING
	 * @return ARRAY | Inserted Post Url 
	*/
    public function ultp_search_result($server) {
        $post = $server->get_params();
        $searchText = isset($post['searchText'])?ultimate_post()->ultp_rest_sanitize_params($post['searchText']):'';
        $paged = isset($post['paged'])?ultimate_post()->ultp_rest_sanitize_params($post['paged']):'';
        $postPerPage = isset($post['postPerPage'])?ultimate_post()->ultp_rest_sanitize_params($post['postPerPage']):'';
        $query_args = array(
            's' => $searchText,
            'paged' =>  $paged, 
            'compare' => 'LIKE',
            'orderby' => 'relevance',
            'posts_per_page' => $postPerPage,
        );
        if(isset($post['exclude']) && is_array($post['exclude']) && count($post['exclude']) > 0) {
            $post['exclude'] = ultimate_post()->ultp_rest_sanitize_params($post['exclude']);
            $post_exclude = array();
            foreach( $post['exclude'] as $data ){
                $post_exclude[$data['title']] = $data['title'];
            }
            $all_types = get_post_types( array( 'public' => true ), 'names' );
            $post_type = array_diff_key($all_types, $post_exclude);
            $query_args['post_type'] = $post_type;
        }
        $output = '';
        $query_result = new \WP_Query($query_args);

        if ($query_result->have_posts()) {
            while ($query_result->have_posts()) {
                $query_result->the_post(); 
                $post_id = get_the_ID();
                $title = get_the_title();
                
                $output .= '<div class="ultp-search-result__item">';
                    if ($post['image'] == 1 && has_post_thumbnail()) {
                        $thumb_id = get_post_thumbnail_id($post_id);
                        $output .= '<img class="ultp-searchresult-image" src='.wp_get_attachment_image_src($thumb_id, 'thumbnail', false)[0].' alt="'.$title.'"/>';
                    }
                    $output .= '<div class="ultp-searchresult-content">';
                        $output .= '<div class="ultp-rescontent-meta">';
                            // Category
                            $post_cat = get_the_terms($post_id, 'category');
                            if ($post['category'] == 1 && $post_cat && count($post_cat)) {
                                $output .= '<div class="ultp-searchresult-category">';
                                    foreach($post_cat as $cat){
                                        $output .= '<a href="'.get_term_link($cat->term_id).'">'.$cat->name.'</a>';
                                    }
                                $output .= '</div>';
                            }
                            // Author
                            if ($post['author'] == 1) {
                                $user_id = get_the_author_meta('ID');
                                $output .= '<a href="'.get_author_posts_url($user_id).'" class="ultp-searchresult-author">'.get_the_author_meta('display_name').'</a>';
                            }
                            // Date
                            if ($post['date'] == 1) {
                                $output .= '<div class="ultp-searchresult-publishdate">'.get_the_date('F j, Y').'</div>';
                            }
                        $output .= '</div>';
                        $output .= '<a href="'.get_permalink().'" class="ultp-searchresult-title">'.$title.'</a>';
                        if ($post['excerpt'] == 1) {
                            $output .= '<div class="ultp-searchresult-excerpt">'.wp_trim_words(get_the_excerpt(), isset($post['excerptLimit'])?ultimate_post()->ultp_rest_sanitize_params($post['excerptLimit']):55).'</div>';
                        }
                    $output .= '</div>';
                $output .= '</div>';
            }
        }
        
        return array('post_data' => $output, 'post_count' => $query_result->found_posts);
    }
    
    /**
	 * PostX Site Dark Logo Init
     * 
     * @since v.3.1.9
     * @param ARRAY 
	 * @return BOOOLEAN | Inserted Post Url 
	*/
    public function init_site_dark_logo_callback ($server) {
        $logo_data = $server->get_params();
        $success = true;
        if( isset($logo_data['logo']['url'] ) ){
            update_option( 'ultp_site_dark_logo', $logo_data['logo']['url'] );
        } else {
            $success = false;
        }
        return rest_ensure_response([
            'success' => $success,
        ]);
    }

    
    /**
	 * Getting Image Size
     * 
     * @since v.4.0.1     
     * @param ARRAY | Parameter (number)
	 * @return ARRAY | Image Size List as Array
	*/
    public function get_custom_image_size($server) {
        $img = $server->get_params();
        $image_src = array();
        $image_sizes = ultimate_post()->get_image_size();
        foreach ($image_sizes as $key => $value) {
            $image_src[$key] = wp_get_attachment_image_src($img['id'], $key, false)[0];
        }
        return rest_ensure_response( ['success' => true, 'size' => $image_src, 'id' => $img  ]);
    }
}