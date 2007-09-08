<?php
	require_once MODULE_ROOT.'inc.adminsite.php';

	class UserAdminSite extends AdminSite {
		protected $dbo_class = 'User';
		protected $multilanguage = false;

		protected $mode = 'single';
		protected $creation_button = false;

		public function info_actions()
		{
			return array(
				'create' => '',
				'manage' => ''
				);
		}

		protected function create_edit_component($dbo)
		{
			$cmp = new EditComponent($dbo);
			$cmp->init();
			$form = $cmp->form();

			$cmp->form_builder()->build($form);

			unset($form['user_password']);
			if(isset($form['user_text']))
				$form['user_text']->set_auto_xss_protection(false, false);

			$p1 = $form->add('a_user_password_new_1', new PasswordInput(), 'New password');
			$p2 = $form->add('a_user_password_new_2', new PasswordInput(), 'New password (repeat)');
			$form->add_rule(new EqualFieldsRule($p1, $p2));

			$dbo->listener_add('pre-store', 'swisdk_user_dbo_pre_store_cb');

			FormUtil::submit_bar($form);

			return $cmp;
		}

		protected function create_list_component($dbo)
		{
			$cmp = new ListComponent($dbo);
			$cmp->init();
			$tableview = $cmp->tableview();

			$primary = $cmp->dbobj()->dbobj()->primary();

			$tableview->disable('multi');
			$cmp->tableview_builder()->build($tableview);
			$tableview->append_column(new UserCmdsTableViewColumn(
				$primary, $this->url()));

			$tableview->set_form_defaults(array(
				'order' => $primary,
				'dir' => 'ASC',
				'limit' => 10));

			return $cmp;
		}
	}

	Swisdk::register('UserAdminSite');

	class TableViewForm_User extends TableViewForm {
		public function setup_search()
		{
			$this->search->add_auto('UserGroup');
			$this->add_fulltext_field();
			$this->add_default_items();
		}

		public function set_clauses(DBOContainer &$container)
		{
			parent::set_clauses($container);

			if(count($groups = $this->dbobj()->get('UserGroup'))) {
				$container->add_join('UserGroup');
				$container->add_clause('user_group_id IN {ids}', array(
					'ids' => $groups));
			}
		}
	}

	class UserCmdsTableViewColumn extends CmdsTableViewColumn {
		public function html(&$data)
		{
			$id = $data[$this->column];
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			return parent::html($data).<<<EOD
<a href="/admin/userroles/$id"><img src="$prefix/icons/shield.png" alt="roles" /></a>
EOD;
		}
	}

	function swisdk_user_dbo_pre_store_cb($dbo)
	{
		if(($p = $dbo->get('a_user_password_new_1'))
				&& $p==$dbo->get('a_user_password_new_2'))
			$dbo->password = md5($p);
	}

?>
