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
