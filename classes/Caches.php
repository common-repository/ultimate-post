<?php
/**
 * Cache Designs/Templates/Starter Sites.
 * @package ULTP\Caches
 * @since v.1.0.0
*/

namespace ULTP;
defined('ABSPATH') || exit;

/*
* Caches class.
*/
class Caches {
    
    /*
	 * Setup class.
	 * @since v.1.0.0
	*/
    public function __construct() {
        add_action('rest_api_init', array($this, 'caches_register_rest_route'));
    }

    /**
	 * GET/UPDATE templates/starter sites based on the API action
	*/
	public function caches_register_rest_route() {
        register_rest_route(
			'ultp/v2', 
			'/fetch_premade_data/',
			array(
				array(
					'methods'  => 'POST', 
					'callback' => array( $this, 'fetch_premade_data_callback'),
					'permission_callback' => function () { 
                        return current_user_can( 'edit_others_posts' ); 
                    },
					'args' => array()
				)
			)
        );
    }

    /**
	 * Fetch Premade Data Callback
     * @since 4.0.0
     * @param ARRAY
	 * @return ARRAY | Premade Data ( templates/starter sites )
	*/
    public function fetch_premade_data_callback($request) {
        $post = $request->get_params();
		$type = isset($post['type']) ? ultimate_post()->ultp_rest_sanitize_params($post['type']) : '';

        if ( $type == 'fetch_all_data' ) {
            $this->fetch_all_data_callback([]);
            return [ 'success'=> true, 'message'=> __('Data Fetched!!!', 'ultimate-post') ];
        } else {
            try {
                $upload_dir_url = wp_upload_dir();
                $dir 			= trailingslashit($upload_dir_url['basedir']) . 'ultp/';

                /* sync after 3 days */
                if ( 
                    file_exists(trailingslashit(wp_upload_dir()['basedir']) . 'ultp/starter_lists.json') &&
                    time() - filemtime(trailingslashit(wp_upload_dir()['basedir']) . 'ultp/starter_lists.json') >= 2 * DAY_IN_SECONDS 
                ) {
                    $this->fetch_all_data_callback([]);
                }
                
                if ( $type == 'get_starter_lists_nd_design' ) {
                    return array( 
                        'success' => true,
                        'success2' => is_admin(),
                        'data' => array(
                            "starter_lists" => file_exists( $dir . "starter_lists.json" ) ? 
                                ultimate_post()->get_path_file_contents($dir . "starter_lists.json") 
                                : 
                                $this->reset_premade_json_file('starter_lists'),
                            "design" => file_exists( $dir . "design.json" ) ? 
                                ultimate_post()->get_path_file_contents($dir . "design.json") 
                                : 
                                $this->reset_premade_json_file('design')
                        )
                    );
                } else {
                    $_path = $dir . $type . '.json';
                    return array( 
                        'success' => true,
                        'data' => file_exists( $_path ) ?
                            ultimate_post()->get_path_file_contents($_path) 
                            :
                            $this->reset_premade_json_file($type) 
                    );
                }
            } catch ( \Exception $e ) {
                return [ 'success'=> false, 'message'=> $e->getMessage() ];
            }
        }
    }

    /**
	 * ResetData from API
     * 
     * @since v.2.4.4
     * @param ARRAY
	 * @return ARRAY | Data of the Design
	*/
    public function fetch_all_data_callback($request) {
        $upload = wp_upload_dir();
        $upload_dir = trailingslashit($upload['basedir']) . 'ultp/';

        if ( file_exists($upload_dir . '/template_nd_design.json') ) {
            wp_delete_file($upload_dir . '/template_nd_design.json');
        }
        if ( file_exists($upload_dir . '/premade.json') ) {
            wp_delete_file($upload_dir . '/premade.json');
        }
        if ( file_exists($upload_dir . '/design.json') ) {
            wp_delete_file($upload_dir . '/design.json');
        }
        if ( file_exists($upload_dir . '/starter_lists.json') ) {
            wp_delete_file($upload_dir . '/starter_lists.json');
        }
        $this->reset_premade_json_file('all');
        return array('success' => true, 'message' => __('Data Fetched!!!', 'ultimate-post'));
    }

    /**
	 * Get and save Source Data from the file or API
     * @since v.1.0.0 updated from 4.0.0
     * @param STRING | Type (STRING)
	 * @return ARRAY | Exception Message
	*/
    public function reset_premade_json_file( $type = 'all' ) {
        global $wp_filesystem;
        if (! $wp_filesystem ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }
        WP_Filesystem();

        $file_names = $type == 'all' ? array( 'starter_lists', 'design' ) : array( $type );
        foreach ( $file_names as $key => $name ) {
            if ( $name == 'starter_lists' ) {
                $response = wp_remote_post(
                    'https://postxkit.wpxpo.com/wp-json/importer/site_lists', 
                    array( 
                        'method' => 'POST', 
                        'timeout' => 120,
                        'body' => array(
                            'ultp_ver' => ULTP_VER,
                        )
                    )
                );
            } else {
                $response = wp_remote_post(
                    'https://ultp.wpxpo.com/wp-json/restapi/v2/design', 
                    array( 
                        'method' => 'POST', 
                        'timeout' => 120
                    )
                );
            }
            if ( !is_wp_error( $response ) ) {
                $path_url = $this->create_directory_for_premade( $name );
                $wp_filesystem->put_contents($path_url. $name.'.json', $response['body']);
                if ( $type != 'all' ) {
                    return $response['body'];
                }
            }
        }
    }

    /**
	 * Create a Directory in Upload Folder
     * @since v.1.0.0 updated from 4.0.0
     * @param File_Name
	 * @return STRING | Directory Path
	*/
    public function create_directory_for_premade( $type = '' ) {
        try {
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}
            $upload_dir_url = wp_upload_dir();
			$dir = trailingslashit($upload_dir_url['basedir']) . 'ultp/';
            WP_Filesystem( false, $upload_dir_url['basedir'], true );
            if ( ! $wp_filesystem->is_dir( $dir ) ) {
                $wp_filesystem->mkdir( $dir );
            }
            if ( !file_exists($dir . $type. '.json') ) {
                fopen( $dir . $type. '.json', "w" );     //phpcs:ignore
            }
            return $dir;
        } catch ( \Exception $e ) {
			return [ 'success'=> false, 'message'=> $e->getMessage() ];
        }
    }
}