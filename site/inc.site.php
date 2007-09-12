<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.permission.php';

	abstract class Site implements IComponent {
		public function __construct()
		{
		}

		public function url()
		{
			return Swisdk::config_value('runtime.controller.url');
		}

		public function goto($frag=null)
		{
			redirect($this->url().$frag);
		}

		public function handle_component_call($args=null)
		{
			$cmp_class = Swisdk::config_value('runtime.dispatcher.component');

			if(!$cmp_class)
				return;

			$cmp = Swisdk::load_instance($cmp_class.'Component', 'components');
			if($cmp instanceof IComponent)
				$cmp->run();
			if($cmp instanceof IHtmlComponent)
				echo $cmp->html();
			if($cmp instanceof ISmartyComponent) {
				$smarty = $this->smarty();
				$cmp->set_smarty($smarty);
				$smarty->display_template('base.full');
			}

			Swisdk::shutdown();
		}

		public function run_website_components($smarty)
		{
			$components = Swisdk::website_config_value('components');

			if(is_array($components)) {
				foreach($components as &$c) {
					$c = trim($c);
					if(!$c)
						continue;
					$cmp = Swisdk::load_instance($c.'Component', 'components');
					if($cmp instanceof IComponent)
						$cmp->run();
					if($cmp instanceof IHtmlComponent)
						$smarty->assign(strtolower($c), $cmp->html());
					if($cmp instanceof ISmartyComponent)
						$cmp->set_smarty($smarty);
				}
			}
		}

		public function basedir()
		{
			return dirname(Swisdk::config_value('runtime.includefile')).'/';
		}

		public function smarty()
		{
			require_once MODULE_ROOT.'inc.smarty.php';
			static $smarty = null;

			if(!$smarty)
				$smarty = new SwisdkSmarty();

			return $smarty;
		}

		public function catalog_load($file)
		{
			require_once MODULE_ROOT.'inc.catalog.php';

			Catalog::load($this->basedir().$file);
		}
	}

?>
