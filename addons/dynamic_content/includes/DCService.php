<?php

namespace ULTP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *
 * Dynamic Content
 *
 * @package ULTP
 * @since 4.1.1
 */
final class DCService {

	/**
	 * Gets the dynamic data to show in the editor
	 *
	 * @since 4.1.1
	 *
	 * @param array $args - Arguments
	 *
	 * @return string
	 */
	public static function get_dynamic_data( $args ) {
		$post_id   = $args['post_id'];
		$key       = $args['key'];
		$data_type = $args['data_type'];

		$res            = '';
		$process_string = true;

		switch ( $data_type ) {
			case 'link':
				$res            = self::get_link( $post_id, $key );
				$process_string = false;
				break;
			case 'post_types':
				$res            = self::get_post_types();
				$process_string = false;
				break;
			case 'post_info':
				$res = self::get_post_info( $post_id, $key );
				break;
			case 'author_info':
				$res = self::get_author_info( $post_id, $key );
				break;
			case 'site_info':
				$res = self::get_site_info( $key );
				break;
			case 'cf':
				$res            = self::get_field_value( $post_id, $key );
				$process_string = false;
				break;
			case 'comment_count':
				$res = self::get_comment_count( $post_id );
				break;
		}

		return $process_string ? self::process( $res ) : $res;
	}

	/**
	 * Gets the comment count for a post
	 *
	 * @since 4.1.1
	 *
	 * @param string|int|null $post_id - Post Id.
	 *
	 * @return string
	 */
	public static function get_comment_count( $post_id ) {
		$res = '';

		if ( $post_id !== 0 ) {
			$comments = get_comment_count( $post_id );
			$res      = $comments['total_comments'];
		}

		return $res;
	}

	/**
	 * Applies dynamic content to RichText
	 *
	 * @since 4.1.1
	 *
	 * @param string|int|null $post_id - Post Id.
	 * @param string          $content_src - Key value.
	 *
	 * @return string
	 */
	public static function get_dc_content_for_image( $post_id, $content_src ) {
		$res = '';

		if (
			str_starts_with( $content_src, 'acf_' ) ||
			str_starts_with( $content_src, 'cmeta_' ) ||
			str_starts_with( $content_src, 'pods_' ) ||
			str_starts_with( $content_src, 'mb_' )
		) {
			$res = self::get_field_value( $post_id, $content_src );
		} elseif (
			str_starts_with( $content_src, 'link_' )
		) {
			$res = self::get_link( $post_id, $content_src );
		}

		if ( empty( $res ) ) {
			$res = ULTP_URL . 'assets/img/ultp-placeholder.jpg';
		}

		return $res;
	}

	/**
	 * Applies dynamic content to RichText
	 *
	 * @since 4.1.1
	 *
	 * @param array $attr - block attributes.
	 *
	 * @return string
	 */
	public static function get_dc_content_for_rich_text( &$attr ) {
		$text = '';
		$url  = '';

		$data_src     = $attr['dc']['dataSrc'];
		$content_src  = $attr['dc']['contentSrc'];
		$link_src     = $attr['dc']['linkSrc'];
		$link_enabled = $attr['dc']['linkEnabled'];
		$post_id      = $attr['dc']['postId'];
		$fallback     = $attr['dc']['fallback'];
		$max_length   = $attr['dc']['maxCharLen'];

		if ( str_starts_with( $data_src, 'site_' ) ) {
			$text = self::get_site_info( $data_src );
		} elseif ( str_starts_with( $content_src, 'post_' ) ) {
			$text = self::get_post_info( $post_id, $content_src );
		} elseif ( str_starts_with( $content_src, 'a_' ) ) {
			$text = self::get_author_info( $post_id, $content_src );
		} elseif (
			str_starts_with( $content_src, 'acf_' ) ||
			str_starts_with( $content_src, 'cmeta_' ) ||
			str_starts_with( $content_src, 'pods_' ) ||
			str_starts_with( $content_src, 'mb_' )
		) {
			$text = self::get_field_value( $post_id, $content_src );
		}

		if ( ! empty( $text ) && ! empty( $max_length ) && is_string( $max_length ) && intval( $max_length ) > 0 ) {
			$text = self::limit_str( $text, intval( $max_length ) );
		}

		if ( empty( $text ) ) {
			$text = $fallback;
		}

		$text = $attr['dc']['beforeText'] . $text . $attr['dc']['afterText'];

		if ( ( $link_enabled && $link_src ) ) {
			$url = self::get_link( $post_id, $link_src );
		}

		return array( $text, $url );
	}

