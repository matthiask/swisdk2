<?php
	/**
	*	Copyright (c) 2007 Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class UrlPatternDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$input = trim($this->input(), '/');

			$patterns = array(
				array('/^article/', 'article/All_ctrl.php'),
				array('/^download/', 'download/All_ctrl.php'),
				array('/^event/', 'event/All_ctrl.php'),
				array('/^forum/', 'forum/All_ctrl.php'),
				array('/^/', 'All_ctrl.php'),
				null
				);

			$current = null;
			$matches = array();

			while($current = array_shift($patterns)) {
				if(preg_match($current[0], $input, $matches))
					break;
			}

			if(Swisdk::config_value('runtime.controller.url', '#')=='#')
				Swisdk::set_config_value('runtime.controller.url',
					Swisdk::config_value('runtime.navigation.prepend')
					.$matches[0]);
			Swisdk::set_config_value('runtime.includefile', CONTENT_ROOT.$current[1]);
			Swisdk::set_config_value('runtime.arguments', explode('/',
				preg_replace('/^'.preg_quote($matches[0], '/').'/', '', $input)));

			return true;
		}
	}

?>
