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
			$page = SwisdkSitemap::page($this->input());
			if($page!==false && isset($page['path']))
				$this->set_output($page['path']);
		}
		
	}

?>
