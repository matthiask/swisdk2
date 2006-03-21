<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'formhandling/inc.simpleform.validation.php';
	require_once MODULE_ROOT . 'formhandling/inc.simpleform.formitems.php';
	
	class Swisdk_SimpleForm {
		public function __construct($name='_form', $method='POST', $action=null)
		{
			$this->types = array(
				'hidden' => 'Swisdk_SimpleForm_HiddenEntry',
				'text' => 'Swisdk_SimpleForm_TextEntry',
				'textarea' => 'Swisdk_SimpleForm_TextareaEntry',
				'password' => 'Swisdk_SimpleForm_PasswordEntry',
				'checkbox' => 'Swisdk_SimpleForm_CheckBox',
				'combobox' => 'Swisdk_SimpleForm_ComboBox',
				'multiselect' => 'Swisdk_SimpleForm_Multiselect',
				'submit' => 'Swisdk_SimpleForm_SubmitButton'
			);
			
			$this->name = $name;
			$this->method = $method;
			if($action===null) {
				$this->action = $_SERVER['REQUEST_URI'];
			} else {
				$this->action = $action;
			}
			
			$this->grid = new Swisdk_Layout_Grid();
			
			$this->grid->set_column_class(0, 'label');
			$this->grid->set_column_class(1, 'entry');
			$this->grid->set_column_class(2, 'error');
		}
		
		public function &add($type, $name, $label=null, $value=null)
		{
			if(isset($this->types[$type])) {
				$class = $this->types[$type];
				$tmp = func_get_args();
				$tmp = array_slice($tmp, 4);
				$item = new $class($name, $label, $value, isset($tmp[0])?$tmp[0]:null);
				$item->render($this->grid);
				$this->container[$name] =& $item;
				
				// special treatment for the 'hidden' type
				if($type=='hidden') {
					$this->container_hidden[] =& $item;
				}
				return $item;
			}
			
			$msg = 'Unable to find type ' . $type . ' in SimpleForm';
			die($msg);
			return null;
		}
		
		public function register_type($typename, $class)
		{
			$this->types[$typename] = $class;
		}
		
		public function html()
		{
			$html = '<form id="' . $this->name . '" id="' . $this->name
					. '" method="' . $this->method . '" action="'
					. $this->action . '">';
			if(count($this->container_hidden)) {
				foreach($this->container_hidden as &$item) {
					$html .= $item->entry()->html();
				}
			}
			
			$html .= $this->grid->html();
			
			return $html . '</form>';
		}
		
		public function &data()
		{
			$data = array();
			foreach($this->container as &$item) {
				$entry =& $item->entry();
				if($entry->name())
					$data[ $entry->name() ] = $entry->value();
			}
			return $data;
		}

		public function set_data($data)
		{
			foreach($this->container as &$item) {
				$entry =& $item->entry();
				if($val =& $data[$entry->name()]) {
					$entry->set_value($val);
				}
			}
		}
		
		public function &element($name)
		{
			return $this->container[$name];
		}
		
		public function &grid()
		{
			return $this->grid;
		}
		
		public function is_valid()
		{
			// TODO really need something better here...
			if(!count($_POST))
				return false;
			$valid = true;
			foreach($this->container as &$item) {
				if(!$item->is_valid()) {
					$valid = false;
				}
			}
			return $valid;
		}
		
		protected $types;
		
		protected $grid;
		protected $container, $container_hidden;
		protected $name, $method, $action;
	}
	
?>
