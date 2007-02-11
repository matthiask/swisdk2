<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	$user = DBObject::create('User');
	$usergroup = DBObject::create('UserGroup');
	$usermeta = DBObject::create('UserMeta');
	$realm = DBObject::create('Realm');
	$role = DBObject::create('Role');

	DBObject::belongs_to($usermeta, $user);
	DBObject::belongs_to($usergroup, $realm);
	DBObject::has_a($usergroup, $usergroup, 'user_group_parent_id');
	DBObject::n_to_m($user, $usergroup);
	DBObject::threeway($user, $realm, $role);
	DBObject::threeway($usergroup, $realm, $role);
	DBObject::has_a($realm, $role);

?>
