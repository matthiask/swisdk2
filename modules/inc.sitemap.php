<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SwisdkSitemap {

		protected $sitemap;
		protected $yamlfile;
		protected $sitemap_raw;

		protected function __construct()
		{
			$this->yamlfile = Swisdk::website_config_value('sitemap',
				'sitemap.yaml');
			$phpfile = CACHE_ROOT.'sitemap-'.sanitizeFilename($this->yamlfile).'.php';
			$this->yamlfile = CONTENT_ROOT.$this->yamlfile;

			global $_swisdk2_sitemap;

			$regenerate = true;
			if(file_exists($phpfile)) {
				// regenerate if either sitemap.yaml or the xsl
				// stylesheet are newer than the cached php
				// version
				if(filemtime($this->yamlfile)>filemtime($phpfile))
					$regenerate = true;
			} else
				$regenerate = true;

			if($regenerate) {
				$this->sitemap_raw = $this->_sitemap_raw();
				$_swisdk2_sitemap = $this->sitemap_raw;

				// do some postprocessing
				// add full URLs and titles for every page, and also
				// parent references where applicable
				require_once UTF8.'/ucwords.php';
				$this->loop_pages($_swisdk2_sitemap,
					Swisdk::config_value('runtime.domain')?'':'/');

				file_put_contents($phpfile,
					'<?php $_swisdk2_sitemap='
					.var_export($_swisdk2_sitemap, true).'?>');
			} else {
				require_once $phpfile;
			}

			$this->sitemap = $_swisdk2_sitemap;
		}

		public static function instance()
		{
			static $instance;
			if($instance===null)
				$instance = new SwisdkSitemap();

			return $instance;
		}

		/**
		 * return a fragment of the sitemap as specified
		 * with the $url parameter
		 *
		 * @param partial: is a partial URL allowed?
		 */
		public static function page($url, $site = 'default', $partial = false)
		{
			$sitemap = SwisdkSitemap::sitemap();
			$ref =& $sitemap;
			if(array_keys($ref)==array(''))
				$ref = reset($ref);
			$tokens = array();

			$tokens = explode('/', trim($url, '/'));

			// drill down until there are no more URL tokens
			while(($t = array_shift($tokens))!==null) {
				if(isset($ref['pages'][$t]))
					$ref =& $ref['pages'][$t];
				else if($partial)
					return $ref;
				else
					return false;
			}

			return $ref;
		}

		/**
		 * return the whole sitemap
		 */
		public static function &sitemap()
		{
			return SwisdkSitemap::instance()->sitemap;
		}

		public static function page_raw($url)
		{
			$sitemap =& SwisdkSitemap::sitemap_raw();
			$ref =& $sitemap;
			if(array_keys($ref)==array(''))
				$ref = reset($ref);
			$tokens = array();

			$tokens = explode('/', trim($url, '/'));

			// drill down until there are no more URL tokens
			while(($t = array_shift($tokens))!==null) {
				if(isset($ref['pages'][$t]))
					$ref =& $ref['pages'][$t];
				else
					return false;
			}

			return $ref;
		}

		public static function set_page_raw($url, $page)
		{
			$sitemap =& SwisdkSitemap::sitemap_raw();
			$ref =& $sitemap;

			if(array_keys($ref)==array(''))
				$ref =& reset($ref);
			$tokens = array();

			$tokens = explode('/', trim($url, '/'));

			while(($t = array_shift($tokens))!==null) {
				if(isset($ref['pages'][$t]))
					$ref =& $ref['pages'][$t];
				else {
					array_unshift($tokens, $t);
					break;
				}
			}

			while(($t = array_shift($tokens))!==null) {
				$ref['pages'][$t] = array();
				$ref =& $ref['pages'][$t];
			}

			$ref = array_merge($ref, $page);
		}

		public static function page_raw_remove($url)
		{
			$tokens = explode('/', rtrim($url, '/'));
			$last = array_pop($tokens);
			$page =& SwisdkSitemap::page(implode('/', $tokens));
			if(!$page)
				return false;

			s_unset($page['pages'][$last]);
			return true;
		}

		public function reorder_pages($url, $order)
		{
			$page = SwisdkSitemap::page($url);
			if(!$page)
				return false;

			$children = $page['pages'];
			$page['pages'] = array();

			foreach($order as $child) {
				if(isset($children[$child])) {
					$page['pages'][$child] = $children[$child];
					unset($children[$child]);
				}
			}

			foreach($children as $id => $child)
				$page['pages'][$id] = $child;

			return true;
		}

		protected function &_sitemap_raw()
		{
			if(!$this->sitemap_raw)
				$this->sitemap_raw = Spyc::YAMLLoad($this->yamlfile);

			return $this->sitemap_raw;
		}

		public static function &sitemap_raw()
		{
			return SwisdkSitemap::instance()->_sitemap_raw();
		}

		public function _store()
		{
			file_put_contents($this->yamlfile, Spyc::YAMLDump($this->sitemap_raw));
		}

		public static function store()
		{
			SwisdkSitemap::instance()->_store();
		}

		protected function loop_pages(&$pages, $prefix=null)
		{
			if(!isset($pages['pages']))
				return;
			foreach($pages['pages'] as $id => &$page) {
				$page['id'] = $id;
				if(!isset($page['url']))
					$page['url'] = '/'.$prefix.$id;
				if(!isset($page['title']))
					$page['title'] = utf8_ucwords(preg_replace('/[ _]+/', ' ', $id));
				if(isset($page['pages'])) {
					if(isset($page['domain']))
						$this->loop_pages($page, $prefix.'/');
					else
						$this->loop_pages($page, $prefix.$id.'/');
				}
				$page['parent_title'] = isset($pages['title'])?$pages['title']:'';
				$page['parent_url'] = isset($pages['url'])?$pages['url']:'/';
			}
		}
	}

?>
