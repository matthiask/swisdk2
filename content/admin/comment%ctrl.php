<?php
	require_once MODULE_ROOT.'inc.adminsite.php';

	class CommentAdminSite extends AdminSite {
		protected $dbo_class = 'Comment';
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
			if(getInput('comment_new_state_action')) {
				if($dboc = DBOContainer::find_by_id('Comment', getInput('comment_id'))) {
					$dboc->state = getInput('comment_new_state');
					$dboc->store();
				}
			}

			$cmp = new ListComponent($dbo);
			$cmp->init();
			$tableview = $cmp->tableview();

			$primary = $cmp->dbobj()->dbobj()->primary();

			$tableview->disable('');

			$tableview->append_auto_c('realm,creation_dttm,author,author_email,text,'
				.'state,type,notify');

			$tableview->append_column(new CmdsTableViewColumn(
				$primary, $this->url()));

			$tableview->set_form_defaults(array(
				'order' => 'comment_creation_dttm',
				'dir' => 'DESC',
				'limit' => 40));

			$tableview->set_rowclass_callback('rowclass');

			return $cmp;
		}
	}

	Swisdk::register('CommentAdminSite');

	function rowclass($data)
	{
		return 'c-'.$data->state;
	}

	class TableViewForm_Comment extends TableViewForm {
		public function setup_search()
		{
			$items = DBObject::db_get_array(
					'SELECT DISTINCT comment_state FROM tbl_comment',
					array('comment_state', 'comment_state'));

			$this->search->add('comment_state', new Multiselect())
				->set_items($items);

			$this->action->add(new HiddenInput('comment_new_state_action'))
				->force_value(0);
			$item = $this->action->add(new DropdownInput('comment_new_state'));
			$item->set_items($items)
				->set_title('Change state to')
				->add_null_item()
				->force_value(0);
			$id = $item->id();
			$form_id = $this->id();
			Swisdk::needs_library('jquery');
			$js = <<<EOD
$('#$id').change(function(){
	document.getElementById('comment_new_state_action').value=1;
	document.getElementById('$form_id').submit();
});

EOD;
			$item->add_javascript($js);

			$this->add_fulltext_field();
			$this->add_default_items();
		}

		public function set_clauses(DBOContainer &$container)
		{
			parent::set_clauses($container);
			$obj = $this->dbobj();
			if(count($states = $obj->get('comment_state')))
				$container->add_clause('comment_state IN {states}',
					array('states' => $states));
		}
	}

?>
