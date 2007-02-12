<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'lib/contrib/class.microakismet.inc.php';

	class SpamChecker {
		protected $akismet;

		public function __construct()
		{
			$this->akismet = new MicroAkismet(
				Swisdk::config_value('akismet.api_key'),
				Swisdk::config_value('akismet.blog_url'),
				Swisdk::version());
		}

		public function check($dbo)
		{
			$class = $dbo->_class();
			if($class=='Comment') {
				return $this->akismet->check(
					$this->akismet_from_comment($dbo));
			}

			SwisdkError::handle(new FatalError('Cannot spam-check '.$class));
		}

		public function spam($dbo)
		{
			$class = $dbo->_class();
			if($class=='Comment') {
				return $this->akismet->spam(
					$this->akismet_from_comment($dbo));
			}

			SwisdkError::handle(new FatalError('Cannot classify '.$class.' as spam'));
		}

		public function ham($dbo)
		{
			$class = $dbo->_class();
			if($class=='Comment') {
				return $this->akismet->ham(
					$this->akismet_from_comment($dbo));
			}

			SwisdkError::handle(new FatalError('Cannot classify '.$class.' as ham'));
		}

		protected function akismet_from_comment($dbo)
		{
			return array(
				'user_ip' => $dbo->author_ip,
				'user_agent' => $dbo->author_agent,
				'comment_content' => $dbo->text,
				'comment_type' => $dbo->type,
				'comment_author' => $dbo->author,
				'comment_author_email' => $dbo->author_email,
				'comment_author_url' => $dbo->author_url);
		}
	}

?>
