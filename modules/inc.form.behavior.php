<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class FormBehavior {
		protected $form;
		protected $args;

		public function __construct()
		{
			$this->args = func_get_args();
		}

		public function set_form($form)
		{
			$this->form = $form;
		}

		abstract public function javascript();
	}

	class NoEnterSubmitFormBehavior extends FormBehavior {
		public function javascript()
		{
			$id = $this->form->id();
			$init = <<<EOD
$('#$id input').keydown(function(event){
	if(event.keyCode==13)
		return false;
});

EOD;
			return array($init, '');
		}
	}

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
		}

		public function set_form_item($item)
		{
			$this->item = $item;
			$this->setup();
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
			$name = $this->item->id();
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
			if(!$this->item->is_valid()) {
				foreach($this->args as $item)
					$item->set_attributes(array('disabled' => 'disabled'));
			}
		}

		public function javascript()
		{
			$name = $this->item->id();
			$elems = array();
			foreach($this->args as $item)
				$elems[] = $item->id();
			$elems = '\''.implode('\',\'', $elems).'\'';
			$ruleobjs = $this->item->rules();
			$rules = '';
			foreach($ruleobjs as $rule)
				$rules[] = $rule->javascript_rule_name($this->item);
			if(!is_array($rules) || !count($rules))
				return;
			$valid = implode('(\''.$name.'\') && ', $rules).'(\''.$name.'\')';
			$js = <<<EOD
$(function(){
	var disable = !($valid);
	var elems = new Array($elems);
	for(var i=0; i<elems.length; i++)
		document.getElementById(elems[i]).disabled = disable;
});

EOD;
			return array(
				"$('#$name').blur(function(){enable_on_valid_behavior_handler_$name();});\n\t"
				."$('#$name').change(function(){enable_on_valid_behavior_handler_$name();});",
				$js, "enable_on_valid_behavior_handler_$name");
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
			$n1 = $this->item->id();
			$n2 = $this->args[0]->id();
			$method = $this->args[2];

			$url = sprintf('%s//%s%s_ajax',
				Swisdk::config_value('runtime.request.protocol'),
				Swisdk::config_value('runtime.request.host'),
				Swisdk::config_value('runtime.controller.url'));

			$js = <<<EOD
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

function update_on_change_ajax_behavior_handler_$n1()
{
	$.post('$url', {
		rs: '$method',
		rsargs: [$('#$n1').get(0).value]
	},
	function(data)
	{
		if(data.charAt(0)=='+')
			update_selection_box('$n2', eval('('+data.substring(2)+')'));
	});
}

EOD;
			return array(
				"$('#$n1').change(function(){update_on_change_ajax_behavior_handler_$n1();});",
				$js, "update_on_change_ajax_behavior_handler_$n1");
		}
	}

	class UrlifyBehavior extends FormItemBehavior {
		public function javascript()
		{
			$n1 = $this->item->id();
			$n2 = $this->args[0]->id();

			$date_js = '';
			if(isset($this->args[1])) {
				$n3 = $this->args[1]->id();
				$date_js = 'document.getElementById(\''.$n3
					.'\').value.replace(/^([0-9]+)\.([0-9]+)\.([0-9]+)$/g, \'$3$2$1\') + \'-\' +';
				$this->args[1]->add_action('close', "urlify_behavior_handler_$n1();");
			}

			$js = <<<EOD
function urlify_behavior_handler_$n1()
{
	str = document.getElementById('$n1').value;
	removelist = ["a", "an", "as", "at", "before", "but", "by", "for", "from",
		"is", "in", "into", "like", "of", "off", "on", "onto", "per",
		"since", "than", "the", "this", "that", "to", "up", "via",
		"with"];
	replacement = [
		['ä', 'ae'],
		['ö', 'oe'],
		['ü', 'ue'],
		['ß', 'ss'],
		['à', 'a'],
		['á', 'a'],
		['è', 'e'],
		['é', 'e'],
		['î', 'i'],
		['ô', 'o'],
		['ç', 'c']];
	r = new RegExp('\\b(' + removelist.join('|') + ')\\b', 'gi');
	for(i=0; i<replacement.length; i++)
		str = str.replace(new RegExp(replacement[i][0]), replacement[i][1]);
	document.getElementById('$n2').value = $date_js str.replace(r, '').replace(r, '')
		.replace(/[^-A-Z0-9\s]/gi, '').replace(/^\s+|\s+$/g, '')
		.replace(/[_\s]+/g, '-').toLowerCase();
}

EOD;
			return array(
				"$('#$n1').change(function(){urlify_behavior_handler_$n1();});",
				$js);
		}
	}

?>
