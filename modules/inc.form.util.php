<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class FormUtil {
		public static function realm_dropdown($formbox, $id='Realm', $role=ROLE_MANAGER)
		{
			$elem = new DropdownInput();
			$elem->set_items(
				PermissionManager::realms_for_role($role));
			$ret = $formbox->add($id, $elem);
			$realm = PermissionManager::realm_for_url();
			$ret->set_default_value($realm['realm_id']);
			return $ret;
		}

		public static function realm_multiselect($formbox, $id='Realm', $role=ROLE_MANAGER)
		{
			$elem = new Multiselect();
			$elem->set_items(
				PermissionManager::realms_for_role($role));
			$ret = $formbox->add($id, $elem);
			$realm = PermissionManager::realm_for_url();
			$ret->set_default_value(array($realm['realm_id']));
			return $ret;
		}

		public static function language_dropdown($formbox, $id='Language')
		{
			$_langs = Swisdk::languages();
			$items = array();
			foreach($_langs as $id => &$l)
				$items[$id] = $l['language_title'];

			if(count($items)>1) {
				$elem = new DropdownInput();
				$elem->set_items($items);
				return $formbox->add($id, $elem);
			} else {
				$elem = $formbox->add($id, new HiddenInput());
				$elem->set_value(reset(array_keys($items)));
				return $elem;
			}
		}

		public static function language_multiselect($formbox, $id='Language')
		{
			$_langs = Swisdk::languages();
			$items = array();
			foreach($_langs as $lid => &$l)
				$items[$lid] = $l['language_title'];

			$inp = getInput($formbox->id().'_'.$id);

			if(count($items)==1 || is_string($inp)) {
				$items = array_keys($items);
				$elem = $formbox->add($id, new HiddenArrayInput());
				if($inp===null)
					$elem->set_value(array($items[0]));
				return $elem;
			} else {
				$elem = new Multiselect();
				$elem->set_items($items);
				return $formbox->add($id, $elem);
			}
		}

		public static function textarea($formbox, $field, $title=null, $default=true,
			$config_key='richtextarea')
		{
			if(Swisdk::user_config_value($config_key, $default)
					|| strpos($formbox->dbobj()->get($field), '<!-- RT -->')===0)
				return $formbox->add($field, new RichTextarea(), $title);
			else
				return $formbox->add($field, new Textarea(), $title);
		}
	}

?>
