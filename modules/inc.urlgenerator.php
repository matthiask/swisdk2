<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class UrlGenerator {
		protected $controller_url;

		public function __construct()
		{
			$this->controller_url = Swisdk::config_value('runtime.controller.url');
		}

		public function generate_url($obj)
		{
			$class = $obj->_class();
			$method = 'generate_'.$class.'_url';
			if(method_exists($this, $method))
				return call_user_func(array($this, $method), $obj);
			else
				return $this->generate_dbobject_url($obj);
		}

		public function generate_dbobject_url($obj)
		{
			return '/'.strtolower($obj->_class()).'?id='.$obj->id();
		}

		public function generate_article_url($obj)
		{
			$date = getdate($obj->start_dttm);
			return sprintf('%s/%04d/%02d/%02d/%s/',
				Swisdk::config_value('runtime.urlgenerator.article',
					$this->controller_url),
				$date['year'], $date['mon'],
				$date['mday'], $obj->name);
		}

		public function generate_event_url($obj)
		{
			$date = getdate($obj->start_dttm);
			return sprintf('%s/%04d/%02d/%02d/%s/',
				Swisdk::config_value('runtime.urlgenerator.event',
					$this->controller_url),
				$date['year'], $date['mon'],
				$date['mday'], $obj->name);
		}

		public function generate_download_url($obj)
		{
			return Swisdk::config_value('runtime.urlgenerator.download',
					$this->controller_url).'/'.$obj->file_name;
		}
	}

?>
