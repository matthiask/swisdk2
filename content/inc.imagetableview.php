<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.tableview.php';
	require_once SWISDK_ROOT.'content/inc.imagemanager.php';

	class ImageTableViewColumn extends TableViewColumn {
		public function html(&$data)
		{
			$type = $this->args[0];
			$path = $this->args[1];
			$fname = $data[$this->column];

			if(!$fname)
				return '(none)';

			return '<img src="'
				.Swisdk::config_value('runtime.webroot.data', '/data')
				.'/'.$path.'/'
				.ImageManager::generate_type(
					DATA_ROOT.$path.'/'.$fname, $type, $path)
				.'" />';
		}
	}

?>
