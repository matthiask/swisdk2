<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class FormUtil {
		public static function realm_dropdown($formbox, $id='Realm', $role=ROLE_MANAGER)
		{
			$rel = $formbox->dbobj()->relations();
			if(isset($rel[$id]['field']))
				$id = $rel[$id]['field'];
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
			$rel = $formbox->dbobj()->relations();
			if(isset($rel[$id]['field']))
				$id = $rel[$id]['field'];
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
			$rel = $formbox->dbobj()->relations();
			if(isset($rel[$id]['field']))
				$id = $rel[$id]['field'];
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
			$rel = $formbox->dbobj()->relations();
			if(isset($rel[$id]['field']))
				$id = $rel[$id]['field'];
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

		public static function realmed_relation($formbox, $relation, $realm_obj=null, $realm=null)
		{
			if(!$realm) {
				$r = PermissionManager::realm_for_url();
				$realm = $r['realm_id'];
			}

			$relations = DBOContainer::find($relation, array(
				':join' => 'Realm',
				DBObject::create($relation)->name('realm_id').'=' => $realm));

			$elem = $formbox->add_auto($relation);
			$elem->set_items($relations->collect('id','title'));

			if($realm_obj) {
				$realm_obj->add_behavior(new UpdateOnChangeAjaxBehavior(
					$elem, 'AdminComponent_'.$formbox->dbobj()->_class().'_Ajax_Server',
					strtolower($relation.'_for_realm')));
			}

			return $elem;
		}
	}

?>
