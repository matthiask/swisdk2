<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	*	some functions (especially those dealing with language ids) only make sense
	*	if we are dealing with a multilingual database
	*/
	
	class LanguageHandler {
		
		protected static $currentLanguageKey = null;
		protected static $availLanguages = array();
		
		private static $initialized = false;
		
		protected function __construct()
		{
			$registry = SwisdkRegistry::getInstance();
			// for now we get the available languages from the configuration file
			// tbl_languages (if it exists at all) is not looked at
			LanguageHandler::$availLanguages = $registry->getValue( '/config/language/available/lang', true );

			if( $lang = getInput( 'lang' ) ) {
				if( in_array( $lang, LanguageHandler::$availLanguages ) ) {
					LanguageHandler::$currentLanguageKey = $lang;
					// cookie expires in 30 days
					setcookie( 'language', $lang, time()+2592000 );
				}
			}
			
			if( LanguageHandler::$currentLanguageKey === null ) {
				if( isset( $_COOKIE[ 'language' ] ) && in_array( $_COOKIE[ 'language' ], LanguageHandler::$availLanguages ) ) {
					LanguageHandler::$currentLanguageKey = $_COOKIE[ 'language' ];
				} else {
					LanguageHandler::$currentLanguageKey = $registry->getValue( '/config/language/default' );
				}
			}
			
			$_SESSION[ 'language' ] = LanguageHandler::$currentLanguageKey;
			LanguageHandler::$initialized = true;
		}
		
		public static function getInstance()
		{
			static $instance = null;
			if( $instance === null ) {
				$instance = new LanguageHandler();
			}
			
			return $instance;
		}
		
		/**
		*	maps language keys to language ids
		*	@param key	language key.
		*	@return 	current	language id if key omitted, id corresponding to key
		*			otherwise
		*/
		public static function getLanguageId( $key = null )
		{
			if( !LanguageHandler::$initialized )
				LanguageHandler::getInstance();
		}
		
		/**
		*	maps language ids to language keys
		*	@param id	language id.
		*	@return 	current	language key if id omitted, key corresponding to id
		*			otherwise
		*/
		public static function getLanguageKey( $id = null )
		{
			if( !LanguageHandler::$initialized )
				LanguageHandler::getInstance();
			return LanguageHandler::$currentLanguageKey;
		}
	}

?>
