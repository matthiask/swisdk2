<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Behaviors are bound to FormItems and enhance them or allow for
	 * side interactions between FormItems. They can also add the AJAX
	 * Web 2.0 touch to forms.
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

		/**
		 * do server side setup here
		 */
		protected function setup()
		{
		}

		/**
		 * @return array with two items:
		 *
		 * - the first gets executed automatically using the window.onload event
		 * - the second item may contain f.e. javascript function definitions
		 */
		abstract public function javascript();
	}

	/**
	 * this behavior gets the FormItem it belongs to the keyboard focus 
	 */
	class GrabFocusBehavior extends FormItemBehavior {
		public function javascript()
		{
			$name = $this->item->iname();
			return array('document.getElementById(\''.$name.'\').focus();', null);
		}
	}

	/**
	 * on every change, the validity of a form field is examined and other form fields
	 * are enabled or disabled depending on the validity
	 *
	 * Usage:
	 *
	 * $formitem->add_behavior(new EnableOnValidBehavior($item1[, $item2[, $item3[...]]]))
	 *
	 * This handler gets activated both on onblur and onchange events.
	 * (In Firefox, onchange events are not triggered when the field value was filled in
	 * with autocompletion)
	 */
	class EnableOnValidBehavior extends FormItemBehavior {
		protected function setup()
		{
			foreach($this->args as $item)
				$item->set_attributes(array('disabled' => 'disabled'));
		}

		public function javascript()
		{
			$id = uniqid();
			$name = $this->item->iname();
			$elems = array();
			foreach($this->args as $item)
				$elems[] = $item->iname();
			$elems = '\''.implode('\',\'', $elems).'\'';
			$ruleobjs = $this->item->rules();
			$rules = '';
			foreach($ruleobjs as $rule) {
				$rules[] = $rule->javascript_rule_name($this->item);
			}
			$valid = implode('(\''.$name.'\') && ', $rules).'(\''.$name.'\')';
			$js = <<<EOD
function enable_on_valid_behavior_handler_$id()
{
	var disable = !($valid);
	var elems = new Array($elems);
	for(var i=0; i<elems.length; i++)
		document.getElementById(elems[i]).disabled = disable;
}

EOD;
			return array(
				"add_event(document.getElementById('$name'), 'blur',"
					." enable_on_valid_behavior_handler_$id);\n\t"
				."add_event(document.getElementById('$name'), 'change',"
					." enable_on_valid_behavior_handler_$id);",
				$js);
		}
	}

	/**
	 * Make a server call to replace a <select> element's options
	 *
	 * Note! You have to provide your own Ajax_Server
	 */
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

function update_on_change_ajax_behavior_handler_{$id}_callback(items)
{
	update_selection_box('$n2', items);
}

function update_on_change_ajax_behavior_handler_$id()
{
	x_$method(document.getElementById('$n1').value, update_on_change_ajax_behavior_handler_{$id}_callback);
}

EOD;
			return array(
				"add_event(document.getElementById('$n1'), 'change',"
					." update_on_change_ajax_behavior_handler_$id);",
				$js);
		}
	}

?>
