<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/

	class UserSessionHandler {
		
		protected $username;
		protected $password;
		
		protected function __construct()
		{
			if( !session_id() ) {
				session_start();
			}
			
			$registry = SwisdkRegistry::getInstance();
			
			if( $this->getLoginCredentials() ) {
				
				$login = false;
			
				$authModules = $registry->getValue( '/swisdk/session/authentification/module', true );
				foreach( $authModules as $class ) {
					$module = new $class;
					if( $module->checkLogin( $this->username, $this->password ) ) {
						$login = true;
						break;
					}
				}
				
				if( $login ) {
					$_SESSION[ 'swisdk2_authentificated' ] = true;
				}
			}
			
			if( getInput( 'logout' ) ) {
				session_destroy();
				redirect( '/' );
			}
		}
		
		public static function getInstance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new UserSessionHandler();
			}
			
			return $instance;
		}
		
		public static function isUserLoggedIn()
		{
			return true == $_SESSION[ 'swisdk2_authentificated' ];
		}
		
		protected function getLoginCredentials()
		{
			if( isset( $_REQUEST[ 'login_username' ] ) ) {
				$this->username = $_REQUEST[ 'login_username' ];
			}
			if( isset( $_REQUEST[ 'login_password' ] ) ) {
				$this->password = $_REQUEST[ 'login_password' ];
			}
		}
	}

	abstract class SwisdkAuthModule {
		public function checkLogin( $login, $password )
		{}
	}
	
	class SinglePasswordAuthModule extends SwisdkAuthModule {
		public function checkLogin( $login, $password )
		{
			if( $login == 'admin' && $password == 'dc71ab14bc5b8f02ed878df90dd7e7af' /* suppe */ ) {
				return true;
			} else {
				return false;
			}
		}
	}
	
	class DatabaseAuthModule extends SwisdkAuthModule {
		public function checkLogin( $login, $password )
		{
			Swisdk::loadModule( 'DB' );
			$result = SwisdkDB::getRow( "SELECT user_id FROM tbl_users WHERE user_login='" . SwisdkDB::escapeString( $login ) . "' AND user_password='" . md5( $password ) . "'" );
			if( $result !== null ) {
				return $result[ 'user_id' ];
			} else {
				return false;
			}
		}
	}

?>
