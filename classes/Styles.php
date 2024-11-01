<?php
/**
 * Styles Add and Style REST API Action.
 * 
 * @package ULTP\Styles
 * @since v.1.0.0
 */
namespace ULTP;

defined('ABSPATH') || exit;

/**
 * Styles class.
 */
class Styles {

	/**
	 * Setup class.
	 *
	 * @since v.1.0.0
	*/
	private $changed_wp_block = '';
    
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_callback' ) );
		add_action( 'wp_ajax_disable_google_font', array( $this, 'disable_google_font_callback' ) );

		add_action( 'admin_init', array( $this, 'postx_global_css_callback' ) );
		add_action( 'admin_init', array( $this, 'postx_global_css_dependancies' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_postx_block_css' ) );
		add_filter( 'render_block', array( $this, 'render_block_callback' ), 10, 2 ); // render block to enqueue corresponding css
		add_action( 'ultp_enqueue_postx_block_css', array( $this, 'ultp_enqueue_postx_block_css_callback' ), 10, 1 ); // action to enqueue the block css
		
		add_action( 'after_delete_post', array( $this, 'ultp_delete_post_callback' ), 10, 2 ); // Delete Plugin Data CSS file delete Action
	}

	/**
	 * REST API Action
     * 
     * @since v.1.0.0
	 * @return NULL
	*/
	public function rest_api_callback() {
		register_rest_route(
			'ultp/v1', 
			'/save_block_css/',
			array(
				array(
					'methods'  => 'POST', 
					'callback' => array( $this, 'save_block_content_css'),
					'permission_callback' => function () {
						return current_user_can( 'publish_posts' );
					},
					'args' => array()
				)
			)
		);
		register_rest_route(
			'ultp/v1',
			'/get_other_post_content/',
			array(
				array(
					'methods'  => 'POST',
					'callback' => array($this, 'get_other_post_content_callback'),
					'permission_callback' => function () {
						return current_user_can('publish_posts');
					},
					'args' => array()
				)
			)
		);
		register_rest_route(
			'ultp/v1',
			'/action_option/',
			array(
				array(
					'methods'  => 'POST',
					'callback' => array($this, 'global_settings_action'),
					'permission_callback' => function () {
						return current_user_can('edit_posts');
					},
					'args' => array()
				)
			)
		);
		register_rest_route(
			'ultp/v1',
			'/postx_presets/',
			array(
				array(
					'methods'  => 'POST',
					'callback' => array($this, 'postx_presets_callback'),
					'permission_callback' => function () {
						return current_user_can('edit_posts');
					},
					'args' => array()
				)
			)
		);
	}

	/**
	 * Save block css corresponding to page id
     * 
     * @since v.1.0.0
	 * @param OBJECT | Request Param of the REST API
	 * @return ARRAY/Exception | Array of the Custom Message
	*/
	public function save_block_content_css($request) {

		$params = $request->get_params();
		$post_id = isset($params['post_id']) ? ultimate_post()->ultp_rest_sanitize_params($params['post_id']) : '';
		$has_block = isset($params['has_block']) ? rest_sanitize_boolean($params['has_block']) : '';
		$is_preview = isset($params['preview']) ? rest_sanitize_boolean($params['preview']) : '';
		$is_widget = $post_id == 'ultp-widget';

		$cap = '';
		if ( $post_id == 'ultp-widget' || get_post_type($post_id) == 'wp_template' || get_post_type($post_id) == 'wp_template_part' ) {
			$cap = 'publish_posts';
		}
		if( !ultimate_post()->permission_check_for_restapi(is_numeric($post_id) ? $post_id : false, $cap ) ) {
			return;
		}
		if ( !empty($params['fseTempId']) ) {
			// Delete previously saved fse css/old compatibility
			$this->ultp_delete_post_callback(str_replace('//', '__', $params['fseTempId']), '');
		}
		try {
			$upload_dir_url = wp_upload_dir();
			$dir = trailingslashit($upload_dir_url['basedir']) . 'ultimate-post/';
			if ( $has_block ) {
				
				$ultp_block_css = $this->set_top_css($params['block_css']);
				if ( $is_preview ) {
					set_transient('_ultp_preview_'.$post_id, $ultp_block_css , 60*60);
					return ['success' => true, 'preview' => true];
				}

				global $wp_filesystem;
				if ( ! $wp_filesystem ) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
				}
				if ( $is_widget ) {
					update_option($post_id, $params['block_css']);
				} else {
					$post_id = (int) $post_id;
					update_post_meta($post_id, '_ultp_active', 'yes');
					update_post_meta($post_id, '_ultp_css', $ultp_block_css);
				}
				ultimate_post()->set_setting('save_version', wp_rand(1, 1000));
				$filename = "ultp-css-{$post_id}.css";

				WP_Filesystem( false, $upload_dir_url['basedir'], true );
				if ( ! $wp_filesystem->is_dir( $dir ) ) {
					$wp_filesystem->mkdir( $dir );
				}
				if ( ! $wp_filesystem->put_contents( $dir . $filename, $ultp_block_css ) ) {
					throw new \Exception(__('CSS can not be saved due to permission!!!', 'ultimate-post')); //phpcs:ignore
				}
				return ['success'=>true, 'message'=> __('PostX css file has been updated.', 'ultimate-post')];

			} else {
				if ( $is_widget ) {
					update_option($post_id, '');
				} else {
					$post_id = (int) $post_id;
					delete_post_meta($post_id, '_ultp_active');
					delete_post_meta($post_id, '_ultp_css');
				}
				$filename = "ultp-css-{$post_id}.css";
				if ( file_exists($dir.$filename) ) {
					wp_delete_file($dir.$filename);
				}
				return ['success' => true, 'message' => __('Data Delete Done', 'ultimate-post')];
			}
		}catch( \Exception $e ) {
			return [ 'success'=> false, 'message'=> $e->getMessage() ];
        }
	}

