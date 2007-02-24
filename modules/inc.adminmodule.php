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
	require_once MODULE_ROOT.'inc.tableview.php';
	require_once MODULE_ROOT.'inc.builder.php';

	/**
	 * AdminModule
	 *
	 * Reusable and extensible components using which you can build your own
	 * administration modules (create/edit/delete/list database entries)
	 */

	class AdminModule extends Site {
		/**
		 * DBObject class
		 *
		 * set multilanguage to true if the AdminModule should automatically
		 * handle a DBObjectML
		 */
		protected $dbo_class;
		protected $multilanguage = false;

		/**
		 * temporary storage for component arguments
		 */
		protected $arguments;

		/**
		 * the minimal role which is needed to access this AdminModule
		 */
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

			$cmd = '';

			if(isset($args[0]) && $args[0]) {
				$cmp_class = 'AdminComponent_'.$this->dbo_class.$args[0];
				if(class_exists($cmp_class)) {
					$cmp = new $cmp_class;
					$cmd = substr($args[0], 1);
				} else if($args[0]=='_new' && class_exists($cmp_class =
						'AdminComponent_'.$this->dbo_class
						.'_edit')) {
					$cmp = new $cmp_class;
					$cmd = 'edit';
				} else if(class_exists($cmp_class = 'AdminComponent'
						.$args[0])) {
					$cmp = new $cmp_class;
					$cmd = substr($args[0], 1);
				} else if($args[0]=='_new') {
					$cmp = new AdminComponent_edit();
					$cmd = 'new';
				}
				array_shift($args);
			}

			$this->arguments = $args;

			if(!$cmp) {
				$cmp_class = 'AdminComponent_'.$this->dbo_class.'_index';
				if(class_exists($cmp_class))
					$cmp = new $cmp_class;
				else
					$cmp = new AdminComponent_index();
				$cmd = 'index';
			}

			$cmp->set_module($this);
			$cmp->set_template_keys(array(
				'admin.'.$this->dbo_class.'.'.$cmd,
				'admin.'.$this->dbo_class.'.index',
				'swisdk.adminmodule.'.$cmd,
				'base.admin',
				'base.full'));

			return $cmp;
		}

		public function run()
		{
			PermissionManager::check_throw($this->role);
			$cmp = $this->component_dispatch();
			$cmp->run();
			$cmp->display();
		}

		/**
		 * @return the DBObject class
		 */
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
		/**
		 * the following four variables are all initialized in AdminComponent::set_module
		 */
		/**
		 * AdminModule URL
		 */
		protected $module_url;

		/**
		 * DBObject class
		 */
		protected $dbo_class;

		/**
		 * AdminComponent arguments
		 */
		protected $args;

		/**
		 * multilanguage flag
		 */
		protected $multilanguage;

		/**
		 * a list of template keys which should be used to display this
		 * AdminComponent
		 */
		protected $template_keys;

		/**
		 * SwisdkSmarty instance
		 */
		protected $smarty;

		/**
		 * resulting HTML code
		 */
		protected $html;

		public function html()
		{
			return $this->html;
		}

		public function display()
		{
			$this->smarty->assign('content', $this->html);
			$this->smarty->display_template($this->template_keys);
		}

		public function init()
		{
			$this->smarty = new SwisdkSmarty();
			$this->smarty->assign('module_url', $this->module_url);
		}

		public function template_keys()
		{
			return $this->template_keys;
		}

		public function set_template_keys($keys)
		{
			$this->template_keys = $keys;
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
			$this->init();
			$module->run_website_components($this->smarty);
		}

		/**
		 * goto - i can't get started without you!
		 *
		 * Shortcut to redirect the user to another AdminComponent
		 *
		 * Usage:
		 * $this->goto('_list');
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

	/**
	 * Create your own specialization of AdminComponent_index to override the default
	 * action for an adminmodule!
	 *
	 * The default is to redirect the user to the entry list
	 */
	class AdminComponent_index extends AdminComponent {
		public function run()
		{
			// default component is list view
			$this->goto('_list');
		}
	}

	/**
	 * Create your own AdminComponent_DBOClass_Ajax_Server to provide ajax services
	 * for a Form
	 *
	 * This is used f.e. by the UpdateOnChangeAjaxBehavior
	 *
	 * See
	 * http://spinlock.ch/pub/git/?p=swisdk2/webapp.git;a=tree;h=multi-domain;hb=multi-domain
	 *
	 * files content/inc.common.php and content/admin/article_ctrl.php and the function
	 * FormUtil::realmed_relation() for an usage example
	 */
	class AdminComponent_ajax extends AdminComponent {
		public function run()
		{
			$server_class = 'AdminComponent_'.$this->dbo_class.'_Ajax_Server';
			if(class_exists($server_class)) {
				$server = new $server_class();
				$server->handle_request();
			} else
				$this->goto('_index');
		}
	}

	/**
	 * Edit or create a database entry
	 */
	class AdminComponent_edit extends AdminComponent {
		/**
		 * Form object
		 */
		protected $form;

		/**
		 * DBObject or DBOContainer (if $muliple is true)
		 */
		protected $obj;

		/**
		 * Note! You might want to take a look at the default CmdsTableViewColumn
		 * implementation first!
		 */

		/**
		 * Edit or create multiple entries at once
		 */
		protected $multiple = false;

		/**
		 * Create a copy of another entry
		 *
		 * This will be set to the ID of the entry which shall be copied
		 */
		protected $copy = null;

		/**
		 * This will be set to false if the user wants to create new entries
		 */
		protected $editmode = true;

		/**
		 * The ID of the entry which the user is currently editing
		 */
		protected $id = false;

		public function run()
		{
			// if multiple is passed, the IDs of the records which should
			// be edited are passed via $_POST
			if(isset($this->args[0])) {
				switch($this->args[0]) {
					case 'multiple':
						$this->multiple = true;
						break;
					case 'from':
						if(isset($this->args[1]))
							$this->copy = $this->args[1];
						break;
					default:
						$this->id = $this->args[0];
				}
			}

			if($this->multiple)
				$this->edit_multiple();
			else
				$this->edit_single();
		}

		/**
		 * return a single DBObject[ML] of the correct type
		 */
		public function get_dbobj($val = null)
		{
			if($this->multiple && $this->obj)
				return $this->obj->dbobj_clone();
			if($this->multilanguage) {
				if($val)
					return DBObjectML::find($this->dbo_class, $val, LANGUAGE_ALL);
				else
					return DBObjectML::create($this->dbo_class, LANGUAGE_ALL);
			} else {
				if($val)
					return DBObject::find($this->dbo_class, $val);
				else
					return DBObject::create($this->dbo_class);
			}
		}

		/**
		 * initialize the DBObject or DBOContainer
		 */
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
				if($this->id)
					$this->obj = $this->get_dbobj($this->id);
				else {
					$this->obj = $this->get_dbobj($this->copy);
					$this->editmode = false;
				}
			}
		}

		/**
		 * init Form or FormML
		 */
		public function init_form()
		{
			if($this->multilanguage)
				$this->form = new FormML();
			else
				$this->form = new Form();
		}

		/**
		 * build the Form or FormBox using the default FormBuilder
		 */
		public function build_form($box = null)
		{
			$builder = $this->form_builder();
			if($box)
				$builder->build($box);
			else
				$builder->build($this->form);
		}

		/**
		 * stop talking (initializing) and DO IT
		 */
		public function execute()
		{
			if($this->form->is_valid()) {
				if(!$this->editmode || $this->copy)
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
			if(!$this->editmode) {
				for($i=1; $i<=3; $i++) {
					$box = $this->form->box($this->dbo_class.'_'.$i);
					$box->set_title(sprintf(
						dgettext('swisdk', 'New %s'), $this->dbo_class));
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
					$box->set_title(sprintf(
						dgettext('swisdk', 'Edit %s'),
						$this->dbo_class.' '.$obj->id()));
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
				$this->form->set_title(sprintf(
					dgettext('swisdk', 'Edit %s'),
					$this->dbo_class.' '.$this->obj->id()));
			else
				$this->form->set_title(sprintf(
					dgettext('swisdk', 'New %s'), $this->dbo_class));
			$this->build_form($this->form);

			$this->execute();
		}
	}

	/**
	 * list all available entries and optionally also search and sort them
	 */
	class AdminComponent_list extends AdminComponent {
		/**
		 * TableView instance
		 */
		protected $tableview;

		/**
		 * Show "Create Entry" button?
		 */
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
			$this->tableview = new TableView($this->get_dbobj());
		}

		/**
		 * the TableView will create a TableViewForm itself if no instance
		 * has been assigned
		 */
		public function init_tableview_form()
		{
			if(class_exists($c = 'TableViewForm_'.$this->dbo_class))
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
				.sprintf(dgettext('swisdk', 'Create %s'), $this->dbo_class)
				."</button>\n":'')
				.$this->tableview->html();
		}

		public function build_tableview()
		{
			$this->tableview_builder()->build($this->tableview);
			$this->complete_columns();
		}

		protected function execute_actions()
		{
			// hook
		}

		protected function complete_columns()
		{
			// hook
		}
	}

	/**
	 * delete one or more entries
	 *
	 * This function is guarded by a token to prevent CSRF attacks
	 *
	 * The standard CmdsTableViewColumn delete action will automatically add the
	 * token to the query string. If the token does not match (or no token has
	 * been passed, f.e. if javascript is deactivated) the user will have to
	 * confirm the request in a simple yes/no form.
	 *
	 * The confirmation page will only be shown in single mode (multiple deletion
	 * mode can only be activated if javascript is enabled anyway)
	 */
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

		protected function delete_confirmation()
		{
			// has aready been on confirmation page?
			if(getInput('delete_confirmation_page')
					&& (getInput('confirmation_command')
						!=dgettext('swisdk', 'Delete')))
				$this->goto('_index');

			// invalid guard token? show confirmation page
			if(getInput('guard')!=guardToken('delete')) {
				$this->display_confirmation_page();
				return;
			}

			return true;
		}

		protected function delete_single()
		{
			if(!$this->delete_confirmation())
				return;

			$dbo = null;
			if($this->multilanguage)
				$dbo = DBObjectML::find($this->dbo_class, $this->args[0]);
			else
				$dbo = DBObject::find($this->dbo_class, $this->args[0]);
			if(!$dbo)
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Can\'t find the data. Class: %s. Argument: %s'),
					$this->dbo_class, intval($this->args[0]))));

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
				SwisdkError::handle(new FatalError(sprintf(
					dgettext('swisdk', 'Can\'t find the data. Class: %s. Argument: %s'),
					$this->dbo_class, intval($this->args[0]))));

			$token = guardToken('delete');
			$class = $dbo->_class();
			$id = $dbo->id();
			$title = $dbo->title();

			$question_title = dgettext('swisdk', 'Confirmation required');
			$question_text = sprintf(dgettext('swisdk', 'Do you really want to delete %s?'),
				$class.' '.$id);
			$delete = dgettext('swisdk', 'Delete');
			$cancel = dgettext('swisdk', 'Cancel');

			$this->html = <<<EOD
<form method="post" action="{$this->module_url}/_delete/$id" class="sf-form" accept-charset="utf-8">
<input type="hidden" name="delete_confirmation_page" value="1" />
<input type="hidden" name="guard" value="$token" />
<table>
<tr class="sf-form-title">
	<td colspan="2"><big><strong>$question_title</strong></big></td>
</tr>
<tr>
	<td></td>
	<td>$question_text ($title)?</td>
</tr>
<tr class="sf-button">
	<td colspan="2">
		<input type="submit" name="confirmation_command" value="$delete" />
		<input type="submit" name="confirmation_command" value="$cancel" />
	</td>
</tr>
</table>
</form>
EOD;
		}
	}

?>
