<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch>,
	*		Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * tries to find a file below CONTENT_ROOT which would be suitable to handle
	 * the incoming request
	 *
	 * Rules:
	 *
	 * Incoming: /request/path/
	 *
	 * Checks for (always prepend CONTENT_ROOT):
	 * 
	 * 1. request/path/Index_*
	 * 2. request/path/All_*
	 * 3. request/path_*
	 * 4. request/All_*
	 * 5. request_*
	 * 6. All_*
	 */
	class ControllerDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$input = trim($this->input(), '/');

			$tokens = array();
			$bases = array(
				CONTENT_ROOT,
				SWISDK_ROOT.'content/');

			if($domain = Swisdk::config_value('runtime.domain'))
				array_unshift($bases, CONTENT_ROOT.$domain.'/');

			$paths = array();

			if($input) {
				$tokens = explode('/', $input);
				foreach($bases as $b)
					$paths[] = $b.$input;
			} else {
				foreach($bases as $b)
					$paths[] = rtrim($b, '/');
			}

			$t = $tokens;
			$matches = array();

			// try to find an Index_* controller/template only
			// for the full REQUEST_URI path
			foreach($paths as $p) {
				if($matches = glob($p.'/Index_*'))
					break;
			}

			if(!$matches) {
				while(true) {
					foreach($paths as $p) {
						if(($matches = glob($p.'/All_*'))
								||($matches = glob($p.'_*'))) {
							if(count($matches) && is_file($matches[0]))
								break 2;
						}
					}

					if(!count($tokens))
						return false;

					array_pop($tokens);
					$paths = array();
					foreach($bases as $b)
						$paths[] = rtrim($b.implode('/', $tokens), '/');
				}
			}

			if(Swisdk::config_value('runtime.controller.url', '#')=='#')
				Swisdk::set_config_value('runtime.controller.url',
					Swisdk::config_value('runtime.navigation.prepend')
					.preg_replace('/[\/]+/', '/', '/'.implode('/',$tokens).'/'));
			Swisdk::set_config_value('runtime.includefile', $matches[0] );
			Swisdk::set_config_value('runtime.arguments', array_slice(
				$t, count($tokens)));

			return true;
		}
	}

?>
