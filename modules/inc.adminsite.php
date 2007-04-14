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

		protected $role = ROLE_MANAGER;

		public function run()
		{
			PermissionManager::check_throw($this->role);
			$this->run_combined();
		}

		protected function run_combined()
		{
			$args = Swisdk::config_value('runtime.arguments');

			$cmp = $this->dispatch(
				s_get($args, 0, '_create'),
				s_get($args, 1));

			$list = $this->create_list_component(
				DBOContainer::create($this->create_dbobject($this->dbo_class)));
			$list->run();

			$smarty = new SwisdkSmarty();
			$this->run_website_components($smarty);
			$smarty->assign('content',
				$list->html()
				.($cmp?$cmp->html():''));
			$smarty->display_template('base.admin');
		}

		protected function dispatch($cmd, $id=null)
		{
			switch($cmd) {
				case '_edit':
				case '_copy':
					$dbo = $this->find_dbobject($this->dbo_class, $id);
					if($dbo && $cmd=='_copy')
						$dbo->unset_primary();
				case '_create':
					if(!isset($dbo) || !$dbo) {
						$dbo = $this->create_dbobject($this->dbo_class);
						$dbo->id = -1;
					}
					$cmp = $this->create_edit_component($dbo);
					$cmp->run();

					if($cmp->has_state(STATE_FINISHED))
						$this->goto();
					if($cmp->has_state(STATE_CONTINUE) && $cmd!='_edit')
						$this->goto('_edit/'.$dbo->id());

					return $cmp;
				case '_delete':
					$dbo = $this->find_dbobject($this->dbo_class, $id);
					if(!$dbo)
						$this->goto();

					$cmp = $this->create_delete_component($dbo);
					$cmp->run();

					if($cmp->has_state(STATE_FINISHED))
						$this->goto();

					return $cmp;
			}
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

		protected function create_edit_component($dbo)
		{
			return new EditComponent($dbo);
		}

		protected function create_delete_component($dbo)
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
