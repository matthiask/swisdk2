<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class SiteHandler {
		abstract public function handle( $includeFile );
	}
	
	class DynamicSiteHandler extends SiteHandler {
		public function handle( $includeFile )
		{
			Swisdk::loadSiteController( $includeFile );
			$class = Swisdk::config_value('runtime.controller');
			
			if(!class_exists($class)) {
				SwisdkError::handle(new BasicSwisdkError("SiteController $class could not be found"));
			}
			
			$ctrl = new $class;
			$ctrl->set_arguments(SwisdkResolver::arguments());
			$ctrl->run();
		}
	}
	
	class TemplateSiteHandler extends SiteHandler {
		public function handle($includeFile)
		{
			echo file_get_contents($includeFile);
		}
	}

?>
