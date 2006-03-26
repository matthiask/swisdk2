<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class SiteHandler {
		abstract public function handle($file);
	}
	
	class DynamicSiteHandler extends SiteHandler {
		public function handle( $file )
		{
			require_once $file;
			$class = Swisdk::config_value('runtime.controller');
			
			if(!class_exists($class)) {
				SwisdkError::handle(new BasicSwisdkError("SiteController $class could not be found"));
			}
			
			$ctrl = new $class;
			if($ctrl instanceof Site) {
				$ctrl->run();
			} else if($ctrl instanceof IComponent) {
				require_once SWISDK_ROOT . 'site/inc.site.php';
				$site = new ComponentRunnerSite($ctrl);
				$site->run();
			} else {
				echo 'Oops';
			}
		}
	}
	
	class TemplateSiteHandler extends SiteHandler {
		public function handle($file)
		{
			echo file_get_contents($file);
		}
	}

?>
