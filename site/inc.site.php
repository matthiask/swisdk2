<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.component.php';
	require_once MODULE_ROOT . 'inc.permission.php';

	abstract class Site implements IComponent {
		public function __construct()
		{
		}

		public function url()
		{
			return Swisdk::config_value('runtime.controller.url');
		}

		public function run_website_components($smarty)
		{
			$components = explode(',', Swisdk::website_config_value('components'));

			foreach($components as &$c) {
				$c = trim($c);
				if(!$c)
					continue;
				$cmp = Swisdk::load_instance($c.'Component', 'components');
				if($cmp instanceof IComponent)
					$cmp->run();
				if($cmp instanceof IHtmlComponent)
					$smarty->assign(strtolower($c), $cmp->html());
				if($cmp instanceof ISmartyComponent)
					$cmp->set_smarty($smarty);
			}
		}
	}


	abstract class SecuritySite extends Site
	{
		public function check_login()
		{
			// do something to check if the user
			// is allowed to see this page

			PermissionManager::instance()->check_throw();
		}

		public function run()
		{
			$this->check_login();
		}
	}

?>
