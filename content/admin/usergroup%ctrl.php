<?php
	require_once MODULE_ROOT.'inc.adminsite.php';

	class UserGroupAdminSite extends AdminSite {
		protected $dbo_class = 'UserGroup';
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
			FormUtil::submit_bar($form);

			$elem = new ListSelector('User');
			$elem->set_items(DBOContainer::find('User'));
			$form->add('User', $elem);

			Swisdk::kill_cache('permission');

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
			$tableview->append_column(new UserGroupCmdsTableViewColumn(
				$primary, $this->url()));

			$tableview['User']->set_ellipsize(200);

			$tableview->set_form_defaults(array(
				'order' => $primary,
				'dir' => 'ASC',
				'limit' => 10));

			return $cmp;
		}
	}

	Swisdk::register('UserGroupAdminSite');

	class TableViewForm_UserGroup extends TableViewForm {
		public function setup_search()
		{
			$this->add_fulltext_field();
			$this->add_default_items();
		}
	}

	class UserGroupCmdsTableViewColumn extends CmdsTableViewColumn {
		public function html(&$data)
		{
			$id = $data[$this->column];
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			return parent::html($data).<<<EOD
<a href="/admin/usergrouproles/$id"><img src="$prefix/icons/shield.png" alt="roles" /></a>
EOD;
		}
	}

?>
