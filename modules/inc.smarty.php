<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>, Moritz ZumbŸhl <mail@momoetomo.ch>
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
		public function __construct($assign=true)
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
			$this->smarty->register_function('include_template', '_smarty_swisdk_include_template');
			$this->smarty->register_block('if_block', '_smarty_swisdk_if_block');
			$this->smarty->register_block('if_not_block', '_smarty_swisdk_if_not_block');
			$this->smarty->assign_by_ref('_swisdk_smarty_instance', $this);
			$this->smarty->register_function('css_classify', '_smarty_swisdk_css_classify');
			$this->smarty->register_function('generate_url', '_smarty_swisdk_generate_url');

			if($assign) {
				$this->assign('swisdk_user', SessionHandler::user()->data());
				$this->assign('swisdk_language', Swisdk::language_key());
			}
			error_reporting($er);

			Swisdk::require_data_directory($this->smarty->compile_dir);
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
				$ret = $this->fetch_template($resource);
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

		public function resolve_template($key, $throw=true)
		{
			if(is_array($key)) {
				foreach($key as $k)
					if($tmpl = $this->resolve_template($k, false))
						return $tmpl;

				if($throw)
					SwisdkError::handle(new FatalError('Could not resolve template '.$key));
			}

			if($key{0}=='/')
				return $key;

			$bases = Swisdk::loader_bases();
			$tmpl = $this->_template($key);

			foreach($bases as $b)
				if($this->template_exists($b.$tmpl))
					return $b.$tmpl;

			if($throw)
				SwisdkError::handle(new FatalError('Could not resolve template '.$key));
		}

		protected function _template($key)
		{
			if(strpos($key, '/')!==false)
				return $key;
			$tmp = $key = strtolower($key);
			if(strpos($key, 'website.')===0)
				return Swisdk::config_value($key);
			if(strpos($tmp, '.template.')===false)
				$tmp = 'template.'.$tmp;
			$tmpl = Swisdk::website_config_value($tmp);
			if(!$tmpl)
				$tmpl = str_replace('.', '/', $key).'.tpl';

			return $tmpl;
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
		if(!$val && isset($params['default']))
			return $params['default'];
		if(isset($params['format']) && $val)
			return sprintf($params['format'], $val);
		return $val;
	}

	function _smarty_swisdk_css_classify($params, &$smarty)
	{
		return cssClassify(Swisdk::config_value('runtime.'.$params['key']));
	}

	function _smarty_swisdk_generate_url($params, &$smarty)
	{
		static $generator = null;
		if($generator===null)
			$generator = Swisdk::load_instance('UrlGenerator', 'modules');
		return $generator->generate_url($params['item']);
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
			$ss->_derived = $params['template'];
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

	function _smarty_swisdk_include_template($params, &$smarty)
	{
		$ss = $smarty->get_template_vars('_swisdk_smarty_instance');
		if($tmpl = $ss->resolve_template($params['key']))
			return $ss->fetch($tmpl);
		return null;
	}

?>
