<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.smarty.php';
	require_once MODULE_ROOT.'inc.form.php';
	require_once MODULE_ROOT.'inc.tableview.php';

	class PickerTableViewForm extends TableViewForm {
		public function set_clauses(DBOContainer &$container)
		{
			parent::set_clauses($container);
			$params = getInput('params');
			if(is_array($params)) {
				foreach($params as $k => $v) {
					if($k==':order')
						$container->add_order_column($v);
					else if($k==':exclude_ids') {
						if(($ids = explode(',', $v)) && count($ids))
							$container->add_clause(
								$container->dbobj()->name('id').' NOT IN {ids}',
								array('ids' => $ids));
					}
				}
			}
		}
	}

	function _picker_row($dbo)
	{
		$id = $dbo->id();
		$title = $dbo->title();
		return '<div onclick="do_select(this, '.$id.', \''.$title.'\');">'.$title.'</div>';
	}

	class PickerSite extends Site {
		public function run()
		{
			$element = getInput('element');
			$class = getInput('class');
			$dbo = DBObject::create($class);

			$tableview = new TableView($dbo);
			$tableview->disable('multi');

			$title = $dbo->name('title');
			$id = $dbo->primary();

			$tableview->append_column(new TemplateTableViewColumn(
				$title, 'Title',
				'<div onclick="do_select(this, {'.$id.'}, \'{'
					.$title.'}\');">{'.$title.'}</div>'));

			$form = new PickerTableViewForm($dbo);
			$form->add_fulltext_field();
			$tableview->set_form($form);
			$tableview->init();

			$html = $tableview->html().<<<EOD
<style type="text/css">
label {
	width: 40px;
}
</style>

EOD;

			$smarty = new SwisdkSmarty();
			$smarty->assign('content', $html);
			$smarty->assign('element', $element);
			$smarty->display_template('base.picker');
		}
	}

	Swisdk::register('PickerSite');

?>
