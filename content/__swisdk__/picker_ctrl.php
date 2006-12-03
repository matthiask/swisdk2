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
				}
			}
			$dboc->init();
			echo <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
	<title>$class Picker</title>
	<link rel="stylesheet" type="text/css" href="/css/feinware.css" />
	<script type="text/javascript">
	//<![CDATA[
	function do_select(elem, val, str)
	{
		opener.select_$element(val, str);
		this.close();
	}
	//]]>
	</script>
	<style type="text/css">
	html, body {
		margin: 0;
		padding: 0;
	}
	.s-table {
		width: 100%;
	}
	</style>
</head>
<body>
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
				echo <<<EOD
<tr class="$odd" onclick="do_select(this, $id, '$title');">
<td>$title</td>
</tr>

EOD;
				$odd = $odd?'':'odd';
			}

			echo <<<EOD
</tbody>
</table>
</body>
</html>

EOD;
		}
	}

	Swisdk::register('PickerSite');

?>
