<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SiteRunner {
		public static function run()
		{
			// the following lines should really be in the siterunner...
			$registry = SwisdkRegistry::getInstance();
			$includeFile = $registry->getValue( '/runtime/includefile' );
			if( !$includeFile ) {
				SwisdkError::handle( new SiteNotFoundError() );
			}
			
			$fileType = substr( $includeFile, strrpos( $includeFile, '_' ) + 1 );
			
			$handler = $registry->getValue( "/swisdk/siterunner/filetypes/filetype[@extension='$fileType']/*", true, true );
			
			if( isset( $handler['includefile'] ) && is_file( $handler['includefile'] ) ) {
				require_once $handler['includefile'];
			}
			
			$fileHandler = new $handler['class'];
			return $fileHandler->handle( $includeFile );
		}
	}
	
	abstract class SiteHandler {
		abstract public static function handle( $includeFile );
	}
	
	class DynamicSiteHandler extends SiteHandler {
		public static function handle( $includeFile )
		{
			Swisdk::loadSiteController( $includeFile );
			
			$class = SwisdkRegistry::getInstance()->getValue( '/runtime/controller' );
			
			if( !class_exists( $class ) ) {
				SwisdkError::handle( new BasicSwisdkError( "SiteController $class could not be found" ) );
			}
			
			$ctrl = new $class;
			$ctrl->setArguments( SwisdkResolver::getArguments() );
			$ctrl->run();
		}
	}
	
	class TemplateSiteHandler extends SiteHandler {
		public static function handle( $includeFile )
		{
			echo file_get_contents( $includeFile );
		}
	}

?>
