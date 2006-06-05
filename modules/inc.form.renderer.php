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

		abstract public function html();
		abstract protected function _render($obj, $field_html);
		abstract protected function _render_bar($obj, $html);

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
			if($obj instanceof Form)
				$class = 'Form';
			else if($obj instanceof FormBox)
				$class = 'FormBox';

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

			SwisdkError::handle(new FatalError(
				'FormRenderer::visit: Cannot visit '.$class));
		}

		protected function visit_Form_start($obj)
		{
			if($title = $obj->title())
				$this->_render_bar($obj,
					'<big><strong>'.$title.'</strong></big>');
		}

		protected function visit_Form_end($obj)
		{
			$upload = '';
			if($this->file_upload)
				$upload = ' enctype="multipart/form-data">'
					.'<input type="hidden" name="MAX_FILE_SIZE" '
					.'value="2000000"';
			$this->add_html_start(
				'<form method="post" action="'.$_SERVER['REQUEST_URI']
				.'" name="'.$obj->id()."\"$upload>\n");
			$this->add_html_end('</form>');
		}

		protected function visit_FormBox_start($obj)
		{
			if($message = $obj->message())
				$this->add_html_end('<span style="color:red">'
					.$message.'</span>');
			if($title = $obj->title())
				$this->_render_bar($obj, '<strong>'.$title.'</strong>');
		}

		protected function visit_FormBox_end($obj)
		{
		}

		protected function visit_HiddenInput($obj)
		{
			$this->javascript .= $obj->javascript();
			$this->add_html($this->_simpleinput_html($obj));
		}

		protected function visit_SimpleInput($obj)
		{
			$this->javascript .= $obj->javascript();
			$this->_render($obj, $this->_simpleinput_html($obj));
		}

		protected function visit_FileUpload($obj)
		{
			$this->file_upload = true;
			$this->visit_SimpleInput($obj);
		}

		protected function visit_CheckboxInput($obj)
		{
			$this->javascript .= $obj->javascript();
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
			$this->javascript .= $obj->javascript();
			static $js = "
<script type=\"text/javascript\">
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
</span>', $name, $name, $name, $cb_html, $name, $name, $value));

			// only send the javascript once
			$js = '';
		}

		protected function visit_Textarea($obj)
		{
			$this->javascript .= $obj->javascript();
			$name = $obj->iname();
			$this->_render($obj, sprintf(
				'<textarea name="%s" id="%s" %s>%s</textarea>',
				$name, $name, $obj->attribute_html(),
				$obj->value()));
		}

		protected function visit_RichTextarea($obj)
		{
			$this->javascript .= $obj->javascript();
			$name = $obj->iname();
			$value = $obj->value();
			$attributes = $obj->attribute_html();
			$html = <<<EOD
<textarea name="$name" id="$name" $attributes>$value</textarea>
<script type="text/javascript" src="/scripts/util.js"></script>
<script type="text/javascript" src="/scripts/fckeditor/fckeditor.js"></script>
<script type="text/javascript">
function load_editor_$name(){
var oFCKeditor = new FCKeditor('$name');
oFCKeditor.BasePath = '/scripts/fckeditor/';
oFCKeditor.Height = 450;
oFCKeditor.Width = 750;
oFCKeditor.ReplaceTextarea();
}
add_event(window,'load',load_editor_$name);
</script>
EOD;
			$this->_render($obj, $html);
		}

		protected function visit_DropdownInput($obj)
		{
			$this->javascript .= $obj->javascript();
			$name = $obj->iname();
			$html = '<select name="'.$name.'" id="'.$name.'"'
				.$obj->attribute_html().'>';
			$value = $obj->value();
			$items = $obj->items();
			foreach($items as $id => $title) {
				$html .= '<option ';
				if((is_numeric($id) && $id===intval($value))
						|| (!is_numeric($id) && $id==="$value"))
					$html .= 'selected="selected" ';
				$html .= 'value="'.$id.'">'.$title.'</option>';
			}
			$html .= '</select>';
			$this->_render($obj, $html);
		}

		protected function visit_Multiselect($obj)
		{
			$this->javascript .= $obj->javascript();
			$name = $obj->iname();
			$html = '<select name="'.$name.'[]" id="'.$name
				.'" multiple="multiple"'.$obj->attribute_html().'>';
			$value = $obj->value();
			if(!$value)
				$value = array();
			$items = $obj->items();
			foreach($items as $k => $v) {
				$html .= '<option ';
				if(in_array($k,$value))
					$html .= 'selected="selected" ';
				$html .= 'value="'.$k.'">'.$v.'</option>';
			}
			$html .= '</select>';
			$this->_render($obj, $html);
		}

		protected function visit_DateInput($obj)
		{
			$this->javascript .= $obj->javascript();
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
			// TODO use iname

			$display_value = strftime("%d. %B %Y : %H:%M", $value);

			$html.=<<<EOD
<input type="hidden" name="$name" id="$name" value="$value" />
<span id="$span_name">$display_value</span> <img src="/scripts/calendar/img.gif"
	id="$trigger_name"
	style="cursor: pointer; border: 1px solid red;" title="Date selector"
	onmouseover="this.style.background='red';" onmouseout="this.style.background=''" />
<script type="text/javascript">
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
</script>
EOD;
			$this->_render($obj, $html);
		}

		protected function visit_SubmitButton($obj)
		{
			$this->javascript .= $obj->javascript();
			$name = $obj->name();
			$value = $obj->value();
			if(!$value)
				$value = 'Submit';
			$this->_render_bar($obj,
				'<input type="submit" '.$obj->attribute_html()
				.($name?' name="'.$name.'"':'')
				.' value="'.$value.'" />');
		}

		protected function _title_html($obj)
		{
			return '<label for="'.$obj->iname().'">'.$obj->title().'</label>';
		}

		protected function _message_html($obj)
		{
			$msg = $obj->message();
			return $msg?'<div style="clear:both;color:red">'.$msg.'</div>':'';
		}

		protected function _info_html($obj)
		{
			$msg = $obj->info();
			return $msg?'<div style="float:left;">'.$msg.'</div>':'';
		}

		protected function _simpleinput_html($obj)
		{
			$name = $obj->iname();
			return sprintf(
				'<input type="%s" name="%s" id="%s" value="%s" %s />',
				$obj->type(), $name, $name, $obj->value(),
				$obj->attribute_html());
		}

		protected function javascript()
		{
			return '<script type="text/javascript">'.$this->javascript.'</script>';
		}
	}

	class TableFormRenderer extends FormRenderer {
		protected $grid;

		public function html()
		{
			return $this->html_start
				.$this->grid()->html()
				.$this->javascript()
				.$this->html_end;
		}

		protected function &grid()
		{
			if(!$this->grid)
				$this->grid = new Layout_Grid();
			return $this->grid;
		}

		protected function _render($obj, $field_html)
		{
			$y = $this->grid()->height();
			$this->grid()->add_item(0, $y, $this->_title_html($obj));
			$this->grid()->add_item(1, $y,
				'<div style="float:left;">'.$field_html.'</div>'
				.$this->_info_html($obj)
				.$this->_message_html($obj));
		}

		protected function _render_bar($obj, $html)
		{
			$this->grid()->add_item(0, $this->grid()->height(), $html, 2, 1);
		}
	}

?>
