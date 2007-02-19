<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Generic XML exporter for DBObjects and DBOContainers
	 */
	class XMLExporter {

		protected $data = array();
		protected $field_list = array();

		/**
		 * add another DBObject or DBOContainer to the output
		 */
		public function add($data)
		{
			$this->data[] = $data;
		}

		/**
		 * limit shown fields of DBObject class to the ones specified here
		 *
		 * Examples:
		 *
		 * $list = $dbo->field_list();
		 * unset($list['something']);
		 * $exporter->set_field_list($dbo->_class(), array_keys($list));
		 *
		 * or
		 *
		 * $exporter->set_field_list('User', array(
		 * 	'user_name', 'user_forename', 'UserGroup'));
		 *
		 * This function accepts everything which can be passed to
		 * DBObject::get()
		 */
		public function set_field_list($class, $fields)
		{
			$this->field_list[$class] = $fields;
		}

		/**
		 * Fetch the XML document
		 */
		public function fetch()
		{
			$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<xml-export>\n";
			foreach($this->data as &$entry) {
				if($entry instanceof DBObject)
					$xml .= $this->_handle_dbobject($entry);
				else if($entry instanceof DBOContainer)
					$xml .= $this->_handle_dbocontainer($entry);
				else
					$xml .= "<oops/>\n";
			}
			$xml .= "</xml-export>\n";
			return $xml;
		}

		protected function _handle_dbobject($dbo)
		{
			$class = $dbo->_class();
			$classelem = $this->_elementify($class);
			$xml = "<$classelem>\n";
			if(isset($this->field_list[$class])) {
				foreach($this->field_list[$class] as $k) {
					$elem = $this->_elementify($dbo->shortname($k));
					$xml .= "<$elem>"
						.$this->_handle_value($dbo->get($k))
						."</$elem>\n";
				}
			} else {
				foreach($dbo as $k => $v) {
					$elem = $dbo->shortname($k);
					$xml .= "<$elem>"
						.$this->_handle_value($v)
						."</$elem>\n";
				}
			}
			$xml .= "</$classelem>\n";
			return $xml;
		}

		protected function _handle_dbocontainer($dboc)
		{
			$class = $this->_elementify($dboc->dbobj()->_class());
			$xml = "<$class>\n";
			foreach($dboc as $dbo)
				$xml .= $this->_handle_dbobject($dbo);
			$xml .= "</$class>\n";
			return $xml;
		}

		protected function _handle_value($value)
		{
			if(is_numeric($value))
				return $value;
			else if(is_array($value))
				return $this->_handle_array($value);
			else if(is_string($value))
				return '<![CDATA['.$value.']]>';
			else
				return '<oops/>';
		}

		protected function _handle_array($value)
		{
			$xml = "<array>\n";
			foreach($value as $v)
				$xml .= "<item>"
					.$this->_handle_value($v)
					."</item>\n";
			$xml .= "</array>\n";
			return $xml;
		}

		protected function _elementify($str)
		{
			$str = strtolower(preg_replace('/([A-Z])/', '_\1', $str));
			if($str{0}=='_')
				return substr($str, 1);
			return $str;
		}
	}

?>
