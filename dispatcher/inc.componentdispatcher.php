<?php
	/**
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class ComponentDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$input = $this->input();
			$tokens = explode('/', $input);

			$idx = array_search('_component', $tokens);
			if($idx===false)
				return;

			Swisdk::set_config_value('runtime.dispatcher.component',
				s_get($tokens, $idx+1));
			$this->set_output(implode('/', array_merge(
				array_slice($tokens, 0, $idx),
				array_slice($tokens, $idx+2))));
		}
	}
?>
