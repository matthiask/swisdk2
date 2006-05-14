<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >, Moritz Zumbühl < mail@momoetomo.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse (http://pflanzschule.irregular.ch/)
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/

	date_default_timezone_set('Europe/Zurich');
	
	class Swisdk {
		
		public static function runFromHttpRequest()
		{
			define('APP_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/../');
			define('HTDOCS_ROOT', APP_ROOT . 'htdocs/');
			define('SWISDK_ROOT', APP_ROOT . 'swisdk/');
			define('SMARTY_ROOT', SWISDK_ROOT . 'lib/smarty/');
			define('MODULE_ROOT', SWISDK_ROOT . 'modules/');
			define('CONTENT_ROOT' , APP_ROOT . 'content/');
			define('LOG_ROOT', APP_ROOT.'log/');
				
			require_once SWISDK_ROOT . 'core/inc.functions.php';
			require_once SWISDK_ROOT . 'core/inc.error.php';
			require_once SWISDK_ROOT . 'resolver/inc.resolver.php';
			require_once SWISDK_ROOT . 'site/inc.handlers.php';
			
			// FIXME this url might be incomplete (port, procotol etc)
			Swisdk::run(array('REQUEST_URI' => 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']));
		}
		
		public static function runFromCommandLine() 
		{
			
			/**
			 * the $_Server[Document_root] is not set when the call comes from the commandline.
			 * so we use the SCRIPT_NAME and assume that the file is in APP_ROOT/swisdk/commandline.php 
			 */
			$swisdk = substr( __FILE__ ,  0 , strrpos(__FILE__ , "/") );
			$apppath = substr( $swisdk , 0 ,  strrpos( $swisdk , "/")+1 );
	
			define( 'APP_ROOT', $apppath );
			define( 'HTDOCS_ROOT', APP_ROOT . 'htdocs/' );
			define( 'SWISDK_ROOT', APP_ROOT . 'swisdk/' );
			define( 'SMARTY_ROOT', SWISDK_ROOT . 'lib/smarty/' );
			define( 'MODULE_ROOT', SWISDK_ROOT . 'modules/' );
			define( 'CONTENT_ROOT' , APP_ROOT . 'content/' );
			define('LOG_ROOT', APP_ROOT.'log/');
				
			require_once SWISDK_ROOT . 'core/inc.functions.php';
			require_once SWISDK_ROOT . 'core/inc.error.php';
			require_once SWISDK_ROOT . 'resolver/inc.resolver.php';
			require_once SWISDK_ROOT . 'site/inc.handlers.php';
			
			$requestUri = '';
			if( isset( $_SERVER['argv'][1]) ) {
				$requestUri = $_SERVER['argv'][1];
			}
			
			Swisdk::run( array( 'REQUEST_URI' => $requestUri  ) );
		}
		
		public static function run($arguments) 
		{
			Swisdk::read_configfile();
			SwisdkError::setup();
			SwisdkResolver::run($arguments['REQUEST_URI']);

			Swisdk::$arguments = SwisdkResolver::arguments();

			$file = Swisdk::config_value('runtime.includefile');
			if(!$file)
				SwisdkError::handle(new SiteNotFoundError());

			$type = substr($file, strrpos($file, '_')+1);

			$handler = 'SiteHandler';
			switch($type) {
				case 'tpl.html':
					$handler = 'TemplateSiteHandler';
					break;
				case 'ctrl.php':
					$handler = 'DynamicSiteHandler';
					break;
			}

			$obj = new $handler;
			$obj->handle($file);
		}

		protected static $config;

		public static function read_configfile()
		{
			if(file_exists(CONTENT_ROOT.'config.ini')) {
				$cfg = parse_ini_file(CONTENT_ROOT.'config.ini', true);
				foreach($cfg as $section => $array)
					foreach($array as $key => $value)
						Swisdk::$config[$section.'.'.$key] = $value;
			}
		}

		public static function set_config_value($key, $value)
		{
			Swisdk::$config[$key] = $value;
		}

		public static function config_value($key)
		{
			if(isset(Swisdk::$config[$key]))
				return Swisdk::$config[$key];
			return null;
		}

		public static function language($key=null)
		{
			if($key) {
				$l = DBObject::db_get_array('SELECT * FROM tbl_language',
					array('language_key', 'language_id'));
				if(isset($l[$key]))
					return $l[$key];
			}

			if($val = Swisdk::config_value('runtime.language_id'))
				return $val;
			else if($val = Swisdk::config_value('runtime.language')) {
				$l = DBObject::db_get_array('SELECT * FROM tbl_language',
					array('language_key', 'language_id'));
				if(isset($l[$val]) && ($val = $l[$val])) {
					Swisdk::set_config_value('runtime.language_id', $val);
					return $val;
				}
			}
		}

		protected static $arguments;

		public static function arguments()
		{
			return Swisdk::$arguments;
		}

		public static function register($class)
		{
			Swisdk::set_config_value('runtime.controller.class', $class);
		}
	}

?>
