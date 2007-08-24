<?php
	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.smarty.php';

	class LoginSite extends Site {
		public function run()
		{
			PermissionManager::check_throw(ROLE_MANAGER);

			$smarty = $this->smarty();
			$this->run_website_components($smarty);
			$smarty->assign('adminindex', true);
			$smarty->display_template(array(
				'base.admin.start', 'base.admin', 'base.full'));
		}
	}

	Swisdk::register('LoginSite');

?>
