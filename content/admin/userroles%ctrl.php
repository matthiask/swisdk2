<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.smarty.php';

	DBObject::threeway('User', 'Realm', 'Role');

	class UserRoleSite extends Site {
		public function run()
		{
			PermissionManager::check_throw(ROLE_ADMINISTRATOR);
			$args = Swisdk::config_value('runtime.arguments');
			$user = DBObject::find('User', $args[0]);
			$form = new Form($user);
			$form->set_title('Roles of '.$user->forename.' '.$user->name.($user->login?' ('.$user->login.')':''));
			$form->add_auto('Realm');
			$form->add(new SubmitButton());

			if($form->is_valid()) {
				$user->store();
				Swisdk::kill_cache('permission');
				redirect('/admin/user/');
			}

			$smarty = new SwisdkSmarty();
			$smarty->assign('content', $form->html());
			$this->run_website_components($smarty);
			$smarty->display_template(array('base.admin', 'base.full'));
		}
	}

	Swisdk::register('UserRoleSite');

?>
