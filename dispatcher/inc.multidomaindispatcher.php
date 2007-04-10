<?php
	/**
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class MultiDomainDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$matches = array();
			$match = preg_match(
				'/(?P<proto>http(s?):)\/\/(?P<host>[^\/]*)'
					.'(:(?P<port>[0-9])+)?(?P<remainder>.*)/',
				$this->input(), $matches);
			$this->set_output($matches['remainder']);

			Swisdk::set_config_value('runtime.request.protocol', $matches['proto']);
			Swisdk::set_config_value('runtime.request.host', $matches['host']);
			Swisdk::set_config_value('runtime.request.uri', $matches['remainder']);

			$host = preg_replace('/^www\./', '', $matches['host']);
			$out = null;

			$domains = Swisdk::config_value('runtime.parser.domain');

			if(!in_array($host, $domains)) {
				foreach($domains as &$d) {
					if($aliases = Swisdk::config_value('domain.'.$d.'.alias')) {
						$aliases = split('[ ,]+', $aliases);
						if(in_array($host, $aliases)) {
							$out = $d;
							break;
						}
					}
				}
			} else
				$out = $host;

			if($out) {
				$fd = Swisdk::config_value('domain.'.$out.'.force-domain');
				if($fd && count($fd = explode(',', $fd))
						&& !in_array($matches['host'], $fd))
					redirect('http://'.$fd[0].$matches['remainder']);
				Swisdk::set_config_value('runtime.domain', $out);
				Swisdk::set_config_value('runtime.website', Swisdk::config_value(
					'domain.'.$out.'.website'));
				Swisdk::add_loader_base(CONTENT_ROOT.$out.'/');
				if($cfg = Swisdk::config_value('domain.'.$out.'.include'))
					Swisdk::read_configfile($cfg);
			} else {
				SwisdkError::handle(new FatalError(dgettext('swisdk',
					'No matching host found')));
			}
		}
	}
?>
