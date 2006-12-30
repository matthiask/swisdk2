<?php
	/**
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.sitemap.php';

	/**
	 * Uses the sitemap to find out more about the current site and also to rewrite
	 * the incoming request if applicable
	 *
	 * - Sets runtime.page.title
	 * - If @path is set on the exact node for the current request, the request uri
	 *   is changed to @path
	 * - If rewrite is set, a whole url tree gets mirrored
	 *
	 * Example:
	 *
	 * /d/news[@path=/blog]
	 * - requests to /d/news get redirected to /blog
	 * - requests to /d/news/something are left as they are
	 *
	 * /d/news[@rewrite=/blog]
	 * - requests to /d/news as well as requests for /d/news/something are redirected
	 *   to /blog/ respectively /blog/something
	 */
	class SitemapDispatcher extends ControllerDispatcherModule {
		protected $redirect = null;
		protected $rewroten = null;
		protected $tokens;
		protected $domain;

		public function collect_informations()
		{
			$input = $this->input();

			$sitemap = SwisdkSitemap::sitemap();
			$ref =& $sitemap;

			$this->tokens = explode('/', trim($input, '/'));
			$this->domain = Swisdk::config_value('runtime.domain');
			// null is ok too
			array_unshift($this->tokens, $this->domain);
			$this->rewroten = null;

			while(($t = array_shift($this->tokens))!==null) {
				if(!isset($ref['pages'][$t]))
					break;

				$this->rewroten = null;

				$ref =& $ref['pages'][$t];
				$this->walk_page($ref);
			}

			if($this->redirect)
				redirect($this->redirect);
			if($this->rewroten)
				$this->set_output($this->rewroten);
		}

		protected function walk_page(&$page)
		{
			foreach($page as $k => &$v) {
				switch($k) {
					case 'language':
						Swisdk::set_language($v);
						break;
					case 'website':
						Swisdk::set_config_value(
							'runtime.website', $v);
						Swisdk::set_config_value('runtime.website.title',
							Swisdk::website_config_value('title'));
						Swisdk::add_loader_base(
							CONTENT_ROOT
							.($this->domain?$this->domain.'/':'')
							.substr($page['url'], 1).'/');
						break;
					case 'title':
						Swisdk::set_config_value('runtime.page.title',
							$v);
						break;
					case 'redirect-exact':
						if(!count($this->tokens))
							redirect($v);
						break;
					case 'redirect':
						$this->redirect = str_replace($page['url'], $v,
							$this->input());
						break;
					case 'rewrite-exact':
						if(!count($this->tokens))
							$this->set_output($v);
						break;
					case 'rewrite':
						Swisdk::set_config_value('runtime.controller.url',
							$page['url'].'/');
					case 'rewrite-tree':
						$this->rewroten = str_replace($page['url'], $v,
							$this->input());
						break;
					case 'pages':
					case 'parent_title':
					case 'parent_url':
					case 'id':
					case 'url':
					case 'domain':
						break;
					default:
						Swisdk::set_config_value(
							'runtime.'.str_replace(':', '.', $k), $v);
				}
			}
		}
	}

?>
