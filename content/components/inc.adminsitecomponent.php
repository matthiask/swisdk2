<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.sitemap.php';

	class AdminSiteComponent implements ISmartyComponent {
		protected $prepend = null;

		public function run()
		{
		}

		public function set_smarty(&$smarty)
		{
			$smarty->assign('modules', SwisdkSitemap::page('/admin'));
			$smarty->assign('currentmodule', SwisdkSitemap::page(
				Swisdk::config_value('runtime.controller.url')));
		}

		protected $html;
	}

?>
