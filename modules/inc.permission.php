<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.session.php';

	// XXX make actions configurable?
	define('OP_VIEW', 1);
	define('OP_NEW', 2);
	define('OP_EDIT', 4);
	define('OP_DELETE', 8);
	define('OP_PUBLISH', 16);
	define('OP_PERMISSION', 32);

	class PermissionManager {
		protected function __construct()
		{
			if(($id = SessionHandler::instance()->user_id())
					&& $this->user = DBObject::find('User', $id)) {
				$this->access_level = $this->user->access_level;
			} else
				$this->access_level = 1;
		}

		public static function &instance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new PermissionManager();
			}
			
			return $instance;
		}

		public static function check($access_level, $operation=OP_VIEW)
		{
			$pm = PermissionManager::instance();
			// TODO do not simply bomb out wenn access is denied

			if($operation==OP_VIEW && $access_level<=$pm->access_level)
				return true;

			if(SessionHandler::authenticated()) {
				if($access_level<=$pm->access_level)
					return true;
				$pm->access_denied();
			}
			$pm->login_form();
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
		
		protected $user;
		protected $access_level;
	}

?>
