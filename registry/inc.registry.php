<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/

	require_once SWISDK_ROOT . 'registry/inc.domxmlclipboard.php';
	
	class SwisdkRegistry extends DOMXMLClipboardBase {
		
		/**
		*	singleton accessor
		*/
		public static function getInstance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new SwisdkRegistry( 'config.xml' );
				$instance->rootElement = '/registry';
			}
			
			return $instance;
		}
	}

?>
