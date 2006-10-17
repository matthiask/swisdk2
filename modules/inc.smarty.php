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

	require_once MODULE_ROOT.'inc.session.php';

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
			$this->smarty->compile_dir = CACHE_ROOT.'smarty';
			$this->smarty->template_dir = CONTENT_ROOT;
			//$this->smarty->cache_dir = CACHE_ROOT.'smarty';
			//$this->config_dir
			$this->smarty->caching = false;
			$this->smarty->security = false;
			$this->smarty->register_function('swisdk_runtime_value',
				'_smarty_swisdk_runtime_value');
			$this->smarty->register_block('block', '_smarty_swisdk_process_block');
			$this->smarty->register_function('extends', '_smarty_swisdk_extends');
			$this->smarty->register_function('db_assign', '_smarty_swisdk_db_assign');
			$this->smarty->register_block('if_block', '_smarty_swisdk_if_block');
			$this->smarty->register_block('if_not_block', '_smarty_swisdk_if_not_block');
			$this->smarty->assign_by_ref('_swisdk_smarty_instance', $this);
			$this->smarty->register_function('css_classify', '_smarty_swisdk_css_classify');
			error_reporting($er);

			Swisdk::require_data_directory($this->smarty->compile_dir);
			$this->assign('swisdk_user', SessionHandler::user()->data());
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

		public function display($resource_name)
		{
			echo $this->fetch($resource_name);
		}

		public function fetch($resource_name)
		{
			$ret = $this->smarty->fetch($resource_name);
			while($resource = $this->_derived) {
				$this->_derived = null;
				$ret = $this->smarty->fetch($resource);
			}
			return $ret;
		}

		public function display_template($key)
		{
			echo $this->fetch_template($key);
		}

		public function fetch_template($key)
		{
			return $this->fetch($this->resolve_template($key));
		}

		public function resolve_template($key)
		{
			if(is_array($key)) {
				foreach($key as $k)
					if($tmpl = $this->resolve_template($k))
						return $tmpl;
			}

			$tmpl = Swisdk::template($key);
			if($this->template_exists($tmpl))
				return $tmpl;
			return null;
		}

		public function block_content($block)
		{
			return $this->_blocks[$block];
		}

		public function set_block_content($block, $content)
		{
			$this->_blocks[$block] = $content;
		}

		protected $smarty;

		// template inheritance
		public $_blocks = array();
		public $_derived = null;
	}

	/**
	 * {swisdk_runtime_value key="request.host"}  inserts the value of
	 * runtime.request.host
	 */
	function _smarty_swisdk_runtime_value($params, &$smarty)
	{
		$val = Swisdk::config_value('runtime.'.$params['key']);
		if(isset($params['format']) && $val)
			return sprintf($params['format'], $val);
		return $val;
	}

	function _smarty_swisdk_css_classify($params, &$smarty)
	{
		return cssClassify(Swisdk::config_value('runtime.'.$params['key']));
	}

	/**
	 * Template inheritance
	 *
	 * Base template example:
	 * --8<--
	 * Content here is used verbatim
	 * 
	 * {block name="test-block"}
	 * Here, you may insert default content for the block
	 * {/block}
	 * --8<--
	 * 
	 * 
	 * Derived template example:
	 * --8<--
	 * {extends file="base.tpl"}
	 * OR
	 * {extends template="article.list"}
	 * 
	 * Content here is ignored
	 * 
	 * {block name="test-block"}
	 * The content here replaces everything in the test-block of the base template
	 * {/block}
	 * --8<--
	 */

	function _smarty_swisdk_process_block($params, $content, &$smarty, &$repeat)
	{
		if($content===null)
			return;
		$name = $params['name'];
		$ss = $smarty->get_template_vars('_swisdk_smarty_instance');
		if(!isset($ss->_blocks[$name]))
			$ss->_blocks[$name] = $content;
		return $ss->_blocks[$name];
	}

	function _smarty_swisdk_extends($params, &$smarty)
	{
		$ss = $smarty->get_template_vars('_swisdk_smarty_instance');
		if(isset($params['template']))
			$ss->_derived = Swisdk::template($params['template']);
		else
			$ss->_derived = $params['file'];
	}

	function _smarty_swisdk_if_block($params, $content, &$smarty, &$repeat)
	{
		$name = $params['name'];
		$ss = $smarty->get_template_vars('_swisdk_smarty_instance');
		if(isset($ss->_blocks[$name]) && $ss->_blocks[$name])
			return $content;
		return null;
	}

	function _smarty_swisdk_if_not_block($params, $content, &$smarty, &$repeat)
	{
		$name = $params['name'];
		$ss = $smarty->get_template_vars('_swisdk_smarty_instance');
		if(isset($ss->_blocks[$name]) && $ss->_blocks[$name])
			return null;
		return $content;
	}

	/**
	 * {db_assign name="articles" class="Article" order="article_start_dttm" limit="10"}
	 */
	function _smarty_swisdk_db_assign($params, &$smarty)
	{
		$clauses = array();
		if(isset($params['order']) && $order = $params['order'])
			$clauses[':order'] = $order;
		if(isset($params['limit']) && $limit = $params['limit'])
			$clauses[':limit'] = $limit;
		if(isset($params['cut_off_future']) && $cof = $params['cut_off_future'])
			$clauses[$cof.'<'] = time();
		if(isset($params['cut_off_past']) && $cop = $params['cut_off_past'])
			$clauses[$cop.'<'] = time();

		$class = $params['class'];
		$name = $params['name'];
		$smarty->assign($params['name'], DBOContainer::find($params['class'],
			$clauses));
	}

	class SmartyMaster {
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
			$this->mTitle = Swisdk::website_config_value('title');
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
				$template = Swisdk::template('base.full');

			$smarty = $this->smarty();
			if($smarty->template_exists($template)) {
				if($this->generate_sections($generate))
					$smarty->display($template);
			} else {
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Smarty template %s does not exist'), $template)));
			}
		}

		/**
		 * display header template
		 */
		public function display_header($generate = STREGION_HEADER)
		{
			$this->display(Swisdk::template('base.header'), $generate);
		}

		/**
		 * display footer template
		 */
		public function display_footer($generate = STREGION_FOOTER)
		{
			$this->display(Swisdk::template('base.footer'), $generate);
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
			$this->run_components();

			$smarty = $this->smarty();

			foreach($this->mHtmlFragments as $name => &$html)
				$smarty->assign($name, $html);

			if($region == STREGION_NONE)
				return true;
			else if($region == STREGION_ALL)
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

		public function run_components()
		{
			$key = 'website.'.Swisdk::config_value('runtime.website').'.components';
			$cfg = Swisdk::config_value($key);
			if($cfg) {
				$components = explode(',', $cfg);
				foreach($components as $c) {
					$tokens = split('([\s]+)?=>([\s]+)?', trim($c));
					if(count($tokens)==2) {
						$this->add_html_handler($tokens[0],
							$this->get_and_run_component($tokens[1]));
					} else if(count($tokens)==1) {
						$this->add_html_handler(strtolower($tokens[0]),
							$this->get_and_run_component($tokens[0]));
					}
				}
			}
		}

		private function get_and_run_component($name)
		{
			$class = $name.'Component';
			require_once CONTENT_ROOT.'components/'.$class.'.inc.php';
			$obj = new $class;
			$obj->run();
			return $obj;
		}
	}

?>
