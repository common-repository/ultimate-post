<?php

namespace ULTP;

use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *
 * Dynamic Content
 *
 * @package ULTP\Addons
 * @since 4.1.1
 */
final class DCController {

	/**
	 * Setup class.
	 *
	 * @since v.4.1.1
	 */
	public function __construct() {
		require_once ULTP_PATH . '/addons/dynamic_content/includes/DCService.php';

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			'ultp/v2',
			'/get_dynamic_content/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_dynamic_data' ),
					'permission_callback' => '__return_true',
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			'ultp/v2',
			'/get_custom_fields/',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_custom_fields' ),
					'permission_callback' => '__return_true',
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Dynamic data to show in the editor
	 *
	 * @since v.4.1.1
	 * @param WP_REST_Request $server
	 * @return WP_REST_Response
	 */
	public function handle_dynamic_data( $server ) {
		$args      = $server->get_params();
		$post_id   = isset( $args['post_id'] ) ? intval( $args['post_id'] ) : '';
		$key       = isset( $args['key'] ) ? $args['key'] : '';
		$data_type = isset( $args['data_type'] ) ? $args['data_type'] : '';

		return rest_ensure_response(
			array(
				'data' => DCService::get_dynamic_data(
					array(
						'post_id'   => $post_id,
						'key'       => $key,
						'data_type' => $data_type,
					)
				),
			)
		);
	}

	/**
	 * Gets custom fields keys and labels
	 *
	 * @since v.4.1.1
	 * @param WP_REST_Request $server
	 * @return WP_REST_Response
	 */
	public function handle_custom_fields( $server ) {
		$args = $server->get_params();

		$post_id    = isset( $args['post_id'] ) ? intval( $args['post_id'] ) : 0;
		$post_type  = isset( $args['post_type'] ) ? $args['post_type'] : '';
		$field_type = isset( $args['acf_field_type'] ) ? $args['acf_field_type'] : '';

		$res = array();

		// All metas
		$all_custom_metas = $this->get_custom_metas( $post_id, $post_type );

		// ACF
		$acf_field_keys = $this->get_acf_fields( $post_id, $field_type, $post_type );

		// Meta Box
		$mb_field_keys = $this->get_mb_fields( $post_id, $field_type, $post_type );

		// Pods
		$pods_field_keys = $this->get_pods_fields( $post_id, $field_type, $post_type );

		$filtered_custom_metas = array_values( array_diff( $all_custom_metas, $acf_field_keys['_fields'] ) );
		$filtered_custom_metas = array_filter(
			$filtered_custom_metas,
			function ( $item ) use ( $acf_field_keys ) {
				foreach ( $acf_field_keys['prefixes'] as $prefix ) {
					if ( str_starts_with( $item, $prefix ) ) {
						return false;
					}
				}
				return true;
			}
		);
		$filtered_custom_metas = array_values( array_diff( $filtered_custom_metas, $mb_field_keys['_fields'] ) );
		$filtered_custom_metas = array_values( array_diff( $filtered_custom_metas, $pods_field_keys['_fields'] ) );

		// Return value
		$res['custom_metas'] = array(
			'fields' => array_map(
				function ( $item ) {
					return array(
						'label' => $item,
						'value' => $item,
					);
				},
				$filtered_custom_metas
			),
		);

		$res['acf'] = array(
			'fields' => $acf_field_keys['fields'],
		);

		$res['mb'] = array(
			'fields' => $mb_field_keys['fields'],
		);

		$res['pods'] = array(
			'fields' => $pods_field_keys['fields'],
		);

		return rest_ensure_response(
			array(
				'data' => $res,
			)
		);
	}

