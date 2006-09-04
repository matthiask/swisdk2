<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>,
	*		Moritz Zumbhl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.component.php';
	require_once MODULE_ROOT.'inc.permission.php';
	require_once MODULE_ROOT.'inc.smarty.php';
	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.tableview.php';
	require_once MODULE_ROOT.'inc.dbtableview.php';
	require_once MODULE_ROOT.'inc.builder.php';

	class AdminModule extends Site {
		protected $dbo_class;
		protected $arguments;
		protected $multilanguage = false;
		protected $role = ROLE_MANAGER;

		/**
		 * this function tries to find a AdminComponent to handle the
		 * incoming request.
		 *
		 * 1.: AdminComponent_DBObjectClass_action
		 * 	for example: AdminComponent_News_edit
		 * 2.: AdminComponent_action
		 * 	these default implementations are provided below
		 * 3.: AdminComponent_DBObjectClass_index
		 * 4.: AdminComponent_index
		 *
		 * It always tries to load the "edit" action if no "new" handler
		 * can be found.
		 */
		protected function component_dispatch()
		{
			$args = Swisdk::config_value('runtime.arguments');
			$cmp = null;

			if(isset($args[0]) && $args[0]) {
				$cmp_class = 'AdminComponent_'.$this->dbo_class.$args[0];
				if(class_exists($cmp_class))
					$cmp = new $cmp_class;
				else if($args[0]=='_new' && class_exists($cmp_class =
						'AdminComponent_'.$this->dbo_class
						.'_edit'))
					$cmp = new $cmp_class;
				else if(class_exists($cmp_class = 'AdminComponent'
						.$args[0]))
					$cmp = new $cmp_class;
				else if($args[0]=='_new')
					$cmp = new AdminComponent_edit();
				array_shift($args);
			}

			$this->arguments = $args;

			if(!$cmp) {
				$cmp_class = 'AdminComponent_'.$this->dbo_class.'_index';
				if(class_exists($cmp_class))
					$cmp = new $cmp_class;
				else
					$cmp = new AdminComponent_index();
			}

			$cmp->set_module($this);

			return $cmp;
		}

		public function run()
		{
			PermissionManager::check_throw($this->role);
			$cmp = $this->component_dispatch();
			$cmp->run();

			Swisdk::set_config_value('runtime.navigation.url',
				Swisdk::config_value('runtime.controller.url'));

			$sm = SmartyMaster::instance();
			$sm->add_html_handler('content', $cmp);
			$sm->display();
		}

		public function dbo_class()
		{
			return $this->dbo_class;
		}

		/**
		 * @return the remaining arguments after the module
		 */
		public function component_arguments()
		{
			return $this->arguments;
		}

		/**
		 * @return multilanguage flag
		 */
		public function multilanguage()
		{
			return $this->multilanguage;
		}
	}

	abstract class AdminComponent implements IHtmlComponent {
		protected $module_url;
		protected $dbo_class;
		protected $args;
		protected $html;
		protected $multilanguage;

		public function html()
		{
			return $this->html;
		}

		/**
		 * the Component will get all informations it needs
		 * from the AdminModule
		 */
		public function set_module(&$module)
		{
			$this->module_url = $module->url();
			$this->dbo_class = $module->dbo_class();
			$this->args = $module->component_arguments();
			$this->multilanguage = $module->multilanguage();
		}

		/**
		 * goto - i can't get started without you!
		 */
		public function goto($tok)
		{
			redirect($this->module_url.$tok);
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
			return new TableFormRenderer();
		}
	}

	class AdminComponent_index extends AdminComponent {
		public function run()
		{
			$this->goto('_list');
		}
	}

	class AdminComponent_edit extends AdminComponent {
		protected $form;
		protected $obj;
		protected $multiple = false;
		protected $editmode = true;

		public function run()
		{
			if($this->args[0]=='multiple')
				$this->multiple = true;
			if($this->multiple)
				$this->edit_multiple();
			else
				$this->edit_single();
		}

		public function get_dbobj($val = null)
		{
			if($this->multiple && $this->obj)
				return $this->obj->dbobj_clone();
			if($this->multilanguage) {
				if($val)
					return DBObjectML::find($this->dbo_class, $val);
				else
					return DBObjectML::create($this->dbo_class);
			} else {
				if($val)
					return DBObject::find($this->dbo_class, $val);
				else
					return DBObject::create($this->dbo_class);
			}
		}

		public function init_dbobj()
		{
			if($this->multiple) {
				$obj = $this->get_dbobj();
				if(($val = getInput($obj->primary()))
						&& is_array($val)) {
					$this->obj = DBOContainer::find_by_id($obj, $val);
				} else {
					$this->obj = DBOContainer::create($obj);
					$this->editmode = false;
				}
			} else {
				if(isset($this->args[0]))
					$this->obj = $this->get_dbobj($this->args[0]);
				else {
					$this->obj = $this->get_dbobj();
					$this->editmode = false;
				}
			}
		}

		public function init_form()
		{
			if($this->multilanguage)
				$this->form = new FormML();
			else
				$this->form = new Form();
		}

		public function build_form($box = null)
		{
			$builder = $this->form_builder();
			if($box)
				$builder->build($box);
			else
				$builder->build($this->form);
		}

		public function execute()
		{
			if($this->form->is_valid()) {
				if(!$this->editmode)
					$this->obj->unset_primary();
				$this->post_process();
				$this->obj->store();
				$this->goto('_index');
			} else
				$this->html = $this->form->html($this->form_renderer());
		}

		public function post_process()
		{
			// hook
		}

		protected function edit_multiple()
		{
			$this->init_dbobj();
			if(!$this->obj)
				$this->goto('_list');
			$this->init_form();
			$this->form->enable_unique();
			if(!$this->editmode) {
				for($i=1; $i<=3; $i++) {
					$box = $this->form->box($this->dbo_class.'_'.$i);
					$box->set_title('New '.$this->dbo_class);
					$obj = $this->get_dbobj();
					$obj->id = -$i;
					$box->bind($obj);
					$this->build_form($box);
					$this->obj->add($obj);
				}
			} else {
				foreach($this->obj as $obj) {
					$box = $this->form->box($this->dbo_class
						.'_'.$obj->id());
					$box->set_title('Edit '.$this->dbo_class
						.' '.$obj->id());
					$box->bind($obj);
					$box->add(new HiddenInput($obj->primary().'[]'))
						->set_value($obj->id());
					$this->build_form($box);
				}
			}

			$this->execute();
		}

		protected function edit_single()
		{
			$this->init_dbobj();
			if(!$this->obj)
				$this->goto('_list');
			$this->init_form();
			$this->form->bind($this->obj);
			if($this->editmode)
				$this->form->set_title('Edit '.$this->dbo_class
					.' '.$this->obj->id());
			else
				$this->form->set_title('New '.$this->dbo_class);
			$this->build_form($this->form);

			$this->execute();
		}
	}

	class AdminComponent_list extends AdminComponent {
		protected $tableview;
		protected $creation_enabled = true;

		public function get_dbobj()
		{
			if($this->multilanguage)
				return DBObjectML::create($this->dbo_class);
			else
				return DBObject::create($this->dbo_class);
		}

		public function init_tableview()
		{
			$this->tableview = new DBTableView($this->get_dbobj());
		}

		public function init_tableview_form()
		{
			if(class_exists($c = 'DBTableViewForm_'.$this->dbo_class))
				$this->tableview->set_form($c);
		}

		public function run()
		{
			$this->init_tableview();
			$this->init_tableview_form();
			$this->execute_actions();
			$this->tableview->init();
			$this->build_tableview();
			$this->html = ($this->creation_enabled?'<button type="button" '
				.'onclick="window.location.href=\''.$this->module_url
					.'_new\'">'
				.'Create '.$this->dbo_class."</button>\n":'')
				.$this->tableview->html();
		}

		public function build_tableview()
		{
			$this->tableview_builder()->build($this->tableview);
			$this->complete_columns();
		}

		protected function execute_actions()
		{
		}

		protected function complete_columns()
		{
		}
	}

	class AdminComponent_delete extends AdminComponent {
		public function run()
		{
			if($this->args[0]=='multiple')
				$this->delete_multiple();
			else
				$this->delete_single();
		}

		protected function delete_multiple()
		{
			if(getInput('guard')!=guardToken('delete'))
				$this->goto('_index');

			$dbo = null;

			if($this->multilanguage)
				$dbo = DBObjectML::create($this->dbo_class);
			else
				$dbo = DBObject::create($this->dbo_class);
			$p = $dbo->primary();

			$list = getInput($p);
			if(!is_array($list) || !count($list))
				$this->goto('_index');
			$dboc = DBOContainer::find($dbo, array(
				$p.' IN {list}' => array('list' => $list)));

			$dboc->delete();
			$this->goto('_index');
		}

		protected function delete_single()
		{
			if(getInput('delete_confirmation_page')
					&& (strtolower(getInput('confirmation_command'))!='delete'))
				$this->goto('_index');

			if(getInput('guard')!=guardToken('delete')) {
				$this->display_confirmation_page();
				return;
			}

			$dbo = null;
			if($this->multilanguage)
				$dbo = DBObjectML::find($this->dbo_class, $this->args[0]);
			else
				$dbo = DBObject::find($this->dbo_class, $this->args[0]);
			if(!$dbo)
				SwisdkError::handle(new FatalError(
					'AdminComponent_delete::run() - Can\'t find '
					.'the data.'
					.' Class is: '.$this->dbo_class.' Argument is: '
					.intval($this->args[0])));

			$dbo->delete();
			$this->goto('_index');
		}

		protected function display_confirmation_page()
		{
			$dbo = null;
			if($this->multilanguage)
				$dbo = DBObjectML::find($this->dbo_class, $this->args[0]);
			else
				$dbo = DBObject::find($this->dbo_class, $this->args[0]);
			if(!$dbo)
				SwisdkError::handle(new FatalError(
					'AdminComponent_delete::run() - Can\'t find '
					.'the data.'
					.' Class is: '.$this->dbo_class.' Argument is: '
					.intval($this->args[0])));

			$token = guardToken('delete');
			$class = $dbo->_class();
			$id = $dbo->id();
			$title = $dbo->title();
			$name = $dbo->file_name;

			$this->html = <<<EOD
<form method="post" action="?delete_confirmation_page=1" class="sf-form" accept-charset="utf-8">
<input type="hidden" name="guard" value="$token" />
<table>
<tr class="sf-form-title">
	<td colspan="2"><big><strong>Confirmation required</strong></big></td>
</tr>
<tr>
	<td></td>
	<td>Do you really want to delete $class $id (<a href="/download/$name">$title</a>)?</td>
</tr>
<tr class="sf-button">
	<td colspan="2">
		<input type="submit" name="confirmation_command" value="Delete" />
		<input type="submit" name="confirmation_command" value="Cancel" />
	</td>
</tr>
</table>
</form>
EOD;
		}
	}

?>
