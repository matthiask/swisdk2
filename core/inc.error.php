<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch> and Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	*	entry point for php errors
	*/
	function swisdk_php_error_handler($errno, $errstr, $file, $line, $context)
	{
		SwisdkError::handle(new PHPError($errno, $errstr, $file, $line, $context));
	}

	function swisdk_php_exception_handler($exception)
	{
		SwisdkError::handle(new UnhandledExceptionError($exception));
	}

	class SwisdkError {
		public static function handle($error)
		{
			$error->run();
		}

		public static function setup()
		{
			set_error_handler('swisdk_php_error_handler');
			set_exception_handler('swisdk_php_exception_handler');
		}

		public static function is_error($obj)
		{
			return ($obj instanceof BasicSwisdkError);
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
		protected $send_email;

		public function __construct( $message = null )
		{
			$this->args = func_get_args();
			$this->debug_mode = Swisdk::config_value('error.debug_mode');
			$this->send_email = Swisdk::config_value('error.email_notification');
		}

		public function run()
		{
			$dbgmsg = $this->to_string(true);
			if($this->send_email)
				$this->send_notification($dbgmsg);
			if(Swisdk::config_value('error.logging'))
				Swisdk::log($dbgmsg);
			$this->display();
			die();
		}

		public function display($message=null)
		{
			if(!$message)
				$message = $this->to_string($this->debug_mode);

			echo '<div style="background:#f88;padding:10px;border:2px solid black;">'
				.$message.'</div>';
		}

		public function to_string($debug_mode = false)
		{
			if($debug_mode)
				return $this->args[0] . $this->debug_string();

			return $this->args[0];
		}

		protected function debug_string()
		{
			return backtrace(true);
		}

		public function send_notification($message)
		{
			$this->send_email = false;

			$recipient = Swisdk::config_value('core.admin_email');
			if(!$recipient)
				return;
			@mail($recipient, 'Error: '.Swisdk::config_value('core.name'), $message,
				'From: swisdk-suckage@'.preg_replace('/^www\./', '',
					Swisdk::config_value('runtime.request.host')));
		}
	}

	/**
	 * - error output
	 * - sql string is shown if debug_mode is activated
	 * - terminates script
	 */
	class DBError extends BasicSwisdkError {
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
	 * same as BasicSwisdkError
	 * this class is a type hint (and its name is classy)
	 */
	class FatalError extends BasicSwisdkError {
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

			return dgettext('swisdk', 'An unexpected error occurred.');
		}
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
			$this->args[0] = sprintf(dgettext('swisdk', 'Uncaught exception: %s'), $exception->getMessage());
		}
	}

	/**
	 * this is self-explaining (...)
	 */
	class SiteNotFoundError extends BasicSwisdkError {
		public function run()
		{
			header('HTTP/1.0 404 Not Found');
			echo dgettext('swisdk', 'Site not found');
			exit();
		}
	}

	class AccessDeniedError extends BasicSwisdkError {
		public function run()
		{
			header('HTTP/1.0 401 Unauthorized');
			echo dgettext('swisdk', 'Access denied');
			exit();
		}
	}

?>
