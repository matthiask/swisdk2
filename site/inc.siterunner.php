<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse (http://pflanzschule.irregular.ch/)
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
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
