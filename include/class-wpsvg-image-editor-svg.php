<?php

class WPSVG_Image_Editor_SVG extends WP_Image_Editor {
	protected $default_mime_type = 'image/svg+xml';
	private $xml_document;
	private $transform_group;

	/*
	resize					OK
	crop 					OK
	crop -> crop 			OK
	crop -> resize			OK
	resize -> crop 			OK
	crop -> resize -> crop	OK

	flip					-
	rotate					-

	*/

	public static function test( $args = array() ) {
		// check if xml extension is loaded
		return function_exists('simplexml_load_file');
	}
	public static function supports_mime_type( $mime_type ) {
		$inst = new self('');
		return $mime_type == $inst->default_mime_type;
	}

	public function load() {
		if ( $this->xml_document )
			return true;

		if ( ! is_file( $this->file ) && ! preg_match( '|^https?://|', $this->file ) )
			return new WP_Error( 'error_loading_image', __('File doesn&#8217;t exist?'), $this->file );

		$this->xml_document = simplexml_load_file( $this->file );
		$this->xml_document->registerXPathNamespace('svg', 'http://www.w3.org/2000/svg');

		$this->update_size( );
	//	$this->get_transform_group();
	}

	public function resize( $max_w, $max_h, $crop = false ) {
		if ( ( $this->size['width'] == $max_w ) && ( $this->size['height'] == $max_h ) )
			return true;

		if ( ! is_wp_error( $this->_resize( $max_w, $max_h, $crop ) )) {
			return true;
		}
		return new WP_Error( 'image_resize_error', __('Image resize failed.'), $this->xml_document );
	}
	protected function _resize( $max_w, $max_h, $crop = false ) {
		$dims = image_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __('Could not calculate resized image dimensions'), $this->file );
		}
		// crop this
		// resize this

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		if ( $crop ) {
			$this->crop( $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $src_abs = false );
 		} else {
			$this->xml_document[0]['width']  = $dst_w . 'px';
			$this->xml_document[0]['height'] = $dst_h . 'px';
 		}
		$this->update_size( $dst_w, $dst_h );
		return $this->xml_document;
	}
	/**
	 * Resize multiple images from a single source.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param array $sizes {
	 *     An array of image size arrays. Default sizes are 'small', 'medium', 'large'.
	 *
	 *     Either a height or width must be provided.
	 *     If one of the two is set to null, the resize will
	 *     maintain aspect ratio according to the provided dimension.
	 *
	 *     @type array $size {
	 *         @type int  ['width']  Optional. Image width.
	 *         @type int  ['height'] Optional. Image height.
	 *         @type bool ['crop']   Optional. Whether to crop the image. Default false.
	 *     }
	 * }
	 * @return array An array of resized images' metadata by size.
	 */
	public function multi_resize( $sizes ) {
		$metadata = array();
		$orig_size = $this->size;

		foreach ( $sizes as $size => $size_data ) {
			if ( ! isset( $size_data['width'] ) && ! isset( $size_data['height'] ) ) {
				continue;
			}

			if ( ! isset( $size_data['width'] ) ) {
				$size_data['width'] = null;
			}
			if ( ! isset( $size_data['height'] ) ) {
				$size_data['height'] = null;
			}

			if ( ! isset( $size_data['crop'] ) ) {
				$size_data['crop'] = false;
			}

			$image = $this->_resize( $size_data['width'], $size_data['height'], $size_data['crop'] );

			if( ! is_wp_error( $image ) ) {
				$resized = $this->_save( $image );

				if ( ! is_wp_error( $resized ) && $resized ) {
					unset( $resized['path'] );
					$metadata[$size] = $resized;
				}
			}

			$this->size = $orig_size;
		}

		return $metadata;
	}

	/**
	 * Crops Image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string|int $src The source file or Attachment ID.
	 * @param int $src_x The start x position to crop from.
	 * @param int $src_y The start y position to crop from.
	 * @param int $src_w The width to crop.
	 * @param int $src_h The height to crop.
	 * @param int $dst_w Optional. The destination width.
	 * @param int $dst_h Optional. The destination height.
	 * @param boolean $src_abs Optional. If the source crop points are absolute.
	 * @return boolean|WP_Error
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}
		// add transform from values

		$viewbox = $this->get_viewbox();

		$original_size = $this->size;

		$factor = $viewbox->width / $original_size['width'];

		$this->_resize( $viewbox->width , $viewbox->height , false );

		$viewbox->x += $src_x*$factor;
		$viewbox->y += $src_y*$factor;
		$viewbox->width = $src_w*$factor;
		$viewbox->height = $src_h*$factor;

		$this->set_viewbox( $viewbox );

		$this->xml_document[0]['width']  = ( ! is_null($dst_w) ? $dst_w : $src_w ) . 'px';
		$this->xml_document[0]['height'] = ( ! is_null($dst_h) ? $dst_h : $src_h )  . 'px';

		$this->_resize( $original_size['width'] , $original_size['height'] , false );

		$this->update_size( );

		return true;

		//return new WP_Error( 'image_crop_error', __('Image crop failed.'), $this->file );
	}

	/**
	 * Rotates current image counter-clockwise by $angle.
	 * Ported from image-edit.php
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param float $angle
	 * @return boolean|WP_Error
	 */
	public function rotate( $angle ) {
	/*
		list( $prev_x , $prev_y , $prev_w , $prev_h ) = explode( ' ' , $this->xml_document[0]['viewBox'] );

		//$this->xml_document[0]['viewBox'] = implode(' ',$vb);
	*/
		return new WP_Error( 'image_rotate_error', __('Image rotate failed.'), $this->file );
	}

	/**
	 * Flips current image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param boolean $horz Flip along Horizontal Axis
	 * @param boolean $vert Flip along Vertical Axis
	 * @returns boolean|WP_Error
	 */
	public function flip( $horz, $vert ) {
	/*
		$transform_group = $this->get_transform_group();
		$transform = $this->get_transform();
		$viewbox = $this->get_viewbox();
		$transform->scale->x *= $vert ? -1 : 1;
		$transform->scale->y *= $horz ? -1 : 1;

		if ( $transform->scale->y < 0 ) {
			$transform->translate->y = ($viewbox->y + $viewbox->height/2)*2;
		} else {
			$transform->translate->y = 0;
		}
		if ( $transform->scale->x < 0 ) {
			$transform->translate->x = ($viewbox->x + $viewbox->width/2)*2;
		} else {
			$transform->translate->x = 0;
		}
		$this->set_transform( $transform );
		*/
		return new WP_Error( 'image_flip_error', __('Image flip failed.'), $this->file );
	}
	/**
	 * Returns stream of current image.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string $mime_type
	 */
	public function stream( $mime_type = null ) {
		header( "Content-Type: $mime_type" );
		echo $this->xml_document->asXML();
	}

	/**
	 * Sets or updates current image size.
	 *
	 * @since 3.5.0
	 * @access protected
	 *
	 * @param int $width
	 * @param int $height
	 */
	protected function update_size( $width = null, $height = null ) {
		// read from xml attr
		if ( !$width || !$height ) {
			$width  = floatval($this->xml_document[0]['width']);
			$height = floatval($this->xml_document[0]['height']);
		}
		// calc from viewbox
		if ( !$width || !$height ) {
			$viewbox = $this->xml_document[0]['viewBox'];
			@list( $x, $y, $width, $height)  = explode( ' ', $viewbox );
			$width = floatval( $width );
			$height = floatval( $height );
		}
		parent::update_size( $width , $height );
	}

	private function get_viewbox(){
		list($x , $y , $width , $height ) = explode( ' ' , $this->xml_document[0]['viewBox'] );
		return (object) array(
			'x'			=> isset($x) ? $x : 0,
			'y'			=> isset($y) ? $y : 0,
			'width'		=> isset($width) ? $width : floatval($this->xml_document[0]['width']),
			'height'	=> isset($height) ? $height : floatval($this->xml_document[0]['height']),
		);
	}
	private function set_viewbox( $viewbox ) {
		$vb = array( $viewbox->x , $viewbox->y , $viewbox->width , $viewbox->height );
		$this->xml_document[0]['viewBox'] = implode( ' ' , $vb );
	}

	/*

	private function set_transform( $transform ) {
		$old_transform = $this->get_transform();
		foreach ( get_object_vars( $old_transform ) as $key => $values ) {
			foreach ( get_object_vars( $values ) as $dim => $value ) {
				if ( ! isset( $transform->$key ) )
					$transform->$key = (object) array();
				if ( ! isset( $transform->$key->$dim ) )
					$transform->$key->$dim = $old_transform->$key->$dim;
			}
		}

		$transforms = array();
		$transforms[] = sprintf( 'translate(%f,%f)' , $transform->translate->x , $transform->translate->y );
		$transforms[] = sprintf( 'scale(%f,%f)'     , $transform->scale->x , $transform->scale->y );
		$transforms[] = sprintf( 'rotate(%f,%f,%f)' , $transform->rotate->angle , $transform->rotate->x0 , $transform->rotate->y0 );

		$transform_group = $this->get_transform_group();
		$transform_group['transform'] = implode( ' ' , $transforms );
	}
	private function get_transform() {
		// translate scale rotate
		$transform_group = $this->get_transform_group();

		$transform = (object) array(
			'translate' => (object) array(
				'x' => 0,
				'y' => 0,
			),
			'scale' => (object) array(
				'x' => 1,
				'y' => 1,
			),
			'rotate' => (object) array(
				'angle' => 0,
				'x0' => 0,
				'y0' => 0,
			),
		);
		$transform_str = strval($transform_group['transform']);

		// scale
		$scale_matches = array();
		preg_match('/scale\((-?[\d\.]+)(,(-?[\d\.]+))?\)/',$transform_str,$scale_matches);
		if ( isset($scale_matches[1]) ) {
			$transform->scale->x = floatval($scale_matches[1]);
			if ( isset($scale_matches[3]) ) {
				$transform->scale->y = floatval($scale_matches[3]);
			} else {
				$transform->scale->y = $transform->scale->x;
			}
		}

		// scale
		$translate_matches = array();
		preg_match('/translate\((-?[\d\.]+)(,(-?[\d\.]+))?\)/',$transform_str,$translate_matches);
		if ( isset($translate_matches[1]) ) {
			$transform->translate->x = floatval($translate_matches[1]);
			if ( isset($translate_matches[3]) ) {
				$transform->translate->y = floatval($translate_matches[3]);
			} else {
				$transform->translate->y = $transform->translate->x;
			}
		}

		$rotate_matches = array();
		preg_match('/rotate\((-?[\d\.]+)(,(-?[\d\.]+),(-?[\d\.]+))?\)/',$transform_str,$rotate_matches);
		if ( isset($rotate_matches[1]) ) {
			$transform->rotate->angle = floatval($rotate_matches[1]);
			if ( isset($rotate_matches[3]) && isset($rotate_matches[4]) ) {
				$transform->rotate->x0 = floatval($rotate_matches[3]);
				$transform->rotate->y0 = floatval($rotate_matches[4]);
			}
		}

		return $transform;
	}
	private function get_transform_group() {
		if ( ! $g = @$this->xml_document->xpath("//svg:g[@class='wpsvg-transform-group']")[0]) {
			$width = floatval($this->xml_document[0]['width']);
			$height = floatval($this->xml_document[0]['height']);
			$g = new SimpleXMLElement("<g class='wpsvg-transform-group' width='$width' height='$height' />");
			xml_wrap( $this->xml_document , $g );
		}
		return $g;
	}
	*/

	/**
	 * Saves current in-memory image to file.
	 *
	 * @since 3.5.0
	 * @access public
	 *
	 * @param string $destfilename
	 * @param string $mime_type
	 * @return array|WP_Error {'path'=>string, 'file'=>string, 'width'=>int, 'height'=>int, 'mime-type'=>string}
	 */
	public function save( $filename = null, $mime_type = null ) {
		$saved = $this->_save( $this->xml_document, $filename, $mime_type );

		if ( ! is_wp_error( $saved ) ) {
			$this->file = $saved['path'];
			$this->mime_type = $saved['mime-type'];
		}

		return $saved;
	}

	protected function _save( $image, $filename = null, $mime_type = null ) {
		list( $filename, $extension, $mime_type ) = $this->get_output_format( $filename, $mime_type );

		if ( ! $filename )
			$filename = $this->generate_filename( null, null, $extension );

		if ( $this->default_mime_type == $mime_type ) {

			wp_mkdir_p( dirname( $filename ) );

			$fp = fopen( $filename, 'w' );
			if ( ! $fp )
				return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );

			fwrite( $fp, $image->asXML() );
			fclose( $fp );
		} else {
			return new WP_Error( 'image_save_error', __('Image Editor Save Failed') );
		}

		// Set correct file permissions
		$stat = stat( dirname( $filename ) );
		$perms = $stat['mode'] & 0000666; //same permissions as parent folder, strip off the executable bits
		@ chmod( $filename, $perms );

		/**
		 * Filter the name of the saved image file.
		 *
		 * @since 2.6.0
		 *
		 * @param string $filename Name of the file.
		 */
		return array(
			'path'      => $filename,
			'file'      => wp_basename( apply_filters( 'image_make_intermediate_size', $filename ) ),
			'width'     => $this->size['width'],
			'height'    => $this->size['height'],
			'mime-type' => $mime_type,
		);
	}

/*
	public function get_size() {}
	public function get_quality() {}
	public function set_quality( $quality = null ) {}
	protected function get_output_format( $filename = null, $mime_type = null ) {}
	public function generate_filename( $suffix = null, $dest_path = null, $extension = null ) {}
	public function get_suffix() {}
	protected function make_image( $filename, $function, $arguments ) {}
	protected static function get_mime_type( $extension = null ) {}
	protected static function get_extension( $mime_type = null ) {}
*/
}