	/**
	 * Get Post Content for other Posts while performing css save
     * 
     * @since v.4.1.10
	 * @param OBJECT | Request Param of the REST API
	 * @return ARRAY/Exception | Array of the Custom Message
	 */
	public function get_other_post_content_callback($server) {
		$post = $server->get_params();
		$post_id = isset($post['postId']) ? ultimate_post()->ultp_rest_sanitize_params($post['postId']) : '';
		$p_type = get_post_type($post_id);

		if ( 
			$post_id && 
			( 
				'wp_template_part' === $p_type || 
				'wp_block'=== $p_type  || 
				ultimate_post()->permission_check_for_restapi($post_id)
			) 
		) {
			if ( 'wp_block' === $p_type ) {
				$this->handle_wpblock_current_id($post_id);
			}
			return array( 
				'success' => true, 
				'data'=> get_post($post_id)->post_content,
				'message' => __('Data retrive done', 'ultimate-post')
			);
		} else {
			return array(
				'success' => false, 
				'message' => __('Data not found!!', 'ultimate-post')
			);
		}
	}

	
	/**
	 * Get and Set PostX Global Settings
     * 
     * @since v.2.4.24
	 * @param OBJECT | Request Param of the REST API
	 * @return ARRAY | Array of the Custom Message
	 */
	public function global_settings_action($server) {
		$post = $server->get_params();
		$_type = isset($post['type'])?ultimate_post()->ultp_rest_sanitize_params($post['type']):'';
		if ( $_type && ultimate_post()->permission_check_for_restapi() ) {
			if ( $_type == 'set' ) {
				if ( current_user_can('edit_others_posts') ) {
					update_option('postx_global', ultimate_post()->ultp_rest_sanitize_params( $post['data']));
				}
				return ['success' => true];
			} else {
				return ['success' => true, 'data' => get_option('postx_global', [])];
			}
		} else {
			return ['success' => false];
		}
	}

