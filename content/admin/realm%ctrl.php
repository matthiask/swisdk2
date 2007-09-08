<?php
	require_once MODULE_ROOT.'inc.adminsite.php';

	class RealmAdminSite extends AdminSite {
		protected $dbo_class = 'Realm';
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

			return $cmp;
		}

		protected function create_list_component($dbo)
		{
			$cmp = new ListComponent($dbo);
			$cmp->init();
			$tableview = $cmp->tableview();

			$primary = $cmp->dbobj()->dbobj()->primary();

			$tableview->disable('multi,persistence');
			$cmp->tableview_builder()->build($tableview);
			$tableview->append_column(new CmdsTableViewColumn(
				$primary, $this->url()));

			$tableview->set_form_defaults(array(
				'order' => $primary,
				'dir' => 'ASC',
				'limit' => 10));

			return $cmp;
		}
	}

	Swisdk::register('RealmAdminSite');

	class TableViewForm_Realm extends TableViewForm {
		public function setup_search()
		{
			$this->add_fulltext_field();
			$this->add_default_items();
		}
	}

?>