	/**
	 * Get ACF fields
	 *
	 * @param int    $post_id
	 * @param string $field_type
	 * @param string $post_type
	 *
	 * @return array
	 * @since 4.1.1
	 */
	public function get_acf_fields( $post_id, $field_type, $post_type ) {
		$res = array(
			'fields'   => array(),
			'_fields'  => array(),
			'prefixes' => array(),
		);

		if ( ! class_exists( 'ACF' ) ) {
			return $res;
		}

		if ( empty( $post_id ) && empty( $post_type ) ) {
			global $post;
			$post_id = isset( $post->ID ) ? $post->ID : '';
		}

		$allowed_types = $this->get_allowed_acf_field_types( $field_type );

		// If post_id set set, return ACF fields for that post
		if ( ! empty( $post_id ) ) {
			$fields = get_field_objects( $post_id );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( in_array( $field['type'], $allowed_types, true ) ) {
						$res['fields'][] = array(
							'value' => $field['name'],
							'label' => $field['label'],
						);
					}

					$res['_fields'][] = $field['name'];

					// For filtering out pesky repeater field's sub fields
					if ( $field['type'] === 'repeater' ) {
						$res['prefixes'][] = $field['name'];
					}
				}
			}
		} elseif ( ! empty( $post_type ) ) {
			$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
			foreach ( $groups as $group ) {
				$fields = acf_get_fields( $group['ID'] );
				if ( is_array( $fields ) ) {
					foreach ( $fields as $field ) {
						if ( in_array( $field['type'], $allowed_types, true ) ) {
							$res['fields'][] = array(
								'value' => $field['name'],
								'label' => $field['label'] . ' [' . $group['title'] . ']',
							);
						}
						$res['_fields'][] = $field['name'];

						if ( $field['type'] === 'repeater' ) {
							$res['prefixes'][] = $field['name'];
						}
					}
				}
			}
		}

		return $res;
	}

	/**
	 * Get Meta Box fields
	 *
	 * @param int    $post_id
	 * @param string $field_type
	 * @param string $post_type
	 *
	 * @return array
	 * @since 4.1.1
	 */
	public function get_mb_fields( $post_id, $field_type, $post_type ) {
		$res = array(
			'fields'   => array(),
			'_fields'  => array(),
			'prefixes' => array(),
		);

		if ( ! function_exists( 'rwmb_get_field_settings' ) ) {
			return $res;
		}

		$value = null;

		if ( empty( $post_id ) && empty( $post_type ) ) {
			global $post;
			$value = isset( $post->ID ) ? $post->ID : '';
		} elseif ( ! empty( $post_id ) ) {
			$value = $post_id;
		} elseif ( ! empty( $post_type ) ) {
			$value = $post_type;
		}

		if ( empty( $value ) ) {
			return $res;
		}

		$allowed_types = self::get_allowed_mb_field_type( $field_type );

		$fields = rwmb_get_object_fields( $value );

		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( in_array( $field['type'], $allowed_types, true ) ) {
					$res['fields'][] = array(
						'value' => $field['id'],
						'label' => $field['name'],
					);
				}

				$res['_fields'][] = $field['id'];
			}
		}

		return $res;
	}

	/**
	 * Get Pods fields
	 *
	 * @param int    $post_id
	 * @param string $field_type
	 * @param string $post_type
	 *
	 * @return array
	 * @since 4.1.1
	 */
	public function get_pods_fields( $post_id, $field_type, $post_type ) {
		$res = array(
			'fields'   => array(),
			'_fields'  => array(),
			'prefixes' => array(),
		);

		if ( ! function_exists( 'pods' ) ) {
			return $res;
		}

		if ( empty( $post_id ) ) {
			global $post;
			$post_id = isset( $post->ID ) ? $post->ID : '';
		}

		if ( empty( $post_type ) ) {
			$post_type = get_post_type( $post_id );
		}

		if ( empty( $post_type ) ) {
			return $res;
		}

		$allowed_types = self::get_allowed_pods_field_types( $field_type );

		$pods = pods( $post_type, $post_id );
		if ( is_object( $pods ) && method_exists( $pods, 'exists' ) && $pods->exists() && method_exists( $pods, 'fields' ) ) {
			$fields = $pods->fields();
			if ( is_array( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( in_array( $field['type'], $allowed_types, true ) &&
						'field' === $field['object_type'] &&
						'post_type' === $field['object_storage_type']
					) {
						$res['fields'][] = array(
							'value' => $field['name'],
							'label' => $field['label'],
						);
					}

					$res['_fields'][] = $field['name'];
				}
			}
		}

		return $res;
	}

	/**
	 * Get ACF allowed field types
	 *
	 * @param string $type optional, possible values 'text'|'image'|'url'.
	 *
	 * @return array
	 * @since 4.1.1
	 */
	public static function get_allowed_acf_field_types( $type = 'text' ) {

		if ( 'image' === $type ) {
			return array(
				'image',
			);
		} elseif ( 'url' === $type ) {
			return array(
				'text',
				'email',
				'image',
				'file',
				'page_link',
				'url',
				'link',
			);
		}

		return array(
			'text',
			'textarea',
			'number',
			'range',
			'email',
			'url',
			'password',
			'wysiwyg',
			'select',
			'checkbox',
			'radio',
			'true_false',
			'date_picker',
			'time_picker',
			'date_time_picker',
			'color_picker',
		);
	}

	/**
	 * Get Meta Box allowed field types
	 *
	 * @param string $type optional, possible values 'text'|'image'|'url'.
	 *
	 * @return array
	 * @since 4.1.1
	 */
	public static function get_allowed_mb_field_type( $type ) {

		if ( $type === 'image' ) {
			return array(
				'image',
				'image_advanced',
				'image_upload',
				'single_image',
				'url',
				'file',
				'file_advanced',
				'file_input',
				'file_upload',
			);
		} elseif ( $type === 'url' ) {
			return array(
				'url',
				'file',
				'file_advanced',
				'file_input',
				'file_upload',
			);
		}

		return array(
			'text',
			'email',
			'number',
			'textaraa',
			'select',
			'radio',
			'checkbox',
			'checkbox_list',
		);
	}

	/**
	 * Get Pods allowed field types
	 *
	 * @param string $type optional, possible values 'text'|'image'|'url'.
	 *
	 * @return array
	 * @since 4.1.1
	 */
	public static function get_allowed_pods_field_types( $type = 'text' ) {

		if ( 'image' === $type ) {
			return array(
				'images',
				'file',
			);
		} elseif ( 'url' === $type ) {
			return array(
				'text',
				'email',
				'images',
				'file',
				'link',
				'website',
			);
		}

		return array(
			'text',
			'paragraph',
			'password',
			'phone',
			'time',
			'website',
			'number',
			'wysiwyg',
			'email',
			'link',
			'boolean',
			'code',
			'number',
			'currency',
			'date',
			'datetime',
		);
	}

	/**
	 * Get custom post metas
	 *
	 * @param string $post_type Post Type.
	 * @since 4.1.0
	 * @return array
	 */
	public function get_custom_metas( $post_id, $post_type ) {

		$meta_keys = array();
		$all_keys  = array();

		if ( $post_id !== 0 ) {
			$all_keys = get_post_custom_keys( $post_id );

		} else {
			if ( ! isset( $post_type ) ) {
				return $meta_keys;
			}

			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'fields'         => 'id',
				)
			);

			if ( empty( $posts ) ) {
				return $meta_keys;
			}

			foreach ( $posts as $post ) {
				$post_id = isset( $post->ID ) ? $post->ID : 0;
				$keys    = get_post_custom_keys( $post_id );
				if ( is_array( $keys ) ) {
					foreach ( $keys as $key ) {
						$all_keys[] = $key;
					}
				}
			}
		}

		if ( is_array( $all_keys ) ) {
			foreach ( $all_keys as $key ) {
				if ( ! isset( $meta_keys[ $key ] ) && ! str_starts_with( $key, '_' ) ) {
					$meta_keys[ $key ] = $key;
				}
			}
		}

		return array_keys( $meta_keys );
	}
}
