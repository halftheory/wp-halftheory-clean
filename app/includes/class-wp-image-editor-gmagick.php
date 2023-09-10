<?php
/**
 * WordPress Gmagick Image Editor
 *
 * @package WordPress
 * @subpackage Image_Editor
 */

/**
 * WordPress Image Editor Class for Image Manipulation through Gmagick PHP Module
 *
 * @see WP_Image_Editor
 */
#[AllowDynamicProperties]
class WP_Image_Editor_Gmagick extends WP_Image_Editor {
	/**
	 * Gmagick object.
	 *
	 * @var Gmagick
	 */
	protected $image;

	public function __destruct() {
		if ( $this->image instanceof Gmagick ) {
			// We don't need the original in memory anymore.
			$this->image->clear();
			$this->image->destroy();
		}
	}

	/**
	 * Checks to see if current environment supports Gmagick.
	 *
	 * @param array $args
	 * @return bool
	 */
	public static function test( $args = array() ) {
		// First, test Gmagick's extension and classes.
		if ( ! extension_loaded( 'gmagick' ) || ! class_exists( 'Gmagick', false ) || ! class_exists( 'GmagickPixel', false ) ) {
			return false;
		}

		if ( version_compare( phpversion( 'gmagick' ), '2.0', '<' ) ) {
			return false;
		}

        // Now, test for deep requirements within Gmagick.
        if ( ! defined( 'gmagick::COMPRESSION_JPEG' ) ) {
            return false;
        }

		$required_methods = array(
			'clear',
            'cropimage',
			'destroy',
            'flipimage',
            'flopimage',
			'getimage',
			'getimageblob',
            'getimageformat',
			'getimagegeometry',
            'readimage',
            'readimageblob',
            'rotateimage',
            'scaleimage',
            'setcompressionquality',
            'setimagecompression',
			'setimageformat',
            'setimageoption',
            'setimagepage',
            'writeimage',
		);

		$class_methods = array_map( 'strtolower', get_class_methods( 'Gmagick' ) );
		if ( array_diff( $required_methods, $class_methods ) ) {
			return false;
		}

        // Test for: Uncaught GmagickException: Unable to load module.
        $arr = array(
            ABSPATH . WPINC . '/images/blank.gif',
            ABSPATH . WPINC . '/images/w-logo-blue.png',
        );
        foreach ( $arr as $value ) {
            if ( file_exists($value) ) {
                try {
                    $gm = new Gmagick();
                    $gm->readimage($value);
                    $gm->clear();
                    $gm->destroy();
                } catch ( Exception $e ) {
                    return false;
                }
            } else {
                return false;
            }
        }

		return true;
	}

	/**
	 * Checks to see if editor supports the mime-type specified.
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public static function supports_mime_type( $mime_type ) {
		$gmagick_extension = strtoupper( self::get_extension( $mime_type ) );

		if ( ! $gmagick_extension ) {
			return false;
		}

		// setimageindex is optional unless mime is an animated format.
		// Here, we just say no if you are missing it and aren't loading a jpeg.
		if ( ! method_exists( 'Gmagick', 'setimageindex' ) && 'image/jpeg' !== $mime_type ) {
				return false;
		}

        $gm = new Gmagick();
        $arr = array();

		try {
            $arr = $gm->queryformats( $gmagick_extension );
		} catch ( Exception $e ) {
			return false;
		}

        if ( empty($arr) ) {
            return false;
        }
        if ( ! in_array($gmagick_extension, array_keys($arr), true) ) {
            return false;
        }
        return true;
	}

	/**
	 * Loads image from $this->file into new Gmagick Object.
	 *
	 * @return true|WP_Error True if loaded; WP_Error on failure.
	 */
	public function load() {
		if ( $this->image instanceof Gmagick ) {
			return true;
		}

		if ( ! is_file( $this->file ) && ! wp_is_stream( $this->file ) ) {
			return new WP_Error( 'error_loading_image', __( 'File doesn&#8217;t exist?' ), $this->file );
		}

		/*
		 * Even though Gmagick uses less PHP memory than GD, set higher limit
		 * for users that have low PHP.ini limits.
		 */
		wp_raise_memory_limit( 'image' );

		try {
			$this->image    = new Gmagick();
			$file_extension = strtolower( pathinfo( $this->file, PATHINFO_EXTENSION ) );

			if ( 'pdf' === $file_extension ) {
				$pdf_loaded = $this->pdf_load_source();

				if ( is_wp_error( $pdf_loaded ) ) {
					return $pdf_loaded;
				}
			} else {
				if ( wp_is_stream( $this->file ) ) {
					// Due to reports of issues with streams with `Gmagick::readimageFile()`, uses `Gmagick::readimageblob()` instead.
					$this->image->readimageblob( file_get_contents( $this->file ), $this->file );
				} else {
					$this->image->readimage( $this->file );
				}
			}

			// Select the first frame to handle animated images properly.
			if ( is_callable( array( $this->image, 'setimageindex' ) ) ) {
				$this->image->setimageindex( 0 );
			}

			$this->mime_type = $this->get_mime_type( $this->image->getimageformat() );
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_image', $e->getMessage(), $this->file );
		}

		$updated_size = $this->update_size();

		if ( is_wp_error( $updated_size ) ) {
			return $updated_size;
		}

		return $this->set_quality();
	}

