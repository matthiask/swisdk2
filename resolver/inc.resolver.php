<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class SwisdkResolver {
		
		protected static $arguments;
		
		public static function run($uri)
		{
			$tmp = explode('?', $uri);
			$urifragment = $tmp[0];
			$modules = array(
				'DomainResolver',
				'WebsiteResolver',
				'FilesystemResolver'
			);

			$resolved = false;
			foreach($modules as $class) {
				$module = new $class;
				if($module->process($urifragment)) {
					// controller found - do not proceed further
					SwisdkResolver::$arguments = $module->arguments();
					return true;
				}
				$urifragment = $module->get_uri_fragment();
			}

			return false;
		}
		
		public static function arguments()
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
		public function get_uri_fragment()
		{
			return $this->urifragment;
		}
		
		/**
		*	@returns arguments for the site controller
		*/
		public function arguments()
		{
			return $this->arguments;
		}
	}

	class DomainResolver extends SwisdkResolverModule {
		public function process( $urifragment )
		{
			$matches = array();
			$this->urifragment = preg_match('/http(s?):\/\/([^\/]*)(:[0-9]+)?(.*)/', $urifragment, $matches);
			$this->urifragment = $matches[4];
			Swisdk::set_config_value('request.host', $matches[2]);
			Swisdk::set_config_value('request.uri', $matches[4]);
		}
	}

	class WebsiteResolver extends SwisdkResolverModule {
		/* don't do anything
		public function process($urifragment)
		{
			// if url begins with admin, set admin mode (very secure :-)
			$tokens = explode('/', substr($urifragment, 1));
			if(in_array(array_shift($tokens), array('admin'))) {
				SwisdkRegistry::getInstance()->setValue( '/runtime/admin', 1 );
				$this->urifragment = implode( '/', $tokens );
			} else {
				SwisdkRegistry::getInstance()->setValue( '/runtime/admin', 0 );
				$this->urifragment = $urifragment;
			}
		}
		*/
	}
	
	class FilesystemResolver extends SwisdkResolverModule {
		public function process($urifragment)
		{
			$tokens = explode('/', substr($urifragment,1));

			while(true) {
				$path = CONTENT_ROOT.implode('/', $tokens);
				if(count($matches=glob($path.'/Index_*'))
					|| count($matches=glob($path.'_*'))) {
					if(is_file($matches[0])) {
						Swisdk::set_config_value('runtime.controller.url',
							preg_replace('/[\/]+/', '/', '/'.implode('/',$tokens).'/'));
						Swisdk::set_config_value('runtime.includefile', $matches[0]);
						$this->arguments = array_slice(
							explode('/', substr($urifragment,1)),
							count($tokens));
						return true;
					}
				}
				if(!count($tokens))
					return false;
				array_pop($tokens);
			}
		}
	}

?>
