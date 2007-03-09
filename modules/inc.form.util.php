<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class FormUtil {
		public static function submit_bar($form)
		{
			$box = $form->box('zzz_last')->add(new GroupItemBar())->box();
			$box->add(new SubmitButton());
			$box->add(new ResetButton());
		}

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

		/**
		 * Add a textarea or richtextarea depending on the current content and on the
		 * preferences of the user (if core.user_config is true, see
		 * Swisdk::user_config_value)
		 */
		public static function textarea($formbox, $field, $title=null, $default=true,
			$config_key='richtextarea')
		{
			if(Swisdk::user_config_value($config_key, $default)
					|| strpos($formbox->dbobj()->get($field), '<!-- RT -->')===0)
				return $formbox->add($field, new RichTextarea(), $title);
			else
				return $formbox->add($field, new Textarea(), $title);
		}

		/**
		 * Example:
		 *
		 * Add a category multiselect item which depends on a realm dropdown
		 * (the categories are assigned to realms and should only be shown if
		 * the correspoding realm has been selected.)
		 *
		 * Note! You need to provide an Ajax_Server! See AdminComponent_ajax to
		 * find out how to do that.
		 *
		 * FormUtil::realmed_relation($form, 'Category',
		 * 	FormUtil::realm_dropdown($form));
		 */
		public static function realmed_relation($formbox, $relation, $realm_obj=null, $realm=null)
		{
			if(!$realm) {
				if($r = $formbox->dbobj()->realm_id)
					$realm = $r;
				else if($r = PermissionManager::realm_for_url())
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

		/**
		 * assign the returned objects to the main tag input!
		 *
		 * Usage:
		 * $tag_inp = $form->add_auto('Tag');
		 * $tag_sel = FormUtil::tag_multiselect($form, 'themes.%')
		 * $tag_inp->add_tag_formitems($tag_sel);
		 */
		public static function tag_multiselect($formbox, $query=null)
		{
			$sql = 'SELECT tag_title FROM tbl_tag WHERE 1';
			if($query)
				$sql .= ' AND tag_title LIKE '.DBObject::db_escape($query);

			$sql .= ' ORDER BY tag_title';

			$tags = DBObject::db_get_array($sql, array('tag_title', 'tag_title'));
			$key = md5($query);

			$elem = new Multiselect();
			$elem->set_items($tags);
			$formbox->add('tag_'.$key, $elem);

			$current = $formbox->dbobj()->related('Tag')->collect('id', 'title');

			$value = array_intersect($tags, $current);

			if(getInput($elem->id())) {
				$v = $elem->value();
				$value = array_merge(array_intersect($value, $v), $v);
			}

			$elem->set_value($value);
			$elem->set_title($query);

			return $elem;
		}
	}

?>
