<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'content/inc.imagemanager.php';

	DBObject::has_many('GalleryAlbum', 'GalleryImage');

	class GalleryManager {
		protected static $initialized = false;

		public static function init($root=null)
		{
			if(GalleryManager::$initialized)
				return;

			Swisdk::require_htdocs_data_directory('gallery');
			define('GALLERY_HTDOCS_ROOT',
				substr(Swisdk::config_value('runtime.webroot.data', '/data')
					.'/gallery/', 1));

			Swisdk::require_data_directory('gallery');
			define('GALLERY_INCOMING_ROOT', DATA_ROOT.'gallery/');

			GalleryManager::$initialized = true;
		}

		public static function image_fullpath($album, $image)
		{
			GalleryManager::init();

			return GALLERY_INCOMING_ROOT.$album->name.'/'.$image->file;
		}

		public static function generate_images($album, $type1='thumb', $type2='full')
		{
			$types = func_get_args();
			$album = array_shift($types);
			return GalleryManager::generate_images_a($album, $types);
		}

		public static function generate_images_a($album, $types)
		{
			GalleryManager::init();

			$images = $album->related('GalleryImage');

			foreach($types as $type) {
				foreach($images as $image) {
					ImageManager::generate_type(
						GALLERY_INCOMING_ROOT.$album->name.'/'.$image->file,
						$type, 'gallery/'.$album->name);
				}
			}
		}

		public static function rescan_directory($album)
		{
			GalleryManager::init();

			$directory = GALLERY_INCOMING_ROOT.$album->name.'/';
			$files = array();
			if(file_exists($directory))
				$files = scandir($directory);
			$images = $album->related('GalleryImage')->collect('file', 'id');

			foreach($files as $file) {
				$full = $directory.$file;
				if(strpos($file, '.')===0
						|| strpos($file, '__')===0
						|| !getimagesize($full))
					continue;

				if(isset($images[$file])) {
					unset($images[$file]);
					continue;
				}

				$image = DBObject::create('GalleryImage');
				$image->gallery_album_id = $album->id();
				$image->original_file = $file;
				$image->file = $file;
				$image->title = pathinfo($file, PATHINFO_FILENAME);
				$image->sortkey = 999;
				$image->store();
			}

			if(count($images))
				DBOContainer::find_by_id('GalleryImage', $images)->delete();
		}

		public static function images($album)
		{
			$types = func_get_args();
			$album = array_shift($types);
			return GalleryManager::images_a($album, $types);
		}

		public static function images_a($album, $types)
		{
			GalleryManager::init();

			$images = $album->related('GalleryImage', array(
					':order' => 'gallery_image_sortkey'));
			return ImageManager::add_type_filename_a($images, $types);
		}

		public static function cleanup($album)
		{
			GalleryManager::init();

			Swisdk::clean_data_directory(DATA_ROOT.'scratch/');

			$images = $album->related('GalleryImage');
			$imagehash = array();

			foreach($images as $image)
				$imagehash[pathinfo($image->file, PATHINFO_FILENAME)] = true;

			$dir = HTDOCS_ROOT.GALLERY_HTDOCS_ROOT.$album->name.'/';

			if(!file_exists($dir))
				return;

			$generated_files = scandir($dir);
			foreach($generated_files as $gf) {
				if($gf{0}=='.')
					continue;
				if(!isset($imagehash[substr($gf, 0, strrpos($gf, '__'))]))
					unlink($dir.$gf);
			}
		}

		public static function nuke($album)
		{
			GalleryManager::init();

			$dir = HTDOCS_ROOT.GALLERY_HTDOCS_ROOT.$album->name.'/';
			$generated_files = scandir($dir);
			foreach($generated_files as $gf) {
				if($gf{0}=='.')
					continue;
				unlink($dir.$gf);
			}
			rmdir($dir);
		}

		public static function add_image_from_fileupload($album, $fileupload)
		{
			GalleryManager::init();

			$fdata = $fileupload->files_data();

			$image = DBObject::create('GalleryImage');

			$image->original_file = sanitizeFilename($fdata['name']);
			$image->file = uniquifyFilename($image->original_file);
			$image->title = pathinfo($image->original_file, PATHINFO_FILENAME);
			$image->set_owner($album);

			Swisdk::require_data_directory('gallery/'.$album->name);
			rename($fdata['path'], GALLERY_INCOMING_ROOT.$album->name.'/'.$image->file);

			$image->store();
			return $image;
		}

		public static function album_one_image($albums, $type='thumb')
		{
			GalleryManager::init();

			$images = DBOContainer::create('GalleryImage');
			$ids = implode(',', $albums->ids());
			$sql = <<<EOD
SELECT * FROM tbl_gallery_image
	JOIN tbl_gallery_album ON gallery_image_gallery_album_id=gallery_album_id
	WHERE gallery_album_id IN ($ids) AND gallery_image_id IN
		(SELECT MAX(gallery_image_id) FROM tbl_gallery_image gi
			WHERE gallery_image_gallery_album_id=gi.gallery_image_gallery_album_id
			GROUP BY gallery_image_gallery_album_id)
EOD;
			$images->set_index('gallery_album_id');
			$images->init_by_sql($sql);

			return ImageManager::add_type_filename($images, $type);
		}

		/**
		 *
		 */
		public static function reorder_images($album, $order)
		{
			$images = $album->related('GalleryImage');

			if(!is_array($order))
				return;

			$sortkey = 1;
			foreach($order as $id) {
				$obj =& $images[$id];
				$obj->sortkey = $sortkey++;
			}
			$images->store();
		}
	}

?>
