<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class DomainDispatcher extends ControllerDispatcherModule
	{
		public function collectInformations()
		{
			echo "DD: my input is " . $this->getInput() . "<br>";
			
			$matches = array();
			$match = preg_match('/http(s?):\/\/([^\/]*)(:[0-9]+)?(.*)/', $this->getInput(), $matches);
			echo "DD output: " . $matches[4] . "<br>";
			parent::setOutput( $matches[4] );
			Swisdk::set_config_value('runtime.request.host', $matches[2]);
			Swisdk::set_config_value('runtime.request.uri', $matches[4]);
						
		}
	}
?>