<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>, Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	define( 'STREGION_ALL' , 0 );
	define( 'STREGION_FULL' , 1 );
	define( 'STREGION_HEADER' , 2 );
	define( 'STREGION_FOOTER' , 3 );

	/**
	 * E_STRICT wrapper for PHP4 compatible Smarty code!
	 */

	class SwisdkSmarty {
		public function __construct()
		{
			// make sure E_STRICT is turned off
			$er = error_reporting(E_ALL^E_NOTICE);
			require_once SWISDK_ROOT . 'lib/smarty/libs/Smarty.class.php';
			$this->smarty = new Smarty();
			$this->smarty->compile_dir = SWISDK_ROOT . 'lib/smarty/templates_c';
			$this->smarty->cache_dir = SWISDK_ROOT . 'lib/smarty/cache';
			$this->smarty->template_dir = CONTENT_ROOT;
			//$this->config_dir
			$this->caching = false;
			$this->security = false;
			error_reporting($er);
		}

		public function __call($method, $args)
		{
			$er = error_reporting(E_ALL^E_NOTICE);
			$ret = call_user_func_array(
				array(&$this->smarty, $method),
				$args);
			error_reporting($er);
			return $ret;
		}
	
		public function __get($var)
		{
			$er = error_reporting(E_ALL^E_NOTICE);
			$ret = $this->smarty->$var;
			error_reporting($er);
			return $ret;
		}

		public function __set($var, $value)
		{
			$er = error_reporting(E_ALL^E_NOTICE);
			$ret = ($this->smarty->$var = $value);
			error_reporting($er);
			return $ret;
		}

		protected $smarty;
	}

	class SmartyMaster {
		private static $mInstance = null;
		private $mSmarty = null;
		private $mHtmlHandlers = array();
	
		/**
		*	I'm a singleton!
		*/
		private function __construct() {}
	
		/**
		*	Use this to get the instance of the SmartyMaster.
		*	This method calls by the frst time also setup().
		*	So you don't have to call setup() after retrieving the instance.
		*/
		public static function instance() {
	
			if( SmartyMaster::$mInstance === null ) {
				SmartyMaster::$mInstance = new SmartyMaster();
				SmartyMaster::$mInstance->setup();
			}
		
			return SmartyMaster::$mInstance;
		}

		/**
		*	Setups the smarty master. The smarty master needs following config values:
		*	To know in which config section the template paths are stored the master reads
		*	the "runtime.website" value. This value is typically written by the
		*	dispatcher.
		*/
		public function setup( $website = null )
		{
		
			// the website ... we need that lather to read out the correct section
			// in the config
			if( $website === null ) {
				$website = Swisdk::config_value( "runtime.website" );
			}
		
			$this->mWebsite = $website;
		
			// check if there is a config section with values
			$cf = $this->mWebsite . ".";
			$this->mFullTemplate = Swisdk::config_value( $cf . "fullTemplate" );
			$this->mHeaderTemplate = Swisdk::config_value( $cf . "header" );
			$this->mFooterTemplate = Swisdk::config_value( $cf . "footer" );
			$this->mTitle = Swisdk::config_value( $cf . "title" );
		}

		/**
		*	Returns the reference to the smarty object of type SwisdkSmarty.
		*/
		public function smarty()
		{
			if( $this->mSmarty === null ) {
				$this->mSmarty = new SwisdkSmarty();
			}
			return $this->mSmarty;
		}
	
		/**
		*	Display a template.
		*/
		public function display( $template = "" , $region = STREGION_FULL )
		{
			if( $template === "" ) {
				$template = $this->mFullTemplate;
			}
		
			$smarty = $this->smarty();
			if( $smarty->template_exists( $template ) ) {
			
				if( $this->generate_handler_output( $region ) ) {
					$smarty->display($template);
					return true;
				} else  {
					// FIXME handle proper error... content generation error
					SwisdkError::handle( new FatalError( "" ) );
					return false;
				}
			
			} else {
				// FIXME handle proper error... ressource not found error
				SwisdkError::handle( new FatalError("Template not found! Path: $template") );
				return false;			
			}
		}
	
		public function display_header( $region = STREGION_HEADER )
		{
			return $this->display( $this->mHeaderTemplate , $region );
		}
	
		public function display_footer( $region = STREGION_FOOTER  )
		{
			return $this->display( $this->mFooterTemplate , $region );
		}
	
		public function add_html_handler( IHtmlComponent $component , $region = STREGION_FULL )
		{
			if( !is_array($this->mHtmlHandlers[ $region ] ) ) {
				$this->mHtmlHandlers[ $region ] = array( $component );
			} else {
				$this->mHtmlHandlers[ $region ][] = $component;
			}		
		}
	
		public function generate_handler_output( $region = STREGION_ALL )
		{		
			if( $region === STREGION_ALL ) {
			
				foreach( $this->mHtmlHandlers as $hGroup )
				{
					if( is_array( $hGroup ) ) {
						if( !$this->make_handler_output( $hGroup ) ) {
							return false;
						}
					}
				}
			
			} else {

				if( is_array( $this->mHtmlHandlers[$region] ) )
					return $this->make_handler_output( $this->mHtmlHandlers[$region] );
			}
		
			return true;
		}
	
	
		public function make_handler_output( $hGroup )
		{
			foreach( $hGroup as $element )
			{
				$smarty = $this->smarty();
			
				$content = $element->html();
				if( SwisdkError::is_error($content) ) {
					// FIXME return the error up to were generation error is outputed
					return false;
				}
			
				$smarty->assign( $element->name() , $content );
			}
		
			return true;
		}
	}

?>
