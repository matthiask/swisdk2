<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.smarty.php';
	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.tableview.php';

	require_once SWISDK_ROOT.'content/inc.imagemanager.php';

	class ImageEditorSite extends Site {
		public function run()
		{
			// Image editor invocation starts here
			$args = Swisdk::config_value('runtime.arguments');
			if(!isset($args[0])
					|| !isset($_SESSION['swisdk2']['image_editor'][$args[0]]))
				SwisdkError::handle(new FatalError('Illegal ImageEditor invocation'));
			extract($_SESSION['swisdk2']['image_editor'][$args[0]]);
			// Image editor invocation ends here


			$source_filename = DATA_ROOT.$htdocs_data_dir.'/'.$file;

			$type = getInput('type');
			$this_url = Swisdk::config_value('runtime.controller.url').$args[0];
			$this_url_type = $this_url.($type?'?type='.$type.'&amp;':'?');

			$image_files = ImageManager::filename($file, $types);

			$work_types = $types;
			if($type)
				$work_types = array($type);

			if($cmd = getInput('cmd')) {
				$base = HTDOCS_DATA_ROOT.$htdocs_data_dir.'/';
				switch($cmd) {
					case 'restart':
						foreach($work_types as $t)
							unlink($base.$image_files[$t]);
						break;
					case 'rotate_clockwise':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('rotate', 90)));
						break;
					case 'rotate_anticlockwise':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('rotate', -90)));
						break;
					case 'grayscale':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('grayscale')));
						break;
					case 'colorize-red':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('colorize', '#f00', 5)));
						break;
					case 'colorize-green':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('colorize', '#0f0', 5)));
						break;
					case 'colorize-blue':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('colorize', '#00f', 5)));
						break;
					case 'darken':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('gamma', 0.95)));
						break;
					case 'lighten':
						foreach($work_types as $t)
							ImageManager::process_commands(
								$base.$image_files[$t], array(
								array('gamma', 1.05)));
						break;
					case 'crop':
						$file = ImageManager::filename($file, 'crop');
						$image_files['crop'] = $file;
						if(($w = getInput('w'))
								&& ($h = getInput('h'))
								&& ($x = getInput('x'))
								&& ($y = getInput('y'))) {
							$file = $base.$file;
							$s = getimagesize($file);
							$ss = getimagesize($source_filename);
							$types_desc = ImageManager::types();
							foreach($work_types as $t) {
								$fname = $base.$image_files[$t];
								copy($source_filename, $fname);
								ImageManager::process_commands($fname, array(
									array('crop',
										1.0*$ss[0]*$w/$s[0],
										1.0*$ss[1]*$h/$s[1],
										1.0*$ss[0]*$x/$s[0],
										1.0*$ss[1]*$y/$s[1])));
								ImageManager::process_commands($fname,
									$types_desc[$t]['commands']);
							}

							redirect($this_url_type);
						} else {
							ImageManager::register_type('crop', array(
								'commands' => array(
									array('scale_bounding_box', 600, 600))));
							ImageManager::generate_type($source_filename, 'crop',
								$htdocs_data_dir);
						}
						break;
				}
			}

			ImageManager::generate_types($source_filename,
				$htdocs_data_dir);

			$smarty = new SwisdkSmarty();
			$smarty->assign('htdocs_data_dir', $htdocs_data_dir);
			$smarty->assign('images', $image_files);
			$smarty->assign('types', $types);
			$smarty->assign('type', $type?$type:'full');
			$smarty->assign('this_url', $this_url);
			$smarty->assign('this_url_type', $this_url_type);
			$smarty->display_template('base.imageeditor');
		}
	}

	Swisdk::register('ImageEditorSite');

?>
