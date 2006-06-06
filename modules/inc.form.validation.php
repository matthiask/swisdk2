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
			return null;
			if(!list($rulefunc, $rulejs) = $this->validation_js_impl($item))
				return null;
			$id = uniqid();
			$message = $this->message;
			$funcname = 'form_validate_'.$id;
			$js = <<<EOD

function $funcname()
{
	if(!$rulefunc()) {
		document.getElementById('{$name}_span').firstChild.data = '$message';
		return false;
	}
	document.getElementById('{$name}_span').firstChild.data = ' ';
	return true;
}
$rulejs;
EOD;
			return array($funcname, $js);
		}

		protected $message;
	}

	class EqualFieldsRule extends FormRule {
		protected $message = 'The two related fields are not equal';

		public function __construct($field1, $field2, $message = null)
		{
			$this->field1 = $field1;
			$this->field2 = $field2;
			parent::__construct($message);
		}

		protected function is_valid_impl(&$form)
		{
			$dbobj = $form->dbobj();
			return $dbobj->get($this->field1) == $dbobj->get($this->field2);
		}

		public function validation_javascript(&$form)
		{
			static $sent_func = false;
			$funcname = 'form_validate_equal_'.uniqid();
			$id = $form->id();
			// FIXME should use iname() here
			$js = <<<EOD

function $funcname()
{
	if(document.getElementById('{$this->field1}').value
			==document.getElementById('{$this->field2}').value) {
		document.getElementById('{$id}_span').firstChild.data = ' ';
		return true;
	}
	document.getElementById('{$id}_span').firstChild.data = '{$this->message}';
	return false;
}
EOD;
			return array($funcname, $js);
		}

		protected $field1;
		protected $field2;
	}


	abstract class FormItemRule {
		public function __construct($message=null)
		{
			if($message)
				$this->message = $message;
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
			$funcname = 'formitem_validate_'.$name;
			$js = <<<EOD

function $funcname()
{
	if(!$rulefunc('$name')) {
		document.getElementById('{$name}_span').firstChild.data = '$message';
		return false;
	}
	document.getElementById('{$name}_span').firstChild.data = ' ';
	return true;
}
$rulejs;
EOD;
			return array($funcname, $js);
		}

		protected function validation_js_impl(FormItem &$item)
		{
		}

		protected $message;
	}

	class RequiredRule extends FormItemRule {
		protected $message = 'Value required';

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
		protected $message = 'User required';

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
		protected $message = 'Value must be numeric';

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

	class RegexRule extends FormItemRule {
		protected $message = 'Value does not validate';

		public function __construct($regex, $message = null)
		{
			$this->regex = $regex;
			parent::__construct($message);
		}
		
		protected function is_valid_impl(FormItem &$item)
		{
			return preg_match($this->regex, $item->value());
		}

		protected function validation_js_impl(FormItem &$item)
		{
			static $sent = false;
			$id = $item->iname();
			$sent = true;
			$js = <<<EOD
function formitem_regex_rule_$id(id)
{
	var user = document.getElementById(id).value;
	return value.match({$this->regex});
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
. '((([A-Za-z0-9\-])+\.)+[A-Za-z\-]+))$/',
				$message);
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
		protected $message = 'Value does not validate';

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
		protected $message = 'Please provide a file';

		protected function is_valid_impl(FormItem &$item)
		{
			return !$item->no_upload();
		}
	}

	class ImageFileRule extends FormItemRule {
		protected $message = 'Please provide a valid image file';
		protected $image_mimetypes = array('image/png', 'image/gif',
			'image/jpg', 'image/jpeg', 'image/pjpeg');

		protected function is_valid_impl(FormItem &$item)
		{
			// NOTE! you could probably stuff more checks in here. I
			// hope these should be enough
			$data = $item->files_data();
			$mime = $data['type'];
			if(in_array($data['type'], $this->image_mimetypes)
					&& @getimagesize($data['path'])!==false)
				return true;

			$item->unlink_cachefile();
			return false;
		}
	}

?>
