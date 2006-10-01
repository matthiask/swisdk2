<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz and Moritz Zumbuehl
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class Swisdk {
		public static function runFromHttpRequest()
		{
			Swisdk::run(array('REQUEST_URI' =>
				((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']=='on')
					?'https://':'http://')
				.$_SERVER['SERVER_NAME']
				.(($_SERVER['SERVER_PORT']!=80)?':'.$_SERVER['SERVER_PORT']:'')
				.$_SERVER['REQUEST_URI']));
		}

		public static function runFromCommandLine()
		{
			/**
			 * $_SERVER[DOCUMENT_ROOT] is not set when the call comes from the
			 * commandline.
			 * so we use the SCRIPT_NAME and assume that the file is in
			 * APP_ROOT/swisdk/commandline.php
			 */
			define('APP_ROOT', dirname(dirname(__FILE__)).'/');

			$requestUri = '';
			if( isset( $_SERVER['argv'][1]) ) {
				$requestUri = $_SERVER['argv'][1];
			}

			Swisdk::run( array( 'REQUEST_URI' => $requestUri  ) );
		}

		/**
		*	DO IT! ;)
		*	That means:
			1. Setup Error handling
			2. Read config
			3. Dispatch request
			4. Instance the controller and execute it
		*/
		public static function init()
		{
			static $initialized = false;
			if($initialized)
				return;
			$initialized = true;

			if(!defined('APP_ROOT'))
				define('APP_ROOT', realpath($_SERVER['DOCUMENT_ROOT']
						.'/../../').'/');

			date_default_timezone_set('Europe/Zurich');

			define('HTDOCS_ROOT', APP_ROOT . 'webapp/htdocs/');
			define('SWISDK_ROOT', APP_ROOT . 'swisdk/');
			define('SMARTY_ROOT', SWISDK_ROOT . 'lib/smarty/');
			define('MODULE_ROOT', SWISDK_ROOT . 'modules/');
			define('WEBAPP_ROOT', APP_ROOT.'webapp/');
			define('LOG_ROOT', APP_ROOT.'log/');
			define('CONTENT_ROOT' , WEBAPP_ROOT . 'content/');
			// DATA_ROOT must be writeable for the webserver
			define('DATA_ROOT', WEBAPP_ROOT.'data/');
			define('CACHE_ROOT', DATA_ROOT.'cache/');
			define('UPLOAD_ROOT', DATA_ROOT.'upload/');

			require_once SWISDK_ROOT . 'core/inc.functions.php';
			require_once SWISDK_ROOT . 'core/inc.error.php';

			SwisdkError::setup();
			Swisdk::require_data_directory(CACHE_ROOT);
			Swisdk::read_configfile();
			Swisdk::init_language();
		}

		public static function run($arguments)
		{
			// initialize core components
			Swisdk::init();

			// run dispatcher
			require_once SWISDK_ROOT . "dispatcher/inc.dispatcher.php";
			SwisdkControllerDispatcher::dispatch( $arguments['REQUEST_URI'] );

			// Everything is ready to really rock now. Generate and display
			// the response
			require_once SWISDK_ROOT . 'site/inc.handlers.php';
			SwisdkSiteHandler::run();
		}

		protected static $config;

		public static function read_configfile()
		{
			if(file_exists(APP_ROOT.'webapp/config.ini')) {
				$cfg = parse_ini_file(APP_ROOT.'webapp/config.ini', true);
				foreach($cfg as $section => $array) {
					// special handling for sections which have a dot
					// in their name, f.e. db.second, db.third for multiple
					// db connections
					//
					// Use Swisdk::dump() to see what this piece of code
					// does
					if(($pos=strpos($section, '.'))!==false) {
						$name = 'runtime.parser.'.substr($section, 0, $pos);
						if(!isset(Swisdk::$config[$name])
								|| !is_array(Swisdk::$config[$name]))
							Swisdk::$config[$name] = array();
						array_unshift(Swisdk::$config[$name],
							substr($section, $pos+1));
					}
					// flatten config hierarchy
					foreach($array as $key => $value)
						Swisdk::$config[$section.'.'.$key] = $value;
				}
			} else {
				SwisdkError::handle(new FatalError(
					dgettext('swisdk', 'No configuration file found')));
			}
		}

		/**
		 * create a subdirectory below DATA_ROOT for caching, uploads etc.
		 */
		public static function require_data_directory($dir)
		{
			if(preg_match('/[^A-Za-z0-9\.-_\/\-]/', $dir)
					|| strpos($dir, '..')!==false)
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Invalid path passed to require_data_directory: %s'),
					$dir)));
			if($dir{0}!='/')
				$dir = DATA_ROOT.$dir;
			umask(0002);
			if(!file_exists($dir))
				if(!@mkdir($dir, 0775, true))
					SwisdkError::handle(new FatalError(sprintf(
						dgettext('swisdk', 'Could not create data directory %s'),
						$dir)));
		}

		/**
		 * debugging functions
		 */

		/**
		 * add log message
		 *
		 * uses the configuration variable $log.logfile as logfile
		 *
		 * Example: If you want Swisdk::log($sql, 'db') to work, you need
		 * to define the configuration variable db.logfile
		 */
		public static function log($message, $log='error')
		{
			Swisdk::require_data_directory('log');
			$fname = Swisdk::config_value($log.'.logfile');
			if(!$fname)
				return;
			$fp = @fopen(DATA_ROOT.'log/'.$fname, 'a');
			@fwrite($fp, date(DATE_W3C).' '.$message."\n");
			@fclose($fp);
		}

		/**
		 * dump static Swisdk variables' content
		 *
		 * Only enabled when in debug mode (Might show DB passwords!)
		 */
		public static function dump()
		{
			if(!Swisdk::config_value('error.debug_mode'))
				return;
			echo '<pre>';
			echo '<strong>Swisdk config</strong><br />';
			print_r(Swisdk::$config);
			echo '</pre>';
		}


		/**
		 * configuration functions
		 */

		public static function set_config_value($key, $value)
		{
			Swisdk::$config[strtolower($key)] = $value;
		}

		public static function config_value($key, $default = null)
		{
			$key = strtolower($key);
			if(isset(Swisdk::$config[$key]))
				return Swisdk::$config[$key];
			return $default;
		}

		/**
		 * returns a website config value, for example the website title
		 *
		 * Follows the website inheritance chain
		 *
		 * Example:
		 *
		 * [website.default]
		 * title = Default title
		 *
		 * [website.something]
		 * inherit = default
		 */
		public static function website_config_value($key)
		{
			$key = strtolower($key);
			if(isset(Swisdk::$config[$key]))
				return Swisdk::$config[$key];
			$website = null;
			if(strpos($key, 'website.')===0) {
				$matches = array();
				preg_match('/^website\.([^\.]+)\.(.*)$/', $key, $matches);
				$key = $matches[2];
				$website = $matches[1];
			}
			if(!$website)
				$website = Swisdk::config_value('runtime.website');
			while(!isset(Swisdk::$config['website.'.$website.'.'.$key])) {
				if(isset(Swisdk::$config['website.'.$website.'.inherit']))
					$website = Swisdk::$config['website.'.$website.'.inherit'];
				else
					return null;
			}
			return Swisdk::$config['website.'.$website.'.'.$key];
		}

		/**
		 * returns the path to a smarty template for the current website
		 */
		public static function template($key)
		{
			$key = strtolower($key);
			$tmpl = Swisdk::website_config_value('template.'.$key);
			if(!$tmpl)
				SwisdkError::handle(new FatalError(
					dgettext('swisdk', 'Invalid template key: ').$key));

			$dir = Swisdk::website_config_value('template_dir');
			if($dir)
				$dir = str_replace('//', '/', $dir.'/');

			return $dir.$tmpl;
		}


		/**
		 * i18n support
		 */

		protected static $_languages;

		/**
		 * initialize gettext and set the current locale
		 *
		 * This function might get called more than once;
		 */
		protected static function init_language()
		{
			static $gettext_initialized = false;
			if(!$gettext_initialized) {
				// initialize gettext textdomains
				bindtextdomain('swisdk', SWISDK_ROOT.'i18n/locale');
				bindtextdomain('webapp', WEBAPP_ROOT.'i18n/locale');
				textdomain('webapp');
				$gettext_initialized = true;
			}

			// use the current language to find locale strings and apply
			// them to LC_ALL until the first match
			//
			// Example for language_locale: 'en;en_US;en_US.UTF-8'
			if($language = Swisdk::language(null, true)) {
				$locales = explode(';', $language['language_locale']);
				foreach($locales as $l) {
					$l = trim($l);
					if(stripos(setlocale(LC_ALL, $l), $l)===0)
						break;
				}
			}
		}

		/**
		 * get the current language id or the language id which has the
		 * given key
		 */
		public static function language($key=null, $array=false)
		{
			require_once MODULE_ROOT.'inc.data.php';
			if(!Swisdk::$_languages) {
				Swisdk::$_languages = DBObject::db_get_array(
					'SELECT * FROM tbl_language', 'language_key');
			}
			if($key || $key=Swisdk::config_value('runtime.language')) {
				if(isset(Swisdk::$_languages[$key])) {
					if($array)
						return Swisdk::$_languages[$key];
					else
						return intval(Swisdk::$_languages[$key]['language_id']);
				}
			}

			return 0;
		}

		/**
		 * set new language
		 */
		public static function set_language($key)
		{
			Swisdk::set_config_value('runtime.language', $key);
			Swisdk::init_language();
		}

		/**
		 * register the site controller class which handles the current
		 * website
		 */
		public static function register($class)
		{
			Swisdk::set_config_value('runtime.controller.class', $class);
		}

		/**
		 * load a module taking into account the current stage of request
		 * handling we are in
		 */
		public static function load($class, $stage = null)
		{
			if($stage===null)
				$stage = Swisdk::config_value('runtime.stage');

			$path = sprintf('%s/inc.%s.php', $stage, strtolower($class));

			$bases = array(SWISDK_ROOT, CONTENT_ROOT);

			while(count($bases)) {
				$base = array_shift($bases);
				if(file_exists($base.$path)) {
					require_once $base.$path;
					return true;
				}
			}

			return false;
		}

		/**
		 * load and instanciate a module
		 */
		public static function load_instance($class, $stage = null)
		{
			if(class_exists($class))
				return new $class;

			if($stage===null)
				$stage = Swisdk::config_value('runtime.stage');
			if(Swisdk::load($class, $stage)
					&& class_exists($class))
				return new $class;
			else
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Could not load %s, stage %s'),
					$class, $stage)));
		}
	}

?>
