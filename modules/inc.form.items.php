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
		 * the value (ooh!)
		 */
		protected $value;

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
		 * additional html attributes
		 */
		protected $attributes = array();

		/**
		 * This gets prepended to the name of every FormItem so that
		 * every FormItem's name is unique on the page.
		 */
		protected $box_name = null;

		/**
		 * is this FormItem part of a Form/FormBox with unique-ness
		 * enabled?
		 */
		protected $unique = false;

		/**
		 * has this element beed added to the FormBox with
		 * add_initialized_obj ? If yes, do not mangle the name even
		 * if $unique is true
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

		public function __construct($name=null)
		{
			if($name)
				$this->name = $name;
		}

		/**
		 * accessors and mutators
		 */
		public function value()			{ return $this->value; }
		public function set_value($value)	{ $this->value = $value; }
		public function name()			{ return $this->name; }
		public function set_name($name)		{ $this->name = $name; }
		public function title()			{ return $this->title; }
		public function info()			{ return $this->info; }
		public function set_info($info)		{ $this->info = $info; }
		public function message()		{ return $this->message; }
		public function set_message($message)	{ $this->message = $message; }
		public function add_message($message)
		{
			if($this->message)
				$this->message .= "\n<br />".$message;
			else
				$this->message = $message;
		}
		public function enable_unique() { $this->unique = true; }
		public function set_preinitialized() { $this->preinitialized = true; }
		public function set_title($title)
		{
			$this->title = dgettext('swisdk', $title);
		}

		/**
		 * return a unique name for this FormItem
		 */
		public function id() {
			return ((!$this->preinitialized&&$this->unique)?
				$this->box_name:'').$this->name;
		}

		public function iname()
		{
			return $this->id();
		}

		/**
		 * get some informations from the FormBox containing this
		 * FormItem
		 */
		public function set_form_box(&$box)
		{
			$this->box_name = $box->id().'_';
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
		}

		/**
		 * helper function which composes a html-compatible attribute
		 * string
		 */
		public function attribute_html()
		{
			$html = ' ';
			foreach($this->attributes as $k => $v)
				if($v)
					$html .= $k.'="'.htmlspecialchars($v).'" ';
			return $html;
		}

		/**
		 * get the value from the user and store it in this FormItem
		 * and also in the corresponding field in the bound DBObject
		 */
		public function init_value($dbobj)
		{
			$name = $this->name();
			$iname = $this->iname();
			$val = null;

			// handle one level of array brackets
			if(false!==($pos = strpos($iname, '['))) {
				if($idx = intval(substr($iname, $pos+1, -1))) {
					$array = null;
					$pname = substr($iname, 0, $pos);
					if($val = getInput($pname)) {
						$array = $dbobj->get($pname);
						$array[$idx] = $val[$idx];
						$dbobj->set($pname, $array);
					} else
						$array = $dbobj->get($pname);
					if(!$array)
						$array = array();

					$this->set_value($array[$idx]);
				}
			} else {
				if(($val = getInput($this->iname()))!==null) {
					if(is_array($val))
						$dbobj->set($name, $val);
					else
						$dbobj->set($name, stripslashes($val));
				}

				$this->set_value($dbobj->get($name));
			}
		}

		/**
		 * refresh the FormItem's value (read value from DBObject)
		 */
		public function refresh($dbobj)
		{
			$this->set_value($dbobj->get($this->name()));
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
				return $this->is_valid();

			$this->valid = true;
			foreach($this->rules as &$rule)
				if(!$rule->is_valid($this))
					$this->valid = false;
			return $this->valid;
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
			$iname = $this->iname();
			return $this->javascript.<<<EOD
$behavior_js

function init_$iname()
{
	$behavior_init_js
}
add_event(window, 'load', init_$iname);

EOD;
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
		protected $attributes = array('size' => 60);
	}

	/**
	 * hidden fields get special treatment (see also FormBox::html())
	 */
	class HiddenInput extends TextInput {
		protected $type = 'hidden';
	}

	class PasswordInput extends SimpleInput {
		protected $type = 'password';
		protected $attributes = array('size' => 60);
	}

	/**
	 * you will get a filename relative to CACHE_ROOT.'upload/'. If you want
	 * to store the file permanently, you have to move it somewhere else
	 * yourself!
	 */
	class FileUpload extends SimpleInput {
		protected $type = 'file';
		protected $attributes = array('size' => 60);

		protected $files_data;
		protected $no_upload = true;

		public function init_value($dbobj)
		{
			$name = $this->iname();
			if(isset($_FILES[$name])
					&& ($this->files_data = $_FILES[$name])
					&& $this->check_upload()) {
				$fname = preg_replace('/[^A-Za-z0-9\.-_]+/', '_',
					$this->files_data['name']);
				$pos = strrpos($fname, '.');
				if($pos===false)
					$fname .= uniqid();
				else
					$fname = substr($fname, 0, $pos).'_'
						.uniqid().substr($fname, $pos);

				$this->files_data['path'] = CACHE_ROOT.'upload/'.$fname;
				Swisdk::require_data_directory(CACHE_ROOT.'upload');
				if(move_uploaded_file($this->files_data['tmp_name'],
						$this->files_data['path'])) {
					$dbobj->set($this->name(), $fname);
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
						dgettext('swisdk', 'The uploaded file exceeds the allowed filesize'));
					break;
				case UPLOAD_ERR_PARTIAL:
					$this->add_message(
						dgettext('swisdk', 'The uploaded file was only partially uploaded'));
					break;
				case UPLOAD_ERR_NO_FILE:
					$this->add_message(
						dgettext('swisdk', 'No file was uploaded'));
				case UPLOAD_ERR_NO_TMP_DIR:
					SwisdkError::handle(new FatalError(
						dgettext('swisdk', 'FileUpload: Missing a temporary folder')));
					break;
				case UPLOAD_ERR_CANT_WRITE:
					SwisdkError::handle(new FatalError(
						dgettext('swisdk', 'FileUpload: Failed to write file to disk')));
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
		protected $current_value = null;

		public function init_value($dbobj)
		{
			parent::init_value($dbobj);
			$this->current_value = $dbobj->get($this->name().'_name');
			if(!$this->no_upload) {
				$name = $this->name();
				$dbobj[$name.'_file'] = $dbobj[$name];
				$dbobj[$name.'_name'] = $this->files_data['name'];
				$dbobj[$name.'_mimetype'] = $this->files_data['type'];
				$dbobj[$name.'_size'] = $this->files_data['size'];
				unset($dbobj[$name]);

				// automatically call $this->store_file() while
				// storing the DBObject. Magic!
				$dbobj->listener_add('store', array($this, 'store_file'));
			}
		}

		public function store_file($dbobj)
		{
			if($this->valid && !$this->no_upload) {
				Swisdk::require_data_directory('upload');
				copy($this->files_data['path'],
					DATA_ROOT.'upload/'.$this->files_data['cache_file']);
			}
		}

		public function current_value()
		{
			return $this->current_value;
		}
	}

	/**
	 * CheckboxInput uses another hidden input field to verify if
	 * the Checkbox was submitted at all.
	 */
	class CheckboxInput extends FormItem {
		protected $type = 'checkbox';

		public function init_value($dbobj)
		{
			$name = $this->iname();

			if(isset($_POST['__check_'.$name])) {
				if(getInput($name))
					$dbobj->set($name, 1);
				else
					$dbobj->set($name, 0);
			}

			$this->set_value($dbobj->get($name));
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
		protected $attributes = array('rows' => 12, 'cols' => 60);
	}

	/**
	 * Textarea with all the Wysiwyg-Bling!
	 */
	class RichTextarea extends FormItem {
		protected $attributes = array('style' => 'width:800px;height:300px;');
	}

	/**
	 * base class for all FormItems which offer a choice between several items
	 */
	class SelectionFormItem extends FormItem {
		public function set_items($items)
		{
			if($items instanceof DBOContainer) {
				$this->items = array();
				foreach($items as $item)
					$this->items[$item->id()] = $item->title();
			} else
				$this->items = $items;
		}

		public function items()
		{
			return $this->items;
		}

		public function add_null_item()
		{
			$this->items = array_merge(array(0 => '-- '.dgettext('swisdk', 'select').' --'),
				$this->items);
		}

		protected $items=array();
	}

	class DropdownInput extends SelectionFormItem {
		public function init_value($dbobj)
		{
			// only allow values from the items array
			$name = $this->name();
			$value = $dbobj->get($name);
			parent::init_value($dbobj);
			if(!isset($this->items[$this->value()])) {
				$dbobj->set($name, $value);
				$this->refresh($dbobj);
			}
		}
	}

	class ComboBox extends SelectionFormItem {
		protected $attributes = array('style' => 'width:250px');
	}

	class Multiselect extends SelectionFormItem {
		public function value()
		{
			$val = parent::value();
			if(!$val)
				return array();
			return $val;
		}

		public function init_value($dbobj)
		{
			parent::init_value($dbobj);
			$name = $this->name();
			$newvalue = $dbobj->get($name);
			if(is_array($newvalue)) {
				$dbobj->set($name, array_intersect($newvalue,
					array_keys($this->items)));
				$this->refresh($dbobj);
			}
		}
	}

	class ThreewayInput extends FormItem {
		protected $relation;

		public function set_form_box(&$box)
		{
			parent::set_form_box($box);
			$rels = $box->dbobj()->relations();
			$this->relation = $rels[$this->name()];
		}

		public function second()
		{
			return DBOContainer::find($this->relation['class'])
				->collect('id', 'title');
		}

		public function choices()
		{
			return DBOContainer::find($this->relation['choices'])
				->collect('id', 'title');
		}
	}

	class TagInput extends TextInput {
		public function init_value($dbobj)
		{
			parent::init_value($dbobj);
			if(is_array($this->value)) {
				$this->real_value = $this->value;
				$this->value = implode(', ', $this->real_value);
			} else {
				$this->real_value = array_map('trim', explode(',', $this->value));
				$dbobj->set($this->name(), $this->real_value);
			}
		}

		protected $real_value;
	}

	/**
	 * base class for all FormItems which want to occupy a whole
	 * line (no title, no message)
	 */
	class FormBar extends FormItem {
	}

	class SubmitButton extends FormBar {
		public function init_value($dbobj)
		{
			$this->value = dgettext('swisdk', 'Submit');
		}
	}

	class DateInput extends FormItem {
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
			return sprintf('/picker?element=%s&class=%s&%s',
				$this->iname(), $this->picker_class,
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
			$iname = $this->iname();
			return $this->javascript.<<<EOD
$behavior_js

EOD;
		}

		protected $picker_class;
		protected $funcs;
		protected $params = array();
	}

?>
