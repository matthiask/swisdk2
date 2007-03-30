<?php
	/**
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Sets the current Framework language if the first token is a valid language key
	 *
	 * Does not modify the request
	 */
	class LanguageDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$tokens = explode('/', substr($this->input(), 1));
			if(s_test($tokens, 0) && ($id = Swisdk::language($tokens[0])))
				Swisdk::set_language($tokens[0]);
		}

	}

?>
