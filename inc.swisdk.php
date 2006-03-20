<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >, Moritz ZumbŸhl < mail@momoetomo.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse (http://pflanzschule.irregular.ch/)
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/
	
	class Swisdk {
		
		private static $loadedModules = array();
		
		/**
		*	here the whole fun begins when the request comes from a http or https client. 
		*	the methods set some basic definitions and collects the arguments for the
		*	the method Swisdk::run()
		*/
		public static function runFromHttpRequest()
		{
			define( 'APP_ROOT', $_SERVER['DOCUMENT_ROOT'] . '/../' );
			define( 'HTDOCS_ROOT', APP_ROOT . 'htdocs/' );
			define( 'SWISDK_ROOT', APP_ROOT . 'swisdk/' );
			define( 'SMARTY_ROOT', SWISDK_ROOT . 'lib/smarty/' );
			define( 'MODULE_ROOT', SWISDK_ROOT . 'modules/' );
			define( 'CONTENT_ROOT' , APP_ROOT . 'content/' );
				
			require_once SWISDK_ROOT . 'core/inc.functions.php';
			require_once SWISDK_ROOT . 'core/inc.error.php';
			require_once SWISDK_ROOT . 'registry/inc.registry.php';
			require_once SWISDK_ROOT . 'resolver/inc.resolver.php';
			require_once SWISDK_ROOT . 'site/inc.siterunner.php';
			
			Swisdk::run( array( 'REQUEST_URI' =>  $_SERVER[ 'REQUEST_URI' ] ) );
		}
		
		/**
		*	here the whole fun begins when the request comes from the commandlien.
		* 	the method sets some basic definitons and collects the arguments for the method Swisdk::run()
		*
		*/
		public static function runFromCommandLine() 
		{
			
			/**
			 	the $_Server[Document_root] is not set when the call comes from the commandline.
				so we use the SCRIPT_NAME and assume that the file is in APP_ROOT/swisdk/commandline.php 
			*/
			$swisdk = substr( __FILE__ ,  0 , strrpos(__FILE__ , "/") );
			$apppath = substr( $swisdk , 0 ,  strrpos( $swisdk , "/")+1 );
	
			define( 'APP_ROOT', $apppath );
			define( 'HTDOCS_ROOT', APP_ROOT . 'htdocs/' );
			define( 'SWISDK_ROOT', APP_ROOT . 'swisdk/' );
			define( 'SMARTY_ROOT', SWISDK_ROOT . 'lib/smarty/' );
			define( 'MODULE_ROOT', SWISDK_ROOT . 'modules/' );
			define( 'CONTENT_ROOT' , APP_ROOT . 'content/' );
				
			require_once SWISDK_ROOT . 'core/inc.functions.php';
			require_once SWISDK_ROOT . 'core/inc.error.php';
			require_once SWISDK_ROOT . 'registry/inc.registry.php';
			require_once SWISDK_ROOT . 'resolver/inc.resolver.php';
			
			$requestUri = '';
			if( isset( $_SERVER['argv'][1]) ) {
				$requestUri = $_SERVER['argv'][1];
			}
			
			Swisdk::run( array( 'REQUEST_URI' => $requestUri  ) );
		}
		
		/**
		*	Let the party begin! Setup the registry and run the startup pipe. ...
		*/
		public static function run( $arguments ) 
		{
			SwisdkError::setup();
			SwisdkResolver::run( $arguments[ 'REQUEST_URI' ] );
			SiteRunner::run();
		
		}
		
		/**
		*	load swisdk modules honoring dependencies
		*/
		public static function loadModule( $moduleName )
		{
			if( !in_array( $moduleName, Swisdk::$loadedModules ) ) {
				$registry = SwisdkRegistry::getInstance();
				$params = $registry->getValue( "/swisdk/modules/module[@name='$moduleName']/*", true, true );
				if( !$params or !count( $params ) ) {
					SwisdkError::handle( new FatalError( "Unable to load module $moduleName" ) );
					return false;
				}
				
				$dependencies = $registry->getValue( "/swisdk/modules/module[@name='$moduleName']/depends/module", true );
				foreach( $dependencies as $dep ) {
					Swisdk::loadModule( $dep );
				}
				
				require_once MODULE_ROOT . $params[ 'includefile' ];
				
				Swisdk::$loadedModules[] = $moduleName;
			}
			return true;
		}
		
		/**
		*	helper variable for the load/register SiteController functions
		*/
		protected static $currentIncludeFile;
		
		public static function loadSiteController( $includeFile = null )
		{
			$registry = SwisdkRegistry::getInstance();
			
			if( $includeFile === null ) {
				$includeFile = $registry->getValue( '/runtime/includefile' );
			}
			
			Swisdk::$currentIncludeFile = $includeFile;
			require_once $includeFile;
		}
		
		public static function registerSiteController( $controller, $file = null )
		{
			$registry = SwisdkRegistry::getInstance();
			if( $file === null ) {
				$registry->setValue( '/runtime/includefile', Swisdk::$currentIncludeFile );
			} else {
				$registry->setValue( '/runtime/includefile', $file );
			}
			$registry->setValue( '/runtime/controller', $controller );
		}
		
	}

?>