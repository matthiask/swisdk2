<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SwisdkSitemap {

		protected $namespaces = array(
			'adminsite' => 'http://spinlock.ch/projects/swisdk/adminsite',
			'dispatcher' => 'http://spinlock.ch/projects/swisdk/dispatcher',
			'navigation' => 'http://spinlock.ch/projects/swisdk/navigation',
			'sitemap' => 'http://spinlock.ch/projects/swisdk/sitemap',
			'urlgenerator' => 'http://spinlock.ch/projects/swisdk/urlgenerator',
			'xi' => 'http://www.w3.org/2001/XInclude');

		protected $dom;
		protected $xmlfile;
		protected $sitemap;

		protected function __construct()
		{
			$this->xmlfile = Swisdk::website_config_value('sitemap',
				'sitemap.xml');
			$xslfile = SWISDK_ROOT.'lib/sitemap-php.xsl';
			$phpfile = CACHE_ROOT.'sitemap-'.sanitizeFilename($this->xmlfile).'.php';
			$this->xmlfile = CONTENT_ROOT.$this->xmlfile;

			global $_swisdk2_sitemap;

			$regenerate = false;
			if(file_exists($phpfile)) {
				// regenerate if either sitemap.xml or the xsl
				// stylesheet are newer than the cached php
				// version
				if(max(filemtime($this->xmlfile), filemtime($xslfile))
						>filemtime($phpfile))
					$regenerate = true;
			} else
				$regenerate = true;

			if($regenerate) {
				$prc = new XSLTProcessor();
				$xsl = new DOMDocument();
				$xml = new DOMDocument();
				if(!$xml->load($this->xmlfile))
					SwisdkError::handle(new FatalError(sprintf(
						'Could not load sitemap %s',
						$this->xmlfile)));
				if(!$xsl->load($xslfile))
					SwisdkError::handle(new FatalError(sprintf(
						'Could not load sitemap xsl %s',
						$xslfile)));
				$xml->xinclude();
				$prc->importStyleSheet($xsl);
				file_put_contents($phpfile, $prc->transformToXML($xml));
			}

			require_once $phpfile;

			// do some postprocessing
			// add full URLs and titles for every page, and also
			// parent references where applicable

			if(!isset($_swisdk2_sitemap['processed'])) {
				require_once UTF8.'/ucwords.php';
				$this->loop_pages($_swisdk2_sitemap);

				s_set($_swisdk2_sitemap, 'url', '/');
				s_set($_swisdk2_sitemap, 'title',
					Swisdk::config_value('core.name'));

				$_swisdk2_sitemap['processed'] = true;
				file_put_contents($phpfile,
					'<?php $_swisdk2_sitemap='
					.var_export($_swisdk2_sitemap, true).'?>');
			}

			$this->sitemap = $_swisdk2_sitemap;
		}

		public function &instance()
		{
			static $instance = null;
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
		public static function sitemap()
		{
			return SwisdkSitemap::instance()->_sitemap();
		}

		public function &_sitemap()
		{
			return $this->sitemap;
		}

		protected function loop_pages(&$pages, $prefix=null)
		{
			if(!isset($pages['pages']))
				return;
			foreach($pages['pages'] as $id => &$page) {
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

		public function &_dom()
		{
			if(!$this->dom) {
				$this->dom = new DOMDocument();
				$this->dom->preserveWhiteSpace = false;
				$this->dom->load($this->xmlfile);
			}

			return $this->dom;
		}

		public function _get_page($url, &$remainder=null)
		{
			$search = false;
			if(is_array($remainder))
				$search = true;

			$tokens = explode('/', trim($url, '/'));

			do {
				$query = '/sitemap/page[@id="'.implode('"]/page[@id="', $tokens).'"]';

				$xpath = new DOMXpath($this->_dom());
				$entries = $xpath->query($query);

				if($entries->length) {
					$url = '/'.implode('/', $tokens);
					return $entries->item(0);
				}

				array_unshift($remainder, array_pop($tokens));
			} while($search && count($tokens));

			return false;
		}

		public static function page_raw($url)
		{
			$attributes = array();

			$ss = SwisdkSitemap::instance();

			$page = $ss->_get_page($url);

			foreach($page->attributes as $a) {
				if(($uri = $a->namespaceURI)
						&& $k = array_search($uri, $ss->namespaces))
					$attributes[$k.'.'.$a->name] = $a->value;
				else
					$attributes[$a->name] = $a->value;
			}

			return $attributes;
		}

		public static function set_page_raw($url, $attributes)
		{
			$ss = SwisdkSitemap::instance();

			$dom = $ss->_dom();

			$remainder = array();
			$page = $ss->_get_page($url, $remainder);
			foreach($remainder as $r) {
				$elem = $dom->createElement('page');
				$elem->setAttribute('id', $r);
				$elem->setAttribute('title', $r);
				$page->appendChild($elem);
				$page = clone($page); // WTF... reference problems!?
				$page = $elem;
			}

			foreach($attributes as $k => $v) {
				$toks = explode('.', $k);
				if(count($toks)==2)
					$page->setAttributeNS($ss->namespaces[$toks[0]],
						$toks[1], $v);
				else
					$page->setAttribute($k, $v);
			}
		}

		public static function delete_page_raw($url)
		{
			$ss = SwisdkSitemap::instance();
			$remainder = array();
			$page = $ss->_get_page($url, $remainder);
			if(!count($remainder))
				$page->parentNode->removeChild($page);
		}

		public static function children($url)
		{
			$ss = SwisdkSitemap::instance();

			$page = $ss->_get_page($url);

			$children = array();
			for($i=0; $i<$page->childNodes->length; $i++)
				$children[] = $page->childNodes->item($i)->getAttribute('id');

			return $children;
		}

		public static function reorder_children($url, $children)
		{
			$ss = SwisdkSitemap::instance();

			$page = $ss->_get_page($url);

			$nodes = array();

			while($page->childNodes->length) {
				$node = $page->childNodes->item(0);
				$nodes[$node->getAttribute('id')] = $node;
				$page->removeChild($node);
			}

			foreach($children as $c) {
				$page->appendChild($nodes[$c]);
				unset($nodes[$c]);
			}

			foreach($nodes as $node)
				$page->appendChild($node);
		}

		public static function store()
		{
			$ss = SwisdkSitemap::instance();
			file_put_contents($ss->xmlfile, $ss->_dom()->saveXML());
		}
	}

?>
