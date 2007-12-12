<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * base class of all form items
	 */
	class FormItem {

		/**
		 * the name (DBObject field name)
		 */
		protected $name;

		/**
		 * user-readable title
		 */
		protected $title;

		/**
		 * message (f.e. validation errors)
		 */
		protected $message;

		/**
		 * info text
		 */
		protected $info;

		/**
		 * validation rule objects
		 */
		protected $rules = array();

		/**
		 * behaviors array
		 *
		 * these are client side javascript behaviors which allow
		 * extended interactions between form items and/or
		 * rpc/ajax calls to the server
		 */
		protected $behaviors = array();

		/**
		* css class
		*/
		protected $css_class;

		/**
		 * additional html attributes
		 */
		protected $attributes = array();

		/**
		 * This gets prepended to the name of every FormItem so that
		 * every FormItem's name is unique on the page.
		 */
		protected $box_name = null;

		/**
		 * has this element beed added to the FormBox with
		 * add_initialized_obj ? If yes, do not mangle the name
		 */
		protected $preinitialized = false;

		/**
		 * javascript fragments
		 */
		protected $javascript = '';

		/**
		 * current validation status
		 */
		protected $valid = null;

		/**
		 * the bound DBObject
		 */
		protected $dbobj = null;

		public function __construct($name=null)
		{
			if($name)
				$this->name = $name;
		}

		public function dbobj()
		{
			return $this->dbobj;
		}

		public function bind($dbobj)
		{
			$this->dbobj = $dbobj;
			return $this;
		}

		/**
		 * accessors and mutators
		 */
		public function value()
		{
			return $this->dbobj->get($this->name);
		}

		public function set_value($value)
		{
			$this->dbobj->set($this->name, $value);
			return $this;
		}

		protected $default_value;

		public function set_default_value($value)
		{
			$this->default_value = $value;
			return $this;
		}

		protected $force_value;

		public function force_value($value)
		{
			$this->force_value = $value;
			return $this;
		}

		public function name()			{ return $this->name; }
		public function set_name($name)
		{
			$this->name = $name;
			return $this;
		}

		public function title()			{ return $this->title; }
		public function info()			{ return $this->info; }
		public function set_info($info=null)
		{
			$this->info = $info;
			return $this;
		}

		public function message()		{ return $this->message; }
		public function set_message($message=null)
		{
			$this->message = $message;
			return $this;
		}

		public function add_message($message)
		{
			if($this->message)
				$this->message .= "\n<br />".$message;
			else
				$this->message = $message;
			return $this;
		}

		public function set_preinitialized()
		{
			$this->preinitialized = true;
			return $this;
		}

		public function set_title($title=null)
		{
			$this->title = $title?_T($title):null;
			return $this;
		}

		/**
		 * return a unique name for this FormItem
		 */
		public function id()
		{
			if($this->preinitialized)
				return $this->name;
			$id = $this->box_name.$this->dbobj->shortname($this->name);
			if(strlen($id)>64)
				$id = 'elem_'.dechex(crc32($id)).'_'.substr($id, -40);
			$id = str_replace('-', '_m', $id);
			return $id;
		}

		/**
		 * get some informations from the FormBox containing this
		 * FormItem
		 */
		public function set_form_box(&$box)
		{
			$this->box_name = $box->id().'_';
		}

		public function add_css_class($class)
		{
			$this->css_class .= ' '.$class;
			return $this;
		}

		public function set_css_class($class)
		{
			$this->css_class = $class;
			return $this;
		}

		public function css_class()
		{
			return $this->css_class;
		}

		/**
		 * get an array of html attributes
		 */
		public function attributes()
		{
			return $this->attributes;
		}

		public function set_attributes($attributes)
		{
			$this->attributes = array_merge($this->attributes, $attributes);
			return $this;
		}

		/**
		 * get the value from the user and store it in this FormItem
		 * and also in the corresponding field in the bound DBObject
		 */
		public function init_value()
		{
			$name = $this->name();
			$id = $this->id();
			$val = null;

			// handle one level of array brackets
			if(false!==($pos = strpos($id, '['))) {
				if($idx = intval(substr($id, $pos+1, -1))) {
					$array = null;
					$pname = substr($id, 0, $pos);
					if($val = getInput($pname)) {
						$array = $this->dbobj->get($pname);
						$array[$idx] = $val[$idx];
						$this->dbobj->set($pname, $array);
					} else
						$array = $this->dbobj->get($pname);
					if(!$array) {
						if($this->default_value)
							$array = $this->default_value;
						else
							$array = array();
					}

					$this->set_value($array[$idx]);
				}
			} else {
				if(($val = getInput($this->id()))!==null) {
					if(is_array($val))
						$this->dbobj->set($name, $val);
					else
						$this->dbobj->set($name, stripslashes($val));
				} else if($this->dbobj->get($name)===null)
					$this->dbobj->set($name, $this->default_value);
			}

			if($this->force_value!==null)
				$this->dbobj->set($name, $this->force_value);
		}

		/**
		 * add a FormItem validation rule
		 */
		public function add_rule(FormItemRule $rule)
		{
			$rule->set_form_item($this);
			$this->rules[] = $rule;
			return $this;
		}

		public function &rules()
		{
			return $this->rules;
		}

		public function add_behavior($behavior)
		{
			$behavior->set_form_item($this);
			$this->behaviors[] = $behavior;
			return $this;
		}

		public function is_valid()
		{
			if($this->valid!==null)
				return $this->valid;

			$this->valid = true;
			foreach($this->rules as &$rule) {
				if(!$rule->is_valid($this)) {
					$this->valid = false;
					break;
				}
			}
			return $this->valid;
		}

		public function set_valid($valid=null)
		{
			$this->valid = $valid;
		}

		public function accept($renderer)
		{
			$renderer->visit($this);
		}

		/**
		 * Add static javascript fragments specific to this FormItem
		 */
		public function add_javascript($js)
		{
			$this->javascript .= $js;
			return $this;
		}

		public function javascript()
		{
			if(!count($this->behaviors))
				return $this->javascript;
			$behavior_js = '';
			$behavior_init_js = '';
			foreach($this->behaviors as &$behavior) {
				list($init,$js) = $behavior->javascript();

				$behavior_js .= $js;
				$behavior_init_js .= $init."\n\t";

			}
			return $this->javascript.<<<EOD
$behavior_js

$(function(){
	$behavior_init_js
});

EOD;
		}

		public function set_traits()
		{
			$traits = $this->dbobj->traits();
			if(isset($traits[$name = $this->name()][0])) {
				switch($traits[$name][0]) {
					case 'unique':
						$this->add_rule(new UniqueRule());
				}
			}
		}
	}

	/**
	 * base class for several simple input fields
	 */
	abstract class SimpleInput extends FormItem {
		protected $type = '#INVALID';
		public function type()
		{
			return $this->type;
		}
	}

	class TextInput extends SimpleInput {
		protected $type = 'text';
		protected $css_class = 'sf-textinput';
		protected $format;

		public function set_format($format)
		{
			$this->format = $format;
			return $this;
		}

		public function format()
		{
			return $this->format;
		}
	}

	class SpinButton extends SimpleInput {
		protected $type = 'text';
		protected $css_class = 'sf-spinbutton';
		protected $range = array();

		public function __construct($name=null)
		{
			parent::__construct($name);
			Swisdk::needs_library('jquery_spinbutton');
		}

		public function set_range($min, $max, $step=1)
		{
			$this->range = func_get_args();
			return $this;
		}

		public function range()
		{
			return $this->range;
		}
	}

	/**
	 * hidden fields get special treatment (see also FormBox::html())
	 */
	class HiddenInput extends TextInput {
		protected $type = 'hidden';
	}

	class HiddenArrayInput extends HiddenInput {
	}

	class PasswordInput extends SimpleInput {
		protected $type = 'password';
		protected $css_class = 'sf-spinbutton';
	}

	/**
	 * you will get a filename relative to CACHE_ROOT.'upload/'. If you want
	 * to store the file permanently, you have to move it somewhere else
	 * yourself!
	 */
	class FileUpload extends SimpleInput {
		protected $type = 'file';
		protected $css_class = 'sf-spinbutton';

		protected $files_data;
		protected $no_upload = true;

		public function init_value()
		{
			$name = $this->id();
			if(isset($_FILES[$name])
					&& ($this->files_data = $_FILES[$name])
					&& $this->check_upload()) {
				$fname = uniquifyFilename(preg_replace('/[^A-Za-z0-9\.-_]+/', '_',
					$this->files_data['name']));

				$this->files_data['path'] = CACHE_ROOT.'upload/'.$fname;
				Swisdk::require_data_directory(CACHE_ROOT.'upload');
				if(move_uploaded_file($this->files_data['tmp_name'],
						$this->files_data['path'])) {
					$this->dbobj->set($this->name(), $fname);
					$this->no_upload = false;
					$this->files_data['cache_file'] = $fname;
				}
			}
		}

		protected function check_upload()
		{
			if(!$this->files_data['size'])
				return false;
			switch($this->files_data['error']) {
				case UPLOAD_ERR_OK:
					break;
				case UPLOAD_ERR_INI_SIZE:  // upload_max_filesize in php.ini
				case UPLOAD_ERR_FORM_SIZE: // MAX_FILE_SIZE hidden input field
					$this->add_message(
						_T('The uploaded file exceeds the allowed filesize'));
					break;
				case UPLOAD_ERR_PARTIAL:
					$this->add_message(
						_T('The uploaded file was only partially uploaded'));
					break;
				case UPLOAD_ERR_NO_FILE:
					$this->add_message(
						_T('No file was uploaded'));
				case UPLOAD_ERR_NO_TMP_DIR:
					SwisdkError::handle(new FatalError(
						_T('FileUpload: Missing a temporary folder')));
					break;
				case UPLOAD_ERR_CANT_WRITE:
					SwisdkError::handle(new FatalError(
						_T('FileUpload: Failed to write file to disk')));
					break;
			}

			return true;
		}

		/**
		 * these functions should be used by FormItemRules to validate
		 * the uploaded files
		 */
		public function files_data()
		{
			return $this->files_data;
		}

		public function no_upload()
		{
			return $this->no_upload;
		}

		/**
		 * this function should be called inside the FormItemRule if
		 * the upload does not validate
		 */
		public function unlink_cachefile()
		{
			@unlink($this->files_data['path']);
		}

		public function __destruct()
		{
			$this->unlink_cachefile();
		}
	}

	class DBFileUpload extends FileUpload {
		protected $current_value_field = null;
		protected $delete_file = false;
		protected $download_controller = '/download';

		public function init_value()
		{
			parent::init_value();
			$this->current_value = $this->dbobj->get($this->name().'_name');
			if(!$this->no_upload) {
				$name = $this->name();
				$this->dbobj[$name.'_file'] = $this->dbobj[$name];
				$this->dbobj[$name.'_name'] = $this->files_data['name'];
				$this->dbobj[$name.'_mimetype'] = $this->files_data['type'];
				$this->dbobj[$name.'_size'] = $this->files_data['size'];
				unset($this->dbobj[$name]);
			}

			// automatically call $this->store_file() while
			// storing the DBObject. Magic!
			$this->dbobj->listener_add('pre-store', array($this, 'store_file'));

			if(getInput($this->id().'__delete'))
				$this->delete_file = true;
		}

		public function store_file($dbobj)
		{
			if($this->delete_file) {
				$name = $this->name();
				@unlink(DATA_ROOT.'upload/'.$dbobj->get($name.'_file'));
				foreach(array('_file', '_name', '_mimetype', '_size') as $t)
					$dbobj->set($name.$t, '');
			}

			if($this->valid && !$this->no_upload) {
				Swisdk::require_data_directory('upload');
				copy($this->files_data['path'],
					DATA_ROOT.'upload/'.$this->files_data['cache_file']);
			}
		}

		public function current_value()
		{
			if(!$this->current_value_field)
				return $this->dbobj->get($this->name().'_name');
			return $this->dbobj->get($this->current_value_field);
		}

		public function set_current_value_field($field)
		{
			return $this->current_value_field = $field;
			return $this;
		}

		public function download_controller()
		{
			return $this->download_controller;
		}

		public function set_download_controller($ctrl)
		{
			$this->download_controller = $ctrl;
			return $this;
		}
	}

	/**
	 * CheckboxInput uses another hidden input field to verify if
	 * the Checkbox was submitted at all.
	 */
	class CheckboxInput extends FormItem {
		protected $type = 'checkbox';

		public function init_value()
		{
			$name = $this->name();
			$id = $this->id();

			if(isset($_POST[$id.'__check'])) {
				if(getInput($id))
					$this->dbobj->set($name, 1);
				else
					$this->dbobj->set($name, 0);
			}

			if($this->dbobj->get($name)===null)
				$this->dbobj->set($name, $this->default_value);

			if($this->force_value!==null)
				$this->dbobj->set($name, $this->force_value);
		}
	}

	/**
	 * true, false and i-don't know!
	 *
	 * (or, more accurately, checked, unchecked and mixed)
	 */
	class TristateInput extends FormItem {
	}

	class Textarea extends FormItem {
		protected $css_class = 'sf-textarea';
		protected $auto_xss_protection = true;
		protected $xss_protection = true;

		public function auto_xss_protection()
		{
			return $this->auto_xss_protection;
		}

		public function xss_protection()
		{
			return $this->xss_protection;
		}

		public function set_auto_xss_protection($enabled=true, $default_enabled=true)
		{
			$this->auto_xss_protection = $enabled;
			$this->xss_protection = $default_enabled;
			return $this;
		}

		public function init_value()
		{
			$name = $this->name();
			$id = $this->id();

			if(($v = getInputRaw($id))!==null) {
				if($this->auto_xss_protection
						|| getInput($id.'__xss'))
					$this->dbobj->set($name, cleanInput($v));
				else
					$this->dbobj->set($name, cleanInput($v, false));
			}

			if($this->dbobj->get($name)===null)
				$this->dbobj->set($name, $this->default_value);

			if($this->force_value!==null)
				$this->dbobj->set($name, $this->force_value);
		}
	}

	class WymEditor extends Textarea {
		protected $css_class = 'sf-wymeditor';

		public function __construct($name=null)
		{
			parent::__construct($name);
			Swisdk::needs_library('jquery_wymeditor');
		}
	}

	/**
	 * Textarea with all the Wysiwyg-Bling!
	 */
	class RichTextarea extends Textarea {
		protected $css_class = 'sf-richtextarea';
		protected $type = 'Standard';

		public function __construct($name=null)
		{
			parent::__construct($name);
			Swisdk::needs_library('fckeditor');
		}

		public function init_value()
		{
			parent::init_value();
			$value = $this->value();
			$new = preg_replace('/^<!-- (NO)?RT -->/u', '', $value);
			if(strpos($value, '<!-- NORT -->')===0)
				$this->set_value($new);
			else
				$this->set_value('<!-- RT -->'.$new);

			if($this->force_value!==null)
				$this->dbobj->set($name, $this->force_value);
		}

		public function type()
		{
			return $this->type;
		}

		public function set_type($type)
		{
			$this->type = $type;
			return $this;
		}
	}

	/**
	 * base class for all FormItems which offer a choice between several items
	 */
	class SelectionFormItem extends FormItem {
		protected $css_class = 'sf-selection';

		public function set_items($items)
		{
			if($items instanceof DBOContainer) {
				$this->items = array();
				foreach($items as $item)
					$this->items[$item->id()] = $item->title();
			} else
				$this->items = $items;
			return $this;
		}

		public function items()
		{
			return $this->items;
		}

		public function add_null_item($title=null)
		{
			if(!$title)
				$title = _T('select');

			$items = array(0 => '-- '.$title.' --');
			foreach($this->items as $k => $v)
				$items[$k] = $v;
			$this->items = $items;
			return $this;
		}

		public function sort_items_by_key()
		{
			ksort($this->items);
			return $this;
		}

		public function sort_items_by_title()
		{
			natcasesort($this->items);
			return $this;
		}

		public function remove_visitor()
		{
			if(isset($this->items[SWISDK2_VISITOR]))
				unset($this->items[SWISDK2_VISITOR]);
			return $this;
		}

		protected $items=array();
	}

	class DropdownInput extends SelectionFormItem {
		public function init_value()
		{
			// only allow values from the items array
			$name = $this->name();
			parent::init_value();
			if(!isset($this->items[$this->value()])) {
				$this->dbobj->set($name, $this->default_value);
			}
		}
	}

	class TimeInput extends DropdownInput {
		protected $ranges = array();

		public function __construct($name=null)
		{
			parent::__construct($name);
		}

		public function init_value()
		{
			$name = $this->name();
			$id = $this->id();

			if($v = getInput($id))
				$this->dbobj->set($name, cleanInput($v, false));

			if($this->dbobj->get($name)===null)
				$this->dbobj->set($name, $this->default_value);

			if($this->force_value!==null)
				$this->dbobj->set($name, $this->force_value);

			if(($value = $this->dbobj->get($name))>100000)
				$this->dbobj->set($name, $value-86400-82800);
		}

		public function items()
		{
			$base = 86400+82800;
			$items = array();

			if(count($this->ranges)) {
				foreach($this->ranges as $range) {
					list($a,$b,$c) = $range;
					for($i=$a; $i<=$b; $i+=$c)
						$items[$i] = strftime('%H:%M', $i);
				}
			} else {
				for($i=$base; $i<$base+86400; $i+=900)
					$items[$i] = strftime('%H:%M', $i);
			}

			return $items;
		}

		public function add_range($start, $end, $step=900)
		{
			$this->ranges[] = array($start, $end, $step);
			return $this;
		}

		public function set_ranges($ranges)
		{
			$this->ranges = $ranges;
			return $this;
		}

		public function ranges()
		{
			return $this->ranges;
		}
	}

	class RadioButtons extends DropdownInput {
		protected $css_class = 'sf-radiobuttons';
	}

	class ComboBox extends SelectionFormItem {
		protected $attributes = array('style' => 'width:250px');
		protected $class;

		public function __construct($class)
		{
			$this->class = $class;
		}

		public function init_value()
		{
			parent::init_value();
			$name = $this->name();
			$value = $this->value();
			if(!is_numeric($value)) {
				$dbo = DBObject::create($this->class);
				if(DBObject::find($this->class, array(
						$dbo->name('title').'=' => $value)))
					return;
				$dbo->title = $value;
				$dbo->store();
				$this->dbobj->set($name, $dbo->id());
			}
		}
	}

	class Multiselect extends SelectionFormItem {
		public function value()
		{
			$val = parent::value();
			if(!$val)
				return array();
			return $val;
		}

		public function init_value()
		{
			$name = $this->name();
			$id = $this->id();

			$values = array_keys($this->items);

			if(s_test($_POST, $this->id().'__check')) {
				if(($val = getInput($id)) && is_array($val))
					$this->dbobj->set($name,
						array_intersect($values, $val));
				else
					$this->dbobj->set($name, array());
			} else if(!$this->dbobj->get($name)
					&& is_array($this->default_value))
				$this->dbobj->set($name, $this->default_value);

			if($this->force_value!==null)
				$this->dbobj->set($name, $this->force_value);
		}
	}

	class ThreewayInput extends FormItem {
		protected $relation;

		public function __construct($name=null)
		{
			parent::__construct($name);
			Swisdk::needs_library('jquery');
		}

		public function init_value()
		{
			parent::init_value();
			$id = $this->id();
			if(isset($_POST[$id.'__check'])
					&& !getInput($id)) {
				$this->set_value(array());
			}
		}

		public function set_form_box(&$box)
		{
			parent::set_form_box($box);
			$rels = $box->dbobj()->relations();
			$this->relation = $rels[$this->name()];
		}

		public function _class()
		{
			return $this->relation['foreign_class'];
		}

		public function second()
		{
			return DBOContainer::find($this->relation['foreign_class'])
				->collect('id', 'title');
		}

		public function choices()
		{
			return DBOContainer::find($this->relation['choices_class'])
				->collect('id', 'title');
		}
	}

	class InlineEditor extends FormItem {
		protected $class;
		protected $fields;

		public function __construct($class, $fields)
		{
			$this->class = $class;
			$this->fields = $fields;
		}

		public function init_value()
		{
			$dboc = $this->dbobj->related($this->class);
			parent::init_value();
			$values = $this->value();
			if(is_array(reset($values))) {
				$ids = array_flip($dboc->ids());

				foreach($values as $key => $value) {
					$dbo = null;
					$add = null;
					if(is_numeric($key)) {
						$dbo = $dboc[$key];
						unset($ids[$key]);
					} else {
						$dbo = DBObject::create($this->class);
						$add = false;
					}

					foreach($this->fields as $f) {
						if($value[$f])
							$add = true;
						$dbo->$f = $value[$f];
					}

					if($add===true)
						$dboc->add($dbo);
				}

				$delete_dboc = DBOContainer::find_by_id($this->class,
					array_flip($ids));
				$this->dbobj->listener_add('store', array(
					&$dboc, 'set_owner'));
				$this->dbobj->listener_add('store', array(
					&$dboc, 'store'));
				$this->dbobj->listener_add('store', array(
					&$delete_dboc, 'delete'));
			}
		}

		public function _class()
		{
			return $this->class;
		}

		public function fields()
		{
			return $this->fields;
		}
	}

	class TagInput extends TextInput {
		protected $css_class = 'sf-textinput';
		protected $tag_formitems = array();

		public function init_value()
		{
			parent::init_value();
			$value = $this->value();
			if(!is_array($value)) {
				$_v = s_array($value);
				$value = array();
				foreach($_v as $v)
					if($v)
						$value[] = $v;
				$this->set_value($value);
			}
		}

		public function add_tag_formitems()
		{
			$args = func_get_args();
			$value = $this->value();
			foreach($args as $item) {
				$value = array_merge($value, $item->value());
				$this->tag_formitems[] = $item;
			}

			$value = array_unique($value);

			$this->set_value($value);
		}

		public function tag_formitems()
		{
			return $this->tag_formitems();
		}

		public function tag_string()
		{
			$value = $this->value();
			$other_values = array();
			foreach($this->tag_formitems as &$item) {
				$other_values = array_merge($other_values, $item->value());
			}

			$other_values = array_unique($other_values);

			return implode(', ', array_diff($value, $other_values));
		}
	}

	/**
	 * base class for all FormItems which want to occupy a whole
	 * line (no title, no message)
	 */
	class FormBar extends FormItem {
	}

	class FormButton extends FormBar {
		protected $value;
		protected $caption = 'Submit';

		public function __construct($name=null)
		{
			if($name)
				$this->name = $name;

			$this->caption = _T($this->caption);
		}

		public function caption()
		{
			return $this->caption;
		}

		public function set_caption($caption)
		{
			$this->caption = $caption;
			return $this;
		}

		public function init_value()
		{
		}

		public function value()
		{
			return $this->value;
		}

		public function set_value($value)
		{
			$this->value = $value;
			return $this;
		}
	}

	class SubmitButton extends FormButton {
	}

	class ResetButton extends FormButton {
		protected $caption = 'Reset';
	}

	class CancelButton extends FormButton {
		protected $caption = 'Cancel';
		protected $name = 'sf_button_cancel';
	}

	class DateInput extends FormItem {
		protected $time = true;
		protected $actions = array();
		protected $properties = array();

		public function __construct($name=null)
		{
			parent::__construct($name);
			Swisdk::needs_library('jquery_datepicker');
		}

		public function init_value()
		{
			parent::init_value();

			if(!is_numeric($val = $this->value())) {
				$new = strtotime($val);
				if($new) {
					$id = $this->id();
					$h = getInput($id.'__hour');
					$m = getInput($id.'__minute');
					$new += $h*3600 + $m*60;
					$this->set_value($new);
				}
			}
		}

		public function disable_time($disable=true)
		{
			$this->time = !$disable;
			return $this;
		}

		public function time()
		{
			return $this->time;
		}

		public function add_action($event, $action)
		{
			if(!isset($this->actions[$event]))
				$this->actions[$event] = '';
			$this->actions[$event] .= $action."\n";
		}

		public function actions()
		{
			return $this->actions;
		}

		public function set_property($prop, $value)
		{
			$this->properties[$prop] = $value;
			return $this;
		}

		public function properties()
		{
			return $this->properties;
		}
	}

	class CaptchaInput extends TextInput {
		public function captcha_html()
		{
			return '<img src="'
				.Swisdk::config_value('runtime.webroot.data', '/data').'/captcha/'
				.$this->captcha_id.$this->token.'.png" />';
		}

		public function validation_cb($item)
		{
			if(($v=$this->value())
					&& !strcasecmp($v, $this->captcha_code)) {
				return true;
			}

			return false;
		}

		public function generate_captcha($guard_token)
		{
			$this->captcha_id = $guard_token;

			if(isset($_SESSION['swisdk2']['captcha'][$this->captcha_id]['code'])) {
				$this->captcha_code =
					$_SESSION['swisdk2']['captcha'][$this->captcha_id]['code'];
				$this->token =
					$_SESSION['swisdk2']['captcha'][$this->captcha_id]['token'];
				return;
			}

			require_once SWISDK_ROOT.'lib/contrib/captcha.class.php';
			Swisdk::require_htdocs_data_directory('captcha');
			Swisdk::clean_data_directory(HTDOCS_DATA_ROOT.'captcha', 86400);

			$this->token = '_'.uniqid();

			$c = new Captcha(4);
			$this->captcha_code = $c->Generate(HTDOCS_DATA_ROOT
				.'captcha/'.$this->captcha_id.$this->token.'.png');

			$_SESSION['swisdk2']['captcha'][$this->captcha_id]['code'] =
				$this->captcha_code;
			$_SESSION['swisdk2']['captcha'][$this->captcha_id]['token'] =
				$this->token;
		}

		protected $captcha_id;
		protected $captcha_code;
		protected $token;
	}

	class PickerBase extends FormItem {
		public function __construct($class=null)
		{
			if($class)
				$this->picker_class = $class;
		}

		public function display_string()
		{
			if($dbo = DBObject::find($this->picker_class, $this->value()))
				return $dbo->title();
			return '&hellip;';
		}

		public function popup_url()
		{
			return sprintf('/__swisdk__/picker?element=%s&class=%s&%s',
				$this->id(), $this->picker_class,
				http_build_query(array('params' => $this->params)));
		}

		public function behavior_functions()
		{
			return $this->funcs;
		}

		public function add_params($params)
		{
			$this->params = array_merge_recursive($this->params, $params);
			return $this;
		}

		public function javascript()
		{
			if(!count($this->behaviors))
				return $this->javascript;
			$behavior_js = '';
			foreach($this->behaviors as &$behavior) {
				list($init,$js,$func) = $behavior->javascript();

				$behavior_js .= $js;
				$this->funcs .= 'window.setTimeout('.$func.", 0);\n\t";
			}
			return $this->javascript.<<<EOD
$behavior_js

EOD;
		}

		protected $picker_class;
		protected $funcs;
		protected $params = array();
	}

	class ListSelector extends SelectionFormItem {
		public function __construct($class=null)
		{
			if($class)
				$this->class = $class;
		}

		public function _class()
		{
			return $this->class;
		}

		protected $class;
	}

	class GroupItem extends FormItem {
		protected $box = null;
		protected $separator = ' ';

		public function box()
		{
			if(!$this->box) {
				$this->box = new FormBox();
				$this->box->set_name($this->name());
				$this->box->bind($this->dbobj);
			}

			return $this->box;
		}

		public function add()
		{
			$args = func_get_args();
			$box = $this->box();
			return call_user_func_array(array(
				$box, 'add'), $args);
		}

		public function add_auto()
		{
			$args = func_get_args();
			$box = $this->box();
			return call_user_func_array(array(
				$box, 'add_auto'), $args);
		}

		public function init_value()
		{
			$this->box()->init_value();
		}

		public function set_separator($separator)
		{
			$this->separator = $separator;
			return $this;
		}

		public function separator()
		{
			return $this->separator;
		}
	}

	class GroupItemBar extends GroupItem {
	}

	class PreviewFormItem extends FormItem {
		public function init_value()
		{
		}

		public function html()
		{
			if(!$this->dbobj->id())
				return;

			return '<iframe src="'
				.Swisdk::load_instance('UrlGenerator')->generate_url($this->dbobj)
				.'" style="width:900px;height:700px;background:#fff"></iframe>';
		}
	}

	class InfoItem extends FormItem {
		protected $info_text;

		public function __construct($info_text=null)
		{
			$this->info_text = $info_text;
		}

		public function set_html($html)
		{
			$this->info_text = $html;
		}

		public function html()
		{
			return $this->info_text;
		}
	}

?>
