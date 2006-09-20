<?php
	/**
	*	Copyright (c) 2006, Moritz ZumbŸhl <mail@momoetomo.ch>,
	*		Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Checks if the website name (the second part of the website config section
	 * titles) matches the first part of the request OR checks if the website
	 * match regex matches the request and if so, sets the current website and
	 * website title.
	 */
	class WebsiteDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$input = $this->input();
			$websites = Swisdk::config_value('runtime.parser.website');
			$website = 'default';
			foreach($websites as $w) {
				$regex = str_replace('/', '\/',
					Swisdk::config_value('website.'.$w.'.match'));
				if(!$regex)
					$regex = '\/'.$w.'\/';
				if(preg_match('/^'.$regex.'/', $input)) {
					$website = $w;
					break;
				}
			}

			Swisdk::set_config_value('runtime.website', $website);
			Swisdk::set_config_value('runtime.website.title',
				Swisdk::config_value('website.'.$w.'.title'));
		}
	}

?>
