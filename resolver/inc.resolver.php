<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/

	class SwisdkResolver {
		
		protected static $arguments;
		
		public static function run( $uri )
		{
			$tmp = explode( '?', $uri );
			$urifragment = $tmp[0];
			$registry = SwisdkRegistry::getInstance();

			$resolverModules = $registry->getValue( '/config/resolver/module', true );

			$resolved = false;
			foreach( $resolverModules as $moduleClass ) {
				$module = new $moduleClass;
				if( $module->process( $urifragment ) ) {
					// controller found - do not proceed further
					
					SwisdkResolver::$arguments = $module->getArguments();
					
					return true;
				}
				$urifragment = $module->getURIFragment();
			}

			return false;
		}
		
		public static function getArguments()
		{
			return SwisdkResolver::$arguments;
		}
	}
	
	abstract class SwisdkResolverModule {
		
		protected $urifragment;
		protected $arguments;
		
		public function __construct()
		{
		}

		/**
		*	@param	urifragment
		*	@returns bool: true if resolving may stop (controller found)
		*/
		public function process( $urifragment )
		{
			$this->urifragment = $urifragment;
		}

		/**
		*	@returns uri fragment for further processing (if necessary)
		*/
		public function getURIFragment()
		{
			return $this->urifragment;
		}
		
		/**
		*	@returns arguments for the site controller
		*/
		public function getArguments()
		{
			return $this->arguments;
		}
	}

	class DomainResolver extends SwisdkResolverModule {
		public function process( $urifragment )
		{
			// strip and ignore domain
			$this->urifragment = preg_replace( '/http(s?):\/\/[^\/]*(.*)/', '$1', $urifragment );
		}
	}

	class WebsiteResolver extends SwisdkResolverModule {
		public function process( $urifragment )
		{
			// if url begins with admin, set admin mode (very secure :-)
			$tokens = explode( '/', substr( $urifragment, 1 ) );
			if( in_array( array_shift( $tokens ), array( 'admin' ) ) ) {
				SwisdkRegistry::getInstance()->setValue( '/runtime/admin', 1 );
				$this->urifragment = implode( '/', $tokens );
			} else {
				SwisdkRegistry::getInstance()->setValue( '/runtime/admin', 0 );
				$this->urifragment = $urifragment;
			}
		}
	}
	
	class DBSitekeyResolver extends SwisdkResolverModule {
		public function process( $urifragment )
		{
			// for now, do no db resolving. I could as well disactivate
			// this module in the configuration...
			$this->urifragment = $urifragment;
			return false;
		}
	}
	
	class FilesystemResolver extends SwisdkResolverModule {
		public function process( $urifragment )
		{
			$registry = SwisdkRegistry::getInstance();

			$matches = array();
			$tokens = explode( '/', substr( $urifragment, 1 ) );
			$tokens[] = 'Index';	// default controller name
			
			while( ( !count( $matches = glob( CONTENT_ROOT . implode( '/', $tokens ) . '_*' ) )
									// continue while no matches were found at all
				&& ( count( $tokens ) >= 2 ) )		// and while token count is still greater than 1
									// (otherwise we glob for CONTENT_ROOT . '.*' )
				&& ( count( $matches ) == 0 || !is_file( $matches[0] ) ) ) {	// or the path is not a file
				// remove the last array element
				array_pop( $tokens );
			}
			
			if( isset( $matches[0] ) && $matches[0] ) {
				$registry->setValue( '/runtime/includefile', $matches[0] );
				$this->arguments = array_slice( explode( '/', substr( $urifragment, 1 ) ) , count( $tokens ) );
				return true;
			} else {
				return false;
			}
		}
	}

?>