	/**
	 * Sets Image Compression quality on a 1-100% scale.
	 *
	 * @param int $quality Compression Quality. Range: [1,100]
	 * @return true|WP_Error True if set successfully; WP_Error on failure.
	 */
	public function set_quality( $quality = null ) {
		$quality_result = parent::set_quality( $quality );
		if ( is_wp_error( $quality_result ) ) {
			return $quality_result;
		} else {
			$quality = $this->get_quality();
		}

		try {
			switch ( $this->mime_type ) {
				case 'image/jpeg':
					$this->image->setcompressionquality( $quality );
					$this->image->setimagecompression( gmagick::COMPRESSION_JPEG );
					break;
				case 'image/webp':
					$webp_info = wp_get_webp_info( $this->file );

					if ( 'lossless' === $webp_info['type'] ) {
						// Use WebP lossless settings.
						$this->image->setcompressionquality( 100 );
						$this->image->setimageoption( 'webp:lossless', 'true' );
					} else {
						$this->image->setcompressionquality( $quality );
					}
					break;
				default:
					$this->image->setcompressionquality( $quality );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_quality_error', $e->getMessage() );
		}
		return true;
	}


	/**
	 * Sets or updates current image size.
	 *
	 * @param int $width
	 * @param int $height
	 * @return true|WP_Error
	 */
	protected function update_size( $width = null, $height = null ) {
		if ( ! $width ) {
			$width = $this->image->getimagewidth();
		}

		if ( ! $height ) {
            $width = $this->image->getimageheight();
		}

		return parent::update_size( $width, $height );
	}

	/**
	 * Resizes current image.
	 *
	 * At minimum, either a height or width must be provided.
	 * If one of the two is set to null, the resize will
	 * maintain aspect ratio according to the provided dimension.
	 *
	 * @param int|null $max_w Image width.
	 * @param int|null $max_h Image height.
	 * @param bool     $crop
	 * @return true|WP_Error
	 */
	public function resize( $max_w, $max_h, $crop = false ) {
		if ( ( $this->size['width'] == $max_w ) && ( $this->size['height'] == $max_h ) ) {
			return true;
		}

		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __( 'Could not calculate resized image dimensions' ) );
		}

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		if ( $crop ) {
			return $this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h );
		}

		// Execute the resize.
		$thumb_result = $this->thumbnail_image( $dst_w, $dst_h );
		if ( is_wp_error( $thumb_result ) ) {
			return $thumb_result;
		}

		return $this->update_size( $dst_w, $dst_h );
	}

	/**
	 * Efficiently resize the current image
	 *
	 * This is a WordPress specific implementation of Gmagick::thumbnailImage(),
	 * which resizes an image to given dimensions and removes any associated profiles.
	 *
	 * @param int    $dst_w       The destination width.
	 * @param int    $dst_h       The destination height.
	 * @param string $filter_name Optional. The Gmagick filter to use when resizing. Default 'FILTER_TRIANGLE'.
	 * @param bool   $strip_meta  Optional. Strip all profiles, excluding color profiles, from the image. Default true.
	 * @return void|WP_Error
	 */
	protected function thumbnail_image( $dst_w, $dst_h, $filter_name = 'FILTER_TRIANGLE', $strip_meta = true ) {
		$allowed_filters = array(
			'FILTER_POINT',
			'FILTER_BOX',
			'FILTER_TRIANGLE',
			'FILTER_HERMITE',
			'FILTER_HANNING',
			'FILTER_HAMMING',
			'FILTER_BLACKMAN',
			'FILTER_GAUSSIAN',
			'FILTER_QUADRATIC',
			'FILTER_CUBIC',
			'FILTER_CATROM',
			'FILTER_MITCHELL',
			'FILTER_LANCZOS',
			'FILTER_BESSEL',
			'FILTER_SINC',
		);

		/**
		 * Set the filter value if '$filter_name' name is in the allowed list and the related
		 * Gmagick constant is defined or fall back to the default filter.
		 */
		if ( in_array( $filter_name, $allowed_filters, true ) && defined( 'Gmagick::' . $filter_name ) ) {
			$filter = constant( 'Gmagick::' . $filter_name );
		} else {
			$filter = defined( 'Gmagick::FILTER_TRIANGLE' ) ? Gmagick::FILTER_TRIANGLE : false;
		}

		/**
		 * Filters whether to strip metadata from images when they're resized.
		 *
		 * This filter only applies when resizing using the Gmagick editor since GD
		 * always strips profiles by default.
		 *
		 * @param bool $strip_meta Whether to strip image metadata during resizing. Default true.
		 */
		if ( apply_filters( 'image_strip_meta', $strip_meta ) ) {
			$this->strip_meta(); // Fail silently if not supported.
		}

		try {
			/*
			 * To be more efficient, resample large images to 5x the destination size before resizing
			 * whenever the output size is less that 1/3 of the original image size (1/3^2 ~= .111),
			 * unless we would be resampling to a scale smaller than 128x128.
			 */
			if ( is_callable( array( $this->image, 'resampleimage' ) ) ) {
				$resize_ratio  = ( $dst_w / $this->size['width'] ) * ( $dst_h / $this->size['height'] );
				$sample_factor = 5;

				if ( $resize_ratio < .111 && ( $dst_w * $sample_factor > 128 && $dst_h * $sample_factor > 128 ) ) {
					$this->image->resampleimage( $dst_w * $sample_factor, $dst_h * $sample_factor );
				}
			}

			/*
			 * Use resizeimage() when it's available and a valid filter value is set.
			 * Otherwise, fall back to the scaleimage() method for resizing, which
			 * results in better image quality over resizeimage() with default filter
			 * settings and retains backward compatibility with pre 4.5 functionality.
			 */
			if ( is_callable( array( $this->image, 'resizeimage' ) ) && $filter ) {
				$this->image->setimageoption( 'filter:support', '2.0' );
				$this->image->resizeimage( $dst_w, $dst_h, $filter, 1 );
			} else {
				$this->image->scaleimage( $dst_w, $dst_h );
			}

			// Set appropriate quality settings after resizing.
			if ( 'image/jpeg' === $this->mime_type ) {
				if ( is_callable( array( $this->image, 'unsharpmaskimage' ) ) ) {
					$this->image->unsharpmaskimage( 0.25, 0.25, 8, 0.065 );
				}

				$this->image->setimageoption( 'jpeg:fancy-upsampling', 'off' );
			}

			if ( 'image/png' === $this->mime_type ) {
				$this->image->setimageoption( 'png:compression-filter', '5' );
				$this->image->setimageoption( 'png:compression-level', '9' );
				$this->image->setimageoption( 'png:compression-strategy', '1' );
				$this->image->setimageoption( 'png:exclude-chunk', 'all' );
			}

			// Limit the bit depth of resized images to 8 bits per channel.
			if ( is_callable( array( $this->image, 'getimagedepth' ) ) && is_callable( array( $this->image, 'setimagedepth' ) ) ) {
				if ( 8 < $this->image->getimagedepth() ) {
					$this->image->setimagedepth( 8 );
				}
			}

			if ( is_callable( array( $this->image, 'setimageinterlacescheme' ) ) && defined( 'Gmagick::INTERLACE_NO' ) ) {
				$this->image->setimageinterlacescheme( Gmagick::INTERLACE_NO );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_resize_error', $e->getMessage() );
		}
	}

	/**
	 * Create multiple smaller images from a single source.
	 *
	 * Attempts to create all sub-sizes and returns the meta data at the end. This
	 * may result in the server running out of resources. When it fails there may be few
	 * "orphaned" images left over as the meta data is never returned and saved.
	 *
	 * As of 5.3.0 the preferred way to do this is with `make_subsize()`. It creates
	 * the new images one at a time and allows for the meta data to be saved after
	 * each new image is created.
	 *
	 * @param array $sizes {
	 *     An array of image size data arrays.
	 *
	 *     Either a height or width must be provided.
	 *     If one of the two is set to null, the resize will
	 *     maintain aspect ratio according to the provided dimension.
	 *
	 *     @type array $size {
	 *         Array of height, width values, and whether to crop.
	 *
	 *         @type int  $width  Image width. Optional if `$height` is specified.
	 *         @type int  $height Image height. Optional if `$width` is specified.
	 *         @type bool $crop   Optional. Whether to crop the image. Default false.
	 *     }
	 * }
	 * @return array An array of resized images' metadata by size.
	 */
	public function multi_resize( $sizes ) {
		$metadata = array();

		foreach ( $sizes as $size => $size_data ) {
			$meta = $this->make_subsize( $size_data );

			if ( ! is_wp_error( $meta ) ) {
				$metadata[ $size ] = $meta;
			}
		}

		return $metadata;
	}

	/**
	 * Create an image sub-size and return the image meta data value for it.
	 *
	 * @param array $size_data {
	 *     Array of size data.
	 *
	 *     @type int  $width  The maximum width in pixels.
	 *     @type int  $height The maximum height in pixels.
	 *     @type bool $crop   Whether to crop the image to exact dimensions.
	 * }
	 * @return array|WP_Error The image data array for inclusion in the `sizes` array in the image meta,
	 *                        WP_Error object on error.
	 */
	public function make_subsize( $size_data ) {
		if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
			return new WP_Error( 'image_subsize_create_error', __( 'Cannot resize the image. Both width and height are not set.' ) );
		}

		$orig_size  = $this->size;
		$orig_image = $this->image->current();

		if ( ! isset( $size_data['width'] ) ) {
			$size_data['width'] = null;
		}

		if ( ! isset( $size_data['height'] ) ) {
			$size_data['height'] = null;
		}

		if ( ! isset( $size_data['crop'] ) ) {
			$size_data['crop'] = false;
		}

		$resized = $this->resize( $size_data['width'], $size_data['height'], $size_data['crop'] );

		if ( is_wp_error( $resized ) ) {
			$saved = $resized;
		} else {
			$saved = $this->_save( $this->image );

			$this->image->clear();
			$this->image->destroy();
			$this->image = null;
		}

		$this->size  = $orig_size;
		$this->image = $orig_image;

		if ( ! is_wp_error( $saved ) ) {
			unset( $saved['path'] );
		}

		return $saved;
	}

	/**
	 * Crops Image.
	 *
	 * @param int  $src_x   The start x position to crop from.
	 * @param int  $src_y   The start y position to crop from.
	 * @param int  $src_w   The width to crop.
	 * @param int  $src_h   The height to crop.
	 * @param int  $dst_w   Optional. The destination width.
	 * @param int  $dst_h   Optional. The destination height.
	 * @param bool $src_abs Optional. If the source crop points are absolute.
	 * @return true|WP_Error
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}

		try {
			$this->image->cropimage( $src_w, $src_h, $src_x, $src_y );
			$this->image->setimagepage( $src_w, $src_h, 0, 0 );

			if ( $dst_w || $dst_h ) {
				// If destination width/height isn't specified,
				// use same as width/height from source.
				if ( ! $dst_w ) {
					$dst_w = $src_w;
				}
				if ( ! $dst_h ) {
					$dst_h = $src_h;
				}

				$thumb_result = $this->thumbnail_image( $dst_w, $dst_h );
				if ( is_wp_error( $thumb_result ) ) {
					return $thumb_result;
				}

				return $this->update_size();
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_crop_error', $e->getMessage() );
		}

		return $this->update_size();
	}

	/**
	 * Rotates current image counter-clockwise by $angle.
	 *
	 * @param float $angle
	 * @return true|WP_Error
	 */
	public function rotate( $angle ) {
		/**
		 * $angle is 360-$angle because Gmagick rotates clockwise
		 * (GD rotates counter-clockwise)
		 */
		try {
			$this->image->rotateimage( new GmagickPixel( 'none' ), 360 - $angle );

			// Since this changes the dimensions of the image, update the size.
			$result = $this->update_size();
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$this->image->setimagepage( $this->size['width'], $this->size['height'], 0, 0 );
		} catch ( Exception $e ) {
			return new WP_Error( 'image_rotate_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Flips current image.
	 *
	 * @param bool $horz Flip along Horizontal Axis
	 * @param bool $vert Flip along Vertical Axis
	 * @return true|WP_Error
	 */
	public function flip( $horz, $vert ) {
		try {
			if ( $horz ) {
				$this->image->flipimage();
			}

			if ( $vert ) {
				$this->image->flopimage();
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_flip_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Check if a JPEG image has EXIF Orientation tag and rotate it if needed.
	 *
	 * As ImageMagick copies the EXIF data to the flipped/rotated image, proceed only
	 * if EXIF Orientation can be reset afterwards.
	 *
	 * @return bool|WP_Error True if the image was rotated. False if no EXIF data or if the image doesn't need rotation.
	 *                       WP_Error if error while rotating.
	 */
	public function maybe_exif_rotate() {
		if ( is_callable( array( $this->image, 'setImageOrientation' ) ) && defined( 'Gmagick::ORIENTATION_TOPLEFT' ) ) {
			return parent::maybe_exif_rotate();
		} else {
			return new WP_Error( 'write_exif_error', __( 'The image cannot be rotated because the embedded meta data cannot be updated.' ) );
		}
	}

	/**
	 * Saves current image to file.
	 *
	 * @param string $destfilename Optional. Destination filename. Default null.
	 * @param string $mime_type    Optional. The mime-type. Default null.
	 * @return array|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		$saved = $this->_save( $this->image, $destfilename, $mime_type );

		if ( ! is_wp_error( $saved ) ) {
			$this->file      = $saved['path'];
			$this->mime_type = $saved['mime-type'];

			try {
				$this->image->setimageformat( strtoupper( $this->get_extension( $this->mime_type ) ) );
			} catch ( Exception $e ) {
				return new WP_Error( 'image_save_error', $e->getMessage(), $this->file );
			}
		}

		return $saved;
	}

	/**
	 * @param Gmagick $image
	 * @param string  $filename
	 * @param string  $mime_type
	 * @return array|WP_Error
	 */
	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename ) {
			$filename = $this->generate_filename( null, null, $extension );
		}

		try {
			// Store initial format.
			$orig_format = $this->image->getimageformat();

			$this->image->setimageformat( strtoupper( $this->get_extension( $mime_type ) ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
		}

		$write_image_result = $this->write_image( $this->image, $filename );
		if ( is_wp_error( $write_image_result ) ) {
			return $write_image_result;
		}

		try {
			// Reset original format.
			$this->image->setimageformat( $orig_format );
		} catch ( Exception $e ) {
			return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
		}

		// Set correct file permissions.
		$stat  = stat( dirname( $filename ) );
		$perms = $stat['mode'] & 0000666; // Same permissions as parent folder, strip off the executable bits.
		chmod( $filename, $perms );

		return array(
			'path'      => $filename,
			/** This filter is documented in wp-includes/class-wp-image-editor-gd.php */
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

	/**
	 * Writes an image to a file or stream.
	 *
	 * @param Gmagick $image
	 * @param string  $filename The destination filename or stream URL.
	 * @return true|WP_Error
	 */
	private function write_image( $image, $filename ) {
		if ( wp_is_stream( $filename ) ) {
			/*
			 * Due to reports of issues with streams with `Gmagick::writeimageFile()` and `Gmagick::writeimage()`, copies the blob instead.
			 * Checks for exact type due to: https://www.php.net/manual/en/function.file-put-contents.php
			 */
			if ( file_put_contents( $filename, $image->getimageblob() ) === false ) {
				return new WP_Error(
					'image_save_error',
					sprintf(
						/* translators: %s: PHP function name. */
						__( '%s failed while writing image to stream.' ),
						'<code>file_put_contents()</code>'
					),
					$filename
				);
			} else {
				return true;
			}
		} else {
			$dirname = dirname( $filename );

			if ( ! wp_mkdir_p( $dirname ) ) {
				return new WP_Error(
					'image_save_error',
					sprintf(
						/* translators: %s: Directory path. */
						__( 'Unable to create directory %s. Is its parent directory writable by the server?' ),
						esc_html( $dirname )
					)
				);
			}

			try {
				return $image->writeimage( $filename );
			} catch ( Exception $e ) {
				return new WP_Error( 'image_save_error', $e->getMessage(), $filename );
			}
		}
	}

	/**
	 * Streams current image to browser.
	 *
	 * @param string $mime_type The mime type of the image.
	 * @return true|WP_Error True on success, WP_Error object on failure.
	 */
	public function stream( $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( null, $mime_type );

		try {
			// Temporarily change format for stream.
			$this->image->setimageformat( strtoupper( $extension ) );

			// Output stream of image content.
			header( "Content-Type: $mime_type" );
			print $this->image->getimageblob();

			// Reset image to original format.
			$this->image->setimageformat( $this->get_extension( $this->mime_type ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'image_stream_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Strips all image meta except color profiles from an image.
	 *
	 * @return true|WP_Error True if stripping metadata was successful. WP_Error object on error.
	 */
	protected function strip_meta() {

		if ( ! is_callable( array( $this->image, 'getimageprofile' ) ) ) {
			return new WP_Error(
				'image_strip_meta_error',
				sprintf(
					/* translators: %s: ImageMagick method name. */
					__( '%s is required to strip image meta.' ),
					'<code>Gmagick::getimageprofile()</code>'
				)
			);
		}

		if ( ! is_callable( array( $this->image, 'removeimageprofile' ) ) ) {
			return new WP_Error(
				'image_strip_meta_error',
				sprintf(
					/* translators: %s: ImageMagick method name. */
					__( '%s is required to strip image meta.' ),
					'<code>Gmagick::removeimageprofile()</code>'
				)
			);
		}

		/*
		 * Protect a few profiles from being stripped for the following reasons:
		 *
		 * - icc:  Color profile information
		 * - icm:  Color profile information
		 * - iptc: Copyright data
		 * - exif: Orientation data
		 * - xmp:  Rights usage data
		 */
		$protected_profiles = array(
			'icc',
			'icm',
			'iptc',
			'exif',
			'xmp',
		);

		try {
			// Strip profiles.
            $key = $this->image->getimageprofile();
			if ( ! in_array( $key, $protected_profiles, true ) ) {
				$this->image->removeimageprofile( $key );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'image_strip_meta_error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Sets up Gmagick for PDF processing.
	 * Increases rendering DPI and only loads first page.
	 *
	 * @return string|WP_Error File to load or WP_Error on failure.
	 */
	protected function pdf_setup() {
		try {
			// By default, PDFs are rendered in a very low resolution.
			// We want the thumbnail to be readable, so increase the rendering DPI.
			$this->image->setimageresolution( 128, 128 );

			// Only load the first page.
			return $this->file . '[0]';
		} catch ( Exception $e ) {
			return new WP_Error( 'pdf_setup_failed', $e->getMessage(), $this->file );
		}
	}

	/**
	 * Load the image produced by Ghostscript.
	 *
	 * Includes a workaround for a bug in Ghostscript 8.70 that prevents processing of some PDF files
	 * when `use-cropbox` is set.
	 *
	 * @return true|WP_Error
	 */
	protected function pdf_load_source() {
		$filename = $this->pdf_setup();

		if ( is_wp_error( $filename ) ) {
			return $filename;
		}

		try {
			// When generating thumbnails from cropped PDF pages, Imagemagick uses the uncropped
			// area (resulting in unnecessary whitespace) unless the following option is set.
			$this->image->setimageoption( 'pdf:use-cropbox', true );

			// Reading image after Gmagick instantiation because `setimageresolution`
			// only applies correctly before the image is read.
			$this->image->readimage( $filename );
		} catch ( Exception $e ) {
			// Attempt to run `gs` without the `use-cropbox` option. See #48853.
			$this->image->setimageoption( 'pdf:use-cropbox', false );

			$this->image->readimage( $filename );
		}

		return true;
	}
}
