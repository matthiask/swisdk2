<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'content/inc.imagemanager.php';

	DBObject::has_many('GalleryAlbum', 'GalleryImage');

	class GalleryManager {
		public static function setup($root=null)
		{
			Swisdk::require_htdocs_data_directory('gallery');
			define('GALLERY_HTDOCS_ROOT',
				substr(Swisdk::config_value('runtime.webroot.data', '/data')
					.'/gallery/', 1));

			Swisdk::require_data_directory('gallery');
			define('GALLERY_INCOMING_ROOT', DATA_ROOT.'gallery/');
		}

		public static function generate_images($album, $type1='thumb', $type2='full')
		{
			$types = func_get_args();
			$album = array_shift($types);

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

			$images = $album->related('GalleryImage');
			foreach($images as $image) {
				$paths = ImageManager::filename($image->file, $types);
				$image->set_data_with_prefix($paths, 'gallery_image_filename_');
			}

			return $images;
		}

		public static function cleanup($album)
		{
			Swisdk::clean_data_directory(DATA_ROOT.'scratch/');

			$images = $album->related('GalleryImage');
			$imagehash = array();

			foreach($images as $image)
				$imagehash[pathinfo($image->file, PATHINFO_FILENAME)] = true;

			$dir = HTDOCS_ROOT.GALLERY_HTDOCS_ROOT.$album->name.'/';

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
	}

?>
