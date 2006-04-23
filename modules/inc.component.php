<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/



	class BasicConfigurationComponent
	{
		protected $mConfiguration = array();
		
		/**
		*	Returns a copy of the configuration array.
		*/
		public function getConfiguration() {
			return $this->mConfiguration;
		}
		
		/**
		*	Returns the configuration value for the $name and if no key/value pair is found null.
		*	(the key is case insensitive!)
		*/
		public function getConfigValue( $name , $default = null ) {
			
			$name = strtolower( $name );
			if( isset( $this->mConfiguration[ $name ] ) ) {
				return $this->mConfiguration[ $name ];
			}
			
			return $default;
		}
		
		/**
		*	Sets the config value for the $name parameter. If the key is allready present the value is overwritten, but only
		*	if $overwrite is true.
		*	(the key is case insensitive!)
		*/
		public function setConfigValue( $name , $value , $overwrite = true ) {
		
			$name = strtolower( $name );
			if( !$overwrite && isset( $this->mConfiguration[ $name ] ) ) {
				return false;
			}
			
			return $this->mConfiguration[ $name ] = $value;
		}
		
		/**
		*	Merges the current config values with the new.
		*/
		public function setConfigValues( $values ) {
			
			if( is_array( $values ) ) {
				$values = array_change_key_case( $values , CASE_LOWER );
				$this->mConfiguration = array_merge( $this->mConfiguration , $values);
			}
		}
		
		/**
		*	Deletes all config values.
		*/
		public function emptyConfig() {
			$this->mConfiguration = array();	
		}
		
		public function __get( $var )
		{
			return $this->getConfigValue( $var );
		}

		public function __set($var, $value)
		{
			$this->setConfigValue( $var , $value );
		}
	}

	interface IComponent {
		public function run();
	}

	interface IHtmlComponent extends IComponent {
		public function html();
		public function name();
	}

	interface IFeedComponent extends IComponent {
		public function send_feed();
	}

	abstract class CommandComponent implements IHtmlComponent {
		protected $_html;
		protected $args;

		public function run()
		{
			// TODO make arguments assignable?
			$this->args = Swisdk::arguments();
			if(count($this->args)) {
				$cmd = $this->args[0];
				if(($cmd = $this->args[0])
						&& method_exists($this, 'cmd_'.$cmd)) {
					array_shift($this->args);
					$this->_html = $this->{'cmd_'.$cmd}();
					return;
				}
			}
			$this->_html = $this->cmd_index();
		}

		public function html()
		{
			return $this->_html;
		}

		public function goto($tok=null)
		{
			//FIXME LF attack
			header('Location: http://'
				.Swisdk::config_value('request.host')
				.Swisdk::config_value('runtime.controller.url')
				.$tok);
		}

		abstract protected function cmd_index();
	}

	/**
	 * further hints
	 */
	interface ISmartyAware {
		public function set_smarty_variables(&$smarty);
	}

	
	class BasicViewComponent implements IHtmlComponent
	{
		protected $mSmarty = null;
		protected $mTemplate = "";
		
		public function __construct( $template , $data , $name = "data" )
		{
			require_once SWISDK_ROOT . "modules/inc.smarty.php";
			
			$this->mSmarty = new SwisdkSmarty();
			$this->mSmarty->assign( $name , $data );
			$this->mTemplate = $template;
			
			
		}
		
		public function run()
		{
			return true;
		}
		
		public function name()
		{
			return "content";
		}
		
		public function html()
		{
			return $this->mSmarty->fetch( $this->mTemplate );
		}
	
	}

?>
