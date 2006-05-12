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

		public static function check($role=null, $url=null, $abort = true)
		{
			if($group = PermissionManager::group_for_url($url)) {
				//
				// determine the minimally needed role (URI (=Group)
				// role and content role (parameter))
				//
				$needed_role = max($role, $group['group_role_id']);

				if(PermissionManager::check_group_role(
						$group['group_id'], $needed_role))
					return true;
			}

			if(!$abort)
				return false;

			if(SessionHandler::authenticated())
				PermissionManager::access_denied();
			PermissionManager::login_form();
		}

		public static function group_for_url($url=null)
		{
			if(is_null($url))
				$url = Swisdk::config_value('request.uri');
			if($url{0}=='/')
				$url = substr($url, 1);

			//
			// find best matching group
			//
			// If the URI is /a/b/c, the group table will be searched for
			// a/b/c, a/b, a and finally the empty string (root group)
			//
			$tokens = explode('/', $url);
			$clauses = array();
			while(count($tokens)) {
				$clauses[] = implode('/', $tokens);
				array_pop($tokens);
			}
			return DBObject::db_get_row(
				'SELECT group_id,group_role_id FROM tbl_group WHERE '
				.'(group_url=\''.implode('\' OR group_url=\'', $clauses)
				.'\' OR group_url=\'\') ORDER BY group_url DESC LIMIT 1');
		}

		public static function check_group_role($group, $role, $uid=null)
		{
			$user = null;
			if(is_null($uid))
				$user = SessionHandler::user();
			else
				$user = DBObject::find('User', $uid);

			$perms = DBObject::db_get_row('SELECT user_permission_role_id '
				.'FROM tbl_user_permission WHERE user_permission_user_id='
				.$user->id().' AND user_permission_group_id='.$group);

			//
			// the user must have an entry in the permission table, even
			// for simple viewing otherwise, access is denied.
			//
			// Roles are not inherited across groups. This simplifies the
			// code and it is easier to understand. The only drawback is
			// the amount of data which will be necessary once you have some
			// groups in the system. (Roughly #users * #groups)
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
				.implode(',', $groups).') AND user_group_permission_group_id='
				.$group);
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
			$form = new Form();
			$form->bind(DBObject::create('Login'));
			$form->set_title('Login');
			$form->add('login_username');
			$form->add('login_password', new PasswordInput());
			$form->add(new SubmitButton());
			echo $form->html();
			exit();
		}
	}

?>
