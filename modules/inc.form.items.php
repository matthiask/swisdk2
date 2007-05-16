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

		public function set_default_value($value)
		{
			if(!$this->dbobj->get($this->name))
				$this->dbobj->set($this->name, $value);
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
			$this->title = $title?dgettext('swisdk', $title):null;
			return $this;
		}

		/**
		 * return a unique name for this FormItem
		 */
		public function id()
		{
			return $this->preinitialized?$this->name:
				$this->box_name.$this->name;
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
			return $this;
		}

		/**
		 * get the value from the user and store it in this FormItem
		 * and also in the corresponding field in the bound DBObject
		 */
		public function init_value($dbobj)
		{
			$this->dbobj = $dbobj;

			$name = $this->name();
			$id = $this->id();
			$val = null;

			// handle one level of array brackets
			if(false!==($pos = strpos($id, '['))) {
				if($idx = intval(substr($id, $pos+1, -1))) {
					$array = null;
					$pname = substr($id, 0, $pos);
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
				if(($val = getInput($this->id()))!==null) {
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
		public function refresh($dbobj=null)
		{
			if($dbobj)
				$this->dbobj = $dbobj;
			$this->set_value($this->dbobj->get($this->name));
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
			$id = $this->id();
			return $this->javascript.<<<EOD
$behavior_js

function init_$id()
{
	$behavior_init_js
}
add_event(window, 'load', init_$id);

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
		protected $attributes = array('class' => 'sf-textinput');
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
		protected $attributes = array('class' => 'sf-textinput');
	}

	/**
	 * you will get a filename relative to CACHE_ROOT.'upload/'. If you want
	 * to store the file permanently, you have to move it somewhere else
	 * yourself!
	 */
	class FileUpload extends SimpleInput {
		protected $type = 'file';
		protected $attributes = array('class' => 'sf-textinput');

		protected $files_data;
		protected $no_upload = true;

		public function init_value($dbobj)
		{
			$this->dbobj = $dbobj;

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
		protected $delete_file = false;

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
			}

			// automatically call $this->store_file() while
			// storing the DBObject. Magic!
			$dbobj->listener_add('pre-store', array($this, 'store_file'));

			if(getInput($this->id().'___delete'))
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
			$this->dbobj = $dbobj;

			$name = $this->name();
			$id = $this->id();

			if(isset($_POST['__check_'.$id])) {
				if(getInput($id))
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
		protected $attributes = array('class' => 'sf-textarea');
		protected $auto_xss_protection = true;

		public function auto_xss_protection()
		{
			return $this->auto_xss_protection;
		}

		public function set_auto_xss_protection($enabled=true)
		{
			$this->auto_xss_protection = $enabled;
			// XXX hack! init_value should get called later (Form-level change)
			$this->init_value($this->dbobj);
			return $this;
		}

		public function init_value($dbobj)
		{
			$this->dbobj = $dbobj;

			$name = $this->name();
			$id = $this->id();

			if($v = getInputRaw($id)) {
				if($this->auto_xss_protection
						|| getInput($id.'__xss'))
					$dbobj->set($name, cleanInput($v));
				else
					$dbobj->set($name, cleanInput($v, false));
			}

			$this->set_value($dbobj->get($name));
		}
	}

	/**
	 * Textarea with all the Wysiwyg-Bling!
	 */
	class RichTextarea extends Textarea {
		protected $attributes = array('class' => 'sf-richtextarea');
		protected $type = 'Standard';

		public function init_value($dbobj)
		{
			parent::init_value($dbobj);
			$value = $this->value();
			$new = preg_replace('/^<!--.*-->/u', '', $value);
			if(strpos($value, '<!-- NORT -->')===0)
				$this->set_value($new);
			else
				$this->set_value('<!-- RT -->'.$new);
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
		protected $attributes = array('class' => 'sf-selection');

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

		public function add_null_item()
		{
			$items = array(0 => '-- '.dgettext('swisdk', 'select').' --');
			foreach($this->items as $k => $v)
				$items[$k] = $v;
			$this->items = $items;
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

	class RadioButtons extends DropdownInput {
	}

	class ComboBox extends SelectionFormItem {
		protected $attributes = array('style' => 'width:250px');
		protected $class;

		public function __construct($class)
		{
			$this->class = $class;
		}

		public function init_value($dbobj)
		{
			parent::init_value($dbobj);
			$name = $this->name();
			$value = $this->value();
			if(!is_numeric($value)) {
				$dbo = DBObject::create($this->class);
				if(DBObject::find($this->class, array(
						$dbo->name('title').'=' => $value)))
					return;
				$dbo->title = $value;
				$dbo->store();
				$dbobj->set($name, $dbo->id());
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

		public function init_value($dbobj)
		{
			parent::init_value($dbobj);
			$id = $this->id();
			if(isset($_POST['__check_'.$id])
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

		public function init_value($dbobj)
		{
			$dboc = $dbobj->related($this->class);
			parent::init_value($dbobj);
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
		protected $attributes = array('class' => 'sf-textinput');
		protected $tag_formitems = array();

		public function init_value($dbobj)
		{
			parent::init_value($dbobj);
			$value = $this->value();
			if(!is_array($value)) {
				$_v = array_map('trim', explode(',', $value));
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

	class SubmitButton extends FormBar {
		protected $value;

		public function init_value($dbobj)
		{
			$this->dbobj = $dbobj;

			$this->value = dgettext('swisdk', 'Submit');
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

	class ResetButton extends FormBar {
		protected $value;

		public function init_value($dbobj)
		{
			$this->dbobj = $dbobj;

			$this->value = dgettext('swisdk', 'Reset');
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

	class DateInput extends FormItem {
		protected $time = true;
		protected $actions = array();

		public function init_value($dbobj)
		{
			parent::init_value($dbobj);

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
	}

	class CaptchaInput extends TextInput {
		public function captcha_html()
		{
			return '<img src="'
				.Swisdk::config_value('runtime.webroot.data', '/data').'/captcha/'
				.$this->captcha_id.$this->token.'.png" /><br />';
		}

		public function validation_cb($item)
		{
			if(($v=$this->value())
					&& !strcasecmp($v, $this->captcha_code)) {
				return true;
			}

			return false;
		}

		public function generate_captcha()
		{
			$this->captcha_id = guardToken();

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

		public function init_value($dbobj)
		{
			$this->dbobj = $dbobj;
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
		public function init_value($dbobj)
		{
			$this->dbobj = $dbobj;
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

?>
