<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.session.php';

	// see also tbl_role !!!
	define('ROLE_VISITOR', 1);		// view
	define('ROLE_AUTHENTICATED', 2);	// view, comment
	define('ROLE_MEMBER', 3);		// view, comment
	define('ROLE_MANAGER', 4);		// view, comment, create, edit, delete
	define('ROLE_ADMINISTRATOR', 5);	// view, comment, create, edit, delete,
						// permissions
	define('ROLE_SITEADMIN', 6);		// everything!

	class PermissionManager {
		public static function &instance()
		{
			static $instance = null;
			if( $instance === null )
				$instance = new PermissionManager();
			
			return $instance;
		}

		public static function check($role=null, $url=null)
		{
			if($realm = PermissionManager::realm_for_url($url)) {
				//
				// determine the minimally needed role (URI (=Realm)
				// role and content role (parameter))
				//
				$needed_role = max($role, $realm['realm_role_id']);

				if(PermissionManager::check_realm_role(
						$realm['realm_id'], $needed_role))
					return true;
			}

			return false;
		}

		public static function check_throw($role=null, $url=null)
		{
			if(PermissionManager::check($role, $url))
				return true;

			if(SessionHandler::authenticated())
				PermissionManager::access_denied();
			PermissionManager::login_form();
		}

		public static function realm_for_url($url=null)
		{
			if(is_null($url))
				$url = Swisdk::config_value('runtime.request.uri');
			if($url{0}=='/')
				$url = substr($url, 1);

			//
			// find best matching realm
			//
			// If the URI is /a/b/c, the realm table will be searched for
			// a/b/c, a/b, a and finally the empty string (root realm)
			//
			$tokens = explode('/', $url);
			$clauses = array();
			while(count($tokens)) {
				$clauses[] = DBObject::db_escape(implode('/', $tokens));
				array_pop($tokens);
			}
			return DBObject::db_get_row(
				'SELECT realm_id,realm_role_id FROM tbl_realm WHERE '
				.'(realm_url='.implode(' OR realm_url=', $clauses)
				.' OR realm_url=\'\') ORDER BY realm_url DESC LIMIT 1');
		}

		public static function check_realm_role($realm, $role, $uid=null)
		{
			$realm = DBObject::db_escape($realm);
			$user = null;
			if(is_null($uid))
				$user = SessionHandler::user();
			else
				$user = DBObject::find('User', $uid);

			$perms = DBObject::db_get_row('SELECT user_permission_role_id '
				.'FROM tbl_user_permission WHERE user_permission_user_id='
				.$user->id().' AND user_permission_realm_id='.$realm);

			//
			// the user must have an entry in the permission table, even
			// for simple viewing otherwise, access is denied.
			//
			// Roles are not inherited across realms. This simplifies the
			// code and it is easier to understand. The only drawback is
			// the amount of data which will be necessary once you have some
			// realms in the system. (Roughly #users * #realms)
			//
			if($perms && $perms['user_permission_role_id']>=$role)
				return true;

			//
			// check user's groups for sufficient permissions
			//
			$groups = $user->related('UserGroup')->ids();
			if(!count($groups))
				return false;

			$perms = DBObject::db_get_array('SELECT user_group_permission_role_id '
				.'FROM tbl_user_group_permission '
				.'WHERE user_group_permission_user_group_id IN ('
				.implode(',', $groups).') AND user_group_permission_realm_id='
				.$realm);
			foreach($perms as &$p)
				if($p['user_group_permission_role_id']>=$role)
					return true;

			//
			// insufficient roles everywhere... check failed!
			//
			return false;
		}

		public static function access_denied()
		{
			header('HTTP/1.0 401 Unauthorized');
			die('Access denied');
		}

		public static function login_form()
		{
			require_once MODULE_ROOT . 'inc.form.php';
			require_once MODULE_ROOT . 'inc.smarty.php';
			$form = new Form();
			$form->bind(DBObject::create('Login'));
			$form->set_title('Login');
			$form->add('login_username');
			$form->add('login_password', new PasswordInput());
			$form->add(new SubmitButton());

			$sm = SmartyMaster::instance();
			$sm->add_html_fragment('content', $form->html());
			$sm->display();
			exit();
		}
	}

?>
