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
					'SwisdkError::handle: argument has not type'
					.' BasicSwisdkError'));
			}
		}

		public static function setup($handler = array(__CLASS__,
			'standard_output_handler'))
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

		public static function standard_output_handler($obj)
		{
			echo '<div style="background:#f88;padding:10px;border:2px solid black;">'
				.$obj->to_string($obj->debug_mode).'</div>';
		}

		public static function call_output_handler($obj)
		{
			call_user_func(SwisdkError::$handler_callback, $obj);
		}
	}

	/**
	 * - logging to file and email notification (configurable)
	 *
	 * see
	 * error.email_notification and error.logging config values
	 */
	class BasicSwisdkError {
		public $args;
		public $debug_mode;

		public function __construct( $message = null )
		{
			$this->args = func_get_args();
			$this->debug_mode = Swisdk::config_value('error.debug_mode');
		}

		public function run()
		{
			$dbgmsg = $this->to_string(true);
			if(Swisdk::config_value('error.email_notification'))
				$this->send_notification($dbgmsg);
			if(Swisdk::config_value('error.logging'))
				$this->append_log_message($dbgmsg);
			SwisdkError::call_output_handler($this);
			die();
		}

		public function to_string($debug_mode = false)
		{
			if($debug_mode)
				return $this->args[0] . $this->debug_string();

			return $this->args[0];
		}

		protected function debug_string()
		{
			$bt = debug_backtrace();
			$str = "<pre><b>Backtrace:</b>\n";
			$disp = false;
			foreach($bt as $frame) {
				if(!$disp && isset($frame['class'])
						&& $frame['class']=='SwisdkError'
						&& $frame['function']=='handle')
					$disp = true;
				if(!$disp)
					continue;
				$str .= sprintf("%s() called at [%s]\n",
					(isset($frame['class'])?
					$frame['class'].$frame['type']:'').
					$frame['function'],
					isset($frame['file'])?$frame['file'].':'
					.$frame['line']:'');
			}
			$str .= '</pre>';
			return $str;
		}

		public function append_log_message($message)
		{
			$fname = Swisdk::config_value('error.logfile');
			if(!$fname)
				return;
			$fp = @fopen(LOG_ROOT.$fname, 'a');
			@fwrite($fp, date(DATE_W3C).' '.$message."\n");
			@fclose($fp);
		}

		public function send_notification($message)
		{
			$recipient = Swisdk::config_value('core.admin_email');
			if(!$recipient)
				return;
			@mail($recipient, 'NotificationError: '.Swisdk::config_value('core.name'),
				$message, 'From: swisdk-suckage@'.$_SERVER['SERVER_NAME']);
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
			return "\n".$this->args[1]."\n".parent::debug_string();
		}
	}

	/**
	 * - error output
	 * - notification by email (always, do not honor error.email_notification)
	 */
	class NotificationError extends BasicSwisdkError {
		public function run()
		{
			$message = $this->to_string($this->debug_mode);
			$this->send_notification($message);
			parent::run();
		}
	}

	/**
	 * same as NotificationError
	 * this class is a type hint (and its name is classy)
	 */
	class FatalError extends NotificationError {
	}

	/**
	*	TODO add a check if the file which was not found just isnt readable for
	*	the webserver user...
	*/
	class FileNotFoundError extends NotificationError {
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
					Swisdk::config_value('error.ignore_error_nrs'))))
				return;
			parent::run();
		}

		public function to_string($debug_mode = false)
		{
			if($debug_mode)
				return __CLASS__.": {$this->args[0]} ({$this->args[1]}) in"
					." {$this->args[2]} at line {$this->args[3]}";

			return 'An unexpected error occurred.';
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
