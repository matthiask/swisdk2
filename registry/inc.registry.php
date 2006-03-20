<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
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
