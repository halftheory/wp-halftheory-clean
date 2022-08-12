<?php
/*
Available actions:
halftheory_helper_gallery_carousel_print_media_templates_aspectratio
halftheory_helper_gallery_carousel_print_media_templates_carousel
halftheory_helper_gallery_carousel_slick_settings
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Gallery_Carousel', false) ) :
	class Halftheory_Helper_Gallery_Carousel {

		public $prefix = 'gallery-carousel';
		public static $items_carousel = array();
		public static $items_aspectratio = array();
		public $slick_settings = array(
			'arrows' => false,
			'autoplay' => true,
			'autoplaySpeed' => 3000,
            'centerMode' => false,
            'dots' => false,
			'fade' => true,
			'infinite' => true,
			'slidesToShow' => 3,
			'speed' => 500, // set 0 to disable transitions.
		);
		public $aspectratio_options = array(
			'1-1' => '1:1',
            '4-3' => '4:3',
            '16-9' => '16:9',
            '21-9' => '21:9',
            '3-4' => '3:4',
            '9-16' => '9:16',
            '9-21' => '9:21',
		);

		public function __construct() {
			add_filter('post_gallery', array( $this, 'post_gallery' ), 20, 2);
    		add_action('get_footer', array( $this, 'get_footer' ), 20, 2);
			if ( is_admin() ) {
            	add_action('print_media_templates', array( $this, 'print_media_templates' ), 20);
			}
		}

		/* actions */

		public function post_gallery( $output = '', $attr = array(), $instance = 1 ) {
			if ( function_exists('is_front_end') ) {
				if ( ! is_front_end() ) {
					return $output;
				}
			}
			if ( (int) did_action('get_header') === 0 || (int) did_action('loop_start') === 0 || is_feed() ) {
				return $output;
			}

			// carousel detection.
			$carousel = false;
			if ( array_key_exists('carousel', $attr) && is_singular() ) {
				if ( function_exists('is_true') ) {
					$carousel = is_true($attr['carousel']);
				} elseif ( is_bool($attr['carousel']) ) {
					$carousel = $attr['carousel'];
				} elseif ( is_string($attr['carousel']) && trim($attr['carousel']) === 'true' ) {
					$carousel = true;
				}
			}

			// aspectratio detection.
			$aspectratio = false;
			if ( array_key_exists('aspectratio', $attr) ) {
				$attr['aspectratio'] = preg_replace('/[:\/]/', '-', trim($attr['aspectratio']));
				if ( array_key_exists($attr['aspectratio'], $this->aspectratio_options) ) {
					$aspectratio = $attr['aspectratio'];
				}
			}

			if ( ! $carousel && ! $aspectratio ) {
				return $output;
			}

            // remove this filter to prevent infinite looping.
            remove_action(current_action(), array( $this, __FUNCTION__ ), 20);
            // get the html.
            $output = gallery_shortcode($attr);
            // add the filter again.
			add_filter(current_action(), array( $this, __FUNCTION__ ), 20, 2);

            if ( strpos($output, '<div id=') === false ) {
            	return $output;
            }

            // get selector and add classes.
            $selector = false;
            if ( preg_match_all("/<div id=['\"]([^'\"]+)['\"] class=['\"]([^'\"]+)['\"]>/is", $output, $matches, PREG_SET_ORDER) ) {
            	foreach ( $matches as $key => $value ) {
            		if ( ! isset($value[1]) ) {
            			continue;
            		}
            		if ( strpos($value[1], 'gallery-') === 0 ) {
            			$selector = trim($value[1]);
            			$classes = $value[2];
            			if ( $carousel ) {
            				$classes .= ' ' . $this->prefix;
            			}
            			if ( $aspectratio ) {
            				$classes .= ' gallery-aspectratio-' . $aspectratio;
            			}
            			$output = str_replace($value[0], '<div id="' . $selector . '" class="' . trim($classes) . '">', $output);
            			break;
            		}
            	}
            }
            if ( ! $selector ) {
            	return $output;
            }

            // save the item for css + js loading.
            if ( $carousel && ! isset(static::$items_carousel[ $selector ]) ) {
				$gallery_settings = $this->shortcode_attr_to_slick_settings($attr);
            	static::$items_carousel[ $selector ] = $this->get_slick_settings($gallery_settings, null, true);
            }
            if ( $aspectratio && ! isset(static::$items_aspectratio[ $selector ]) ) {
            	static::$items_aspectratio[ $selector ] = $aspectratio;
            }

			return $output;
		}

		public function get_footer( $name, $args ) {
			if ( function_exists('is_front_end') ) {
				if ( ! is_front_end() ) {
					return;
				}
			}
			if ( ! empty(static::$items_carousel) ) {
				$handle = $this->prefix;
				// slick.
				wp_enqueue_style($handle . '-slick', get_template_directory_uri() . '/app/helpers/gallery-carousel/slick/slick.css', array(), '1.8.1', 'screen');
				wp_enqueue_style($handle . '-slick-theme', get_template_directory_uri() . '/app/helpers/gallery-carousel/slick/slick-theme.css', array( $handle . '-slick' ), '1.8.1', 'screen');
				wp_enqueue_script($handle . '-slick', get_template_directory_uri() . '/app/helpers/gallery-carousel/slick/slick' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', array( 'jquery' ), '1.8.1', true);
				// helper.
				if ( method_exists('Halftheory_Clean', 'get_theme_version') ) {
					wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/gallery-carousel/gallery-carousel.css', array( $handle . '-slick-theme' ), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/gallery-carousel/gallery-carousel.css'), 'screen');
					wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/gallery-carousel/gallery-carousel' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', array( 'jquery', $handle . '-slick' ), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/gallery-carousel/gallery-carousel' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.js'), true);
				} else {
					wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/gallery-carousel/gallery-carousel.css', array( $handle . '-slick-theme' ), '', 'screen');
					wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/gallery-carousel/gallery-carousel' . ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '' ) . '.js', array( 'jquery', $handle . '-slick' ), '', true);
				}
				wp_localize_script($handle, 'gallery_carousel', static::$items_carousel);
			}
			if ( ! empty(static::$items_aspectratio) ) {
				$handle = 'gallery-aspectratio';
				if ( method_exists('Halftheory_Clean', 'get_theme_version') ) {
					wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/gallery-carousel/gallery-aspectratio.css', array(), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/gallery-carousel/gallery-aspectratio.css'), 'screen');
				} else {
					wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/gallery-carousel/gallery-aspectratio.css', array(), '', 'screen');
				}
			}
		}

		/* actions - admin */

		public function print_media_templates() {
			// https://wordpress.stackexchange.com/questions/182821/add-custom-fields-to-wp-native-gallery-settings
			$aspectratio = apply_filters('halftheory_helper_gallery_carousel_print_media_templates_aspectratio', true);
			$carousel = apply_filters('halftheory_helper_gallery_carousel_print_media_templates_carousel', true);
			if ( ! $aspectratio && ! $carousel ) {
				return;
			}
			if ( $carousel ) {
            	$settings = $this->get_slick_settings();
            }
			?>
<script type="text/html" id="tmpl-<?php echo esc_attr($this->prefix); ?>">
	<?php if ( $aspectratio ) : ?>
	<span class="setting">
		<label for="gallery-aspectratio" class="name select-label-inline"><?php _e('Aspect ratio'); ?></label>
		<select id="gallery-aspectratio" name="aspectratio" data-setting="aspectratio">
			<option value="">--</option>
		<?php foreach ( $this->aspectratio_options as $key => $value ) : ?>
			<option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
		<?php endforeach; ?>
		</select>
	</span>
	<?php endif; ?>
	<?php if ( $carousel ) : ?>
	<span class="setting"><hr /></span>
	<h2 style="position: static;"><?php _e('Carousel Settings'); ?></h2>
	<span class="setting">
		<input type="checkbox" id="<?php echo esc_attr($this->prefix); ?>-active" data-setting="carousel" />
		<label for="<?php echo esc_attr($this->prefix); ?>-active" class="checkbox-label-inline"><?php _e('Create Carousel?'); ?></label>
	</span>
	<?php foreach ( $settings as $key => $value ) : ?>
	<span class="setting">
		<?php if ( is_bool($value) ) : ?>
		<label for="<?php echo esc_attr($this->prefix); ?>-<?php echo esc_attr(strtolower($key)); ?>" class="checkbox-label-inline" style="min-width: 33%;"><?php echo ucfirst($key); ?></label>
		<input type="checkbox" id="<?php echo esc_attr($this->prefix); ?>-<?php echo esc_attr(strtolower($key)); ?>" data-setting="carousel_<?php echo esc_attr(strtolower($key)); ?>" />
		<?php elseif ( is_float($value) || is_int($value) ) : ?>
		<label for="<?php echo esc_attr($this->prefix); ?>-<?php echo esc_attr(strtolower($key)); ?>" class="checkbox-label-inline"><?php echo ucfirst($key); ?></label>
		<input type="number" id="<?php echo esc_attr($this->prefix); ?>-<?php echo esc_attr(strtolower($key)); ?>" data-setting="carousel_<?php echo esc_attr(strtolower($key)); ?>" value="" />
		<?php else : ?>
		<label for="<?php echo esc_attr($this->prefix); ?>-<?php echo esc_attr(strtolower($key)); ?>" class="checkbox-label-inline"><?php echo ucfirst($key); ?></label>
		<input type="text" id="<?php echo esc_attr($this->prefix); ?>-<?php echo esc_attr(strtolower($key)); ?>" data-setting="carousel_<?php echo esc_attr(strtolower($key)); ?>" value="" />
		<?php endif; ?>
	</span>
	<?php endforeach; ?>
	<?php endif; ?>
</script>
<script>
jQuery(document).ready(function(){
	_.extend(wp.media.galleryDefaults,{
<?php
if ( $carousel ) {
	foreach ( $settings as $key => $value ) {
		$key = 'carousel_' . strtolower($key);
		if ( is_null($value) || is_string($value) || is_float($value) || is_int($value) ) {
			$value = "'" . $value . "'";
		} elseif ( is_bool($value) ) {
			$value = $value === true ? 'true' : 'false';
		}
		echo "\t\t$key: $value,\n";
	}
	echo "\t\tcarousel: false,\n";
}
?>
		aspectratio: ''
	});
	wp.media.view.Settings.Gallery = wp.media.view.Settings.Gallery.extend({
		template: function(view){
			return wp.media.template('gallery-settings')(view) + wp.media.template('<?php echo $this->prefix; ?>')(view);
		},
		update: function( key ) {
			var value = this.model.get(key),
				$setting = this.$('[data-setting="' + key + '"]');
			if ( ! $setting.length ) {
				return;
			}
			// Handle dropdowns.
			if ( $setting.is('select') ) {
				$value = $setting.find('[value="' + value + '"]');

				if ( $value.length ) {
					$setting.find('option').prop( 'selected', false );
					$value.prop( 'selected', true );
				} else {
					// If we can't find the desired value, record what *is* selected.
					this.model.set( key, $setting.find(':selected').val() );
				}
			// Handle text inputs and textareas.
			} else if ( $setting.is('input[type="text"], textarea, input[type="number"]') ) {
				if ( ! $setting.is(':focus') ) {
					$setting.val( value );
				}
			// Handle checkboxes.
			} else if ( $setting.is('input[type="checkbox"]') ) {
				$setting.prop( 'checked', !! value && 'false' !== value );
			} else {
				$setting.val( value );
			}
		}
	});
});
</script>
<?php
		}

		/* functions */

		public function get_slick_defaults( $property = null ) {
			$res = array(
				'accessibility' => true,
				'adaptiveHeight' => false,
				'appendArrows' => 'element',
				'appendDots' => 'element',
				'arrows' => true,
				'asNavFor' => null,
				'prevArrow' => '<button class="slick-prev" aria-label="Previous" type="button">Previous</button>',
				'nextArrow' => '<button class="slick-next" aria-label="Next" type="button">Next</button>',
				'autoplay' => false,
				'autoplaySpeed' => 3000,
				'centerMode' => false,
				'centerPadding' => '50px',
				'cssEase' => 'ease',
				'customPaging' => 'function',
				'dots' => false,
				'dotsClass' => 'slick-dots',
				'draggable' => true,
				'easing' => 'linear',
				'edgeFriction' => 0.35,
				'fade' => false,
				'focusOnSelect' => false,
				'focusOnChange' => false,
				'infinite' => true,
				'initialSlide' => 0,
				'lazyLoad' => 'ondemand',
				'mobileFirst' => false,
				'pauseOnHover' => true,
				'pauseOnFocus' => true,
				'pauseOnDotsHover' => false,
				'respondTo' => 'window',
				'responsive' => 'object',
				'rows' => 1,
				'rtl' => false,
				'slide' => 'element',
				'slidesPerRow' => 1,
				'slidesToShow' => 1,
				'slidesToScroll' => 1,
				'speed' => 500,
				'swipe' => true,
				'swipeToSlide' => false,
				'touchMove' => true,
				'touchThreshold' => 5,
				'useCSS' => true,
				'useTransform' => true,
				'variableWidth' => false,
				'vertical' => false,
				'verticalSwiping' => false,
				'waitForAnimate' => true,
				'zIndex' => 1000,
			);
			if ( ! empty($property) && is_string($property) ) {
				$res = array_key_exists($property, $res) ? $res[ $property ] : false;
			}
			return $res;
		}

		public function get_slick_settings( $gallery_settings = array(), $property = null, $remove_defaults = false ) {
			$res = apply_filters('halftheory_helper_gallery_carousel_slick_settings', array_merge($this->slick_settings, $gallery_settings));
			$defaults = $this->get_slick_defaults();
			// remove problematic values.
			foreach ( $res as $key => &$value ) {
				// valid key?
				if ( ! array_key_exists($key, $defaults) ) {
					unset($res[ $key ]);
					continue;
				}
				// js things.
				if ( in_array($defaults[ $key ], array( 'element', 'function', 'object' ), true) ) {
					unset($res[ $key ]);
					continue;
				}
				// check data types.
				if ( $value !== $defaults[ $key ] ) {
					if ( is_null($defaults[ $key ]) || is_string($defaults[ $key ]) ) {
						$value = (string) $value;
					} elseif ( is_bool($defaults[ $key ]) ) {
						$value = function_exists('is_true') ? is_true($value) : (bool) $value;
					} elseif ( is_float($defaults[ $key ]) ) {
						$value = (float) $value;
					} elseif ( is_int($defaults[ $key ]) ) {
						$value = (int) $value;
					}
				}
				if ( $remove_defaults && $value === $defaults[ $key ] ) {
					unset($res[ $key ]);
					continue;
				}
			}
			if ( ! empty($property) && is_string($property) ) {
				$res = array_key_exists($property, $res) ? $res[ $property ] : false;
			} else {
				ksort($res);
			}
			return $res;
		}

		private function shortcode_attr_to_slick_settings( $attr = array() ) {
			$attr = (array) $attr;
            $res = array();
			if ( empty($attr) ) {
				return $res;
			}
			// keys could be lowercase.
			$defaults = $this->get_slick_defaults();
        	$keys = array_map('strtolower', array_keys($defaults));
        	$defaults_keymap = array_combine($keys, array_keys($defaults));
        	// find relevant values.
			foreach ( $attr as $key => $value ) {
				if ( strpos($key, 'carousel_') === 0 ) {
					$key = str_replace('carousel_', '', $key);
					if ( array_key_exists($key, $defaults) ) {
						$res[ $key ] = $value;
					} elseif ( array_key_exists($key, $defaults_keymap) ) {
						if ( ! array_key_exists($defaults_keymap[ $key ], $res) ) {
							$res[ $defaults_keymap[ $key ] ] = $value;
						}
					}
				}
			}
			if ( ! array_key_exists('slidesToShow', $res) && array_key_exists('columns', $attr) ) {
				$res['slidesToShow'] = $attr['columns'];
			}
			return $res;
		}
	}
endif;
