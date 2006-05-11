<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * E_STRICT wrapper for PHP4 compatible Smarty code!
	 */

	class SwisdkSmarty {
		public function __construct()
		{
			// make sure E_STRICT is turned off
			$er = error_reporting(E_ALL);
			require_once SWISDK_ROOT . 'lib/smarty/libs/Smarty.class.php';
			$this->smarty = new Smarty();
			$this->smarty->compile_dir = SWISDK_ROOT . 'lib/smarty/templates_c';
			$this->smarty->cache_dir = SWISDK_ROOT . 'lib/smarty/cache';
			$this->smarty->template_dir = CONTENT_ROOT;
			//$this->config_dir
			$this->caching = false;
			$this->security = false;
			error_reporting($er);
		}

		public function __call($method, $args)
		{
			$er = error_reporting(E_ALL);
			$ret = call_user_func_array(
				array(&$this->smarty, $method),
				$args);
			error_reporting($er);
			return $ret;
		}
		
		public function __get($var)
		{
			$er = error_reporting(E_ALL);
			$ret = $this->smarty->$var;
			error_reporting($er);
			return $ret;
		}

		public function __set($var, $value)
		{
			$er = error_reporting(E_ALL);
			$ret = ($this->smarty->$var = $value);
			error_reporting($er);
			return $ret;
		}

		protected $smarty;
	}

?>
