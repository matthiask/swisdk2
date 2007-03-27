<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>, Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.session.php';
	require_once SWISDK_ROOT.'lib/smarty/libs/Smarty.class.php';

	class SwisdkSmarty extends Smarty {
		public function __construct($assign=true)
		{
			$this->error_reporting = E_ALL^E_NOTICE;
			// make sure E_STRICT is turned off
			$this->compile_dir = CACHE_ROOT.'smarty';
			$this->template_dir = CONTENT_ROOT;
			//$this->cache_dir = CACHE_ROOT.'smarty';
			//$this->config_dir
			$this->caching = false;
			$this->security = false;
			$this->register_function('swisdk_runtime_value',
				'_smarty_swisdk_runtime_value');
			$this->register_function('webroot', '_smarty_swisdk_webroot');
			$this->register_block('block', '_smarty_swisdk_process_block');
			$this->register_function('extends', '_smarty_swisdk_extends');
			$this->register_function('db_assign', '_smarty_swisdk_db_assign');
			$this->register_function('include_template', '_smarty_swisdk_include_template');
			$this->register_block('if_block', '_smarty_swisdk_if_block');
			$this->register_block('if_not_block', '_smarty_swisdk_if_not_block');
			$this->assign_by_ref('_swisdk_smarty_instance', $this);
			$this->register_function('css_classify', '_smarty_swisdk_css_classify');
			$this->register_function('generate_url', '_smarty_swisdk_generate_url');
			$this->register_function('generate_image_url',
				'_smarty_swisdk_generate_image_url');
			$this->register_function('dttm_range', '_smarty_swisdk_dttm_range');
			$this->register_modifier('pluralize', 'pluralize');

			if($assign) {
				$this->assign('swisdk_user', SessionHandler::user()->data());
				$this->assign('swisdk_language', Swisdk::language_key());
			}

			Swisdk::require_data_directory($this->compile_dir);

			$sc = array('SwisdkCustom', 'swisdksmarty');
			if(is_callable($sc))
				call_user_func($sc, $this);
		}

		public function display($resource_name, $cache_id=null, $compile_id=null)
		{
			echo $this->fetch($resource_name, $cache_id, $compile_id);
		}

		public function fetch($resource_name, $cache_id=null, $compile_id=null, $display=false)
		{
			$ret = parent::fetch($resource_name, $cache_id, $compile_id, $display);
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

			$realm = Swisdk::config_value('runtime.realm', '#');
			if($realm==='#')
				$realm = 0;
			else
				$realm = $realm['realm_id'];

			if(isset(Swisdk::$cache['smarty'][$realm][$key])
					&& ($t = Swisdk::$cache['smarty'][$realm][$key])!==null)
				return $t;


			Swisdk::log('Searching for '.$key, 'smarty');

			$tmpl = $this->_template($key);
			$bases = Swisdk::loader_bases();

			foreach($bases as $b) {
				Swisdk::log('Probing '.$b.$tmpl, 'smarty');
				if($this->template_exists($b.$tmpl)) {
					Swisdk::log('Found '.$b.$tmpl, 'smarty');
					Swisdk::$cache['smarty'][$realm][$key] = $b.$tmpl;
					Swisdk::$cache_modified = true;
					return $b.$tmpl;
				}
			}

			Swisdk::$cache['smarty'][$realm][$key] = false;
			Swisdk::$cache_modified = true;
			Swisdk::log('No match for '.$key, 'smarty');

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

	function _smarty_swisdk_webroot($params, &$smarty)
	{
		static $values = array();
		$key = $params['key'];
		if(!isset($values[$key]))
			$values[$key] = Swisdk::config_value(
				'runtime.webroot.'.$key, '/'.$key);
		return $values[$key];
	}

	function _smarty_swisdk_css_classify($params, &$smarty)
	{
		return cssClassify(Swisdk::config_value('runtime.'.$params['key']));
	}

	function _smarty_swisdk_generate_url($params, &$smarty)
	{
		static $generator = null;
		if($generator===null)
			$generator = Swisdk::load_instance('UrlGenerator');
		return $generator->generate_url($params['item']);
	}

	function _smarty_swisdk_generate_image_url($params, &$smarty)
	{
		$a = $params['album']->name;
		$i = $params['image'];
		$t = $params['type'];

		if(isset($params['generate']) && $params['generate'])
			ImageManager::generate_type(GALLERY_INCOMING_ROOT.$a.'/'.$i->file,
				$t, 'gallery/'.$a);

		return Swisdk::config_value('runtime.webroot.data', '/data')
			.'/gallery/'.$a.'/'.$i->{'filename_'.$t};
	}

	function _smarty_swisdk_dttm_range($params, &$smarty)
	{
		return dttmRange($params['item']);
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
		if(isset($params['id']) && $id = $params['id']) {
			$smarty->assign($params['name'], DBObject::find($params['class'], $id));
			return;
		}
		$clauses = array();
		foreach($params as $k => $v) {
			switch($k) {
				case 'order':
					$clauses[':order'] = explode(':', $v);
					break;
				case 'limit':
					$clauses[':limit'] = $v;
					break;
				case 'join':
					$clauses[':join'] = $v;
					break;
				case 'cut_off_future':
					$clauses[$v.'<'] = time();
					break;
				case 'cut_off_past':
					$clauses[$v.'>'] = time();
					break;
				case 'class':
				case 'name':
				case 'id':
					break;
				default:
					$tokens = explode(':', $v);
					$clauses[$tokens[0]] = $tokens[1];
			}
		}

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
