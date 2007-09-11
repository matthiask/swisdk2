<?php
	/**
	*	Copyright (c) 2006, Moritz Zumbühl <mail@momoetomo.ch> and Matthias Kestenholz
	*	<mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/


	/**
	*	Finds with its submodules the right controller and save the arguments in
	*	the config registry.
	*/
	class SwisdkControllerDispatcher
	{
		static public function dispatch( $url )
		{
			Swisdk::log($url, 'DISPATCHER');

			$modules = Swisdk::config_value('dispatcher.modules');

			if( count( $modules ) )
			{
				$urifragment = $url;

				foreach( $modules as $class ) {
					$class = trim($class);

					// load the dispatcher
					$instance = Swisdk::load_instance($class, 'dispatcher');
					if( SwisdkError::is_error( $instance ) )
						SwisdkError::handle( $instance );

					// run the dispatcher on error stop
					$state = $instance->run( $urifragment );
					if( SwisdkError::is_error( $state ) )
						return $state;

					// set the output of the dispatcher to the new input
					// of the following dispatcher
					$urifragment = $instance->output();
				}

			} else {
				SwisdkError::handle(new FatalError(
					'Dispatcher configuration incomplete. No dispatcher modules'));
			}
		}
	}

	abstract class ControllerDispatcherModule
	{
		protected $mError = null;
		protected $mInput = null;
		protected $mOutput = null;


		/**
		*	Sets the error with the message and automaticaly with the input
		*	and the output
		*/
		private function set_error( $txt )
		{
			$this->mError = new ControllerDispatcherError( $txt ,
				$this->input() , $this->output() );
		}

		/**
		*	Returns the error or null if no error happend
		*/
		public function error()
		{
			return $this->mError;
		}

		/**
		*	Sets the input. The input is the uri fragment which the resolver gets
		*	by argument.
		*/
		public function set_input( $input )
		{
			$this->mInput = $input;
		}

		public function input()
		{
			return $this->mInput;
		}

		public function set_output( $output )
		{
			$this->mOutput = $output;
		}

		public function output()
		{
			return $this->mOutput;
		}

		public function run( $urifragment )
		{
			$this->set_input( $urifragment );
			/*
				default is output = input change this by calling setOuput
				in the dispatcher module
			*/
			$this->set_output( $urifragment );
			$this->collect_informations();
			return $this->error();
		}

		abstract public function collect_informations();
	}
?>
