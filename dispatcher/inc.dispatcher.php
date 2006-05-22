<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch> and Matthias Kestenholz
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
			$modules = explode( "," , Swisdk::config_value( "dispatcher.modules" ) );

			if( count( $modules ) )
			{	
				$urifragment = $url;
				
				foreach( $modules as $class ) {
					
					// load the dispatcher
					$instance = Swisdk::load_module( $class , "dispatcher/" );
					if( SwisdkError::is_error( $instance ) )
						SwisdkError::handle( $instance );
					
					// run the dispatcher on error stop
					$state = $instance->run( $urifragment ); 
					if( SwisdkError::is_error( $state ) )
						return $state;
						
					// set the output of the dispatcher to the new input 
					// of the following dispatcher	
					$urifragment = $instance->getOutput();
				}
				
			} else {
				SwisdkError::handle( new FatalError( "There are no controller dispatch modules! At least give me one - please!" ) );
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
		private function setError( $txt )
		{
			$this->mError = new ControllerDispatcherError( $txt , 
				$this->getInput() , $this->getOutput() );
		}
		
		/**
		*	Returns the error or null if no error happend
		*/
		public function getError()
		{
			return $this->mError;
		}
		
		/**
		*	Sets the input. The input is the uri fragment which the resolver gets
		*	by argument.
		*/
		public function setInput( $input )
		{
			$this->mInput = $input;
		}
		
		public function getInput()
		{
			return $this->mInput;
		}
		
		public function setOutput( $output )
		{
			$this->mOutput = $output;
		}
		
		public function getOutput()
		{
			return $this->mOutput;
		}
		
		public function run( $urifragment )
		{
			$this->setInput( $urifragment );
			/*
				default is output = input change this by calling setOuput 
				in the dispatcher module
			*/
			$this->setOutput( $urifragment ); 
			$this->collectInformations();
			return $this->getError();
		}
		
		abstract public function collectInformations();
	}
?>