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

		protected $realm;
		protected $dbobj;

		protected $html;

		public function __construct($realm)
		{
			$this->realm = $realm;
		}

		public function set_smarty(&$smarty)
		{
			$smarty->assign('comments', $this->html);
			$smarty->assign('comment_count', $this->dbobj->count());
		}


		public function run()
		{
			$smarty = new SwisdkSmarty();
			$user = SessionHandler::user();

			$dbo = DBObject::create('Comment');
			$dbo->realm = $this->realm;
			$dbo->user_id = SessionHandler::user()->id();
			$form = new Form($dbo);
			$form->set_title('Post a comment!');
			if($user->id==SWISDK2_VISITOR) {
				$author = $form->add_auto('author');
				$email = $form->add_auto('author_email');
				$url = $form->add_auto('author_url');

				$author->set_title('Your Name');
				$email->set_title('Your Email<br /><small>(will not be shown)</small>');
				$url->set_title('Your Website');

				$author->add_rule(new RequiredRule());

				$email->add_rule(new EmailRule());
				$email->add_rule(new RequiredRule());

				$url->add_rule(new UrlRule());
			} else {
				$author = $form->add('comment_author', new HiddenInput());
				$email = $form->add('comment_author_email', new HiddenInput());
				$url = $form->add('comment_author_url', new HiddenInput());

				$email->set_value($user->email);
				$author->set_value($user->forename.' '.$user->name);
				$url->set_value($user->url);
			}

			$text = $form->add_auto('text');
			$form->add(new SubmitButton());

			$text->set_title('Comment');
			$text->add_rule(new RequiredRule());
			$text->set_attributes(array('style'
				=> 'width:300px;height:250px'));

			if($form->is_valid() && !empty($_SERVER['HTTP_USER_AGENT'])) {
				$dbo->realm = $this->realm;
				$dbo->author_ip = $_SERVER['REMOTE_ADDR'];
				$dbo->author_agent = $_SERVER['HTTP_USER_AGENT'];
				$dbo->state = 'new';
				$dbo->type = 'comment';
				$dbo->text = nl2br(strip_tags($dbo->text));
				$dbo->store();
				$smarty->assign('commentform', '<p>Thanks!</p>');

				if($user->id==SWISDK2_VISITOR) {
					setcookie('swisdk2_comment_author',
						$dbo->author."\n"
						.$dbo->author_email."\n"
						.$dbo->author_url,
						time()+90*86400);
				}
			} else {
				if($user->id==SWISDK2_VISITOR && isset($_COOKIE['swisdk2_comment_author'])) {
					$comment_author = explode("\n", $_COOKIE['swisdk2_comment_author']);
					if(count($comment_author)==3) {
						$author->set_value($comment_author[0]);
						$email->set_value($comment_author[1]);
						$url->set_value($comment_author[2]);
					}
				}

				$smarty->assign('commentform', $form->html());
			}

			$this->dbobj = DBOContainer::find('Comment', array(
				'comment_realm=' => $this->realm,
				':order' => array('comment_creation_dttm', 'ASC')));

			$smarty->assign('comments', $this->dbobj->data());

			$this->html = $smarty->fetch_template('comment.list');
		}
	}

?>
