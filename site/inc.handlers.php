<?php
	/*
	*	Copyright (c) 2006, 
	*	Matthias Kestenholz <mk@spinlock.ch> 
	*	Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	
	abstract class SwisdkSiteHandler {
		
		/* check if enough information are avaible to instance the controler */
		/* instance handler and run the party! */
		
		public static function run()
		{		
			// at least we need the include file of the controller...
			$includefile = Swisdk::config_value('runtime.includefile');
			if( !$includefile )
				SwisdkError::handle( new SiteNotFoundError() );
			
			// get the type of the controller and instance it 
			$type = substr( $includefile, strrpos( $includefile, '_' )+1 );
			
			$handler = SwisdkSiteHandler::get_type_handler_instance( $type );
			if( SwisdkError::is_error( $handler ) )
				SwisdkError::handle( $handler );
			
			return $handler->handle();
		}
		
		public static function get_type_handler_instance( $type )
		{
			$classname = Swisdk::config_value( "dispatcher." . $type );
			if( $classname != null ) {
				return Swisdk::load_module( $classname , "sitehandlers/" );
			} else {
				return new FatalError( "Could not find the config-entry " . "runtime." . $type );
			}
		}
		
		abstract public function handle();
	}
	
	
	class PhpSiteHandler extends SwisdkSiteHandler
	{
		public function handle()
		{
			$includefile = Swisdk::config_value('runtime.includefile');
			require_once $includefile;
		
			// get the controller class  out of the config , the include file
			// sets the class name with Swisdk::register()
			
			$class = Swisdk::config_value('runtime.controller.class');
			
			if( !class_exists( $class) ) {
				SwisdkError::handle( new FatalError( "SiteController $class could not be found" ) );
			}
			
			$ctrl = new $class;
			if( $ctrl instanceof Site )
			{
				$ctrl->run();
				return;
			} else {
				SwisdkError::handle( new FatalError( "Controller is not subclass of Site!" ) );
			}
		}
	}
		
		
	/**
	*	Just send the file to the client... no fun just raw sending...
	*/
	class XHTMLSiteHandler extends SwisdkSiteHandler {
		public function handle()
		{
			echo file_get_contents($file);
		}
	}

	/** 
	*	Send the file to client using the smarty engine.
	*/
	class SmartySiteHandler extends SwisdkSiteHandler
	{
		public function handle()
		{
			$smartyM = SmartyMaster::instance();
			$smartyM->display( Swisdk::config_value('runtime.includefile') );
			return;
		}
	}

	/**
	*	Send first the header template of the website, then the include file and
	*	in the end the footer template.
	*/
	class EmbeddedSmartySiteHandler extends SwisdkSiteHandler	
	{	
		public function handle()
		{
			$smartyM = SmartyMaster::instance();
			$smartyM->displayHeader();
			$smartyM->display( Swisdk::config_value('runtime.includefile') );
			$smartyM->displayFooter();
		}
	}
	
?>
