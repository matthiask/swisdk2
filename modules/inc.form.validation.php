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

		public function is_valid(&$form)
		{
			if($this->is_valid_impl($form))
				return true;
			$form->add_message($this->message);
			return false;
		}

		protected function is_valid_impl(&$form)
		{
			return false;
		}

		public function validation_javascript(&$form)
		{
			if(!list($rulefunc, $rulejs) = $this->validation_js_impl($form))
				return null;
			$id = $form->id();
			return array(
				"swisdk_form_do_validate($rulefunc, '$id', '{$this->message}')", $rulejs);
		}

		protected function validation_js_impl(&$form)
		{
		}

		protected $message;
	}

	class EqualFieldsRule extends FormRule {
		public function __construct($field1, $field2, $message = null)
		{
			$this->field1 = $field1;
			$this->field2 = $field2;
			parent::__construct($message);
			$this->message = dgettext('swisdk', 'The two related fields are not equal');
		}

		protected function is_valid_impl(&$form)
		{
			$dbobj = $form->dbobj();
			return $dbobj->get($this->field1->name())
				== $dbobj->get($this->field2->name());
		}

		protected function validation_js_impl(&$form)
		{
			$id = $form->id();
			$in1 = $this->field1->iname();
			$in2 = $this->field2->iname();
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


	abstract class FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Value does not validate');
		}

		public function is_valid(FormItem &$item)
		{
			if($this->is_valid_impl($item))
				return true;
			$item->add_message($this->message);
			return false;
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return false;
		}

		public function validation_javascript(FormItem &$item)
		{
			if(!list($rulefunc, $rulejs) = $this->validation_js_impl($item))
				return null;
			$name = $item->iname();
			$message = $this->message;
			return array(
				"swisdk_form_do_validate($rulefunc('$name'), '$name', '{$this->message}')", $rulejs);
		}

		protected function validation_js_impl(FormItem &$item)
		{
		}

		protected $message;
	}

	class RequiredRule extends FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Value required');
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return $item->value()!='';
		}

		protected function validation_js_impl(FormItem &$item)
		{
			static $sent = false;
			$js = '';
			if(!$sent) {
				$sent = true;
				$js = <<<EOD
function formitem_required_rule(id)
{
	return document.getElementById(id).value!='';
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

		protected function is_valid_impl(FormItem &$item)
		{
			require_once MODULE_ROOT.'inc.session.php';
			$value = $item->value();
			return $value!='' && $value!=SWISDK2_VISITOR;
		}

		protected function validation_js_impl(FormItem &$item)
		{
			static $sent = false;
			$js = '';
			if(!$sent) {
				$sent = true;
				$visitor = SWISDK2_VISITOR;
				$js = <<<EOD
function formitem_user_required_rule(id)
{
	var user = document.getElementById(id).value;
	return user!='' && user!=$visitor;
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

		protected function is_valid_impl(FormItem &$item)
		{
			return is_numeric($item->value());
		}

		protected function validation_js_impl(FormItem &$item)
		{
			static $sent = false;
			$js = '';
			if(!$sent) {
				$sent = true;
				$js = <<<EOD
function formitem_numeric_rule(id)
{
	var user = document.getElementById(id).value;
	return value.match(/[0-9]*/);
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

		protected function is_valid_impl(FormItem &$item)
		{
			$value = $item->value();
			return $value>=$this->min && $value<=$this->max;
		}

		protected function validation_js_impl(FormItem &$item)
		{
			$name = $item->iname();
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

		protected function is_valid_impl(FormItem &$item)
		{
			$value = $item->value();
			return (!$value && $this->empty_valid)
				|| preg_match($this->regex, $value);
		}

		protected function validation_js_impl(FormItem &$item)
		{
			static $sent = false;
			$id = $item->iname();
			$sent = true;
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

	class CallbackRule extends FormItemRule {
		public function __construct($callback, $message = null)
		{
			$this->callback = $callback;
			parent::__construct($message);
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return call_user_func($this->callback, $item);
		}

		protected $callback;
	}

	class EqualsRule extends FormItemRule {
		public function __construct($compare_value, $message = null)
		{
			$this->compare_value = $compare_value;
			parent::__construct($message);
		}

		protected function is_valid_impl(FormItem &$item)
		{
			return $this->compare_value == $item->value();
		}

		protected $compare_value;
	}

	class MD5EqualsRule extends EqualsRule {
		protected function is_valid_impl(FormItem &$item)
		{
			return $this->compare_value == md5($item->value());
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

		protected function is_valid_impl(FormItem &$item)
		{
			return !$item->no_upload();
		}
	}

	class ImageFileRule extends FormItemRule {
		protected $image_mimetypes = array('image/png', 'image/gif',
			'image/jpg', 'image/jpeg', 'image/pjpeg');

		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
			else
				$this->message = dgettext('swisdk', 'Please provide a valid image file');
		}

		protected function is_valid_impl(FormItem &$item)
		{
			// NOTE! you could probably stuff more checks in here. I
			// hope these should be enough
			$data = $item->files_data();
			$mime = $data['type'];
			if(in_array($data['type'], $this->image_mimetypes)
					&& preg_match('/\.(png|jpg|jpeg|gif)$/',
						strtolower($data['name']))
					&& @getimagesize($data['path'])!==false)
				return true;

			$item->unlink_cachefile();
			return false;
		}
	}

?>
