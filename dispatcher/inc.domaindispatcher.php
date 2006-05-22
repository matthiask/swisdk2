<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class DomainDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$matches = array();
			$match = preg_match('/http(s?):\/\/([^\/]*)(:[0-9]+)?(.*)/',
				$this->input(), $matches);
			$this->set_output( $matches[4] );
			Swisdk::set_config_value('runtime.request.host', $matches[2]);
			Swisdk::set_config_value('runtime.request.uri', $matches[4]);
						
		}
	}
?>
