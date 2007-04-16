<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz and Moritz Zumbühl
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class Swisdk {
		public static function version()
		{
			return 'SWISDK v2.2';
		}

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

			$options = array(
				'--app-root=' => 'app_root',
				'--controller=' => 'controller');
			$app_root = null;
			$controller = null;
			$requestUri = null;

			$cmd_iface = array_shift($_SERVER['argv']);

			foreach($_SERVER['argv'] as $arg) {
				foreach($options as $o => $v) {
					if(strpos($arg, '--')!==0)
						$requestUri = $arg;
					else if(strpos($arg, $o)===0) {
						$$v = substr($arg, strlen($o));
					}
				}
			}

			if($app_root)
				define('APP_ROOT', realpath($app_root).'/');
			else
				define('APP_ROOT', dirname(dirname(__FILE__)).'/');

			if($requestUri) {
				Swisdk::run(array('REQUEST_URI' => $requestUri));
			} else if($controller) {
				// initialize core components
				Swisdk::init();

				Swisdk::set_config_value('runtime.includefile',
					realpath($controller));

				// load common settings and relations
				Swisdk::load_file('inc.common.php');

				// Everything is ready to really rock now. Generate and display
				// the response
				require_once SWISDK_ROOT . 'site/inc.handlers.php';
				$handler = new PhpSiteHandler();
				$handler->handle();
			} else {
				$usage = <<<EOD
SWISDK2 Commandline Interface
php $cmd_iface 'http://example.com/test/'
php $cmd_iface --app-root=\$dir --controller=controller.php

EOD;
				die($usage);
			}
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

			require_once SWISDK_ROOT . 'lib/contrib/utf8/utf8.php';
			require_once UTF8 . '/utils/validation.php';
			require_once UTF8 . '/utils/ascii.php';
			require_once SWISDK_ROOT . 'core/inc.functions.php';
			require_once SWISDK_ROOT . 'core/inc.error.php';

			SwisdkError::setup();
			Swisdk::init_config();
			Swisdk::init_cache();

			define('HTDOCS_DATA_ROOT', HTDOCS_ROOT.
				substr(Swisdk::config_value('runtime.webroot.data', '/data'), 1)
				.'/');

			Swisdk::log(str_repeat('*', 80), 'swisdk');
			Swisdk::log("Initializing", 'swisdk');

			Swisdk::require_data_directory(CACHE_ROOT);
			Swisdk::init_language();

			Swisdk::$load_bases = array(CONTENT_ROOT, SWISDK_ROOT.'content/', SWISDK_ROOT);

			require_once MODULE_ROOT.'inc.session.php';
			SessionHandler::instance();

			define('GUARD_UNKNOWN', 1);
			define('GUARD_VALID', 2);
			define('GUARD_EXPIRED', 3);
			define('GUARD_USED', 4);
		}

		protected static $load_bases;

		public static function run($arguments)
		{
			// initialize core components
			Swisdk::init();

			// run dispatcher
			require_once SWISDK_ROOT . "dispatcher/inc.dispatcher.php";
			SwisdkControllerDispatcher::dispatch( $arguments['REQUEST_URI'] );

			// load common settings and relations
			Swisdk::load_file('inc.common.php');

			// Everything is ready to really rock now. Generate and display
			// the response
			require_once SWISDK_ROOT . 'site/inc.handlers.php';
			SwisdkSiteHandler::run();

			Swisdk::shutdown(false);
		}

		public static function shutdown($exit = true)
		{
			Swisdk::save_cache();
			if($exit)
				exit();
		}

		/**
		 * caching
		 *
		 * this variable will be exported to a php file and read back in when
		 * initializing SWISDK
		 *
		 * Please behave yourself when putting data in here!
		 */
		public static $cache = array();
		public static $cache_modified = false;
		protected static $cache_active = false;

		protected static function init_cache()
		{
			Swisdk::$cache_active = Swisdk::config_value('core.cache');
			if(Swisdk::$cache_active && file_exists(CACHE_ROOT.'cache.php'))
				require_once CACHE_ROOT.'cache.php';
		}

		protected static function save_cache()
		{
			if(Swisdk::$cache_active && Swisdk::$cache_modified) {
				file_put_contents(CACHE_ROOT.'cache.php', '<?php Swisdk::$cache = '
					.var_export(Swisdk::$cache, true).'; ?>');
			}
		}

		public static function kill_cache($section = null)
		{
			if($section)
				Swisdk::$cache[$section] = array();
			else
				Swisdk::$cache = array();

			Swisdk::$cache_modified = true;
		}


		protected static $config;

		public static function init_config()
		{
			Swisdk::read_configfile(WEBAPP_ROOT.'config.ini');
			if($core_cfg = Swisdk::config_value('core.include'))
				Swisdk::read_configfile(WEBAPP_ROOT.$core_cfg);
		}

		public static function read_configfile($file, $prefix = '', $throw=true)
		{
			if(!isAbsolutePath($file))
				$file = CONTENT_ROOT.$file;
			if($prefix)
				$prefix .= '.';

			if(file_exists($file)) {
				$cfg = parse_ini_file($file, true);
				foreach($cfg as $section => $array) {
					$section = preg_replace('/^([\w]+)\ "(.*)"$/', '\1.\2', $section);
					// special handling for sections which have a dot
					// in their name, f.e. db.second, db.third for multiple
					// db connections
					//
					// Use Swisdk::dump() to see what this piece of code
					// does
					if(($pos=strpos($section, '.'))!==false) {
						$name = $prefix.'runtime.parser.'.substr($section, 0, $pos);
						Swisdk::$config[$name][] = substr($section, $pos+1);
					}
					// flatten config hierarchy
					foreach($array as $key => $value)
						Swisdk::$config[$prefix.$section.'.'.$key] = $value;
				}
			} else if($throw) {
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Configuration file %s not found'), $file)));
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
			if(!isAbsolutePath($dir))
				$dir = DATA_ROOT.$dir;
			umask(0002);
			if(!file_exists($dir))
				if(!@mkdir($dir, 0775, true))
					SwisdkError::handle(new ExtremelyFatalError(sprintf(
						dgettext('swisdk', 'Could not create data directory %s'),
						$dir)));
		}

		/**
		 * create a subdirectory for webserver-managed data below HTDOCS_DATA_ROOT
		 */
		public static function require_htdocs_data_directory($dir)
		{
			if(preg_match('/[^A-Za-z0-9\.-_\/\-]/', $dir)
					|| strpos($dir, '..')!==false)
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Invalid path passed to require_htdocs_data_directory: %s'),
					$dir)));
			if(!isAbsolutePath($dir))
				$dir = HTDOCS_DATA_ROOT.$dir;
			umask(0002);
			if(!file_exists($dir))
				if(!@mkdir($dir, 0775, true))
					SwisdkError::handle(new FatalError(sprintf(
						dgettext('swisdk', 'Could not create data directory %s'),
						$dir)));
		}

		/**
		 * Example:
		 * Swisdk::clean_data_directory(HTDOCS_DATA_ROOT.'captcha/', 43200);
		 */
		public static function clean_data_directory($dir, $age=86400)
		{
			$dir = str_replace('//', '/', $dir.'/');

			if($dh = opendir($dir)) {
				while(($file = readdir($dh))!==false) {
					$s = stat($dir.$file);
					if(($s['mode'] & 0170000)==0100000
							&& $s['mtime']<(time()-$age))
						@unlink($dir.$file);
				}
			}
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
			$fname = Swisdk::config_value('error.logfile');
			if(!$fname)
				return;
			$fp = @fopen(DATA_ROOT.'log/'.$fname, 'a');
			@fwrite($fp, date(DATE_W3C).' |'.strtoupper(str_pad($log, 10)).'| '.$message."\n");
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
			echo '<strong>Swisdk load bases</strong><br />';
			print_r(Swisdk::$load_bases);
			echo '</pre>';
		}


		/**
		 * configuration functions
		 */

		public static function set_config_value($key, $value)
		{
			Swisdk::$config[strtolower($key)] = $value;
		}

		public static function config_value($key, $default = null, $throw = false)
		{
			if(!is_array($key))
				$key = array($key);

			foreach($key as $k) {
				$k = strtolower($k);
				if(isset(Swisdk::$config[$k]))
					return Swisdk::$config[$k];
			}

			if($throw)
				SwisdkError::handle(new FatalError(
					'No configuration for "'.implode(', ', $key).'" found'));

			return $default;
		}

		public static function webroot($key)
		{
			return Swisdk::config_value('runtime.webroot.'.$key, '/'.$key);
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
		public static function website_config_value($key, $default=null, $website=null)
		{
			if(!$website)
				$website = Swisdk::config_value('runtime.website');

			if(!is_array($key))
				$key = array($key);

			$keys = array();
			foreach($key as $k)
				$keys[] = strtolower($k);

			while(true) {
				$w = 'website.'.$website.'.';
				foreach($keys as $k)
					if(isset(Swisdk::$config[$w.$k]))
						return Swisdk::$config[$w.$k];
				if(isset(Swisdk::$config[$w.'inherit']))
					$website = Swisdk::$config[$w.'inherit'];
				else
					break;
			}
			return $default;
		}

		public static function user_config_value($key, $default=null, $uid=null)
		{
			$val = Swisdk::website_config_value('user.'.$key, $default);
			if($val==='force-true')
				return true;
			else if($val==='force-false')
				return false;

			if(!Swisdk::config_value('core.user_config', false))
				return $val;

			if(!$uid)
				$uid = SessionHandler::user()->id();
			if($dbo = DBObject::find('UserMeta', array(
					'user_meta_user_id=' => $uid,
					'user_meta_key=' => $key)))
				return $dbo->value;

			return $val;
		}


		/**
		 * i18n support
		 */

		protected static $_languages;
		protected static $_all_languages;

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
			if(!Swisdk::$_all_languages) {
				Swisdk::$_all_languages = DBObject::db_get_array(
					'SELECT * FROM tbl_language', 'language_id');
			}
			
			if($key || $key=Swisdk::config_value('runtime.language')) {
				foreach(Swisdk::$_all_languages as $id => &$l)
					if($l['language_key']==$key)
						return $array?$l:$id;
			}

			return 0;
		}

		public static function language_key($id=null)
		{
			if($id===null)
				$id = Swisdk::language();
			if(isset(Swisdk::$_all_languages[$id]))
				return Swisdk::$_all_languages[$id]['language_key'];
			return false;
		}

		public static function all_languages()
		{
			if(!Swisdk::$_all_languages) {
				Swisdk::$_all_languages = DBObject::db_get_array(
					'SELECT * FROM tbl_language', 'language_id');
			}
			return Swisdk::$_all_languages;
		}

		public static function languages()
		{
			if(!Swisdk::$_languages) {
				Swisdk::$_languages = array();
				$_langs = Swisdk::website_config_value('languages');
					
				if($_langs) {
					$langs = array_flip(explode(',', $_langs));
					$languages = Swisdk::all_languages();
					foreach($languages as $lid => &$l)
						if(isset($langs[$l['language_key']]))
							Swisdk::$_languages[$lid] = $l;
					
				} else
					Swisdk::$_languages = Swisdk::all_languages();
			}
			return Swisdk::$_languages;
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
		 * load a class
		 */
		public static function load($class, $prefix_path = null)
		{
			return Swisdk::load_file(
				'inc.'.strtolower($class).'.php', $prefix_path);
		}

		public static function load_file($path, $prefix_path = null)
		{
			if($prefix_path)
				$path = $prefix_path.'/'.$path;

			$realm = isset(Swisdk::$config['runtime.realm'])?
				Swisdk::$config['runtime.realm']['realm_id']:0;

			if(isset(Swisdk::$cache['loader'][$realm][$path])
					&& $p = Swisdk::$cache['loader'][$realm][$path]) {
				require_once $p;
				return true;
			}

			Swisdk::log('Searching for '.$path, 'loader');

			$bases = Swisdk::$load_bases;

			while(count($bases)) {
				$base = array_shift($bases);
				Swisdk::log('Probing '.$base.$path, 'loader');
				if(file_exists($base.$path)) {
					Swisdk::log('Found '.$base.$path, 'loader');
					Swisdk::$cache['loader'][$realm][$path] = $base.$path;
					Swisdk::$cache_modified = true;
					require_once $base.$path;
					return true;
				}
			}

			return false;
		}

		/**
		 * load and instanciate a module
		 */
		public static function load_instance($class, $prefix_path = null)
		{
			$key = 'runtime.loader.'.$class;
			$class = Swisdk::config_value($key, $class);
			if(class_exists($class))
				return new $class;

			Swisdk::set_config_value($key, $class);
			if(Swisdk::load($class, $prefix_path)
					&& class_exists($class = Swisdk::config_value($key)))
				return new $class;
			else
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Could not load %s, prefix %s'),
					$class, $prefix_path)));
		}

		public static function add_loader_base($base)
		{
			array_unshift(Swisdk::$load_bases, $base);
		}

		public static function loader_bases()
		{
			return Swisdk::$load_bases;
		}

		/**
		 * HTML Code for extension libraries
		 */
		protected static $needed_libraries = array();

		public static function needs_library($lib)
		{
			Swisdk::$needed_libraries[] = $lib;
		}

		public static function needed_libraries()
		{
			return Swisdk::$needed_libraries;
		}

		public static function needed_libraries_html()
		{
			$provider = Swisdk::load_instance('LibraryProvider');
			$provider->set_libraries(Swisdk::$needed_libraries);
			return $provider->html();
		}

		/**
		 * CSRF and double-submit guard
		 */

		/**
		 * Generates a unique ID which may be used to guard against CSRF attacks
		 *
		 * http://en.wikipedia.org/wiki/Cross-site_request_forgery
		 */
		public static function guard_token()
		{
			$guard_gc_used = 300;
			$guard_gc_unused = 2700;

			if(!session_id())
				session_start();

			$tokens =& $_SESSION['swisdk2']['guard_tokens'];

			// clean guard token array
			if(is_array($tokens)) {
				$time = time();
				foreach($tokens as $token => $t) {
					if(isset($t[1]) && $t[0]<$time-$guard_gc_used)
						unset($tokens[$token]);
					else if($t[0]<$time-$guard_gc_unused)
						unset($tokens[$token]);
				}
			}

			$token = sha1(uniqid().Swisdk::config_value('core.token'));
			$tokens[$token] = array(time());
			return $token;
		}

		/**
		 * Get the validation state of this token
		 *
		 * @param $token	guard token
		 * @return state
		 */
		public static function guard_token_state($token)
		{
			$guard_expire = 1800;

			static $state = array();
			if($s = s_get($state, $token))
				return $s;

			if($t = s_get($_SESSION['swisdk2']['guard_tokens'],
					$token)) {
				if(s_get($t, 1))
					$newstate = GUARD_USED;
				else if($t[0]<time()-$guard_expire)
					$newstate = GUARD_EXPIRED;
				else {
					$_SESSION['swisdk2']['guard_tokens'][$token][1] = true;
					$newstate = GUARD_VALID;

				}
			} else
				$newstate = GUARD_UNKNOWN;

			$state[$token] = $newstate;
			return $newstate;
		}

		/**
		 * Refresh a token, f.e. if it has expired.
		 *
		 * This can be used to let the user re-submit a request
		 *
		 * @param $token	guard token
		 */
		public static function guard_token_refresh($token)
		{
			$_SESSION['swisdk2']['guard_tokens'][$token] = array(time());
		}

		/**
		 * Wrapper for Swisdk::guard_token() which takes a POST or GET variable
		 * and only returns a new token if no value can be found inside POST or GET
		 *
		 * @param $id	POST or GET variable name
		 */
		public static function guard_token_f($id)
		{
			if($t = getInput($id))
				return $t;
			return Swisdk::guard_token();
		}

		/**
		 * Wrapper for Swisdk::guard_token_state() which takes a POST or GET variable
		 * and only checks the token for validity if it has been submitted by the user
		 *
		 * @param $id	POST or GET variable name
		 */
		public static function guard_token_state_f($id)
		{
			if($t = getInput($id))
				return Swisdk::guard_token_state($t);
			return null;
		}
	}

?>
