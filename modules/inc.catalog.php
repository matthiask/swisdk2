<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'lib/contrib/spyc.php';

	class Catalog {
		protected static $catalog = array();

		public static function load($data)
		{
			Catalog::add(Spyc::YAMLLoad($data));
		}

		public static function add($c)
		{
			Catalog::$catalog = array_merge_recursive(Catalog::$catalog, $c);
		}

		public static function translate($str)
		{
			$lkey = Swisdk::language_key();

			if(isset(Catalog::$catalog[$str][$lkey]))
				return Catalog::$catalog[$str][$lkey];

			return $str;
		}
	}

	function T($arg)
	{
		return Catalog::translate($arg);
	}

?>
