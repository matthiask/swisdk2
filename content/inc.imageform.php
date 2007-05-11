<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.form.php';

	class ImageEditItem extends FormItem {
		public $file_variable;
		public $image_htdocs_root;
		public $preview = 'thumb';
		public $types = array('thumb', 'full');

		public function html()
		{
			$filename = $this->dbobj->{$this->file_variable};
			$file = DATA_ROOT.$this->image_htdocs_root.'/'.$filename;

			if(!is_file($file))
				return '';

			$ipath = Swisdk::config_value('runtime.webroot.data', '/data').'/'
				.$this->image_htdocs_root.'/'
				.ImageManager::generate_type($file, $this->preview,
					$this->image_htdocs_root);

			$token = sha1($file);
			$_SESSION['swisdk2']['image_editor'][$token] = array(
				'htdocs_data_dir' => $this->image_htdocs_root,
				'file' => $filename,
				'types' => $this->types);

			return <<<EOD
<a href="/__swisdk__/imageeditor/$token"
		onclick="window.open(this.href, 'editor', 'location=false,toolbar=false,width=900,height=700');return false">
	<img src="$ipath" />
</a>

EOD;
		}
	}

	function file_upload_pre_store($dbo, $fu, $dir, $field='image')
	{
		if($f = FormUtil::post_process_file_upload($fu, $dir))
			$dbo->$field = $f;
	}

?>
