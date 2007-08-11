<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class FormRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
		}

		public function set_form(&$form)
		{
			$this->form =& $form;
		}

		public function is_valid()
		{
			if($this->is_valid_impl())
				return true;
			$this->form->add_message($this->message);
			return false;
		}

		protected function is_valid_impl()
		{
			return false;
		}

		public function validation_javascript()
		{
			if(!list($rulefunc, $rulejs) = $this->validation_js_impl())
				return null;
			$id = $this->form->id();
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			$message = '<img style="position:relative;top:3px"'
				.' "src="'.$prefix.'/icons/error.png" /> '.$this->message;
			return array(
				"swisdk_form_do_validate($rulefunc, '$id', '$message')", $rulejs);
		}

		protected function validation_js_impl()
		{
		}

		protected $message;
		protected $form;
	}

	class EqualFieldsRule extends FormRule {
		public function __construct($field1, $field2, $message = null)
		{
			$this->field1 = $field1;
			$this->field2 = $field2;
			parent::__construct($message);
			$this->message = dgettext('swisdk', 'The two related fields are not equal');
		}

		protected function is_valid_impl()
		{
			$dbobj = $this->form->dbobj();
			return $dbobj->get($this->field1->name())
				== $dbobj->get($this->field2->name());
		}

		protected function validation_js_impl()
		{
			$id = $this->form->id();
			$in1 = $this->field1->id();
			$in2 = $this->field2->id();
			$js = '';
			static $sent = false;
			if(!$sent) {
				$sent = true;
				$js = <<<EOD
function form_validate_equal_fields(field1, field2)
{
	return document.getElementById(field1).value==document.getElementById(field2).value;
}

EOD;
			}
			return array(
				"form_validate_equal_fields('$in1', '$in2')",
				$js);
		}

		protected $field1;
		protected $field2;
	}

	class ValuesExistRule extends FormRule {
		protected $var;

		protected function is_valid_impl()
		{
			$dbobj = $this->form->dbobj();
			$clauses = array();
			$fl = $dbobj->field_list();
			foreach($dbobj as $k => $v) {
				if(isset($fl[$k])) {
					$clauses[$k.'='] = $v;
				}
			}

			if($obj = DBObject::find($dbobj->_class(), $clauses)) {
				$this->var = $obj;
				return true;
			}

			return false;
		}

		public function match()
		{
			return $this->var;
		}
	}

	class CallbackFormRule extends FormRule {
		public function __construct($callback, $message = null)
		{
			$this->callback = $callback;
			parent::__construct($message);
		}

		protected function is_valid_impl()
		{
			return call_user_func($this->callback, $this);
		}

		protected $callback;
	}

	abstract class FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Value does not validate');
		}

		public function set_form_item(&$item)
		{
			$this->item =& $item;
		}

		public function is_valid()
		{
			if($this->is_valid_impl())
				return true;
			$this->item->add_message($this->message);
			return false;
		}

		protected function is_valid_impl()
		{
			return false;
		}

		public function validation_javascript()
		{
			if(!list($rulefunc, $rulejs) = $this->validation_js_impl())
				return null;
			$name = $this->item->id();
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			$message = '<img style="position:relative;top:3px"'
				.' "src="'.$prefix.'/icons/error.png" /> '.$this->message;
			return array(
				"swisdk_form_do_validate($rulefunc('$name'), '$name', '$message')",
				$rulejs);
		}

		public function  javascript_rule_name()
		{
			list($name, $tmp) = $this->validation_js_impl(false);
			return $name;
		}

		protected function validation_js_impl($set_sent=true)
		{
		}

		protected $message;
		protected $item;
	}

	class RequiredRule extends FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Value required');
		}

		public function set_form_item(&$item)
		{
			parent::set_form_item($item);
			$item->add_css_class('required');
			$item->set_title($item->title()
				.'<span style="color:red">*</span>');
		}

		protected function is_valid_impl()
		{
			$v = trim($this->item->value());
			if($this->item instanceof SelectionFormItem
					|| $this->item instanceof CheckboxInput) {
				return $v!=='' && $v!==0 && $v!=='0' && $v!==array();
			} else
				return $v!='';
		}

		protected function validation_js_impl($set_sent=true)
		{
			static $sent = false;
			$js = '';
			if(!$sent) {
				if($set_sent)
					$sent = true;
				$js = <<<EOD
function formitem_required_rule(id)
{
	var elem = document.getElementById(id);
	var v = elem.value.replace(/^\s+|\s+$/g, '');
	if(elem.tagName=='SELECT') {
		return v!='' && v!=0;
	} else
		return v!='';
}

EOD;
			}
			return array('formitem_required_rule', $js);
		}
	}

	/**
	 * the visitor user (default: user id 1) is not a valid user
	 * if you use this rule.
	 *
	 * It will still be displayed in the DropdownInput (or whatever)!
	 */
	class UserRequiredRule extends RequiredRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'User required');
		}

		protected function is_valid_impl()
		{
			require_once MODULE_ROOT.'inc.session.php';
			$value = $this->item->value();
			return $value!='' && $value>0 && $value!=SWISDK2_VISITOR;
		}

		protected function validation_js_impl($set_sent=true)
		{
			static $sent = false;
			$js = '';
			if(!$sent) {
				if($set_sent)
					$sent = true;
				$visitor = SWISDK2_VISITOR;
				$js = <<<EOD
function formitem_user_required_rule(id)
{
	var user = document.getElementById(id).value;
	return user!='' && user>0 && user!=$visitor;
}

EOD;
			}
			return array('formitem_user_required_rule', $js);
		}
	}

	class NumericRule extends FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Value must be numeric');
		}

		protected function is_valid_impl()
		{
			$v = $this->item->value();
			return !$v || is_numeric($v);
		}

		protected function validation_js_impl($set_sent=true)
		{
			static $sent = false;
			$js = '';
			if(!$sent) {
				if($set_sent)
					$sent = true;
				$js = <<<EOD
function formitem_numeric_rule(id)
{
	var value = document.getElementById(id).value;
	return !value || value.match(/^[0-9\.]*$/);
}

EOD;
			}
			return array('formitem_numeric_rule', $js);
		}
	}

	class RangeRule extends FormItemRule {
		protected $min;
		protected $max;

		public function __construct($min, $max, $message = null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = sprintf(dgettext('swisdk', 'Number must be between %s and %s'),
					$min, $max);
			$this->min = $min;
			$this->max = $max;
		}

		protected function is_valid_impl()
		{
			$value = $this->item->value();
			return $value>=$this->min && $value<=$this->max;
		}

		protected function validation_js_impl($set_sent=true)
		{
			$name = $this->item->id();
			$min = $this->min;
			$max = $this->max;
			$js = <<<EOD
function formitem_range_rule_$name()
{
	var value = parseInt(document.getElementById('$name').value);
	return value>=$min && value<=$max;
}

EOD;
			return array('formitem_range_rule_'.$name, $js);
		}
	}

	class RegexRule extends FormItemRule {
		protected $empty_valid = true;

		public function __construct($regex, $message = null)
		{
			$this->regex = $regex;
			parent::__construct($message);
		}

		protected function is_valid_impl()
		{
			$value = $this->item->value();
			return (!$value && $this->empty_valid)
				|| preg_match($this->regex, $value);
		}

		protected function validation_js_impl($set_sent=true)
		{
			$id = $this->item->id();
			$empty_valid = '';
			if($this->empty_valid)
				$empty_valid = 'value==\'\' || ';
			$js = <<<EOD
function formitem_regex_rule_$id(id)
{
	var value = document.getElementById(id).value;
	return {$empty_valid}value.match({$this->regex});
}

EOD;
			return array('formitem_regex_rule_'.$id, $js);
		}

		protected $regex;
	}

	class EmailRule extends RegexRule {
		public function __construct($message = null)
		{
			parent::__construct(
'/^((\"[^\"\f\n\r\t\v\b]+\")|([\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+(\.'
. '[\w\!\#\$\%\&\'\*\+\-\~\/\^\`\|\{\}]+)*))@((\[(((25[0-5])|(2[0-4][0-9])'
. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.'
. '((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
. '|([0-1]?[0-9]?[0-9])))\])|(((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))'
. '\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])'
. '|([0-1]?[0-9]?[0-9]))\.((25[0-5])|(2[0-4][0-9])|([0-1]?[0-9]?[0-9])))|'
. '((([A-Za-z0-9\-])+\.)+[A-Za-z\-]+))$/', 'dummy');
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Please enter a valid email address');
		}
	}

	class DNSEmailRule extends EmailRule {
		protected function is_valid_impl()
		{
			if(!parent::is_valid_impl())
				return false;

			list($username, $domain) = split('@', $this->item->value());

			return checkdnsrr($domain, 'MX');
		}
	}

	class UrlRule extends RegexRule {
		public function __construct($message = null)
		{
			parent::__construct(
				'/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*'
				.'\.[a-z]{2,5}((:[0-9]{1,5})?\/.*)?$/i', 'dummy');
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Please enter a valid URL');
		}
	}

	class AlnumRule extends RegexRule {
		public function __construct($message = null)
		{
			parent::__construct('/^[A-Za-z0-9]+$/', 'dummy');
			if($message)
				$this->message = $message;
			else
				$this->message =
					dgettext('swisdk', 'Only alphanumeric characters allowed');
		}
	}

	class CallbackRule extends FormItemRule {
		public function __construct($callback, $message = null)
		{
			$this->callback = $callback;
			parent::__construct($message);
		}

		protected function is_valid_impl()
		{
			return call_user_func($this->callback, $this->item);
		}

		protected $callback;
	}

	class EqualsRule extends FormItemRule {
		public function __construct($compare_value, $message = null)
		{
			$this->compare_value = $compare_value;
			parent::__construct($message);
		}

		protected function is_valid_impl()
		{
			return $this->compare_value == $this->item->value();
		}

		protected $compare_value;
	}

	class MD5EqualsRule extends EqualsRule {
		protected function is_valid_impl()
		{
			return $this->compare_value == md5($this->item->value());
		}
	}

	class UniqueRule extends FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Value is not unique');
		}

		protected function is_valid_impl()
		{
			$dbobj = $this->item->dbobj();
			$id = $dbobj->id();
			if(!$id)
				$id = 0;
			return DBOContainer::find($dbobj->_class(), array(
				$this->item->name().'=' => $this->item->value(),
				$dbobj->primary().'!=' => $id))->count()==0;
		}
	}

	class UploadedFileRule extends FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Please provide a file');
		}

		protected function is_valid_impl()
		{
			return !$this->item->no_upload();
		}
	}

	class ImageFileRule extends FormItemRule {
		protected $image_mimetypes = array('image/png', 'image/gif',
			'image/jpg', 'image/jpeg', 'image/pjpeg');

		public function __construct($message=null, $mimetypes=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Please provide a valid image file');

			if($mimetypes)
				$this->image_mimetypes = $mimetypes;
		}

		protected function is_valid_impl()
		{
			// NOTE! you could probably stuff more checks in here. I
			// hope these should be enough
			$data = $this->item->files_data();
			if(!$data['name'])
				return true;
			$mime = $data['type'];
			if(in_array($data['type'], $this->image_mimetypes)
					&& preg_match('/\.(png|jpg|jpeg|gif)$/',
						strtolower($data['name']))
					&& @getimagesize($data['path'])!==false)
				return true;

			$this->item->unlink_cachefile();
			return false;
		}
	}

?>
