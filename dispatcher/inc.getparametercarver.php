<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/


	/* slice away all behind and inclusive the ??? marker... */
	class GetParameterCarver extends ControllerDispatcherModule
	{
		public function collectInformations()
		{
			$tmp = explode('?', $this->getInput() );
			$this->setOutput( $tmp[0] );
		}
	}
?>