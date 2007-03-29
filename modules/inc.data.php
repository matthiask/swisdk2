<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	define('DB_REL_UNSPECIFIED', 0);
	define('DB_REL_SINGLE', 1);
	define('DB_REL_MANY', 2);
	define('DB_REL_N_TO_M', 3);
	define('DB_REL_3WAY', 4);
	define('DB_REL_TAGS', 5);

	define('LANGUAGE_DEFAULT', -1);
	define('LANGUAGE_ALL', -2);

	// this string denotes the section name in the config file!
	define('DB_CONNECTION_DEFAULT', 'db');

	define('DB_FIELD_INTEGER', 1);
	define('DB_FIELD_BOOL', 2);
	define('DB_FIELD_STRING', 4);
	define('DB_FIELD_LONGTEXT', 8);
	define('DB_FIELD_DATE', 16);
	define('DB_FIELD_DTTM', 32);
	define('DB_FIELD_FLOAT', 64);
	// DB_FIELD_FOREIGN_KEY may be OR'ed with DB_REL_*<<10
	define('DB_FIELD_FOREIGN_KEY', 128);

	require_once MODULE_ROOT.'inc.data.dbobject.php';
	require_once MODULE_ROOT.'inc.data.dbocontainer.php';
	require_once MODULE_ROOT.'inc.data.dbobjectml.php';

?>
