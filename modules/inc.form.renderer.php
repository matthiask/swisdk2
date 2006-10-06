<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class FormRenderer {

		protected $html_start = '';
		protected $html_end = '';
		protected $javascript = '';
		protected $file_upload = false;
		protected $js_validation = true;

		/**
		 * Render the HTML of an input field
		 */
		abstract protected function _render($obj, $field_html, $row_class = null);

		/**
		 * Render the HTML of a bar which (normally) spans the whole width of the
		 * form, f.e. a form title or a section title
		 */
		abstract protected function _render_bar($obj, $html, $row_class = null);

		/**
		 * Add a HTML fragment
		 *
		 * <form> (added by call to add_html_start() in visit_Form_end)
		 *
		 * add_html_start()
		 *
		 * 	head
		 *
		 * add_html()
		 *
		 * 	body (input elements)
		 *
		 * 	foot
		 *
		 * add_html_end()
		 *
		 * </form> (added by call to add_html_end() in visit_Form_end)
		 *
		 */
		public function add_html($html)
		{
			$this->html_start .= $html;
		}

		public function add_html_start($html)
		{
			$this->html_start = $html.$this->html_start;
		}

		public function add_html_end($html)
		{
			$this->html_end .= $html;
		}

		/**
		 * Array of javascript validation functions
		 */
		protected $functions = array();

		/**
		 * Enable/disable validation javascript output (has no effect on
		 * the server side of validation)
		 */
		public function set_javascript_validation($enabled = true)
		{
			$this->js_validation = $enabled;
		}

		public function javascript_validation()
		{
			return $this->js_validation;
		}

		/**
		 * handle the passed object
		 *
		 * it first tries to find a method named visit_ObjectClass , and if
		 * not successful, walks the inheritance ancestry to find a matching
		 * visit method.
		 *
		 * That way, you can derive your own FormItems without necessarily
		 * needing to extend the FormRenderer
		 */
		public function visit($obj, $stage = FORMRENDERER_VISIT_DEFAULT)
		{
			$suffix = '';
			if($stage==FORMRENDERER_VISIT_START)
				$suffix = '_start';
			else if($stage==FORMRENDERER_VISIT_END)
				$suffix = '_end';

			$class = get_class($obj);
			$method = 'visit_'.$class.$suffix;
			if(method_exists($this, $method)) {
				call_user_func(array($this, $method), $obj);
				return;
			} else {
				$parents = class_parents($class);
				foreach($parents as $p) {
					$method = 'visit_'.$p.$suffix;
					if(method_exists($this, $method)) {
						call_user_func(array($this, $method),
							$obj);
						return;
					}
				}
			}

			SwisdkError::handle(new FatalError(sprintf(
				dgettext('swisdk', 'FormRenderer: Cannot visit %s'), $class)));
		}

		protected function visit_Form_start($obj)
		{
			if($title = $obj->title())
				$this->_render_bar($obj,
					'<big><strong>'.$title.'</strong></big>',
					'sf-form-title');
		}

		protected function visit_Form_end($obj)
		{
			$this->_collect_javascript($obj);

			$upload = '';
			$valid = '';
			if($this->file_upload)
				$upload = 'enctype="multipart/form-data">'."\n"
					.'<input type="hidden" name="MAX_FILE_SIZE" '
					.'value="2000000"';
			list($html, $js) = $this->_validation_html($obj);
			$this->add_html_start(
				'<form method="post" action="'.$_SERVER['REQUEST_URI']
				.'" id="'.$obj->id()."\" $html class=\"sf-form\" "
				."accept-charset=\"utf-8\" $upload>\n<div>\n".$js);
			$this->add_html_end('<span style="color:red" id="'.$obj->id()."_message_span\"> </span>\n");
			$this->add_html_end("</div></form>\n");
		}

		protected function _validation_html($obj)
		{
			$js = '<script type="text/javascript">
//<![CDATA[
'.$this->javascript;
			$end = '
//]]>
</script>';
			if(!$this->js_validation || !count($this->functions))
				return array(null, $js.$end);

			$id = $obj->id();
			$js .= '
function swisdk_form_do_validate(valid, field, message)
{
	if(!valid) {
		document.getElementById(field+\'_message_span\').innerHTML += message;
		return false;
	}
	return true;
}

function validate_'.$id.'()
{
	var valid = true;
	var form = document.getElementById(\''.$id.'\');
	for(var i=0; i<form.elements.length; i++) {
		spanElem = document.getElementById(form.elements[i].id+\'_message_span\');
		if(spanElem)
			spanElem.innerHTML = \' \';
	}
	document.getElementById(\''.$id.'_message_span\').innerHTML = \' \';
	if(!'.implode(") valid = false;\n\tif(!", $this->functions).') valid = false;
	return valid;
}';
			return array('onsubmit="return validate_'.$id.'()"', $js.$end);
		}

		protected function _collect_javascript($obj)
		{
			// add add_javascript() fragments
			if(!$obj instanceof Form && !$obj instanceof FormBox)
				$this->javascript .= $obj->javascript();

			if(!$this->js_validation)
				return;

			// add validation rule javascript
			$rules = $obj->rules();
			foreach($rules as &$rule) {
				list($funccall, $js) = $rule->validation_javascript($obj);
				$this->javascript .= $js;
				if($funccall)
					$this->functions[] = $funccall;
			}
		}

		protected function visit_FormBox_start($obj)
		{
			// FIXME placement of message div should not always be at the
			// end of form (end of FormBox!)
			$this->add_html_end($this->_message_html($obj));
			if($title = $obj->title())
				$this->_render_bar($obj, '<strong>'.$title.'</strong>',
					'sf-box-title');
		}

		protected function visit_FormBox_end($obj)
		{
		}

		protected function visit_HiddenInput($obj)
		{
			$this->_collect_javascript($obj);
			$this->add_html($this->_simpleinput_html($obj)."\n");
		}

		protected function visit_SimpleInput($obj)
		{
			$this->_collect_javascript($obj);
			$this->_render($obj, $this->_simpleinput_html($obj));
		}

		protected function visit_FileUpload($obj)
		{
			$this->_collect_javascript($obj);
			$this->file_upload = true;
			$this->visit_SimpleInput($obj);
		}

		protected function visit_DBFileUpload($obj)
		{
			$this->_collect_javascript($obj);
			$this->file_upload = true;
			$name = $obj->iname();
			$current = $obj->current_value();
			$this->_render($obj, sprintf(
				'%s<input type="%s" name="%s" id="%s" value="%s" %s />',
				($current
					?sprintf('Current: <a href="/download/%s">%s</a><br />',
						$current, $current)
					:''),
				$obj->type(), $name, $name, $obj->value(),
				$obj->attribute_html()));
		}

		protected function visit_CheckboxInput($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->iname();
			$this->_render($obj, sprintf(
				'<input type="checkbox" name="%s" id="%s" %s value="1" />'
				.'<input type="hidden" name="__check_'.$name
				.'" value="1" />',
				$name, $name,
				($obj->value()?'checked="checked" ':' ')
				.$obj->attribute_html()));
		}

		protected function visit_TristateInput($obj)
		{
			$this->_collect_javascript($obj);
			static $js = "
<script type=\"text/javascript\">
//<![CDATA[
function formitem_tristate(elem)
{
	var value = document.getElementById(elem.id.replace(/^__cont_/, ''));
	var cb = document.getElementById('__cb_'+value.id);

	switch(value.value) {
		case 'checked':
			cb.checked = false;
			value.value = 'unchecked';
			break;
		case 'unchecked':
			cb.checked = true;
			cb.disabled = true;
			value.value = 'mixed';
			break;
		case 'mixed':
		default:
			cb.checked = true;
			cb.disabled = false;
			value.value = 'checked';
			break;
	}

	return false;
}
//]]>
</script>";
			$name = $obj->iname();
			$value = $obj->value();
			$cb_html = '';
			if($value=='mixed')
				$cb_html = 'checked="checked" disabled="disabled"';
			else if($value=='checked')
				$cb_html = 'checked="checked"';
			$this->_render($obj, $js.sprintf(
'<span style="position:relative;">
	<div style="position:absolute;top:0;left:0;width:20px;height:20px;"
		id="__cont_%s" onclick="formitem_tristate(this)"></div>
	<input type="checkbox" name="__cb_%s" id="__cb_%s" %s />
	<input type="hidden" name="%s" id="%s" value="%s" />
</span>'."\n", $name, $name, $name, $cb_html, $name, $name, $value));

			// only send the javascript once
			$js = '';
		}

		protected function visit_Textarea($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->iname();
			$this->_render($obj, sprintf(
				'<textarea name="%s" id="%s" %s>%s</textarea>',
				$name, $name, $obj->attribute_html(),
				$obj->value()));
		}

		protected function visit_RichTextarea($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->iname();
			$value = $obj->value();
			$attributes = $obj->attribute_html();
			$html = <<<EOD
<textarea name="$name" id="$name" $attributes>$value</textarea>
<script type="text/javascript" src="/scripts/util.js"></script>
<script type="text/javascript" src="/scripts/fckeditor/fckeditor.js"></script>
<script type="text/javascript">
//<![CDATA[
function load_editor_$name(){
var oFCKeditor = new FCKeditor('$name');
oFCKeditor.BasePath = '/scripts/fckeditor/';
oFCKeditor.Height = 450;
oFCKeditor.Width = 750;
oFCKeditor.ReplaceTextarea();
}
add_event(window,'load',load_editor_$name);
//]]>
</script>

EOD;
			$this->_render($obj, $html);
		}

		protected function visit_DropdownInput($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->iname();
			$html = '<select name="'.$name.'" id="'.$name.'"'
				.$obj->attribute_html().">\n";
			$value = $obj->value();
			$items = $obj->items();
			foreach($items as $id => $title) {
				$html .= '<option ';
				if((is_numeric($id) && $id===intval($value))
						|| (!is_numeric($id) && $id==="$value"))
					$html .= 'selected="selected" ';
				$html .= 'value="'.$id.'">'.$title."</option>\n";
			}
			$html .= "</select>\n";
			$this->_render($obj, $html);
		}

		protected function visit_Combobox($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->iname();
			$html = '<select name="'.$name.'" id="'.$name.'"'
				.$obj->attribute_html().">\n";
			$value = $obj->value();
			$items = $obj->items();
			foreach($items as $id => $title) {
				$html .= '<option ';
				if((is_numeric($id) && $id===intval($value))
						|| (!is_numeric($id) && $id==="$value"))
					$html .= 'selected="selected" ';
				$html .= 'value="'.$id.'">'.$title."</option>\n";
			}
			$html .= "</select>\n";
			$html .= '<input type="button" name="'.$name.'_button"'
				.'id="'.$name.'_button" value=" + " />';
			$js = '';
			static $js_sent = false;
			if(!$js_sent) {
				$js_sent = true;
				$js = file_get_contents(SWISDK_ROOT
					.'lib/contrib/combobox.js')."\n";
			}
			$html .= <<<EOD
<script type="text/javascript">
//<![CDATA[
$js	toCombo("$name","{$name}_button");
//]]>
</script>
EOD;
			$this->_render($obj, $html);
		}

		protected function visit_Multiselect($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->iname();
			$html = '<select name="'.$name.'[]" id="'.$name
				.'" multiple="multiple"'.$obj->attribute_html().">\n";
			$value = $obj->value();
			if(!$value)
				$value = array();
			$items = $obj->items();
			foreach($items as $k => $v) {
				$html .= '<option ';
				if(in_array($k,$value))
					$html .= 'selected="selected" ';
				$html .= 'value="'.$k.'">'.$v."</option>\n";
			}
			$html .= "</select>\n";
			$this->_render($obj, $html);
		}

		protected function visit_ThreewayInput($obj)
		{
			$values = $obj->value();
			$html = array();
			$second = $obj->second();
			foreach($second as $k => $v) {
				$name = $obj->iname()."[$k]";
				$html[] = sprintf(
					'<label for="%s">%s</label>: '
					.$this->threeway_helper_choice($obj, $values[$k]),
					$name, $v, $name, $name);
			}
			$this->_render($obj, implode("<br />\n", $html));
		}

		protected function threeway_helper_choice($obj, $value=null)
		{
			$choices = $obj->choices();
			$html = "<select name=\"%s\" id=\"%s\">\n"
				."<option value=\"0\"> -- </option>";
			foreach($choices as $k => $v)
				$html .= '<option value="'.$k.'"'
					.($k==$value?' selected="selected"':'')
					.'>'.$v."</option>\n";
			$html .= "</select>\n";
			return $html;
		}

		protected function visit_DateInput($obj)
		{
			$this->_collect_javascript($obj);
			$html = '';
			static $js_sent = false;
			if(!$js_sent) {
				$js_sent = true;
				$html.=<<<EOD
<link rel="stylesheet" type="text/css" media="all"
	href="/scripts/calendar/calendar-win2k-1.css" title="win2k-cold-1" />
<script type="text/javascript" src="/scripts/calendar/calendar.js"></script>
<script type="text/javascript" src="/scripts/calendar/calendar-en.js"></script>
<script type="text/javascript" src="/scripts/calendar/calendar-setup.js"></script>
EOD;
			}

			$name = $obj->iname();
			$span_name = $name.'_span';
			$trigger_name = $name.'_trigger';
			$value = intval($obj->value());
			if(!$value)
				$value = time();

			$display_value = strftime("%d. %B %Y : %H:%M", $value);

			$html.=<<<EOD
<input type="hidden" name="$name" id="$name" value="$value" />
<span id="$span_name">$display_value</span> <img src="/scripts/calendar/img.gif"
	id="$trigger_name"
	style="cursor: pointer; border: 1px solid red;" title="Date selector"
	onmouseover="this.style.background='red';" onmouseout="this.style.background=''" />
<script type="text/javascript">
//<![CDATA[
Calendar.setup({
	inputField  : "$name",
	ifFormat    : "%s",
	displayArea : "$span_name",
	daFormat    : "%d. %B %Y : %H:%M",
	button      : "$trigger_name",
	singleClick : true,
	showsTime   : true,
	step        : 1
});
//]]>
</script>

EOD;
			$this->_render($obj, $html);
		}

		protected function visit_SubmitButton($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->name();
			$value = $obj->value();
			if(!$value)
				$value = 'Submit';
			$this->_render_bar($obj,
				'<input type="submit" '.$obj->attribute_html()
				.($name?' name="'.$name.'"':'')
				.' value="'.$value.'" />',
				'sf-button');
		}

		protected function _title_html($obj)
		{
			return '<label for="'.$obj->iname().'">'.$obj->title().'</label>';
		}

		protected function _message_html($obj)
		{
			$msg = $obj->message();
			$name = $obj->id();
			return '<div id="'.$name.'_message_span" style="clear:both;color:red">'
				.($msg?
				'<img style="position:relative;top:4px" src="/images/icons/error.png" alt="error" />'
				.$msg:' ')."</div>\n";
		}

		protected function _info_html($obj)
		{
			$msg = $obj->info();
			return $msg?'<div style="float:left;clear:both;font-size:80%">'
				.'<img style="position:relative;top:4px;" src="/images/icons/information.png" alt="info" /> '
				.$msg."</div>\n":'';
		}

		protected function _simpleinput_html($obj)
		{
			$name = $obj->iname();
			return sprintf(
				'<input type="%s" name="%s" id="%s" value="%s" %s />',
				$obj->type(), $name, $name, $obj->value(),
				$obj->attribute_html());
		}
	}

	class TableFormRenderer extends FormRenderer {
		protected $grid;

		public function html()
		{
			return $this->html_start
				.$this->grid()->html()
				.$this->html_end;
		}

		protected function &grid()
		{
			if(!$this->grid)
				$this->grid = new Layout_Grid();
			return $this->grid;
		}

		protected function _render($obj, $field_html, $row_class = null)
		{
			$grid = $this->grid();
			$y = $grid->height();
			if($row_class)
				$grid->set_row_class($y, $row_class);
			$grid->add_item(0, $y, $this->_title_html($obj));
			$grid->add_item(1, $y,
				'<div style="float:left;">'.$field_html."</div>\n"
				.$this->_info_html($obj)
				.$this->_message_html($obj));
		}

		protected function _render_bar($obj, $html, $row_class = null)
		{
			$grid = $this->grid();
			$y = $grid->height();
			if($row_class)
				$grid->set_row_class($y, $row_class);
			$grid->add_item(0, $y, $html, 2, 1);
		}
	}

?>
