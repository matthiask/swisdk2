<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SwisdkSitemap {

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

			// if runtime.domain is set (multi domain support has been activated),
			// we need to start directly with the domain token. Otherwise, the
			// empty token is needed too for the first sitemap node
			if(Swisdk::config_value('runtime.domain'))
				$tokens = explode('/', trim($url, '/'));
			else
				$tokens = explode('/', rtrim($url, '/'));

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
			static $sitemap = null;
			if($sitemap === null) {
				$xmlfile = Swisdk::website_config_value('sitemap',
					'sitemap.xml');
				$xslfile = SWISDK_ROOT.'lib/sitemap-php.xsl';
				$phpfile = CACHE_ROOT.'sitemap-'.sanitizeFilename($xmlfile).'.php';
				$xmlfile = CONTENT_ROOT.$xmlfile;

				global $_swisdk2_sitemap;

				$regenerate = false;
				if(file_exists($phpfile)) {
					// regenerate if either sitemap.xml or the xsl
					// stylesheet are newer than the cached php
					// version
					$xmls = stat($xmlfile);
					$xsls = stat($xslfile);
					$phps = stat($phpfile);
					if(max($xmls['mtime'],$xsls['mtime'])>$phps['mtime'])
						$regenerate = true;
				} else
					$regenerate = true;

				if($regenerate) {
					$prc = new XSLTProcessor();
					$xsl = new DOMDocument();
					$xml = new DOMDocument();
					if(!$xml->load($xmlfile))
						SwisdkError::handle(new FatalError(sprintf(
							dgettext('swisdk', 'Could not load sitemap %s'),
							$xmlfile)));
					if(!$xsl->load($xslfile))
						SwisdkError::handle(new FatalError(sprintf(
							dgettext('swisdk', 'Could not load sitemap xsl %s'),
							$xslfile)));
					$prc->importStyleSheet($xsl);
					file_put_contents($phpfile, $prc->transformToXML($xml));
				}

				require_once $phpfile;

				// do some postprocessing
				// add full URLs and titles for every page, and also
				// parent references where applicable

				if(!isset($_swisdk2_sitemap['processed'])) {
					require_once UTF8.'/ucwords.php';
					SwisdkSitemap::loop_pages($_swisdk2_sitemap);
					$_swisdk2_sitemap['processed'] = true;
					file_put_contents($phpfile,
						'<?php $_swisdk2_sitemap='
						.var_export($_swisdk2_sitemap, true).'?>');
				}

				$sitemap = $_swisdk2_sitemap;
			}

			return $sitemap;
		}

		protected static function loop_pages(&$pages, $prefix=null)
		{
			if(!isset($pages['pages']))
				return;
			foreach($pages['pages'] as $id => &$page) {
				if(!isset($page['url']))
					$page['url'] = $prefix.$id;
				if(!isset($page['title']))
					$page['title'] = utf8_ucwords(preg_replace('/[ _]+/', ' ', $id));
				if(isset($page['pages'])) {
					if(isset($page['domain']))
						SwisdkSitemap::loop_pages($page, $prefix.'/');
					else
						SwisdkSitemap::loop_pages($page, $prefix.$id.'/');
				}
				$page['parent_title'] = isset($pages['title'])?$pages['title']:'';
				$page['parent_url'] = isset($pages['url'])?$pages['url']:'/';
			}
		}
	}

?>
