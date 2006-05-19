<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	*	entry point for php errors
	*/
	function swisdk_php_error_handler( $errno, $errstr, $file, $line, $context )
	{
		if( $errno == 1 || $errno == 256 )
			SwisdkError::handle( new PHPError( $errno, $errstr, $file, $line, $context ) );
		
	}
	
	function swisdk_php_exception_handler( $exception )
	{
		SwisdkError::handle( new UnhandledExceptionError( $exception ) );
	}
	
	class SwisdkError {
		public static function handle( $eObj )
		{
			if( $eObj instanceof BasicSwisdkError ) {
				$eObj->run();
			}
		}
		
		public static function isError( $eObj )
		{
			return $eObj instanceof BasicSwisdkError;
		}
		
		public static function setup()
		{
			set_error_handler( 'swisdk_php_error_handler' );
			set_exception_handler( 'swisdk_php_exception_handler' );
		}
	}
	
	class BasicSwisdkError {
		protected $message;
		protected $noerror = false;
		
		public function __construct( $message = null )
		{
			$this->message = $message;
		}
		
		public function run()
		{
			if($this->noerror)
				return;
			echo '<div style="background:#f88;padding:10px;border:2px solid black;">'.$this->message.'<br/><br/><strong>backtrace:</strong><br/><pre>';
			debug_print_backtrace();
			echo '</pre></div>';
			die();
		}
		
		public function to_string()
		{
			return $this->message;
		}
	}
	
	class DBError extends BasicSwisdkError {
		public function __construct( $message, $sql = null )
		{
			// should branch depending on who is logged in
			// admins will see the sql string, all others will not
			$this->message = $message . "\n$sql";
		}
	}
	
	class FatalError extends BasicSwisdkError {
	}
	
	class SiteNotFoundError extends BasicSwisdkError {
		public function run()
		{
			header( 'HTTP/1.0 404 Not Found' );
			echo 'Site not found';
			exit();
		}
	}
	
	class PHPError extends BasicSwisdkError {
		public function __construct( $errno, $errstr, $file, $line, $context )
		{
			if(!($errno&ini_get('error_reporting')))
				$this->noerror = true;
			$this->message = "Error: $errno ( $errstr ) in $file at line $line";
			$this->run();
		}
	}
	
	class UnhandledExceptionError extends BasicSwisdkError {
		public function __construct( $exception )
		{
			$this->message = "Uncaught exception: " . $exception->getMessage();
		}
	}
	
	/**
	class SmartyTemplateError extends BasicSwisdkError {
		// this could be an error class able to display a nice message :-)
	}
	*/
		
?>
