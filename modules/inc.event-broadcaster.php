<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class Broadcaster {
		public function listener_add()
		{
			$args = func_get_args();
			$action = array_shift($args);
			$callback = array_shift($args);
			array_unshift($args, $this);

			$this->listeners[$action][] = array(
				'callback' => $callback,
				'args' => $args);
		}

		public function listener_remove($action, $callback)
		{
			if(!isset($this->listeners[$action]))
				return;
			foreach($this->listeners[$action] as $key => &$l)
				if($l == $callback)
					unset($this->listeners[$action][$key]);
		}

		public function listener_call($action)
		{
			if(!isset($this->listeners[$action]))
				return;
			foreach($this->listeners[$action] as &$l)
				call_user_func_array($l['callback'], $l['args']);
		}

		public function listener_clear($action)
		{
			if(isset($this->listeners[$action]))
				unset($this->listeners[$action]);
		}

		protected $listeners = array();
	}

?>
