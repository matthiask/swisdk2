<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class AdminNavigationComponent implements ISmartyComponent {
		protected $prepend = null;

		public function run()
		{
			$modules = explode(',', Swisdk::website_config_value('modules'));

			$realm = Swisdk::config_value('runtime.realm', '#');
			if($realm==='#')
				$realm = 0;
			else
				$realm = $realm['realm_id'];

			$actions = array();

			Swisdk::$cache['admin_navigation'] = null;

			if(isset(Swisdk::$cache['admin_navigation'][$realm])) {
				$actions = Swisdk::$cache['admin_navigation'][$realm];
			} else {
				$this_ctrl = Swisdk::config_value('runtime.controller.class');

				foreach($modules as $module) {
					$ctrl = '';
					$file = '';
					if(!file_exists($file = CONTENT_ROOT.'admin/'
								.$module.'_ctrl.php')
							&& !file_exists($file = CONTENT_ROOT
								.'admin/'.$module.'/All_ctrl.php'))
						;
					if((require_once $file)===true)
						$ctrl = $this_ctrl;
					else
						$ctrl = Swisdk::config_value('runtime.controller.class');

					$mod = new $ctrl;

					$info = $mod->info();
					foreach($info['actions'] as $action => $command)
						$actions[$action][$module] = '/admin/'.$module.'/'.$command;
				}

				Swisdk::$cache['admin_navigation'][$realm] = $actions;
				Swisdk::$cache_modified = true;
			}

			$this->html = '';

			$m_url = Swisdk::config_value('runtime.adminmodule.url');

			foreach($actions as $action => $list) {
				$display = 'none';
				$list_html = '';
				foreach($list as $module => $url) {
					if($url==$m_url)
						$display = 'block';
					$list_html .= <<<EOD
		<li><a href="$url">$module</a></li>

EOD;
				}
				$this->html .= <<<EOD
<div id="n-$action" class="action-group">
	<h2><a href="#" onclick="return n_toggle(this)">$action</a></h2>
	<ul id="n-{$action}-list" style="display:$display">
$list_html
	</ul>
</div>

EOD;
			}
		}

		public function set_smarty(&$smarty)
		{
			$smarty->set_block_content('navigation', $this->html);
		}

		protected $html;
	}

?>
