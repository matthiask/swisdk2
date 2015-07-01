<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT.'inc.admincomponent.php';
	require_once MODULE_ROOT.'inc.smarty.php';

	abstract class AdminSite extends Site {
		protected $dbo_class;
		protected $multilanguage = false;

		protected $mode = 'combined';
		protected $creation_button = false;

		protected $role = ROLE_MANAGER;

		protected $smarty;

		public function init()
		{
			$this->smarty = new SwisdkSmarty();
		}

		public function run()
		{
			PermissionManager::check_throw($this->role);
			$this->{'run_'.$this->mode}();
		}

		protected function run_single()
		{
			$this->init();
			$args = Swisdk::config_value('runtime.arguments');

			$cmp = $this->dispatch(
				s_get($args, 0, 'list'),
				s_get($args, 1));

			$this->smarty = new SwisdkSmarty();
			$this->run_website_components($this->smarty);
			$this->smarty->assign('content', $cmp?$cmp->html():'');
			$this->display();
		}

		protected function run_combined()
		{
			Swisdk::needs_library('jquery_interface');

			$this->init();
			$args = Swisdk::config_value('runtime.arguments');

			$cmp = $this->dispatch(
				s_get($args, 0, 'create'),
				s_get($args, 1));

			$list = $this->create_list_component(
				DBOContainer::create($this->create_dbobject($this->dbo_class)));
			$list->run();

			$this->smarty = new SwisdkSmarty();
			$this->run_website_components($this->smarty);

			$scroll = '';
			if(s_test($args, 0))
				$scroll = <<<EOD
<div id="adminsite-component"></a>
<script type="text/javascript">
//<![CDATA[
$(function(){
	$('#adminsite-component').ScrollTo(1000);
});
//]]>
</script>

EOD;

			$this->smarty->assign('content',
				$list->html().$scroll.($cmp?$cmp->html():''));
			$this->display();
		}

		protected function display()
		{
			if($this->creation_button) {
				$url = $this->url();

				$button = <<<EOD
<button type="button" onclick="window.location.href='{$url}new'">Create {$this->dbo_class}</button>

EOD;
				$this->smarty->assign('content', $button
					.$this->smarty->get_template_vars('content'));
			}

			$this->smarty->display_template('base.admin');
		}

		protected function dispatch($cmd, $id=null)
		{
			switch($cmd) {
				case 'edit':
				case 'copy':
					$dbo = $this->find_dbobject($this->dbo_class, $id);
					if($dbo && $cmd=='copy')
						$dbo->unset_primary();
				case 'new':
				case 'create':
					if(!isset($dbo) || !$dbo) {
						$dbo = $this->create_dbobject($this->dbo_class);
						$dbo->id = -1;
					}
					$cmp = $this->create_edit_component($dbo);
					$cmp->run();

					if($cmp->has_state(STATE_FINISHED))
						$this->go_to();
					if($cmp->has_state(STATE_CONTINUE)) {
						if($cmd!='edit')
							$this->go_to('edit/'.$dbo->id());
						$cmp->form()->refresh_guard();
					}

					return $cmp;
				case 'delete':
					$dbo = $this->find_dbobject($this->dbo_class, $id);
					if(!$dbo)
						$this->go_to();

					$cmp = $this->create_delete_component($dbo);
					$cmp->run();

					if($cmp->has_state(STATE_FINISHED))
						$this->go_to();

					return $cmp;
				case 'list':
					$dboc = DBOContainer::create(
						$this->create_dbobject($this->dbo_class));
					$cmp = $this->create_list_component($dboc);
					$cmp->run();

					return $cmp;
				case 'edit_multiple':
				case 'copy_multiple':
					$container = $this->find_dbocontainer($this->dbo_class);
					if(!$container)
						$this->go_to();
				case 'new_multiple':
					if(!isset($container) || !$container) {
						$container = $this->create_dbocontainer(
							$this->dbo_class, 3);
					}

					if($cmd=='copy_multiple' || $cmd=='new_multiple') {
						$this->renumber_dbocontainer($container);
					}

					$cmp = $this->create_multi_edit_component($container);
					$cmp->run();

					if($cmp->has_state(STATE_FINISHED))
						$this->go_to();

					return $cmp;
				case 'delete_multiple':
					$container = $this->find_dbocontainer($this->dbo_class);
					if(!$container)
						$this->go_to();

					$cmp = $this->create_multi_delete_component($container);
					$cmp->run();

					if($cmp->has_state(STATE_FINISHED))
						$this->go_to();

					return $cmp;
				default:
					return $this->dispatch_other($cmd, $id);
			}
		}

		protected function dispatch_other($cmd, $id)
		{
			if(method_exists($this, $m = 'handle_'.$cmd))
				return $this->$m($id);
		}

		protected function create_dbobject($class)
		{
			if($this->multilanguage)
				return DBObjectML::create($class, LANGUAGE_ALL);
			else
				return DBObject::create($class);
		}

		protected function find_dbobject($class, $args)
		{
			if($this->multilanguage)
				return DBObjectML::find($class, $args, LANGUAGE_ALL);
			else
				return DBObject::find($class, $args);
		}

		protected function create_dbocontainer($class, $count=0)
		{
			$container = DBOContainer::create($this->create_dbobject($class));
			while($count) {
				$dbo = $container->dbobj_clone();
				$dbo->id = -$count--;
				$container->add($dbo);
			}

			return $container;
		}

		protected function find_dbocontainer($class)
		{
			$dbo = $this->create_dbobject($class);
			$primary = $dbo->primary();

			$ids = getInput($primary);
			if(!is_array($ids) || !count($ids))
				return null;

			$container = DBOContainer::find_by_id($dbo, $ids);
			if($container->count()==0)
				return null;

			return $container;
		}

		protected function renumber_dbocontainer(&$container)
		{
			$idx = 0;
			foreach($container as $dbo) {
				$dbo->unset_primary();
				$dbo->id = --$idx;
			}
		}

		protected function create_edit_component($dbo)
		{
			return new EditComponent($dbo);
		}

		protected function create_multi_edit_component($dbo)
		{
			return new EditComponent($dbo);
		}

		protected function create_delete_component($dbo)
		{
			return new DeleteComponent($dbo);
		}

		protected function create_multi_delete_component($dbo)
		{
			return new DeleteComponent($dbo);
		}

		protected function create_list_component($dbo)
		{
			return new ListComponent($dbo);
		}

		/**
		 * @return various informations about this module
		 */
		public function info()
		{
			return array(
				'class' => $this->dbo_class,
				//'multilanguage' => $this->multilanguage,
				'role' => $this->role,
				'actions' => $this->info_actions());
		}

		public function info_actions()
		{
			return array();
		}
	}

?>
