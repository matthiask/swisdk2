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
			if(is_null($url))
				$url = Swisdk::config_value('request.uri');
			if($url{0}=='/')
				$url = substr($url, 1);

			//
			// find best matching group
			//
			$tokens = explode('/', $url);
			$sql = 'SELECT group_id,group_role_id FROM tbl_group WHERE ';
			$clauses = array();
			while(count($tokens)) {
				$clauses[] = implode('/', $tokens);
				array_pop($tokens);
			}
			$sql .= '(group_url=\''.implode('\' OR group_url=\'', $clauses)
				.'\' OR group_url=\'\') ORDER BY group_url DESC LIMIT 1';
			$group = DBObject::db_get_row($sql);

			//
			// determine the minimally needed role (URI (=Group) role and
			// content role (parameter))
			//
			$needed_role = max($role, $group['group_role_id']);
			$uid = SessionHandler::instance()->user_id();
			$perms = DBObject::db_get_row('SELECT permission_role_id '
				.'FROM tbl_permission WHERE permission_user_id='.$uid
				.' AND permission_group_id='.$group['group_id']);

			//
			// the user must have an entry in the permission table, even
			// for simple viewing otherwise, access is denied.
			//
			// Roles are not inherited across groups. This simplifies the
			// code and it is easier to understand. The only drawback is
			// the amount of data which will be necessary once you have some
			// groups in the system. (Roughly #users * #groups)
			//
			if($perms && $perms['permission_role_id']>=$needed_role)
				return true;
			if(SessionHandler::authenticated())
				PermissionManager::instance()->access_denied();
			PermissionManager::instance()->login_form();
		}

		public function access_denied()
		{
			header('HTTP/1.0 401 Unauthorized');
			die('Access denied');
		}

		public function login_form()
		{
			require_once MODULE_ROOT . 'inc.form.php';
			$form = new Form();
			$form->bind(DBObject::create('Login'));
			$form->set_title('Login');
			$form->add('Username');
			$form->add('Password', new PasswordInput());
			$form->add(new SubmitButton());
			echo $form->html();
			exit();
		}
	}

?>
