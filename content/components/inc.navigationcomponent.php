<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.sitemap.php';

	class NavigationComponent implements ISmartyComponent {
		protected $prepend = null;

		public function run()
		{
			$sitemap = SwisdkSitemap::page(
				Swisdk::config_value('runtime.navigation.base'));
			$this->prepend = Swisdk::config_value('runtime.navigation.prepend');

			$this->html = $this->generate_navigation($sitemap,
				str_replace($this->prepend, '',
					Swisdk::config_value('runtime.request.uri')));
		}

		protected function generate_navigation($sitemap, $current)
		{
			if(isset($sitemap['pages'])) {
				$html = '<ul>';
				foreach($sitemap['pages'] as &$page) {
					if(isset($page['navigation.hidden'])
							&& $page['navigation.hidden']=='true')
						continue;
					$class = '';
					if((!isset($page['navigation.current'])
							|| !$page['navigation.current'])
							&& strpos($current.'/', $page['url'].'/')===0)
						$class = ' class="current"';
					$html .= '<li'.$class.'><a href="'.$this->prepend
						.$page['url'].'/">'.$page['title'].'</a>';
					$html .= $this->generate_navigation($page, $current);
					$html .= '</li>';
				}
				$html .= '</ul>';
				return $html;
			}
		}

		public function set_smarty(&$smarty)
		{
			$smarty->set_block_content('navigation', $this->html);
		}

		protected $html;
	}

?>
