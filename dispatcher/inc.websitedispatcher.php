<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class WebsiteDispatcher extends ControllerDispatcherModule
	{
		public function collectInformations()
		{
			echo "DD: my input is " . $this->getInput() . "<br>";
			
			$input = $this->getInput();
			$websites = explode( "," , Swisdk::config_value("runtime.websites") );	
			$website = "default";
			if( count( $websites ) ) 
			{
				foreach( $websites as $webs )
				{
					if( strpos ( $input , "/$webs" , 0 ) === 0 )
					{
						$website = $webs;
						break;
					}

				}
			} 			
			
			Swisdk::set_config_value("runtime.website", $website );
		}
	}
?>