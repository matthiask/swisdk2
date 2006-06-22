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
		public function set_title($title)	{ $this->title = $title; } 
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
		public function set_default_value($value)
		{
			if($this->value===null)
				$this->value = $value;
		}


		/**
		 * return a unique name for this FormItem
		 */
		public function iname() {
			return ((!$this->preinitialized&&$this->unique)?
				$this->box_name:'').$this->name;
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
			$this->rules[] = $rule;
			return $this;
		}

		public function &rules()
		{
			return $this->rules;
		}

		public function is_valid()
		{
			$valid = true;
			foreach($this->rules as &$rule)
				if(!$rule->is_valid($this))
					$valid = false;
			return $valid;
		}

		public function accept($renderer)
		{
			$renderer->visit($this);
		}

		public function add_javascript($js)
		{
			$this->javascript .= $js;
		}

		public function javascript()
		{
			return $this->javascript;
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
	 * to store the file permanently, you have to move it to UPLOAD_ROOT
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
			if(isset($_FILES[$name])) {
				$this->files_data = $_FILES[$name];
				// TODO error checking
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
				}
			}
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
			$this->items = $items;
		}

		public function items()
		{
			return $this->items;
		}

		public function add_null_item()
		{
			$this->items = array_merge(array(0 => ' -- select -- '), $this->items);
		}

		protected $items=array();
	}

	class DropdownInput extends SelectionFormItem {
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
			if(!$dbobj->get($name))
				$dbobj->set($name, array());
		}
	}

	/**
	 * display all enum choices for a given SQL field
	 */
	class EnumMultiInput extends Multiselect {

		/**
		 * ATTENTION! $table _cannot_ be escaped
		 */
		public function __construct($table, $field)
		{
			$fs = DBObject::db_get_array('SHOW COLUMNS FROM '
				.$table, 'Field');
			$field = $fs[$field];
			$array = explode('\',\'', substr($field['Type'], 6,
				strlen($field['Type'])-8));
			$this->set_items(array_combine($array, $array));
		}
	}

	class EnumInput extends DropdownInput {

		/**
		 * ATTENTION! $table _cannot_ be escaped
		 */
		public function __construct($table, $field)
		{
			$fs = DBObject::db_get_array('SHOW COLUMNS FROM '
				.$table, 'Field');
			$field = $fs[$field];
			$array = explode('\',\'', substr($field['Type'], 6,
				strlen($field['Type'])-8));
			$this->set_items(array_combine($array, $array));
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

	/**
	 * base class for all FormItems which want to occupy a whole
	 * line (no title, no message)
	 */
	class FormBar extends FormItem {
	}

	class SubmitButton extends FormBar {
		public function init_value($dbobj)
		{
			// i have no value
		}
	}

	class DateInput extends FormItem {
	}

?>
