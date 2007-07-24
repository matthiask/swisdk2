<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.permission.php';
	require_once MODULE_ROOT.'inc.component.php';
	require_once MODULE_ROOT.'inc.smarty.php';
	require_once MODULE_ROOT.'inc.form.php';

	class CommentComponent implements ISmartyComponent {

		protected $realm;	///> comment realm
		protected $dbobj;	///> Comment DBOContainer

		protected $form;	///> comment form
		protected $cdbo;	///> Comment DBObject (bound to form)

		protected $mode = 'write';	///> comment component mode

		protected $html;

		public function __construct($realm)
		{
			$this->realm = $realm;
		}

		public function mode()
		{
			return $this->mode;
		}

		public function comments()
		{
			return $this->dbobj;
		}

		public function form()
		{
			return $this->form;
		}

		public function set_smarty(&$smarty)
		{
			$csmarty = new SwisdkSmarty();
			if($this->mode=='write')
				$csmarty->assign('commentform', $this->form->html());

			$csmarty->assign('comments', $this->dbobj);
			$csmarty->assign('mode', $this->mode);

			$smarty->assign('comments', $csmarty->fetch_template('comment.list'));
			$smarty->assign('comment_count', $this->dbobj->count());
		}

		public function init_form()
		{
			$this->form = new Form();
			$this->form->bind($this->cdbo);

			$this->form->set_title('Post a comment!');

			$user = SessionHandler::user();

			if($user->id==SWISDK2_VISITOR) {
				$author = $this->form->add_auto('author');
				$email = $this->form->add_auto('author_email');
				$url = $this->form->add_auto('author_url');

				$author->set_title('Your Name');
				$email->set_title('Your Email');
				$email->set_info('Will not be shown');
				$url->set_title('Your Website');

				$author->add_rule(new RequiredRule());

				$email->add_rule(new DNSEmailRule());
				$email->add_rule(new RequiredRule());

				$url->add_rule(new UrlRule());

				if(isset($_COOKIE['swisdk2_comment_author'])) {
					$comment_author = explode("\n",
						$_COOKIE['swisdk2_comment_author']);
					if(count($comment_author)==3) {
						$author->set_value($comment_author[0]);
						$email->set_value($comment_author[1]);
						$url->set_value($comment_author[2]);
					}
				}
			} else {
				$author = $this->form->add('comment_author', new HiddenInput());
				$email = $this->form->add('comment_author_email', new HiddenInput());
				$url = $this->form->add('comment_author_url', new HiddenInput());

				$email->set_value($user->email);
				$author->set_value($user->forename.' '.$user->name);
				$url->set_value($user->url);
			}

			$text = $this->form->add_auto('text');
			$this->form->add('submit', new SubmitButton());

			$text->set_title('Comment');
			$text->add_rule(new RequiredRule());

			return $this->form;
		}

		public function init_container()
		{
			$this->dbobj = DBOContainer::find('Comment', array(
				'comment_realm=' => $this->realm,
				'(comment_state!=\'maybe-spam\' AND comment_state!=\'spam\''
					.' AND comment_state!=\'moderated\')' => null,
				':order' => array('comment_creation_dttm', 'ASC')));
			return $this->dbobj;
		}

		public function init_dbobj()
		{
			$this->cdbo = DBObject::create('Comment');
			$this->cdbo->realm = $this->realm;
		}

		public function run()
		{
			$this->init_dbobj();
			$this->init_form();

			if($this->form->is_valid() && !empty($_SERVER['HTTP_USER_AGENT'])) {
				$this->cdbo->realm = $this->realm;
				$this->cdbo->author_ip = $_SERVER['REMOTE_ADDR'];
				$this->cdbo->author_agent = $_SERVER['HTTP_USER_AGENT'];
				$this->cdbo->state = 'new';
				$this->cdbo->type = 'comment';
				$this->cdbo->text = nl2br(strip_tags($this->cdbo->text));
				if(Swisdk::load_instance('SpamChecker')->is_spam($this->cdbo)) {
					$this->cdbo->state = 'maybe-spam';
					$this->mode = 'maybe-spam';
				} else
					$this->mode = 'accepted';
				$this->cdbo->store();

				setcookie('swisdk2_comment_author',
					$this->cdbo->author."\n"
					.$this->cdbo->author_email."\n"
					.$this->cdbo->author_url,
					time()+90*86400);
			}

			$this->init_container();
		}
	}

?>
