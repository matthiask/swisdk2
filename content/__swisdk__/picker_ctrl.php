<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'site/inc.site.php';
	require_once MODULE_ROOT.'inc.smarty.php';
	require_once MODULE_ROOT.'inc.form.php';

	class User extends DBObject {
		protected $class = __CLASS__;

		public function title()
		{
			return $this->forename.' '.$this->name;
		}
	}

	class PickerSite extends Site {
		public function run()
		{
			$element = getInput('element');
			$class = getInput('class');
			$params = getInput('params');
			$dboc = DBOContainer::create($class);
			if(is_array($params)) {
				foreach($params as $k => $v) {
					if($k==':order')
						$dboc->add_order_column($v);
					else if($k==':exclude_ids') {
						if(($ids = explode(',', $v)) && count($ids))
							$dboc->add_clause(
								$dboc->dbobj()->name('id').' NOT IN {ids}',
								array('ids' => $ids));
					}
				}
			}
			$dboc->init();
			$html = <<<EOD
<table class="s-table">
<thead>
<tr>
	<th>$class Picker</th>
</tr>
</thead>
<tbody>

EOD;
			$odd = '';
			foreach($dboc as $dbo) {
				$id = $dbo->id();
				$title = $dbo->title();
				$html .= <<<EOD
<tr class="$odd" onclick="do_select(this, $id, '$title');">
<td>$title</td>
</tr>

EOD;
				$odd = $odd?'':'odd';
			}

			$html .= <<<EOD
</tbody>
</table>

EOD;
			$smarty = new SwisdkSmarty();
			$smarty->assign('content', $html);
			$smarty->assign('element', $element);
			$smarty->display_template('base.picker');
		}
	}

	Swisdk::register('PickerSite');

?>
