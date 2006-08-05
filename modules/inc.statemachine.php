<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	*	The State classes indicate the state the component has actually.
	*	They know how to handle their respective state.
	*	These classes should probably be very slim for performace reasons (not tested)
	*/

	define( 'STATE_NULL', -10 );
	define( 'STATE_BASE', -20 );
	class State {
		protected $stateId = STATE_BASE;
		public function getStateId()
		{
			return $this->stateId;
		}

		public function getStateClass()
		{
			return get_class( $this );
		}

		/**
		*	@return	ComponentRunner instance which can be used
		*		to handle the component which is in the respective
		*		state.
		*/
		public function getStateHandler()
		{
			return new ComponentRunner();
		}
	}

	define( 'STATE_UNINITIALIZED', 'Uninitialized' );
	class UninitializedState extends State {
		protected $stateId = STATE_UNINITIALIZED;

		public function getStateHandler()
		{
			return new ComponentInitializer();
		}
	}

	define( 'STATE_INITIALIZED', 'Initialized' );
	class InitializedState extends State {
		protected $stateId = STATE_INITIALIZED;
	}

	define( 'STATE_DISPLAY', 'Display' );
	class DisplayState extends State {
		protected $stateId = STATE_DISPLAY;

		public function getStateHandler()
		{
			return new ComponentDisplayer();
		}
	}

	define( 'STATE_DESTROY', 'Destroy' );
	class DestroyState extends State {
		protected $stateId = STATE_DESTROY;

		public function getStateHandler()
		{
			return new ComponentDestroyer();
		}
	}

	define( 'STATE_FORM_SUBMITTED', 'FormSubmitted' );
	class FormSubmittedState extends State {
		protected $stateId = STATE_FORM_SUBMITTED;
	}

	define( 'STATE_FAILED', 'Failed' );
	class FailedState extends State {
		protected $stateId = STATE_FAILED;

		public function getStateHandler()
		{
			return new FailedComponentRunner();
		}
	}

	define( 'STATE_FAILED_PERMISSION', 'PermissionFailed' );
	class PermissionFailedState extends FailedState {
		protected $stateId = STATE_FAILED_PERMISSION;
	}

	/**
	*	these are the state handler
	*/

	interface IComponentRunner {
		public function handle( $component );
	}

	class ComponentRunner implements IComponentRunner {
		public function handle( $component )
		{
			$component->run();
		}
	}

	class ComponentInitializer implements IComponentRunner {
		public function handle( $component )
		{
			$component->initialize();
		}
	}

	class ComponentDisplayer implements IComponentRunner {
		public function handle( $component )
		{
			$component->display();
		}
	}

	class ComponentDestroyer implements IComponentRunner {
		public function handle( $component )
		{
			$component->destroy();
		}
	}

	/*
	class Component {
		// blah
	}

	class PersistentComponent {
	}
	*/

	class NotDefinedException {}

	class Component {
		protected $state = null;

		public function run()
		{
			$this->state->getStateHandler()->handle( $this );
		}

		public function initialize()
		{
			throw NotDefinedException();
		}

		public function __construct()
		{
			$this->initPersistence();
			if( $this->state === null ) {
				$this->state = new UninitializedState();
			}
		}

		public function __destruct()
		{
			$this->hibernate();
		}

		public function setState( $state )
		{
			if( $state instanceof State ) {
				$this->state = $state;
			} else {
				$classname = $state . 'State';
				$this->state = new $classname;
			}
		}


		/**
		*	component persistence
		*/
		protected $componentId = null;

		public function initPersistence()
		{
			$this->componentId = session_id() . '_' . get_class( $this );
			if( isset( $_SESSION[ 'persistence' ][ $this->componentId ] ) ) {
				//echo "Persistence! ( $this->componentId )\nTrying to ressurrect object\n";
				$this->ressurrect();
			}
		}

		public function hibernate()
		{
			$_SESSION[ 'persistence' ][ $this->componentId ] = $this->state->getStateClass();
		}

		public function ressurrect()
		{
			$ressurrectionStateClass = $_SESSION[ 'persistence' ][ $this->componentId ];
			$this->state = new $ressurrectionStateClass;
		}

		public final function destroy()
		{
			session_destroy();
			unset( $_SESSION[ 'persistence' ][ $this->componentId ] );
		}
	}

?>