	/**
	 * Get Html of Dynamic Content for Blocks
	 *
	 * @since 4.1.1
	 *
	 * @param array $attr
	 *
	 * @return string
	 */
	public static function get_dc_content_for_block( &$attr, $dcContent ) {
		for ( $i = 0; $i < count( $attr['dcFields'] ); $i++ ) {
			$dcGroup = $attr['dcFields'][ $i ];

			if ( ! isset( $dcGroup['fields'] ) || ! isset( $dcGroup['id'] ) ) {
				continue;
			}

			$dcContent[ $i ] .= '<div class="ultp-dynamic-content-group-common ultp-dynamic-content-group-' . $dcGroup['id'] . '">';

			for ( $j = 0; $j < count( $dcGroup['fields'] ); $j++ ) {
				$dcField = $dcGroup['fields'][ $j ];

				$url = '';

				if ( ( $dcField['linkEnabled'] && $dcField['linkSrc'] ) ) {
					$url = self::get_link( $dcField['postId'], $dcField['linkSrc'] );
					$url = esc_url( $url );
				}

				if ( ! empty( $url ) ) {
					$dcContent[ $i ] .= '<a class="ultp-dynamic-content-field-anchor" href="' . $url . '">';
				}

				$dcContent[ $i ] .= '<div class="ultp-dynamic-content-field-common ultp-dynamic-content-field-' . $dcField['id'] . '">';
				if ( ! empty( $dcField['icon'] ) ) {
					$dcContent[ $i ] .= '<span class="ultp-dynamic-content-field-icon">' . ultimate_post()->get_svg_icon( $dcField['icon'] ) . '</span>';
				}
				$dcContent[ $i ] .= $dcField['beforeText'] ? '<p class="ultp-dynamic-content-field-before">' . $dcField['beforeText'] . '</p>' : '';

				$text = '';

				if ( str_starts_with( $dcField['dataSrc'], 'site_' ) ) {
					$text = self::get_site_info( $dcField['dataSrc'] );
				} elseif ( str_starts_with( $dcField['contentSrc'], 'post_' ) ) {
					$text = self::get_post_info( $dcField['postId'], $dcField['contentSrc'] );
				} elseif ( str_starts_with( $dcField['contentSrc'], 'a_' ) ) {
					$text = self::get_author_info( $dcField['postId'], $dcField['contentSrc'] );
				} elseif (
					str_starts_with( $dcField['contentSrc'], 'acf_' ) ||
					str_starts_with( $dcField['contentSrc'], 'cmeta_' ) ||
					str_starts_with( $dcField['contentSrc'], 'pods_' ) ||
					str_starts_with( $dcField['contentSrc'], 'mb_' )
				) {
					$text = self::get_field_value( $dcField['postId'], $dcField['contentSrc'] );
				}

				if ( ! empty( $text ) && ! empty( $dcField['maxCharLen'] ) && intval( $dcField['maxCharLen'] ) > 0 ) {
					$text = self::limit_str( $text, intval( $dcField['maxCharLen'] ) );
				}

				if ( empty( $text ) && '0' !== $text && 0 !== $text ) {
					$text = $dcField['fallback'];
				}

				$dcContent[ $i ] .= '<p class="ultp-dynamic-content-field-dc">' . sanitize_text_field( $text ) . '</p>';

				$dcContent[ $i ] .= $dcField['afterText'] ? '<p class="ultp-dynamic-content-field-after">' . $dcField['afterText'] . '</p>' : '';
				$dcContent[ $i ] .= '</div>';

				if ( ! empty( $url ) ) {
					$dcContent[ $i ] .= '</a>';
				}
			}

			$dcContent[ $i ] .= '</div>';
		}

		return $dcContent;
	}