	/**
	 * Get and Set PostX Presets Settings
     * 
     * @since v.2.4.24
	 * @param OBJECT | Request Param of the REST API
	 * @return ARRAY | Array of the Custom Message
	*/
	public function postx_presets_callback($server) {
		$post = $server->get_params();
		$type = isset($post['type']) ? $post['type'] : '';
		$key = isset($post['key']) ? $post['key'] : '';
		$data = isset($post['data']) ? $post['data'] : '';

		if ( $type ) {
			if ( $type == 'set' ) {
				if ( current_user_can('edit_others_posts') ) {
					update_option($key, $data);
				}
				return ['success' => true];
			} else {
				return ['success' => true, 'data' => get_option($key, [])];
			}
		} else {
			return ['success' => false];
		}
	}

	/**
     * Disable Google Font Callback
     *
     * * @since v.2.8.1
     * @return STRING
     */
    public function disable_google_font_callback() {
		if ( 
			!( isset( $_REQUEST['wpnonce'] ) &&
			wp_verify_nonce( sanitize_key( wp_unslash($_REQUEST['wpnonce']) ), 'ultp-nonce' ) ) 
		) {
            return ;
        }
		if( !ultimate_post()->permission_check_for_restapi() ){
			return;
		}
		
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			WP_Filesystem();
		}

		$upload_dir_url = wp_upload_dir();
		$dir = trailingslashit( $upload_dir_url['basedir'] ) . 'ultimate-post/';
		$css_dir = glob( $dir . '*.css' );

		// Custom Font
		$custom_fonts = array();
	    if ( ultimate_post()->get_setting( 'ultp_custom_font' ) == 'true' ) {
            $args = array(
                'post_type'              => 'ultp_custom_font',
                'post_status'            => 'publish',
                'numberposts'            => -1,
                'order'                  => 'ASC'
            );
            $posts = get_posts( $args );
            if ( $posts ) {
                foreach( $posts as $post ) {
                    if ( !empty($post->post_title) ) {
						$custom_fonts[] = $post->post_title;
                    }
                }
            }
        }
		wp_reset_postdata();
        $custom_fonts = implode( '|', $custom_fonts);
		// system font
		$exclude_typo = implode( '|', ['Arial','Tahoma','Verdana','Helvetica','Times New Roman','Trebuchet MS','Georgia'] );

		if ( count( $css_dir ) > 0 ) {
			foreach ( $css_dir as $key => $value ) {
				$css = $wp_filesystem->get_contents( $value );
				$filter_css = preg_replace( '/(@import)[\w\s:\/?=,;.\'()+]*;/m', '', $css ); // Remove Import Font
				$final_css = preg_replace( '/(font-family:)((?!'.$custom_fonts.$exclude_typo.')[\w\s:,\\\'-])*;/mi', '', $filter_css ); // Font Replace Except Default Font
				$wp_filesystem->put_contents( $value, $final_css ); // Update CSS File
			}
		}

