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

		protected function component_dispatch()
		{
			$args = Swisdk::config_value('runtime.arguments');
			$cmp = null;

			if(isset($args[0])) {
				$cmp_class = 'AdminComponent_'.$this->dbo_class.$args[0];
				if(class_exists($cmp_class))
					$cmp = new $cmp_class;
				else if(class_exists($cmp_class = 'AdminComponent'.$args[0])) 
					$cmp = new $cmp_class;
				array_shift($args);
			}
			
			$this->arguments = $args;

			if(!$cmp)
				$cmp = new AdminComponent_index();

			$cmp->set_module($this);

			return $cmp;
		}

		public function run()
		{
			PermissionManager::check_throw();
			$cmp = $this->component_dispatch();
			$cmp->run();

			$sm = SmartyMaster::instance();
			$sm->add_html_handler('content', $cmp);
			$sm->display();
		}

		public function dbo_class()
		{
			return $this->dbo_class;
		}

		public function component_arguments()
		{
			return $this->arguments;
		}
	}

	class AdminComponent implements IHtmlComponent {
		protected $module_url;
		protected $dbo_class;
		protected $args;
		protected $html;

		public function run()
		{
		}

		public function html()
		{
			return $this->html;
		}

		public function set_module(&$module)
		{
			$this->module_url = $module->url();
			$this->dbo_class = $module->dbo_class();
			$this->args = $module->component_arguments();
		}

		/**
		 * goto - i can't get started without you!
		 */
		public function goto($tok)
		{
			redirect($this->module_url.$tok);
		}


		public function form_builder()
		{
			$cmp_class = 'FormBuilder_'.$this->dbo_class;
			if(class_exists($cmp_class))
				return new $cmp_class();
			return new FormBuilder();
		}

		public function tableview_builder()
		{
			$cmp_class = 'TableViewBuilder_'.$this->dbo_class;
			if(class_exists($cmp_class))
				return new $cmp_class();
			return new TableViewBuilder();
		}
	}

	class AdminComponent_index extends AdminComponent {
		public function run()
		{
			$this->goto('_list');
		}
	}

	class AdminComponent_new extends AdminComponent {
		public function run()
		{
			$form = new Form(DBObject::create($this->dbo_class));
			$this->form_builder()->build($form);
			if($form->is_valid()) {
				$form->dbobj()->store();
				$this->goto('_index');
			} else
				$this->html = $form->html();
		}
	}

	class AdminComponent_edit extends AdminComponent {
		public function run()
		{
			$dbo = DBObject::find($this->dbo_class, $this->args[0]);
			if(!$dbo)
				SwisdkError::handle( new FatalError("AdminComponent_edit::run() - Can't find the data. Class is: {$this->dbo_class} Argument is: {$this->args[0]}" ) );

			$form = new Form($dbo);
			$this->form_builder()->build($form);
			if($form->is_valid()) {
				$dbo->store();
				$this->goto('_index');
			} else
				$this->html = $form->html();
		}
	}

	class AdminComponent_list extends AdminComponent {
		public function run()
		{
			$tv = new DBTableView($this->dbo_class, 'DBTableViewForm');
			$tv->append_column(new CmdsTableViewColumn($this->module_url,
				$tv->dbobj()->dbobj()->primary()));
			$this->html = $tv->html();
		}
	}

	class AdminComponent_delete extends AdminComponent {
		public function run()
		{
			$dbo = DBObject::find($this->dbo_class, $this->args[0]);
			if(!$dbo)
				SwisdkError::handle( new FatalError("AdminComponent_delete::run() - Can't find the data. Class is: {$this->dbo_class} Argument is: {$this->args[0]}" ) );

			$dbo->delete();
			$this->goto('_index');
		}
	}

?>
