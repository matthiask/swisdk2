<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch> and Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	*	entry point for php errors
	*/
	function swisdk_php_error_handler( $errno, $errstr, $file, $line, $context )
	{
		SwisdkError::handle( new PHPError( $errno, $errstr, $file, $line, $context ) );
	}
	
	function swisdk_php_exception_handler( $exception )
	{
		SwisdkError::handle( new UnhandledExceptionError( $exception ) );
	}
	
	class SwisdkError {

		public static function handle( $eObj )
		{
			if($eObj instanceof BasicSwisdkError) {
				$eObj->run();
				return $eObj;
			} else {
				SwisdkError::handle(new FatalError(
					'SwisdkError::handle: argument has not type BasicSwisdkError'));
			}
		}
		
		public static function setup($handler = array(__CLASS__, 'standard_output_handler'))
		{
			SwisdkError::$handler_callback = $handler;
			set_error_handler( 'swisdk_php_error_handler' );
			set_exception_handler( 'swisdk_php_exception_handler' );
		}

		public static function is_error($obj)
		{
			return ($obj instanceof BasicSwisdkError);
		}

		protected static $handler_callback;

		// TODO write handlers for different output methods

		public static function standard_output_handler($obj)
		{
			// XXX delayed output?
			// TODO output brobber hmtl (fuck browsers)
			echo '<div style="background:#f88;padding:10px;border:2px solid black;">'
				.$obj->to_string($obj->debug_mode).'</div>';
		}

		public static function call_output_handler($obj)
		{
			call_user_func(SwisdkError::$handler_callback, $obj);
		}
	}
	
	/** 
	 * - logging to file
	 * TODO make logging configurable
	 */
	class BasicSwisdkError {
		public $args;
		public $debug_mode;
		
		public function __construct( $message = null )
		{
			$this->args = func_get_args();
			$this->debug_mode = Swisdk::config_value('core.debug_mode');
		}
		
		public function run()
		{
			$this->append_log_message('[DEBUG]: '.$this->to_string(true));
			SwisdkError::call_output_handler($this);
			die();
		}

		public function to_string($debug_mode = false)
		{
			if($this->debug_mode)
				return $this->args[0] . $this->debug_string();

			return $this->args[0];
		}

		protected function debug_string()
		{
			// FIXME capture output (use debug_backtrace?)
			return debug_print_backtrace();
		}

		public function append_log_message($message)
		{
			$fname = Swisdk::config_value('core.logfile');
			$fp = @fopen(LOG_ROOT.$fname, 'a');
			@fwrite($fp, date(DATE_W3C).' '.$message."\n");
			@fclose($fp);
		}
	}
	
	/**
	 * - error output
	 * - sql string is shown if debug_mode is activated
	 * - terminates script
	 */
	class DBError extends NotificationError {
		//
		// usage example:
		//
		// SwisdkError::handle(new DBError('DB error while doing xyz', $sql_string));
		//

		protected function debug_string()
		{
			// return the SQL string when debug mode is activated
			return "\n".$this->args[1];
		}
	}
	
	/**
	 * - error output
	 * - notification by email
	 */
	class NotificationError extends BasicSwisdkError {
		public function run()
		{
			$message = $this->to_string($this->debug_mode);
			// TODO make notification systems configurable?
			$this->send_notification($message);
			parent::run();
		}

		public function send_notification($message)
		{
			$recipient = Swisdk::config_value('core.admin_email');
			@mail($recipient, 'NotificationError: '.Swisdk::config_value('core.appname'),
				$message, 'From: swisdk-suckage@'.$_SERVER['SERVER_NAME']);
		}
	}

	/**
	 * same as NotificationError
	 * this class is a type hint (and its name is classy)
	 */
	class FatalError extends NotificationError {
	}
	
	/**
	 * old school php erro :)
	 * (terminates script, or not: configurable, as always)
	 */
	class PHPError extends BasicSwisdkError {
		public function run()
		{
			// honor modified error_reporting value
			if(!($this->args[0] & ini_get('error_reporting')))
				return;

			// see config file, section [core] ignore_error_nrs
			if(in_array($this->args[0], explode(',',
					Swisdk::config_value('core.ignore_error_nrs'))))
				return;
			parent::run();
		}

		public function to_string($debug_mode = false)
		{
			if($debug_mode)
				return __CLASS__.": {$this->args[0]} ({$this->args[1]}) in {$this->args[2]} at line {$this->args[3]}";
			
			return 'An unexpected PHP error occurred.';
		}
	}

	class SuicideError extends BasicSwisdkError {
	}
	
	/**
	 * No comments. We don't like exceptions.
	 *
	 * oops.
	 */
	class UnhandledExceptionError extends BasicSwisdkError {
		public function __construct($exception)
		{
			parent::__construct();
			// XXX
			$this->args[0] = "Uncaught exception: " . $exception->getMessage();
		}
	}

	/**
	 * this is self-explaining (...)
	 */

	class SiteNotFoundError extends BasicSwisdkError {
		public function run()
		{
			header('HTTP/1.0 404 Not Found');
			echo 'Site not found';
			exit();
		}
	}

?>
