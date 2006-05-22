<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class ControllerDispatcher extends ControllerDispatcherModule
	{
		public function collectInformations()
		{
			echo "CD: my input is " . $this->getInput() . "<br>";
			
			$tokens = explode('/', substr( $this->getInput(), 1 ) );


			while(true) {
				
				$path = CONTENT_ROOT . implode( '/', $tokens);
								
				if( count( $matches = glob( $path . '_*' ) ) ) {
				 	
					if( is_file( $matches[ 0 ] ) ) {
						
						Swisdk::set_config_value('runtime.controller.url',
							preg_replace('/[\/]+/', '/', '/'.implode('/',$tokens).'/'));
						Swisdk::set_config_value( 'runtime.includefile', $matches[0] );
						
						Swisdk::set_config_value( 'runtime.arguments', array_slice(
							explode('/', substr($urifragment,1) ),
							count($tokens) ) );
						return;
					}
				}
				
				if( !count( $tokens ) )
					return;
				
				array_pop($tokens);
			}
		}
	}
?>