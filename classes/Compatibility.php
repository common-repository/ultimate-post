<?php
/**
 * Compatibility Action.
 * 
 * @package ULTP\Compatibility
 * @since v.1.1.0
*/

namespace ULTP;
defined('ABSPATH') || exit;

/**
 * Compatibility class.
*/
class Compatibility {

    /**
	 * Setup class.
	 *
	 * @since v.1.1.0
	*/
    public function __construct() {
        add_action( 'admin_init', array( $this, 'handle_front_page_builder' ) );

        add_action( 'upgrader_process_complete', array($this, 'plugin_upgrade_completed'), 10, 2 );
        
        // PublishPress Revisions Plugin Compatibility Add
        add_action('revisionary_copy_postmeta', array($this, 'ultp_revisionary_copy_postmeta_callback'), 10, 3);
    }

    /**
	 * Compatibility for Front Page Builder
     * 
	 * @since 4.1.12
	*/
    public function handle_front_page_builder() {
    
        if ( get_option('ultp_frontpage_builder_comp') != "yes" ) {
            $builder_condition = get_option('ultp_builder_conditions', array());
            if ( 
                !empty($builder_condition) &&
                !empty($builder_condition['singular'])
            ) {
                $f_pages = array();
                foreach ( $builder_condition['singular'] as $id => $paths ) {
                    if ( in_array('include/singular/front_page', $paths) ) {
                        $f_pages[$id] = ["include/front_page"]; // Add the ID to the list
                        update_post_meta($id, '__ultp_builder_type', 'front_page');
                        unset($builder_condition['singular'][$id]);
                    }
                }
                if ( empty($builder_condition['front_page']) ) {
                    $builder_condition['front_page'] = $f_pages;
                } else {
                    $builder_condition['front_page'] = $builder_condition['front_page'] + $f_pages;
                }
                update_option("ultp_frontpage_builder_comp", "yes");
                update_option("ultp_builder_conditions", $builder_condition);
            }
        }
    }

    /**
	 * Compatibility for PublishPress Revisions Plugin
	 * @url https://wordpress.org/plugins/revisionary/
     * 
	 * @since v.2.9.8
	*/
    public function ultp_revisionary_copy_postmeta_callback($from_post, $to_post_id, $args) {
        global $wp_filesystem;
        if (! $wp_filesystem ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            WP_Filesystem();
        }
        $css_meta = get_post_meta( $to_post_id, '_ultp_css', true );
        $upload_dir_url = wp_get_upload_dir();
        $upload_css_dir_url = trailingslashit( $upload_dir_url['basedir'] );
        $css_dir_path = $upload_css_dir_url."ultimate-post/ultp-css-{$to_post_id}.css";
        
        if ( file_exists( $css_dir_path ) ) {
            $css = $wp_filesystem->get_contents($css_dir_path);
            if ($css_meta != $css) {
                $wp_filesystem->put_contents( $css_dir_path, $css_meta ); 
            }
        }
    }

    /**
	 * Compatibility Class Run after Plugin Upgrade
	 *
	 * @since v.1.1.0
	*/
    public function plugin_upgrade_completed( $upgrader_object, $options ) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] )) {
            foreach( $options['plugins'] as $plugin ) {
                if ($plugin == ULTP_BASE ) {
                    // License Check And Active
                    if ( defined('ULTP_PRO_VER') ) {
                        $license = get_option( 'edd_ultp_license_key' );
                        $response = wp_remote_post( 
                            'https://account.wpxpo.com',
                            array(
                                'timeout' => 15,
                                'sslverify' => false,
                                'body' => array(
                                    'edd_action' => 'activate_license',
                                    'license'    => $license,
                                    'item_id'    => 181,
                                    'url'        => home_url()
                                )
                            )
                        );
                        if ( !is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
                            $license_data = json_decode( wp_remote_retrieve_body( $response ) );
                            update_option( 'edd_ultp_license_status', $license_data->license );    
                        }
                    }
                    if ( ultimate_post()->get_setting('init_setup') != 'yes' ) {
                        ultimate_post()->set_setting('init_setup', 'yes');
                    }
                }
            }
        }
    }
}