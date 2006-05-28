<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>, Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	define( 'STREGION_NONE', -1 );
	define( 'STREGION_ALL' , 0 );
	define( 'STREGION_FULL' , 1 );
	define( 'STREGION_HEADER' , 2 );
	define( 'STREGION_FOOTER' , 3 );

	/**
	 * E_STRICT wrapper for PHP4 compatible Smarty code!
	 */

	class SwisdkSmarty {
		public function __construct()
		{
			// make sure E_STRICT is turned off
			$er = error_reporting(E_ALL^E_NOTICE);
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
			$er = error_reporting(E_ALL^E_NOTICE);
			$ret = call_user_func_array(
				array(&$this->smarty, $method),
				$args);
			error_reporting($er);
			return $ret;
		}
	
		public function __get($var)
		{
			$er = error_reporting(E_ALL^E_NOTICE);
			$ret = $this->smarty->$var;
			error_reporting($er);
			return $ret;
		}

		public function __set($var, $value)
		{
			$er = error_reporting(E_ALL^E_NOTICE);
			$ret = ($this->smarty->$var = $value);
			error_reporting($er);
			return $ret;
		}

		protected $smarty;
	}

	class SmartyMaster {
		/**
		 * array holding the template file paths
		 */
		private $mTemplates;

		/**
		 * what is the title variable for? It does not seem to be used anywhere
		 */
		private $mTitle;

		/**
		 * html fragments which will get assigned to smarty
		 */
		private $mHtmlFragments = array();

		/**
		 * array holding IHtmlComponents
		 */
		private $mHtmlHandlers = array();

		/**
		 * SmartyMaster instance
		 */
		private static $mInstance = null;

		/**
		 * SwisdkSmarty instance
		 */
		private $mSmarty = null;

		/**
		 * singleton accessor method
		 */
		public static function instance()
		{
			if(SmartyMaster::$mInstance===null)
				SmartyMaster::$mInstance = new SmartyMaster;

			return SmartyMaster::$mInstance;
		}

		/**
		 * private constructor because it's a singleton
		 */
		private function __construct()
		{
			$w = 'website.'.Swisdk::config_value('runtime.website').'.';
			$this->mTemplates = array(
				'fullTemplate' => Swisdk::config_value($w.'fullTemplate'),
				'header' => Swisdk::config_value($w.'header'),
				'footer' => Swisdk::config_value($w.'footer'));
			$this->mTitle = Swisdk::config_value($w.'title');
		}

		/**
		 * @return smarty instance reference
		 */
		public function smarty()
		{
			if($this->mSmarty===null)
				$this->mSmarty = new SwisdkSmarty();

			return $this->mSmarty;
		}

		/**
		 * display a template. Default is the full page template.
		 *
		 * the second parameter controls the IHtmlComponent's HTML
		 * generation duties
		 */
		public function display($template = null, $generate = STREGION_ALL)
		{
			if($template===null)
				$template = $this->mTemplates['fullTemplate'];

			$smarty = $this->smarty();
			if($smarty->template_exists($template)) {
				if($this->generate_sections($generate))
					$smarty->display($template);
			} else {
				SwisdkError::handle(new FatalError(
					'Smarty template '.$template.' does not exist'));
			}
		}

		/**
		 * display header template
		 */
		public function display_header($generate = STREGION_HEADER)
		{
			$this->display($this->mTemplates['header'], $generate);
		}

		/**
		 * display footer template
		 */
		public function display_footer($generate = STREGION_FOOTER)
		{
			$this->display($this->mTemplates['footer'], $generate);
		}

		/**
		 * this function has two overloads:
		 *
		 * add_html_handler($component, $region = STREGION_FULL)
		 * add_html_handler($name, $component, $region = STREGION_FULL)
		 *
		 * The component needs to have a name() method if you want to use
		 * the first method
		 */
		public function add_html_handler()
		{
			$args = func_get_args();

			$name = null;
			$component = null;
			$region = STREGION_FULL;

			if($args[0] instanceof IHtmlComponent) {
				$component = $args[0];
				$name = $component->name();
				if(isset($args[1]))
					$region = $args[1];
			} else if($args[1] instanceof IHtmlComponent) {
				$name = $args[0];
				$component = $args[1];
				if(isset($args[2]))
					$region = $args[2];
			}

			$this->mHtmlHandlers[$region][$name][] = $component;
		}

		/**
		 * add HTML fragment. This will be passed on as-is to the smarty instance.
		 */
		public function add_html_fragment($name, $fragment)
		{
			$this->mHtmlFragments[$name] = $fragment;
		}

		/**
		 * assign HTML fragments and output of IHtmlComponent to smarty instance
		 */
		private function generate_sections($region = STREGION_ALL)
		{
			$smarty = $this->smarty();

			foreach($this->mHtmlFragments as $name => &$html)
				$smarty->assign($name, $html);

			if($section == STREGION_NONE)
				return true;
			else if($section == STREGION_ALL)
				return $this->generate_section_helper($smarty, STREGION_FULL)
					&& $this->generate_section_helper($smarty, STREGION_HEADER)
					&& $this->generate_section_helper($smarty, STREGION_FOOTER);
			else
				return $this->generate_section_helper($smarty, $region);
		}

		/**
		 * helper doing the real work for generate_sections() above
		 */
		private function generate_section_helper(&$smarty, $region)
		{
			if(!isset($this->mHtmlHandlers[$region]))
				return true;

			foreach($this->mHtmlHandlers[$region] as $section => &$handlers) {
				$output = '';

				foreach($handlers as $handler) {
					$res = $handler->html();
					if(SwisdkError::is_error($res))
						return false;

					$output .= $res;
				}

				$smarty->assign($section, $output);
			}

			return true;
		}
	}

?>
