<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Unified DBObject URL generator
	 *
	 * This URL generator uses the configuration keys
	 * runtime.urlgenerator.<dbo_class> as controller URL and appends a query string
	 * which the controller should be able to evaluate.
	 *
	 * Default implementations for Article, Event and Download DBObjects are provided.
	 *
	 * The URLGenerator should be loaded via Swisdk::load_instance('UrlGenerator') so
	 * that another developer might override or add other generator methods.
	 *
	 * If you extend the UrlGenerator with your own class, you should set the
	 * configuration key runtime.loader.urlgenerator to the class name of your own
	 * class so that Swisdk::load_instance() returns an instance of your class.
	 */
	class UrlGenerator {
		protected $controller_url;

		public function __construct()
		{
			$this->controller_url = rtrim(Swisdk::config_value('runtime.controller.url'), '/');
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
			$c = strtolower($obj->_class());
			return Swisdk::config_value('runtime.urlgenerator.'.$c, '/'.$c)
				.'?p='.$obj->id();
		}

		public function generate_article_url($obj)
		{
			return $this->_generate_wp_style_url('article', $obj);
		}

		public function generate_event_url($obj)
		{
			return $this->_generate_wp_style_url('event', $obj);
		}

		public function generate_download_url($obj)
		{
			return Swisdk::config_value('runtime.urlgenerator.download',
					$this->controller_url).'/'.$obj->file_name;
		}

		public function generate_comment_url($obj)
		{
			$tokens = explode('_', $obj->realm);
			return Swisdk::config_value('runtime.urlgenerator.'.$tokens[0],
					'/'.$tokens[0])
				.'/?p='.$tokens[1].'#comment'.$obj->id();

		}

		/**
		 * generate <controller>/year/month/day/post-slug/ style URLs
		 */
		protected function _generate_wp_style_url($db_class, $obj)
		{
			$date = getdate($obj->start_dttm);
			return sprintf('%s/%04d/%02d/%02d/%s/',
				Swisdk::config_value('runtime.urlgenerator.'.$db_class,
					$this->controller_url),
				$date['year'], $date['mon'], $date['mday'],
				$obj->name);
		}
	}

?>
