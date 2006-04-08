<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.data.php';

	class SessionHandler {
		protected $user;

		protected function __construct()
		{
			if(!session_id()) {
				session_start();
			}
			
			if(isset($_REQUEST['login_username'])
					&& isset($_REQUEST['login_password'])) {
				$user = DBObject::find('User', array(
					'user_login=' => $_REQUEST['login_username'],
					'user_password=' => md5($_REQUEST['login_password'])));
				if($user) {
					$_SESSION['user_id'] = $user->id;
					$_SESSION['authenticated'] = true;
					$this->user = $user;
				}
			}

			// TODO might also check if IP is still the same to prevent session stealing
			if(isset($_REQUEST['logout'])) {
				// XXX maybe only unset SessionHandler variables?
				session_destroy();
				redirect('/');
			}

			if($_SESSION['user_id'] && !$this->user)
				$this->user = DBObject::find('User', $_SESSION['user_id']);
		}

		public static function &instance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new SessionHandler();
			}
			
			return $instance;
		}
		
		public static function authenticated()
		{
			return isset($_SESSION['authenticated'])
				&& $_SESSION['authenticated'];
		}

		public function user_id()
		{
			if(SessionHandler::authenticated() && isset($_SESSION['user_id']))
				return $_SESSION['user_id'];
			return null;
		}

		public function user_data()
		{
			if(SessionHandler::authenticated() && $this->user)
				return $this->user->data();
			return null;
		}
	}

?>
