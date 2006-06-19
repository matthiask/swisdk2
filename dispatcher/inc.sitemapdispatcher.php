<?php
	/**
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.sitemap.php';

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
