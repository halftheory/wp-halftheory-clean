<?php
/*
Available actions:
halftheory_helper_featured_video_html
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Featured_Video', false) ) :
	#[AllowDynamicProperties]
	class Halftheory_Helper_Featured_Video {

		public $prefix = 'featuredvideo';
		public $post_types = array( 'page', 'post' );
		public static $enqueue_css = false;

		public function __construct() {
			if ( is_admin() ) {
				foreach ( $this->post_types as $value ) {
					add_action('add_meta_boxes_' . $value, array( $this, 'add_meta_boxes' ));
					add_action('save_post_' . $value, array( $this, 'save_post' ), 20, 3);
				}
				add_action('admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 20);
				add_filter('media_view_settings', array( $this, 'media_view_settings' ), 10, 2);
				add_action('wp_ajax_get_post_video_html', array( $this, 'wp_ajax_get_post_video_html' ), 1);
				add_action('after_delete_post', array( $this, 'after_delete_post' ), 20);
			}
			add_action('get_footer', array( $this, 'get_footer' ), 20, 2);
		}

		/* actions */

		public function add_meta_boxes( $post ) {
			if ( ! current_user_can('upload_files') ) {
				return;
			}
			if ( empty($post) ) {
				return;
			}
			add_meta_box(
				'postvideodiv',
				 __('Featured video'),
				array( $this, 'add_meta_box' ),
				$this->post_types,
				'side',
				'low',
				array( '__back_compat_meta_box' => true )
			);
		}

		public function add_meta_box( $post ) {
			if ( empty($post) ) {
				return;
			}
			if ( ! is_object($post) ) {
				return;
			}
			if ( ! in_array($post->post_type, $this->post_types, true) ) {
				return;
			}
			$video_id = null;
			if ( $tmp = $this->get_post_video_id($post) ) {
				$video_id = $tmp;
			}
			echo $this->post_video_html($video_id, $post->ID);
		}

		public function admin_enqueue_scripts() {
			if ( ! current_user_can('upload_files') ) {
				return;
			}
			if ( ! function_exists('get_current_screen') ) {
				return;
			}
			if ( ! is_object(get_current_screen()) ) {
				return;
			}
			if ( ! in_array(get_current_screen()->id, $this->post_types, true) ) {
				return;
			}
			global $typenow;
			if ( ! in_array($typenow, $this->post_types, true) ) {
				return;
			}
			$handle = $this->prefix;
            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			if ( method_exists('Halftheory_Clean', 'get_theme_version') ) {
				wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/featured-video/media-editor-featured-video' . $min . '.js', array( 'media-editor' ), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/featured-video/media-editor-featured-video' . $min . '.js'), true);
			} else {
				wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/featured-video/media-editor-featured-video' . $min . '.js', array( 'media-editor' ), '', true);
			}
		}

		public function media_view_settings( $settings = array(), $post = 0 ) {
			if ( empty($post) ) {
				return $settings;
			}
			if ( ! is_object($post) ) {
				return $settings;
			}
			if ( ! in_array($post->post_type, $this->post_types, true) ) {
				return $settings;
			}
			$video_id = -1;
			if ( $tmp = $this->get_post_video_id($post) ) {
				$video_id = $tmp;
			}
			$settings['post']['featuredVideoId'] = $video_id;
			return $settings;
		}

		public function wp_ajax_get_post_video_html() {
			// Ajax handler for retrieving HTML for the featured image.
			if ( ! isset($_POST, $_POST['post_id']) ) {
				wp_die( -1 );
			}
			$post_ID = (int) $_POST['post_id'];

			check_ajax_referer( "update-post_$post_ID" );

			if ( ! current_user_can( 'edit_post', $post_ID ) ) {
				wp_die( -1 );
			}

			$video_id = isset($_POST['video_id']) ? (int) $_POST['video_id'] : null;

			// For backward compatibility, -1 refers to no featured image.
			if ( -1 === $video_id ) {
				$video_id = null;
			}

			$return = $this->post_video_html($video_id, $post_ID);
			wp_send_json_success($return);
		}

		public function save_post( $post_id, $post, $update = false ) {
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
				return;
			}
			if ( empty($update) ) {
				return;
			}
			$hp = false;
			if ( method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
				if ( $tmp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
					$hp = $tmp;
				}
			}
			// update options, only on Edit>Post page.
			if ( isset($_POST, $_POST['_wpnonce']) ) {
				if ( wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-post_' . $post_id) ) {
					if ( isset($_POST[ $this->prefix . '_video_id' ]) ) {
						$video_id = (int) $_POST[ $this->prefix . '_video_id' ];
						if ( $video_id === -1 ) {
							if ( $hp ) {
								$hp->delete_postmeta($post->ID, $this->prefix . '_video_id');
							} else {
								delete_post_meta($post->ID, $this->prefix . '_video_id');
							}
						} elseif ( $hp ) {
							$hp->update_postmeta($post->ID, $this->prefix . '_video_id', $video_id);
						} else {
							update_post_meta($post->ID, $this->prefix . '_video_id', $video_id);
						}
					} elseif ( $hp ) {
						$hp->delete_postmeta($post->ID, $this->prefix . '_video_id');
					} else {
						delete_post_meta($post->ID, $this->prefix . '_video_id');
					}
				}
			}
		}

		public function after_delete_post( $post_id ) {
			$hp = false;
			if ( method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
				if ( $tmp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
					$hp = $tmp;
				}
			}
			if ( $hp ) {
				$hp->delete_postmeta($post_id, $this->prefix . '_video_id');
			} else {
				delete_post_meta($post_id, $this->prefix . '_video_id');
			}
		}

		public function get_footer( $name, $args ) {
			if ( function_exists('is_public') ) {
				if ( ! is_public() ) {
					return;
				}
			}
			if ( ! empty(static::$enqueue_css) ) {
				$handle = $this->prefix;
				if ( method_exists('Halftheory_Clean', 'get_theme_version') ) {
					wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/featured-video/featured-video.css', array(), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/featured-video/featured-video.css'), 'screen');
				} else {
					wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/featured-video/featured-video.css', array(), '', 'screen');
				}
			}
		}

		/* functions */

		private function post_video_html( $video_id = null, $post = null ) {
			$post               = get_post( $post );
			$upload_iframe_src  = get_upload_iframe_src('video', $post->ID);

			$content = wp_sprintf(
				'<p class="hide-if-no-js"><a href="%s" id="set-post-video"%s class="thickbox">%s</a></p>',
				esc_url( $upload_iframe_src ),
				'', // Empty when there's no featured image set, `aria-describedby` attribute otherwise.
				esc_html( __('Set featured video') )
			);

			if ( $video_id && get_post( $video_id ) ) {
				$_wp_additional_image_sizes = wp_get_additional_image_sizes();
				$size = isset( $_wp_additional_image_sizes['post-thumbnail'] ) ? 'post-thumbnail' : array( 266, 266 );

				$video_html = array();
				if ( has_post_thumbnail($video_id) ) {
					$video_html[] = get_the_post_thumbnail($video_id, $size);
				} else {
					$video_html[] = wp_get_attachment_image($video_id, $size, true);
				}
				$video_html[] = get_the_title($video_id);
				$video_html  = array_map('trim', $video_html);
				$video_html = implode('<br />', array_filter($video_html));

				if ( ! empty($video_html) ) {
					$content = wp_sprintf(
						'<p class="hide-if-no-js"><a href="%s" id="set-post-video"%s class="thickbox" style="text-decoration: none; color: inherit; font-weight: bold; text-align: center; display: block;">%s</a></p>',
						esc_url( $upload_iframe_src ),
						' aria-describedby="set-post-video-desc"',
						$video_html
					);
					$content .= '<p class="hide-if-no-js howto" id="set-post-video-desc">' . __( 'Click the icon to update' ) . '</p>';
					$content .= '<p class="hide-if-no-js"><a href="#" id="remove-post-video">' . esc_html__('Remove featured video') . '</a></p>';
				}
			}

			$content .= '<input type="hidden" id="' . $this->prefix . '_video_id" name="' . $this->prefix . '_video_id" value="' . esc_attr( $video_id ? $video_id : '-1' ) . '" />';
			return $content;
		}

		public function get_post_video_id( $post = null ) {
			$post_id = null;
			if ( ! empty($post) ) {
				if ( $post = get_post($post) ) {
					$post_id = $post->ID;
				}
			} else {
				$post_id = get_the_ID();
			}
			if ( empty($post_id) ) {
				return false;
			}
			$video_id = null;
			if ( method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
				if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
					$video_id = $hp->get_postmeta($post_id, $this->prefix . '_video_id');
				}
			}
			$video_id = ! empty($video_id) ? $video_id : get_post_meta($post_id, $this->prefix . '_video_id');
			if ( empty($video_id) ) {
				return false;
			}
			if ( ! wp_attachment_is('video', $video_id) ) {
				return false;
			}
			return $video_id;
		}

		public function get_post_video( $post = null ) {
			$video_id = $this->get_post_video_id($post);
			if ( empty($video_id) ) {
				return false;
			}
			$res = '';
			if ( method_exists('Halftheory_Clean', 'get_video_context') ) {
				if ( $tmp = Halftheory_Clean::get_instance()->get_video_context($video_id, 'video', array( 'class' => 'post-video' )) ) {
					$res = $tmp;
				}
			}
			if ( empty($res) ) {
				$res = '<video width="100%" autoplay playsinline preload="auto" loop muted disablepictureinpicture controlslist="nodownload" controls="false" class="post-video">' . "\n";
				$res .= '<source src="' . esc_url(wp_get_attachment_url($video_id)) . '" type="' . esc_attr(get_post_mime_type($video_id)) . '" />' . "\n";
				$res .= '</video>';
			}
			$res = '<div class="post-video"><a class="post-video" href="' . esc_url(get_permalink($post)) . '" aria-hidden="true" data-content="' . esc_attr(wp_strip_all_tags(get_the_title($post))) . '">' . $res . '</a></div>';
			$res = apply_filters('halftheory_helper_featured_video_html', $res, $video_id, $post);
			if ( ! empty($res) ) {
				static::$enqueue_css = true;
			}
			return $res;
		}

		public function post_video( $post = null ) {
			if ( $res = $this->get_post_video($post) ) {
				echo $res;
			}
		}
	}
endif;
