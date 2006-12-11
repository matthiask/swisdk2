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
			$path_1 = CONTENT_ROOT;
			$path_2 = SWISDK_ROOT.'content/';
			if($input) {
				$tokens = explode('/', $input);
				$path_1 .= $input;
				$path_2 .= $input;
			} else {
				$path_1 = rtrim($path_1, '/');
				$path_2 = rtrim($path_2, '/');
			}
			$t = $tokens;
			$matches = array();

			// try to find an Index_* controller/template only
			// for the full REQUEST_URI path
			if(!($matches = glob($path_1.'/Index_*'))
					&&!($matches=glob($path_2.'/Index_*'))) {
				while(true) {
					if(($matches=glob($path_1.'/All_*'))
							||($matches=glob($path_1.'_*'))
							||($matches=glob($path_2.'/All_*'))
							||($matches=glob($path_2.'_*'))) {
						if(is_file($matches[0]))
							break;
					}

					if(!count($tokens))
						return false;

					array_pop($tokens);
					$path_1 = rtrim(CONTENT_ROOT.implode('/', $tokens), '/');
					$path_2 = rtrim(SWISDK_ROOT.implode('/', $tokens), '/');
				}
			}

			if(Swisdk::config_value('runtime.controller.url', '#')=='#')
				Swisdk::set_config_value('runtime.controller.url',
					preg_replace('/[\/]+/', '/', '/'.implode('/',$tokens).'/'));
			Swisdk::set_config_value('runtime.includefile', $matches[0] );
			Swisdk::set_config_value('runtime.arguments', array_slice(
				$t, count($tokens)));

			return true;
		}
	}

?>
