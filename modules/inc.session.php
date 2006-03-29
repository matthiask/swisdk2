<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.data.php';

	class SessionHandler {
		protected function __construct()
		{
			if( !session_id() ) {
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
				}
			}

			// TODO might also check if IP is still the same to prevent session stealing
			if(getInput('logout')) {
				session_destroy();
				redirect('/');
			}
		}

		public static function instance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new SessionHandler();
			}
			
			return $instance;
		}
		
		public static function authenticated()
		{
			return true == $_SESSION[ 'swisdk2_authenticated' ];
		}
	}

?>
