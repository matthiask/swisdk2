<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	define('DB_REL_UNSPECIFIED', 0);
	define('DB_REL_SINGLE', 1);
	define('DB_REL_MANY', 2);
	define('DB_REL_MANYTOMANY', 3);

	define('LANGUAGE_DEFAULT', -1);
	define('LANGUAGE_ALL', -2);

	// this string denotes the section name in the config file!
	define('DB_CONNECTION_DEFAULT', 'db');

	require_once MODULE_ROOT.'inc.data.dbobject.php';
	require_once MODULE_ROOT.'inc.data.dbocontainer.php';
	require_once MODULE_ROOT.'inc.data.dbobjectml.php';

?>