	/**
	 * Get field value
	 *
	 * @since 4.1.1
	 *
	 * @param int    $post_id
	 * @param string $meta_key
	 *
	 * @return string
	 */
	public static function get_field_value( $post_id, $meta_key ) {
		if ( empty( $meta_key ) ) {
			return '';
		}

		if ( empty( $post_id ) ) {
			if ( in_the_loop() ) {
				$post_id = get_the_ID();
			} else {
				global $post;
				$post_id = isset( $post->ID ) ? $post->ID : '';
			}
		}

		if ( self::check_prefix_and_remove( $meta_key, 'cmeta_' ) ) {
			if ( empty( $post_id ) ) {
				return '';
			}
			return get_post_meta( $post_id, $meta_key, true );
		}

		if ( self::check_prefix_and_remove( $meta_key, 'acf_' ) ) {
			if ( class_exists( 'ACF' ) ) {
				if ( ! empty( $post_id ) ) {
					$value = get_field( $meta_key, $post_id );
					return self::extract_acf_value( $value );
				} else {
					return get_field( $meta_key, false );
				}
			}
		}

		if ( self::check_prefix_and_remove( $meta_key, 'mb_' ) ) {
			if ( function_exists( 'rwmb_get_value' ) ) {
				$value = rwmb_get_value( $meta_key, array(), $post_id );
				return self::extract_mb_value( $value, $meta_key, $post_id );
			}
		}

		if ( self::check_prefix_and_remove( $meta_key, 'pods_' ) ) {
			if ( function_exists( 'pods' ) ) {
				$pods = pods( get_post_type( $post_id ), $post_id );
				if ( is_object( $pods ) && method_exists( $pods, 'exists' ) &&
					$pods->exists() && method_exists( $pods, 'field' )
				) {
					$value = $pods->field( $meta_key );
					return self::extract_pods_value( $value );
				}
			}
		}

		return '';
	}

	/**
	 * Post info
	 *
	 * @since 4.1.1
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key Key value.
	 *
	 * @return string
	 */
	public static function get_post_info( $post_id, $key ) {
		if ( empty( $key ) ) {
			return '';
		}

		$key = self::maybe_remove_prefix( $key, 'post_' );

		$res = '';

		if ( empty( $post_id ) ) {
			if ( in_the_loop() ) {
				$post_id = get_the_ID();
			} else {
				global $post;
				$post_id = isset( $post->ID ) ? $post->ID : '';
			}
		}

		switch ( $key ) {
			case 'id':
				$res = $post_id;
				break;
			case 'title':
				$res = get_the_title( $post_id );
				break;
			case 'excerpt':
				if ( ! doing_filter( 'get_the_excerpt' ) ) {
					$res = get_the_excerpt( $post_id );
				}
				break;
			case 'date':
				$res = get_the_date( '', $post_id );
				break;
			case 'comments':
				$res = strval( get_comment_count( $post_id )['all'] );
				break;
		}

		return $res;
	}