		global $wpdb;
		$results = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta WHERE `meta_key`='_ultp_css'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $results ) ) {
			foreach ( $results as $key => $value ) {
				$filter_css = preg_replace('/(@import)[\w\s:\/?=,;.\'()+]*;/m', '', $value->meta_value); // Remove Import Font
				$final_css = preg_replace('/(font-family:)((?!'.$custom_fonts.$exclude_typo.')[\w\s:,\\\'-])*;/mi', '', $filter_css); // Font Replace Except Default Font
				update_post_meta($value->post_id, '_ultp_css', $final_css);
			}
		}
		
		return wp_send_json_success(__('CSS Updated!', 'ultimate-post'));
    }

	/**
	 * Check global style loaded or not
     * 
     * @since 4.0.0
	 * @return NULL
	*/
	public function postx_global_css_dependancies() {
		$this->postx_global_css_callback();
		$wp_styles = wp_styles();
		$style = $wp_styles->query( 'wp-block-library', 'registered' );
		if( !$style ) {
			return;
		}
		$array = ['wpxpo-global-style', 'ultp-preset-colors-style', 'ultp-preset-gradient-style', 'ultp-preset-typo-style' ];
		foreach ($array as $arr) {
			if( wp_style_is( $arr, 'registered' ) && !in_array( $arr, $style->deps, true ) ) {
				$style->deps[] = $arr;
			}
		}
	}

	/**
	 * Set Global Color Codes
     * 
     * @since v.1.0.0
	 * @return NULL
	 */
	public function postx_global_css_callback() {
		// Preset CSS
		$global = get_option('postx_global', []);
		$custom_css = ':root {
			--preset-color1: '.(isset($global['presetColor1'])?$global['presetColor1']:'#037fff').';
			--preset-color2: '.(isset($global['presetColor2'])?$global['presetColor2']:'#026fe0').';
			--preset-color3: '.(isset($global['presetColor3'])?$global['presetColor3']:'#071323').';
			--preset-color4: '.(isset($global['presetColor4'])?$global['presetColor4']:'#132133').';
			--preset-color5: '.(isset($global['presetColor5'])?$global['presetColor5']:'#34495e').';
			--preset-color6: '.(isset($global['presetColor6'])?$global['presetColor6']:'#787676').';
			--preset-color7: '.(isset($global['presetColor7'])?$global['presetColor7']:'#f0f2f3').';
			--preset-color8: '.(isset($global['presetColor8'])?$global['presetColor8']:'#f8f9fa').';
			--preset-color9: '.(isset($global['presetColor9'])?$global['presetColor9']:'#ffffff').';
		}';
		$theme_css = isset($global['globalCSS']) && $global['globalCSS'] ? $global['globalCSS'] : $custom_css.'{}';

		wp_register_style( 'wpxpo-global-style', false, array(), ULTP_VER );
    	wp_enqueue_style( 'wpxpo-global-style' );
		wp_add_inline_style( 'wpxpo-global-style', $theme_css );

		// preset Colors
		$ultpPresetColors = get_option('ultpPresetColors', []);
		
		$rootCSS = ( isset($ultpPresetColors['rootCSS']) && $ultpPresetColors['rootCSS'] ) ? $ultpPresetColors['rootCSS'] : ':root { --postx_preset_Base_1_color: #f4f4ff; --postx_preset_Base_2_color: #dddff8; --postx_preset_Base_3_color: #B4B4D6; --postx_preset_Primary_color: #3323f0; --postx_preset_Secondary_color: #4a5fff; --postx_preset_Tertiary_color: #FFFFFF; --postx_preset_Contrast_3_color: #545472; --postx_preset_Contrast_2_color: #262657; --postx_preset_Contrast_1_color: #10102e; --postx_preset_Over_Primary_color: #ffffff;  }';
		$savedDLMode = ( isset($global['enableDark']) && $global['enableDark'] ) ? 'ultpdark' : 'ultplight';
		$localDLMode = isset($_COOKIE['ultplocalDLMode']) ? $_COOKIE['ultplocalDLMode'] : $savedDLMode;
		
		
		if ( $localDLMode == 'ultplight' && $savedDLMode == 'ultpdark' ) {
			$rootCSS = $this->handle_dark_light_color_switcher($rootCSS);
		} else if ( $localDLMode == 'ultpdark' ) {
			if ( $savedDLMode == 'ultplight' ) {
				$rootCSS = $this->handle_dark_light_color_switcher($rootCSS);
			}
			$rootCSS = $rootCSS.' .ultp-dark-logo.wp-block-site-logo img {content: url("'.get_option('ultp_site_dark_logo', '').'");}';
		}

		wp_register_style( 'ultp-preset-colors-style', false, array(), ULTP_VER );
		wp_enqueue_style( 'ultp-preset-colors-style' );
		wp_add_inline_style( 'ultp-preset-colors-style', $rootCSS );
		
		// preset Gradients
		$ultpPresetGradients = get_option('ultpPresetGradients', []);
		wp_register_style( 'ultp-preset-gradient-style', false, array(), ULTP_VER );
		wp_enqueue_style( 'ultp-preset-gradient-style' );
		wp_add_inline_style( 'ultp-preset-gradient-style', isset($ultpPresetGradients['rootCSS']) && $ultpPresetGradients['rootCSS'] ? $ultpPresetGradients['rootCSS'] : ':root { --postx_preset_Primary_to_Secondary_to_Right_gradient: linear-gradient(90deg, var(--postx_preset_Primary_color) 0%, var(--postx_preset_Secondary_color) 100%); --postx_preset_Primary_to_Secondary_to_Bottom_gradient: linear-gradient(180deg, var(--postx_preset_Primary_color) 0%, var(--postx_preset_Secondary_color) 100%); --postx_preset_Secondary_to_Primary_to_Right_gradient: linear-gradient(90deg, var(--postx_preset_Secondary_color) 0%, var(--postx_preset_Primary_color) 100%); --postx_preset_Secondary_to_Primary_to_Bottom_gradient: linear-gradient(180deg, var(--postx_preset_Secondary_color) 0%, var(--postx_preset_Primary_color) 100%); --postx_preset_Cold_Evening_gradient: linear-gradient(0deg, rgb(12, 52, 131) 0%, rgb(162, 182, 223) 100%, rgb(107, 140, 206) 100%, rgb(162, 182, 223) 100%); --postx_preset_Purple_Division_gradient: linear-gradient(0deg, rgb(112, 40, 228) 0%, rgb(229, 178, 202) 100%); --postx_preset_Over_Sun_gradient: linear-gradient(60deg, rgb(171, 236, 214) 0%, rgb(251, 237, 150) 100%); --postx_preset_Morning_Salad_gradient: linear-gradient(-255deg, rgb(183, 248, 219) 0%, rgb(80, 167, 194) 100%); --postx_preset_Fabled_Sunset_gradient: linear-gradient(-270deg, rgb(35, 21, 87) 0%, rgb(68, 16, 122) 29%, rgb(255, 19, 97) 67%, rgb(255, 248, 0) 100%);  }' );
		
		// preset Typos
		$ultpPresetTypos = get_option('ultpPresetTypos', []);
		wp_register_style( 'ultp-preset-typo-style', false, array(), ULTP_VER );
		wp_enqueue_style( 'ultp-preset-typo-style' );
		wp_add_inline_style( 'ultp-preset-typo-style', isset($ultpPresetTypos['presetTypoCSS']) && $ultpPresetTypos['presetTypoCSS'] ? $ultpPresetTypos['presetTypoCSS'] : ':root { --postx_preset_Heading_typo_font_family: Helvetica; --postx_preset_Heading_typo_font_family_type: sans-serif; --postx_preset_Heading_typo_font_weight: 600; --postx_preset_Heading_typo_text_transform: capitalize; --postx_preset_Body_and_Others_typo_font_family: Helvetica; --postx_preset_Body_and_Others_typo_font_family_type: sans-serif; --postx_preset_Body_and_Others_typo_font_weight: 400; --postx_preset_Body_and_Others_typo_text_transform: lowercase; --postx_preset_body_typo_font_size_lg: 16px; --postx_preset_paragraph_1_typo_font_size_lg: 12px; --postx_preset_paragraph_2_typo_font_size_lg: 12px; --postx_preset_paragraph_3_typo_font_size_lg: 12px; --postx_preset_heading_h1_typo_font_size_lg: 42px; --postx_preset_heading_h2_typo_font_size_lg: 36px; --postx_preset_heading_h3_typo_font_size_lg: 30px; --postx_preset_heading_h4_typo_font_size_lg: 24px; --postx_preset_heading_h5_typo_font_size_lg: 20px; --postx_preset_heading_h6_typo_font_size_lg: 16px; }'  );
	}

	/**
     * Enqueue The Block Style
     *
     * * @since v.4.1.8
     * @return NULL
    */
	public function enqueue_postx_block_css() {
		$this->postx_global_css_callback();
		if ( 
			apply_filters('postx_common_script', false) || 
			isset($_GET['et_fb'])		// divi theme issue
		) {
            ultimate_post()->register_scripts_common();
        }
		if ( is_admin() ) {
			return ;
		}
		if ( wp_is_block_theme() ) {
			$this->handle_old_fse_css();
		}
		$css = '';
		$post_id = ultimate_post()->get_ID();
		if ( isset($_GET['preview_id']) && isset($_GET['preview_nonce']) ) {	// @codingStandardsIgnoreLine
			$css = get_transient('_ultp_preview_'.$post_id, true);
			if ( !$css ) {
				$css = get_post_meta($post_id, '_ultp_css', true);
			}
		}
		do_action('ultp_enqueue_postx_block_css', 
			[
				'post_id' => $post_id,
				'css' => $css,
			]
		);
	}


	/**
     * Enqueue The Block Style based on block( wp_block, fse_template, wp_template, wp_template_part )
     *
     * * @since v.4.1.8
     * @return NULL
    */
	public function render_block_callback($block_content, $block) {
		if ( 
			!is_admin() &&
			isset($block['blockName']) &&
			strpos($block['blockName'], 'ultimate-post/') === 0
			&& !empty($block['attrs']['currentPostId'])
		) {
			do_action('ultp_enqueue_postx_block_css',
				[
					'post_id' => $block['attrs']['currentPostId'],
					'css' => '',
				]
			);
		}
		return $block_content;
	}

	/**
     * Enqueue The Block Style
     *
     * * @since v.4.1.8
     * @return NULL
    */
	public function ultp_enqueue_postx_block_css_callback($data) {
		$post_id =  isset($data['post_id']) ? $data['post_id'] : '';
		$css = isset($data['css']) ? $data['css'] : '';
		if ( wp_style_is("ultp-post-{$post_id}", "enqueued") ) {
			return ;
		}

		if ( $post_id ) {
			if ( $css == '' ) {
				global $wp_filesystem;
				if ( ! $wp_filesystem ) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
				}
				WP_Filesystem();
				$upload_dir_url = wp_upload_dir();
				$_path 			= trailingslashit($upload_dir_url['basedir']) . "ultimate-post/ultp-css-{$post_id}.css";
				$css = '';
				if ( file_exists( $_path ) ) {
					$css = $wp_filesystem->get_contents($_path);
				} else {
					if ( $post_id == 'ultp-widget' ) {
						$css = get_option($post_id, true);
					} else {
						$css = get_post_meta($post_id, '_ultp_css', true);
					}
				}
			}
			if ( $css ) {
				ultimate_post()->register_scripts_common();
				wp_register_style( "ultp-post-{$post_id}", false );
				wp_enqueue_style( "ultp-post-{$post_id}" );
				wp_add_inline_style( "ultp-post-{$post_id}", $css );
			}
		}
	}

	/**
     * Enqueue The Block Style for old saved fse
     *
     * * @since v.4.1.8
     * @return NULL
    */
	public function handle_old_fse_css() {
		global $_wp_current_template_id;
		if ( isset($_wp_current_template_id) ) {
			$template_id = str_replace('//', '__', $_wp_current_template_id);
			$upload_dir_url = wp_upload_dir();
			$_path 			= trailingslashit($upload_dir_url['basedir']) . "ultimate-post/ultp-css-{$template_id}.css";
			if ( file_exists( $_path ) ) {
				do_action('ultp_enqueue_postx_block_css',
					[
						'post_id' => $template_id,
						'css' => '',
					]
				);
			}
		}
	}


	/**
	 * Handle WP Block postid
     * 
     * @since v.4.1.8
	 * @param OBJECT | Request Param of the REST API
	 * @return ARRAY/Exception | Array of the Custom Message
	 */
	public function handle_wpblock_current_id($post_id) {
		$this->changed_wp_block = '';
		$post = get_post($post_id);
		$post_content = $post->post_content;
		
		// Parse the blocks
		$blocks = parse_blocks($post_content);
		$updated_blocks = $this->update_block_attributes_func($blocks, $post_id);
		if ( $this->changed_wp_block ) {
			wp_update_post(array(
				'ID' => $post_id,
				'post_content' => serialize_blocks($updated_blocks)
			));
		}
	}

	/**
	 * Handle WP Block postid save
	 * 
	 * @since v.4.1.8
	 * @param OBJECT | Request Param of the REST API
	 * @return ARRAY/Exception | Array of the Custom Message
	 */
	function update_block_attributes_func($blocks, $post_id) {
		foreach ($blocks as &$block) {
			if ( 
				strpos($block['blockName'], 'ultimate-post/') > -1 &&
				isset($block['attrs']['currentPostId']) && 
				$post_id != $block['attrs']['currentPostId'] 
			) {
				$this->changed_wp_block = true;
				$block['attrs'] = array_merge($block['attrs'], ['currentPostId' => $post_id]);
			}
			// Recursively update inner blocks
			if ( !empty($block['innerBlocks']) ) {
				$block['innerBlocks'] = $this->update_block_attributes_func($block['innerBlocks'], $post_id);
			}
		}
		return $blocks;
	}

	/**
	 * Save Import CSS in the top of the File
     * 
     * @since v.1.0.0
	 * @param STRING | CSS (STRING)
	 * @return STRING | Generated CSS
	 */
	public function set_top_css($get_css = '') {
		$disable_google_font = ultimate_post()->get_setting('disable_google_font');
		if ( $disable_google_font != 'yes' ) {
			$css_url = "@import url('https://fonts.googleapis.com/css?family=";
			$font_exists = substr_count($get_css, $css_url);
			if ( $font_exists ) {
				$pattern = sprintf('/%s(.+?)%s/ims', preg_quote($css_url, '/'), preg_quote("');", '/'));
				if ( preg_match_all($pattern, $get_css, $matches) ) {
					$fonts = $matches[0];
					$get_css = str_replace($fonts, '', $get_css);
					if ( preg_match_all( '/font-weight[ ]?:[ ]?[\d]{3}[ ]?;/' , $get_css, $matche_weight ) ) {
						$weight = array_map( function($val) {
							$process = trim( str_replace( array( 'font-weight',':',';' ) , '', $val ) );
							if (is_numeric( $process )) {
								return $process;
							}
						}, $matche_weight[0] );
						foreach ( $fonts as $key => $val ) {
							$fonts[$key] = str_replace( "');",'', $val ).':'.implode( ',',$weight )."');";
						}
					}
					$fonts = array_unique($fonts);
					$get_css = implode('', $fonts).$get_css;
				}
			}
		}
		return $get_css;
	}


	/**
	 * swap color for dark light
     * 
     * @since 4.0.0
	 * @return NULL
	*/
	public function handle_dark_light_color_switcher( $rootCSS ) {
		$rootCSS = str_replace(
			[ '--postx_preset_Base_1_color', '--postx_preset_Base_2_color', '--postx_preset_Base_3_color' ],
			[ '--postx_preset_Base_1_color_dum', '--postx_preset_Base_2_color_dum', '--postx_preset_Base_3_color_dum' ],
			$rootCSS
		);
		$rootCSS = str_replace(
			[ '--postx_preset_Contrast_1_color',  '--postx_preset_Contrast_2_color','--postx_preset_Contrast_3_color' ],
			[ '--postx_preset_Base_1_color', '--postx_preset_Base_2_color','--postx_preset_Base_3_color' ],
			$rootCSS
		);
		$rootCSS = str_replace(
			[ '--postx_preset_Base_1_color_dum', '--postx_preset_Base_2_color_dum','--postx_preset_Base_3_color_dum' ],
			[ '--postx_preset_Contrast_1_color', '--postx_preset_Contrast_2_color', '--postx_preset_Contrast_3_color' ],
			$rootCSS
		);
		return $rootCSS;
	}


	/**
     * Delete Plugin Data CSS file delete Action
     *
     * * @since v.2.9.8
     * @return STRING
     */
	public function ultp_delete_post_callback( $post_id, $post ) {
		$upload = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$upload_dir_path = $upload_dir . "/ultimate-post/ultp-css-{$post_id}.css";
		if ( file_exists( $upload_dir_path ) ) {
			wp_delete_file( $upload_dir_path );
		}
	}
}