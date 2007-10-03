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
		protected $backtrace = true;
		protected $message;
		protected $args;

		protected $debug_mode;
		protected $send_email;

		protected $use_template = true;

		public function __construct($message = null)
		{
			$this->args = func_get_args();
			$this->init();
			$this->message = array_shift($this->args);
		}

		public function init()
		{
			if($this->backtrace!==false)
				$this->backtrace = backtrace();

			$this->debug_mode = Swisdk::config_value('error.debug_mode');
			$this->send_email = Swisdk::config_value('error.email_notification');

		}

		public function run()
		{
			$dbgmsg = $this->debug_message();
			if($this->send_email)
				$this->send_notification($dbgmsg);
			Swisdk::log($dbgmsg);
			$this->display();
			die();
		}

		public function debug_message()
		{
			return $this->message." <br />\n".$this->backtrace;
		}

		public function display()
		{
			if(!$this->use_template) {
				echo $this->message."<br />\n";
				if($this->debug_mode)
					echo "<br /><strong>Debug message:</strong><br />\n";
					echo $this->debug_message();
				return;
			}

			require_once MODULE_ROOT.'inc.smarty.php';
			$smarty = new SwisdkSmarty(false);
			$smarty->assign('title', 'Error');
			$smarty->assign('content', $this->message);
			if($this->debug_mode)
				$smarty->assign('messages', $this->debug_message());
			$smarty->display(SWISDK_ROOT.'content/swisdk/box.tpl');
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

		public function debug_message()
		{
			return $this->message." <br />\n".$this->args[0]
				." <br />\n".$this->backtrace;
		}
	}

	/**
	 * same as BasicSwisdkError
	 * this class is a type hint (and its name is classy)
	 */
	class FatalError extends BasicSwisdkError {
	}

	class ExtremelyFatalError extends FatalError {
		protected $use_template = false;
	}

	/**
	 * old school php erro :)
	 * (terminates script, or not: configurable, as always)
	 */
	class PHPError extends BasicSwisdkError {
		public function __construct()
		{
			$this->args = func_get_args();
		}

		public function run()
		{
			// honor modified error_reporting value
			if(!($this->args[0] & ini_get('error_reporting')))
				return;

			// see config file, section [core] ignore_error_nrs
			if(in_array($this->args[0], s_array(
					Swisdk::config_value('error.ignore_error_nrs'))))
				return;

			$this->init();
			$this->message = __CLASS__.": {$this->args[0]} ({$this->args[1]}) in"
					." {$this->args[2]} at line {$this->args[3]}";
			parent::run();
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
			parent::__construct(
				sprintf('Uncaught exception: %s', $exception->getMessage()));
		}
	}

	/**
	 * this is self-explaining (...)
	 */
	class SiteNotFoundError extends BasicSwisdkError {
		protected $backtrace = false;

		public function run()
		{
			header('HTTP/1.0 404 Not Found');
			require_once MODULE_ROOT.'inc.smarty.php';
			$smarty = new SwisdkSmarty();
			$smarty->display_template('swisdk.error.404');
			Swisdk::shutdown();
		}
	}

	class AccessDeniedError extends BasicSwisdkError {
		protected $backtrace = false;

		public function run()
		{
			header('HTTP/1.0 401 Unauthorized');
			require_once MODULE_ROOT.'inc.smarty.php';
			$smarty = new SwisdkSmarty();
			$smarty->display_template('swisdk.error.401');
			Swisdk::shutdown();
		}
	}

?>
