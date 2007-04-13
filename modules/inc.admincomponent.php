<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.builder.php';

	class AdminComponent2 extends StateComponent {
		protected $dbo_class;
		protected $dbobj;
		protected $module_url;

		public function __construct($dbo=null)
		{
			$this->bind($dbo);
			$this->set_state(STATE_START);

			$this->module_url = Swisdk::config_value('runtime.controller.url');
		}

		public function bind($dbo)
		{
			$this->dbobj = $dbo;
			$this->dbo_class = $dbo->_class();
			return $this;
		}

		public function dbobj()
		{
			return $this->dbobj;
		}

		/**
		 * @return a FormBuilder instance
		 *
		 * If a class FormBuilder_DBObjectClass exists, it will be
		 * created and returned, otherwise the default FormBuilder
		 */
		public function form_builder()
		{
			$cmp_class = 'FormBuilder_'.$this->dbo_class;
			if(class_exists($cmp_class))
				return new $cmp_class();
			return new FormBuilder();
		}

		/**
		 * @return a TableViewBuilder instance
		 *
		 * same comments as above apply
		 */
		public function tableview_builder()
		{
			$cmp_class = 'TableViewBuilder_'.$this->dbo_class;
			if(class_exists($cmp_class))
				return new $cmp_class();
			return new TableViewBuilder();
		}

		/**
		 * @return a FormRenderer instance
		 */
		public function form_renderer()
		{
			return new NoTableFormRenderer();
		}
	}

	class EditComponent extends AdminComponent2 implements IHtmlComponent, ISmartyComponent {
		protected $form;
		protected $editmode = true;

		public function form()
		{
			return $this->form;
		}

		public function set_form($form)
		{
			$this->form = $form;
			$this->form->bind($this->dbobj);
			return $this;
		}

		public function editmode()
		{
			return $this->dbobj->id()>0;
		}

		public function init_form()
		{
			if(!$this->form)
				$this->form = new Form($this->dbobj);
		}

		public function init($autobuild=false)
		{
			if($this->has_state(STATE_INITIALIZED))
				return;

			$this->init_form();

			if($this->editmode())
				$this->form->set_title('Edit '.$this->dbo_class.' '
					.$this->dbobj->id());
			else
				$this->form->set_title('Create '.$this->dbo_class);

			if($autobuild) {
				$this->form_builder()->build($this->form);
				FormUtil::submit_bar($this->form);
			}

			$this->add_state(STATE_INITIALIZED);
		}

		public function run()
		{
			$this->init(true);

			if($this->form->canceled())
				$this->add_state(STATE_FINISHED);
			else if($this->form->is_valid()) {
				$dbo = $this->form->dbobj();
				$dbo->store();
				$this->remove_state(STATE_INVALID);
				$this->add_state(STATE_FINISHED);
			} else
				$this->add_state(STATE_INVALID);

			$this->add_state(STATE_RUN);
		}

		public function html()
		{
			$this->add_state(STATE_DISPLAYED);
			return $this->form->html($this->form_renderer());
		}

		public function set_smarty(&$smarty)
		{
			$this->add_state(STATE_DISPLAYED);
		}
	}

	class DeleteComponent extends AdminComponent2 implements IHtmlComponent, ISmartyComponent {
		protected $form;

		public function init_form()
		{
			$question_title = dgettext('swisdk', 'Confirmation required');
			$question_text = sprintf(dgettext('swisdk', 'Do you really want to delete %s (%s)?'),
				$this->dbobj->_class().' '.$this->dbobj->id(),
				$this->dbobj->title());
			$delete = dgettext('swisdk', 'Delete');
			$cancel = dgettext('swisdk', 'Cancel');

			$this->form = new Form($this->dbobj);
			$this->form->set_title($question_title);
			$this->form->add(new InfoItem($question_text));
			$group = $this->form->add(new GroupItem());
			$group->add(new SubmitButton('confirmation_command_delete'))
				->set_caption('Delete');
			$group->add(new CancelButton());
			$this->form->init();
		}

		public function run()
		{
			$state = Swisdk::guard_token_state_f('guard');
			if($state==GUARD_VALID) {
				$this->dbobj->delete();
				$this->add_state(STATE_FINISHED);
			}

			$this->init_form();

			if($this->form->canceled())
				$this->add_state(STATE_FINISHED);
			else if($this->form->is_valid()) {
				$this->dbobj->delete();
				$this->add_state(STATE_FINISHED);
			}
		}

		public function html()
		{
			$this->add_state(STATE_DISPLAYED);
			return $this->form->html($this->form_renderer());
		}

		public function set_smarty(&$smarty)
		{
			$this->add_state(STATE_DISPLAYED);
		}
	}

	class ListComponent extends AdminComponent2 implements IHtmlComponent, ISmartyComponent {
		protected $tableview;

		public function tableview()
		{
			return $this->tableview;
		}

		public function init_tableview()
		{
			if(!$this->tableview) {
				$this->tableview = new TableView($this->dbo_class);
				if(class_exists($c = 'TableViewForm_'.$this->dbo_class))
					$this->tableview->set_form(new $c());
				$this->tableview->init();
			}
		}

		public function init($autobuild=false)
		{
			if($this->has_state(STATE_INITIALIZED))
				return;

			$this->init_tableview();

			if($autobuild)
				$this->tableview_builder()->build($this->tableview);

			$this->add_state(STATE_INITIALIZED);
		}

		public function run()
		{
			$this->init(true);
		}

		public function html()
		{
			return $this->tableview->html();
		}

		public function set_smarty(&$smarty)
		{
		}
	}

?>
