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

		protected $message;
	}

	class RequiredRule extends FormItemRule {
		protected $message = 'Value required';

		protected function is_valid_impl(FormItem &$item)
		{
			return $item->value()!='';
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
	}

	class NumericRule extends FormItemRule {
		protected $message = 'Value must be numeric';

		protected function is_valid_impl(FormItem &$item)
		{
			return is_numeric($item->value());
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

?>
