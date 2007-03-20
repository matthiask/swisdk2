<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class ImageManager {
		protected static $types = array();

		/**
		* Register a ImageManager command array for a type.
		*/
		public static function register_type($type, $commands)
		{
			ImageManager::$types[$type] = $commands;
		}

		/**
		* @return true if the type has been registered, false otherwise
		*/
		public static function type_exists($type)
		{
			return isset(ImageManager::$types[$type]);
		}

		/**
		* Wrapper for ImageManager::generate() without possibility to specify
		* additional ImageManager commands.
		*/
		public static function generate_type($file, $type, $htdocs_data_dir,
			$regenerate=false)
		{
			if(!ImageManager::type_exists($type))
				return false;
			return ImageManager::generate($file, $type, $htdocs_data_dir,
				array(), $regenerate);
		}

		public static function generate_types($file, $htdocs_data_dir,
			$regenerate=false)
		{
			foreach(ImageManager::$types as $type => &$dummy)
				ImageManager::generate($file, $type, $htdocs_data_dir,
					array(), $regenerate);
		}

		/**
		* Generate an image (thumb, whatever)
		*
		* @param $file		Full path to image file
		* @param $type		Info (file name token and type used
		*			while registering if that has been done)
		* @param $htdocs_data_dir	Subdirectory below HTDOCS_DATA_ROOT
		*			where the resulting image should be stored
		* @param $commands	ImageManager command array. See process_commands
		*			for a description.
		*			This array is processed first, the commands array
		*			for the type next if the type has been registered.
		* @param $regenerate	Force regeneration of image if it exists
		*			already
		*
		* Example usage:
		*
		* ImageManager::generate($file, 'thumb', 'gallery/thumbs', array(
		*	array('scale_bounding_box', 150, 150)));
		*/
		public static function generate($file, $type, $htdocs_data_dir,
			$commands=array(), $regenerate=false)
		{
			if(!file_exists($file))
				return null;

			Swisdk::require_htdocs_data_directory($htdocs_data_dir);
			$thumb_name = ImageManager::filename($file, $type);
			$thumb = HTDOCS_DATA_ROOT.$htdocs_data_dir.'/'.$thumb_name;

			if($regenerate || !file_exists($thumb)) {
				SWisdk::require_data_directory('scratch');
				$tmp = DATA_ROOT.'scratch/'.uniqid().$thumb_name;
				copy($file, $tmp);
				ImageManager::process_commands($tmp, $commands);
				if(ImageManager::type_exists($type))
					ImageManager::process_commands($tmp,
						ImageManager::$types[$type]);
				rename($tmp, $thumb);
			}

			return $thumb_name;
		}

		public static function process_types($file, $htdocs_data_dir, $commands)
		{
			foreach(ImageManager::$types as $type => &$dummy) {
				$fname = HTDOCS_DATA_ROOT.$htdocs_data_dir.'/'
					.ImageManager::filename($file, $type);
				ImageManager::process_commands($fname, $commands);
			}
		}

		/**
		* Process a ImageManager commands array
		*
		* The array must be structured as follows:
		*
		* array(
		*  	array('command', arg1, arg2, arg3, ...),
		*	array('command', arg1, ...)
		* )
		*
		* The command must be the name of a ImageManager method
		* without the leading 'transform_'
		*
		* Example usage:
		*
		* ImageManager::process_commands($file, array(
		*	array('scale_bounding_box', 150, 100),
		*	array('grayscale'),
		*	array('tint', 50, 'red')));
		*
		* @param $file		Full path to image file
		* @param $commands	Array of commands
		*/
		public static function process_commands($file, $commands)
		{
			foreach($commands as $args) {
				if(!is_array($args))
					continue;

				$cmd = array_shift($args);
				array_unshift($args, $file);

				call_user_func_array(array('ImageManager', 'transform_'.$cmd),
					$args);
			}
		}

		/**
		 * Scale image so that it fits into a box while preserving the aspect ratio
		 *
		 * @param $file		Full path to the image
		 * @param $width	Width of bounding box
		 * @param $height	Height of bounding box
		 */
		public static function transform_scale_bounding_box($file, $width, $height)
		{
			$s = getimagesize($file);
			if($s[0]/$width > $s[1]/$height)
				ImageManager::transform_scale($file, $width, null);
			else
				ImageManager::transform_scale($file, null, $height);
		}

		/**
		 * Scale image so that it does not exceed a certain width
		 *
		 * @param $file 	Full path to the image
		 * @param $width 	Width which should not be exceeded
		 * @param $scale_up 	Scale image up if the image is smaller
		 */
		public static function transform_scale_width($file, $width, $scale_up=false)
		{
			$s = getimagesize($file);
			if($s[0]<=$width && !$scale_up)
				return;

			ImageManager::transform_scale($file, $width, null);
		}

		/**
		 * Scale image so that it does not exceed a certain height
		 *
		 * @param $file 	Full path to the image
		 * @param $height 	Height which should not be exceeded
		 * @param $scale_up 	Scale image up if the image is smaller
		 */
		public static function transform_scale_height($file, $height, $scale_up=false)
		{
			$s = getimagesize($file);
			if($s[1]<=$height && !$scale_up)
				return;

			ImageManager::transform_scale($file, null, $height);
		}

		/**
		 * Scale an image (preseves aspect ratio if you pass NULL for either width
		 * or height)
		 *
		 * @param $width
		 * @param $height
		 */
		public static function transform_scale($file, $width, $height)
		{
			$cmd = sprintf('mogrify -quality 85 -geometry %sx%s %s -sharpen 0.1',
				($w=intval($width))?$w:'',
				($h=intval($height))?$h:'',
				escapeshellarg($file));
			exec($cmd);
		}

		/**
		 * Rotate an image
		 *
		 * @param $angle
		 */
		public static function transform_rotate($file, $angle)
		{
			$cmd = sprintf('mogrify -quality 100 -rotate %d %s', $angle,
				escapeshellarg($file));
			exec($cmd);
		}

		/**
		 * Crop an image
		 *
		 * @param $width 	Crop area width
		 * @param $height 	Crop area height
		 * @param $x 		Horizontal offset from the left border
		 * @param $y 		Vertical offset from the top border
		 */
		public static function transform_crop($file, $width, $height, $x, $y)
		{
			$cmd = sprintf('mogrify -quality 100 -crop %dx%d+%d+%d %s',
				intval($width),
				intval($height),
				intval($x),
				intval($y),
				escapeshellarg($file));
			exec($cmd);
		}

		/**
		 * Tint an image
		 *
		 * @param $percent 	Tint (0-100)
		 * @param $color 	Color which should be used
		 */
		public static function transform_tint($file, $percent, $color)
		{
			$cmd = sprintf('mogrify -quality 100 -tint %d%% -fill %s %s',
				$percent,
				escapeshellarg($color),
				escapeshellarg($file));
			exec($cmd);
		}

		/**
		 * Convert an image to grayscale
		 */
		public static function transform_grayscale($file)
		{
			$cmd = sprintf('mogrify -quality 100 -type GrayScale %s',
				escapeshellarg($file));
			exec($cmd);
		}

		/**
		 * Filename mangling
		 *
		 * Example:
		 *
		 * ImageManager::filename('DSC_0039.JPG', 'admin_thumb');
		 */
		public static function filename($file, $token=null)
		{
			$info = pathinfo($file);

			if($token===null)
				return $file;
			else if(is_array($token)) {
				$r = array();
				foreach($token as $t)
					$r[$t] = $info['filename'].'__'.$t.'.'
						.$info['extension'];
				return $r;
			}

			return $info['filename']
				.'__'.$token.'.'.$info['extension'];
		}
	}

?>
