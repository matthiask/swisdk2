<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once MODULE_ROOT . 'inc.session.php';

	// see also tbl_role !!!
	define('ROLE_VISITOR', 1);		// view
	define('ROLE_AUTHENTICATED', 2);	// view, comment
	define('ROLE_MEMBER', 3);		// view, comment
	define('ROLE_MANAGER', 4);		// view, comment, create, edit, delete
	define('ROLE_ADMINISTRATOR', 5);	// view, comment, create, edit, delete,
						// permissions
	define('ROLE_SITEADMIN', 6);		// everything! Will be inherited.

	class PermissionManager {
		public static function &instance()
		{
			static $instance = null;
			if( $instance === null )
				$instance = new PermissionManager();

			return $instance;
		}

		/**
		 * return all realms the user has siteadmin role for
		 */
		public static function siteadmin_realms($uid=null)
		{
			if(!$uid=intval($uid))
				$uid = SwisdkSessionHandler::user()->id();

			// <caching>
			if(isset(Swisdk::$cache['permission']['siteadmin_realms']['user'.$uid]))
				return Swisdk::$cache['permission']['siteadmin_realms']['user'.$uid];
			else
				Swisdk::$cache['permission']['siteadmin_realms']['user'.$uid] = null;

			$ref =& Swisdk::$cache['permission']['siteadmin_realms']['user'.$uid];
			// </caching>

			$sql = 'SELECT tbl_realm.realm_id, realm_url FROM tbl_realm '
				.'JOIN tbl_user_to_realm '
					.'ON urr_realm_id=realm_id '
				.'WHERE urr_user_id='.$uid.' AND urr_role_id>='.ROLE_SITEADMIN
				.' UNION '
				.'SELECT realm_id, realm_url FROM tbl_realm '
				.'JOIN tbl_user_group_to_realm '
					.'ON ugrr_realm_id=realm_id '
				.'LEFT JOIN tbl_user_to_user_group '
					.'ON ugrr_user_group_id='
						.'uug_user_group_id '
				.'WHERE uug_user_id='.$uid.' AND ugrr_role_id>='.ROLE_SITEADMIN;
			$urls = DBObject::db_get_array($sql, array('realm_id', 'realm_url'));

			if(count($urls)) {
				$sql = 'SELECT * FROM tbl_realm WHERE ';
				$clauses = array();
				foreach($urls as $url)
					$clauses[] = 'realm_url LIKE \''.$url.'%\'';
				$sql .= implode(' OR ', $clauses);
				$urls = DBObject::db_get_array($sql, array('realm_id', 'realm_title'));
			} else
				$urls = array();

			$ref = $urls;
			Swisdk::$cache_modified = true;

			return $urls;
		}

		public static function check($role=null, $url=null)
		{
			if($realm = PermissionManager::realm_for_url($url)) {
				//
				// determine the minimally needed role (URI (=Realm)
				// role and content role (parameter))
				//
				$needed_role = max($role, $realm['realm_role_id']);

				if(PermissionManager::check_realm_role(
						$realm['realm_id'], $needed_role))
					return true;
			}

			return false;
		}

		public static function check_throw($role=null, $url=null)
		{
			if(PermissionManager::check($role, $url))
				return true;

			if(SwisdkSessionHandler::authenticated())
				SwisdkError::handle(new AccessDeniedError());
			PermissionManager::login_form();
		}

		public static function realm_for_url($url=null)
		{
			$current = false;

			if(is_null($url)) {
				$url = Swisdk::config_value('runtime.request.uri');
				$current = true;
			}

			if($current && $r = Swisdk::config_value('runtime.realm'))
				return $r;

			if($url{0}=='/')
				$url = substr($url, 1);

			//
			// find best matching realm
			//
			// If the URI is /a/b/c, the realm table will be searched for
			// a/b/c, a/b, a and finally the empty string (root realm)
			//
			$tokens = explode('/', $url);
			$clauses = array();
			$d = Swisdk::config_value('runtime.domain');
			if($d)
				$d .= ':';
			while(count($tokens)) {
				$clauses[] = DBObject::db_escape($d.implode('/', $tokens));
				array_pop($tokens);
			}
			$res = DBObject::db_get_row(
				'SELECT realm_id,realm_role_id FROM tbl_realm WHERE '
				.'(realm_url='.implode(' OR realm_url=', $clauses)
				.' OR realm_url=\''.$d.'\') ORDER BY realm_url DESC LIMIT 1');
			if($current)
				Swisdk::set_config_value('runtime.realm', $res);
			return $res;
		}

		public static function check_realm_role($realm, $role, $uid=null)
		{
			$realm = intval($realm);
			$role = intval($role);
			if(is_null($uid))
				$uid = SwisdkSessionHandler::user()->id();

			// <caching>
			$token = $realm.'_'.$role.'_'.$uid;
			if(isset(Swisdk::$cache['permission'][$token]))
				return Swisdk::$cache['permission'][$token];
			// </caching>

			$siteadmin_realms = PermissionManager::siteadmin_realms($uid);
			if(isset($siteadmin_realms[$realm])) {
				Swisdk::$cache['permission'][$token] = true;
				Swisdk::$cache_modified = true;
				return true;
			}

			$perms = DBObject::db_get_row('SELECT urr_role_id AS role_id '
				.'FROM tbl_user_to_realm WHERE urr_user_id='
				.$uid.' AND urr_realm_id='.$realm);

			//
			// the user must have an entry in the permission table, even
			// for simple viewing otherwise, access is denied.
			//
			// Roles are not inherited across realms. This simplifies the
			// code and it is easier to understand. The only drawback is
			// the amount of data which will be necessary once you have some
			// realms in the system. (Roughly #users * #realms)
			//
			if($perms && $perms['role_id']>=$role) {
				Swisdk::$cache['permission'][$token] = true;
				Swisdk::$cache_modified = true;
				return true;
			}

			//
			// check user's groups for sufficient permissions
			//
			$perms = DBObject::db_get_array('SELECT ugrr_role_id AS role_id '
				.'FROM tbl_user_group_to_realm '
				.'LEFT JOIN tbl_user_to_user_group '
					.'ON ugrr_user_group_id='
						.'uug_user_group_id '
				.'WHERE uug_user_id='.$uid.' AND ugrr_realm_id='.$realm);
			foreach($perms as &$p) {
				if($p['role_id']>=$role) {
					Swisdk::$cache['permission'][$token] = true;
					Swisdk::$cache_modified = true;
					return true;
				}
			}

			//
			// insufficient roles everywhere... check failed!
			//
			Swisdk::$cache['permission'][$token] = false;
			Swisdk::$cache_modified = true;
			return false;
		}

		public static function login_form()
		{
			require_once MODULE_ROOT . 'inc.form.php';
			require_once MODULE_ROOT . 'inc.smarty.php';
			$form = new Form();
			$form->bind(DBObject::create('Login'));
			$form->set_title('Login');
			$form->add(new TextInput('login_username'))
				->add_behavior(new GrabFocusBehavior())
				->set_title('Username');
			$form->add(new PasswordInput('login_password'))
				->set_title('Password');
			$form->add(new CheckboxInput('login_private'))
				->set_title('Remember login');
			$form->add(new SubmitButton());

			$smarty = new SwisdkSmarty();
			$smarty->assign('content', $form->html());
			$smarty->display_template('swisdk.login');
			Swisdk::shutdown();
		}

		/**
		 * @return an array of all realms (realm_id as key, realm_title as value)
		 * 	for which the user (current user if none given) has at least
		 * 	the given role
		 */
		public static function realms_for_role($role_id, $uid = null)
		{
			if($uid===null)
				$uid = SwisdkSessionHandler::user()->id();
			$rid = intval($role_id);
			$sql = 'SELECT realm_id, realm_title FROM tbl_realm '
				.'LEFT JOIN tbl_user_to_realm '
					.'ON realm_id=urr_realm_id '
				.'WHERE urr_user_id='.$uid.' AND urr_role_id>='.$rid
				.' UNION ALL '
				.'SELECT realm_id, realm_title FROM tbl_realm '
				.'LEFT JOIN tbl_user_group_to_realm '
					.'ON realm_id=ugrr_realm_id '
				.'LEFT JOIN tbl_user_to_user_group '
					.'ON ugrr_user_group_id='
						.'uug_user_group_id '
				.'WHERE uug_user_id='.$uid.' AND ugrr_role_id>='.$rid;
			return array_flip(array_merge(
				array_flip(DBObject::db_get_array($sql, array('realm_id', 'realm_title'))),
				array_flip(PermissionManager::siteadmin_realms($uid))));
		}

		public static function role_for_realm($realm, $uid = null)
		{
			if($uid===null)
				$uid = SwisdkSessionHandler::user()->id();
			$rsql = '';
			if(is_array($realm)) {
				foreach($realm as $r)
					$rsql .= ','.intval($r);
				$rsql = 'realm_id IN ('.substr($rsql, 1).')';
			} else {
				$rsql = 'realm_id='.intval($realm);
			}

			// <caching>
			if(!isset(Swisdk::$cache['permission']['role_for_realm']['user'.$uid]))
				Swisdk::$cache['permission']['role_for_realm']['user'.$uid] = array();

			$ref =& Swisdk::$cache['permission']['role_for_realm']['user'.$uid];

			if(isset($ref[$rsql]))
				return $ref[$rsql];
			// </caching>

			$rid = intval($realm);
			$usql = 'SELECT urr_role_id,urr_realm_id FROM tbl_user_to_realm WHERE urr_user_id='.$uid.' AND urr_'.$rsql
				.' GROUP BY urr_realm_id';
			$gsql = 'SELECT MAX(ugrr_role_id) AS ugrr_role_id,ugrr_realm_id FROM tbl_user_group_to_realm, tbl_user_to_user_group '
				.'WHERE ugrr_user_group_id=uug_user_group_id'
				.' AND uug_user_id='.$uid.' AND ugrr_'.$rsql.' GROUP BY ugrr_realm_id';

			$role = null;

			if(is_array($realm)) {
				$user = DBObject::db_get_array($usql, array('urr_realm_id', 'urr_role_id'));
				$group = DBObject::db_get_array($gsql, array('ugrr_realm_id', 'ugrr_role_id'));
				$realms = array_merge(array_keys($user), array_keys($group));
				$ret = array();
				foreach($realms as $realm)
					$ret[$realm] = max(
						isset($user[$realm])?$user[$realm]:0,
						isset($group[$realm])?$group[$realm]:0);
				$role = $ret;
			} else {
				$user = DBObject::db_get_row($usql);
				$group = DBObject::db_get_row($gsql);
				$role = max($user['urr_role_id'], $group['ugrr_role_id']);
			}

			$ref[$rsql] = $role;
			Swisdk::$cache_modified = true;
			return $role;
		}

		/**
		 * various helper functions
		 */
		public static function set_realm_clause(&$dboc, $realm_link = 'RealmLink')
		{
			$dbo = $dboc->dbobj();
			$relations = $dbo->relations();
			if(!isset($relations[$realm_link]))
				SwisdkError::handle(new FatalError('Could not set realm clause'));

			$realm = PermissionManager::realm_for_url();
			$params = array(
				'realm_id' => $realm['realm_id'],
				'user_role_id' => PermissionManager::role_for_realm($realm['realm_id']));

			$p = $dbo->_prefix();
			$t = $dbo->table();
			$tp = strtolower(preg_replace('/[^A-Z]/', '', $dbo->_class())).'rr_';

			$sql = "($t.{$p}realm_id={realm_id} AND $t.{$p}role_id<={user_role_id}
					OR $t.{$p}id IN (
						SELECT DISTINCT {$tp}{$p}id FROM tbl_{$p}to_realm
						WHERE {$tp}realm_id={realm_id} AND {$tp}role_id<={user_role_id}
							AND {$p}role_id<={user_role_id}
					)
				)";
			$dboc->add_clause($sql, $params);
		}

		public static function limit_by_realm(&$container, $realms, $role = ROLE_VISITOR, $realm_link = 'RealmLink')
		{
			$dbo = $container->dbobj();
			$relations = $dbo->relations();
			if(!isset($relations[$realm_link]))
				SwisdkError::handle(new FatalError('Could not limit by realm'));

			// if no realms have been specified, apply rules to all
			if(!$realms)
				$realms = DBOContainer::find('Realm')->ids();

			$p = $dbo->_prefix();
			$t = $dbo->table();
			$tp = strtolower(preg_replace('/[^A-Z]/', '', $dbo->_class())).'rr_';

			$role = intval($role);
			$sql1 = array();
			$sql2 = array();

			$realmroles = PermissionManager::role_for_realm($realms);

			foreach($realmroles as $realm => $realmrole) {
				$r = $role;
				if($realmrole)
					$r = max($r, $realmrole);
				$sql1[] = "($t.{$p}realm_id=$realm AND $t.{$p}role_id<=$r)";
				$sql2[] = "({$tp}realm_id=$realm AND {$tp}role_id<=$r)";
			}

			$sql = '('
				.implode(' OR ', $sql1)
				." OR $t.{$p}id IN ("
					."SELECT DISTINCT {$tp}{$p}id FROM tbl_{$p}to_realm"
					.' WHERE '
					.implode(' OR ', $sql2)
				.")"
			.')';

			$container->add_clause($sql);
		}

		public static function check_access($dbobj)
		{
			if(PermissionManager::check_realm_role($dbobj->realm_id, $dbobj->role_id))
				return true;

			$obj_realms = $dbobj->get('RealmLink');
			$realms_for_role = PermissionManager::realms_for_role($dbobj->role_id);
			foreach($obj_realms as $realm => $role)
				if(isset($realms_for_role[$realm]) && $role<=$dbobj->role_id)
					return true;

			return false;
		}

		public static function check_access_throw($dbobj)
		{
			if(PermissionManager::check_access($dbobj))
				return true;

			if(SwisdkSessionHandler::authenticated())
				SwisdkError::handle(new AccessDeniedError());
			PermissionManager::login_form();
		}
	}

?>