	/**
	 * Author info
	 *
	 * @since 4.1.1
	 *
	 * @param int    $post_id
	 * @param string $key
	 *
	 * @return string
	 */
	public static function get_author_info( $post_id, $key ) {
		if ( empty( $key ) ) {
			return '';
		}

		// Removes prefix if present
		$key = self::maybe_remove_prefix( $key, 'a_' );

		if ( empty( $post_id ) ) {
			if ( in_the_loop() ) {
				$post = get_post();
			} else {
				global $post;
			}
		} else {
			$post = get_post( $post_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		return get_the_author_meta( $key, intval( $post->post_author ) );
	}

	/**
	 * Site info
	 *
	 * @since 4.1.1
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public static function get_site_info( $key ) {
		if ( empty( $key ) ) {
			return '';
		}

		// Removes prefix if present
		$key = self::maybe_remove_prefix( $key, 'site_' );

		$res = '';

		switch ( $key ) {
			case 'title':
				$res = get_bloginfo();
				break;
			case 'tagline':
				$res = get_bloginfo( 'description' );
				break;
			case 'arc_title':
				$res = self::get_page_title();
				break;
			case 'arc_desc':
				$res = wp_kses_post( get_the_archive_description() );
				break;
		}

		return $res;
	}

	/**
	 * Gets the link
	 *
	 * @since 4.1.1
	 * @param string $post_id Post ID.
	 * @param string $key Key value.
	 *
	 * @return string
	 */
	public static function get_link( $post_id, $key ) {
		$res = '';

		if ( empty( $post_id ) ) {
			if ( in_the_loop() ) {
				$post_id = get_the_ID();
			} else {
				global $post;
				$post_id = isset( $post->ID ) ? $post->ID : '';
			}
		}

		if ( self::check_prefix( $key, 'acf_' ) ||
			self::check_prefix( $key, 'mb_' ) ||
			self::check_prefix( $key, 'cmeta_' ) ||
			self::check_prefix( $key, 'pods_' )
		) {
			$res = self::get_field_value( $post_id, $key );
		} else {
			$author_id = absint( get_post_field( 'post_author', $post_id ) );
			$key       = self::maybe_remove_prefix( $key, 'link_' );
			switch ( $key ) {
				case 'post_featured_image':
					$res = wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
					break;
				case 'post_permalink':
					$res = get_permalink( $post_id );
					break;
				case 'post_comments':
					$res = get_comments_link( $post_id );
					break;
				case 'a_profile':
					$res = get_avatar_url( $author_id );
					break;
				case 'a_arc':
					$res = get_author_posts_url( $author_id );
					break;
				case 'a_page':
					$res = get_the_author_meta( 'url', $author_id );
					break;
			}
		}

		return $res;
	}

	/**
	 * Replaces substring with a string
	 *
	 * @since 4.1.1
	 * @param string $old Post content string.
	 * @param string $new Value to insert.
	 *
	 * @return string
	 */
	public static function replace( $old, $new ) {
		$pattern = '/<span\s+class\s*=\s*["\']ultp-richtext-dynamic-content["\']\s*>(.*?)<\/span>/';

		// Replaces only the first match, the rest matches are deleted.
		$res = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( &$firstMatch, $new ) {
				if ( ! isset( $firstMatch ) ) {
					$firstMatch = $matches[0];
					return $new;
				} else {
					return '';
				}
			},
			$old
		);

		return is_null( $res ) ? $old : $res;
	}

	/**
	 * Gets the post types
	 *
	 * @since 4.1.1
	 *
	 * @return array[string]
	 */
	public static function get_post_types() {
		$res        = array();
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		foreach ( $post_types as $_ => $value ) {
			if ( 'attachment' !== $value->name ) {
				$res[] = array(
					'value' => $value->name,
					'label' => $value->label,
				);
			}
		}

		return $res;
	}

	/**
	 * Checks if Dynamic Content is active
	 *
	 * @since 4.1.1
	 *
	 * @param array $attr Block attributes.
	 *
	 * @return boolean
	 */
	public static function is_dc_active( &$attr ) {
		return ultimate_post()->get_setting( 'ultp_dynamic_content' ) == 'true' &&
			isset( $attr['dcEnabled'] );
	}

	/**
	 * Get page title
	 *
	 * @since 4.1.1
	 *
	 * @return string
	 */
	private static function get_page_title() {
		$title = '';

		if ( is_singular() ) {
			$title = get_the_title();
		} elseif ( is_search() ) {
			$title = sprintf( esc_html__( 'Search Results for: %s', 'ultimate-post' ), get_search_query() );

			if ( get_query_var( 'paged' ) ) {
				$title .= sprintf( esc_html__( '&nbsp;&ndash; Page %s', 'ultimate-post' ), get_query_var( 'paged' ) );
			}
		} elseif ( is_category() ) {
			$title = single_cat_title( '', false );
		} elseif ( is_tag() ) {
			$title = single_tag_title( '', false );
		} elseif ( is_author() ) {
			$title = get_the_author();
		} elseif ( is_year() ) {
			$title = get_the_date( __( 'Y', 'ultimate-post' ) );
		} elseif ( is_month() ) {
			$title = get_the_date( __( 'F Y', 'ultimate-post' ) );
		} elseif ( is_day() ) {
			$title = get_the_date( __( 'F j, Y', 'ultimate-post' ) );
		} elseif ( is_post_type_archive() ) {
			$title = post_type_archive_title( '', false );
		} elseif ( is_tax() ) {
			$title = single_term_title( '', false );
		} elseif ( is_archive() ) {
			$title = esc_html__( 'Archives', 'ultimate-post' );
		} elseif ( is_404() ) {
			$title = esc_html__( 'Page Not Found', 'ultimate-post' );
		}

		return $title;
	}

	/**
	 * Formats ACF value based upon data type
	 *
	 * @since 4.1.1
	 *
	 * @param array|string $value ACF Meta Value.
	 *
	 * @return string
	 */
	private static function extract_acf_value( $value ) {
		if ( is_array( $value ) ) {
			$image_id = absint( $value['id'] );
			$value    = wp_get_attachment_image_src( $image_id );
		} elseif ( is_numeric( $value ) ) {
			return $value;
		}

		return $value;
	}

	/**
	 * Formats Pods value based upon data type
	 *
	 * @since 4.1.1
	 *
	 * @param array|string $value Pods Meta Value.
	 *
	 * @return string
	 */
	private static function extract_pods_value( $value ) {
		if ( is_array( $value ) ) {

			// Single image
			if ( isset( $value['guid'] ) ) {
				return $value['guid'];
			}

			// Multi image
			if ( isset( $value[0]['guid'] ) ) {
				return $value[0]['guid'];
			}

			// Repeater
			if ( isset( $value[0] ) &&
				( is_string( $value[0] ) || is_numeric( $value[0] ) || is_bool( $value[0] ) )
			) {
				return $value[0];
			}

			return '';
		}

		return $value;
	}

	/**
	 * Formats Meta Box value based upon data type
	 *
	 * @since 4.1.1
	 *
	 * @param array|string $value
	 *
	 * @return string
	 */
	private static function extract_mb_value( $custom_fields, $meta_key, $post_id ) {

		if ( ! is_array( $custom_fields ) ) {
			return strval( $custom_fields );
		}

		$field_data = rwmb_get_field_settings( $meta_key, array(), $post_id );

		// Process the first element if its an array
		foreach ( $custom_fields as $field ) {
			$field_type  = $field_data['type'];
			$field_value = '';

			switch ( $field_type ) {
				case 'checkbox':
				case 'radio':
					$field_value = isset( $field['checked'] ) ? 'checked' : '';
					break;
				case 'select':
				case 'radio_list':
				case 'checkbox_list':
				case 'taxonomy':
				case 'taxonomy_advanced':
				case 'post':
				case 'post_advanced':
				case 'post_checkbox_list':
				case 'post_select':
					$field_value = isset( $field['selected'] ) ? $field['selected'] : '';
					break;
				case 'file':
				case 'file_input':
				case 'file_advanced':
				case 'file_upload':
					$field_value = isset( $field['url'] ) ? $field['url'] : $field['id'];
					break;
				case 'single_image':
					$field_value = wp_get_attachment_image_url( $custom_fields['ID'], 'full' );
					break;
				case 'image':
				case 'image_advanced':
				case 'image_upload':
				case 'plupload_image':
				case 'thickbox_image':
					$field_value = wp_get_attachment_image_url( $field['ID'], 'full' );
					break;
				case 'date':
				case 'datetime':
				case 'time':
					$field_value = $field['date'];
					break;
				default:
					$field_value = $field['value'];
					break;
			}

			return $field_value;
		}

		return '';
	}

	/**
	 * Properly decodes HTML chars from string
	 *
	 * @param mixed $str
	 * @return mixed
	 */
	public static function process( $str ) {
		if ( is_string( $str ) ) {
			$res = esc_html( html_entity_decode( $str ) );
			return str_replace( '&amp;', '&', $res );
		}
		return $str;
	}

	/**
	 * Limits a string by word
	 *
	 * @param string $str
	 * @param int $limit
	 * @return string
	 */
	public static function limit_str( $str, $limit ) {
		return join( ' ', array_slice( explode( ' ', $str ), 0, $limit ) );
	}

	private static function check_prefix( $key, $prefix ) {
		return substr( $key, 0, strlen( $prefix ) ) === $prefix;
	}

	private static function maybe_remove_prefix( $key, $prefix ) {
		if ( substr( $key, 0, strlen( $prefix ) ) === $prefix ) {
			return substr( $key, strlen( $prefix ) );
		}
		return $key;
	}

	private static function check_prefix_and_remove( &$key, $prefix ) {
		if ( substr( $key, 0, strlen( $prefix ) ) === $prefix ) {
			$key = substr( $key, strlen( $prefix ) );
			return true;
		}
		return false;
	}
}
