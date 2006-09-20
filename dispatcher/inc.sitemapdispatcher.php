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
		public function collect_informations()
		{
			$input = $this->input();
			$page = SwisdkSitemap::page($input);
			if(isset($page['title']))
				Swisdk::set_config_value('runtime.page.title',
					$page['title']);
			if($page===false) {
				$page = SwisdkSitemap::page($input,
					'default', true);
				if(isset($page['rewrite']))
					$this->set_output(str_replace(
						$page['url'], $page['rewrite'],
						$input));
			} else if(isset($page['path']))
				$this->set_output($page['path']);
		}
	}

?>
