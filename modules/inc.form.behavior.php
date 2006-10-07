<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class FormItemBehavior {
		protected $item;
		protected $args;

		public function __construct()
		{
			$this->args = func_get_args();
			$this->setup();
		}

		public function set_form_item($item)
		{
			$this->item = $item;
		}

		protected function setup()
		{
		}
	}

	class GrabFocusBehavior extends FormItemBehavior {
		public function javascript()
		{
			$id = uniqid();
			$name = $this->item->iname();
			return array("document.getElementById('$name').focus();", null);
		}
	}

	class EnableOnValidBehavior extends FormItemBehavior {
		protected function setup()
		{
			$this->args[0]->set_attributes(array('disabled' => 'disabled'));
		}

		public function javascript()
		{
			$id = uniqid();
			$n1 = $this->item->iname();
			$n2 = $this->args[0]->iname();
			$ruleobjs = $this->item->rules();
			$rules = '';
			foreach($ruleobjs as $rule) {
				$rules[] = $rule->javascript_rule_name($this->item);
			}
			$valid = implode('(\''.$n1.'\') && ', $rules).'(\''.$n1.'\')';
			$js = <<<EOD
function enable_on_valid_behavior_handler_$id()
{
	if($valid)
		document.getElementById('$n2').disabled = false;
	else
		document.getElementById('$n2').disabled = true;
}

EOD;
			return array(
				"add_event(document.getElementById('$n1'), 'change',"
					." enable_on_valid_behavior_handler_$id);",
				$js);
		}
	}

	class UpdateOnChangeAjaxBehavior extends FormItemBehavior {
		public function javascript()
		{
			$id = uniqid();
			$n1 = $this->item->iname();
			$n2 = $this->args[0]->iname();
			$method = $this->args[2];

			$client = new Ajax_Client($this->args[1],
				sprintf('%s//%s%s_ajax',
					Swisdk::config_value('runtime.request.protocol'),
					Swisdk::config_value('runtime.request.host'),
					Swisdk::config_value('runtime.controller.url')));
			$js = $client->javascript();

			$js .= <<<EOD
// SWISDk2 Forms AJAX helpers
function update_selection_box(elem, items)
{
	var options = document.getElementById(elem).options;
	while(options.length)
		options[0] = null;
	for(var key in items) {
		var opt = new Option(items[key], key);
		options[options.length] = opt;
	}
}

function update_owner_list_cb(items)
{
	update_selection_box('$n2', items);
}

function update_on_change_ajax_behavior_handler_$id()
{
	x_$method(document.getElementById('$n1').value, update_owner_list_cb);
}

EOD;
			return array(
				"add_event(document.getElementById('$n1'), 'change',"
					." update_on_change_ajax_behavior_handler_$id);",
				$js);
		}
	}

?>
