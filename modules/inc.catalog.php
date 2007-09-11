<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	require_once SWISDK_ROOT.'lib/contrib/spyc.php';

	class Catalog {
		protected static $catalog = array();

		protected static $language_key;

		public static function load($data)
		{
			Catalog::add(Spyc::YAMLLoad($data));
			Catalog::$language_key = Swisdk::language_key();
		}

		public static function add($c)
		{
			Catalog::$catalog = array_merge_recursive(Catalog::$catalog, $c);
		}

		public static function translate($str)
		{
			if(isset(Catalog::$catalog[$str][Catalog::$language_key]))
				return Catalog::$catalog[$str][Catalog::$language_key];

			return $str;
		}

		public static function translate_n($count, $str, $str1=null)
		{
			if(!isset(Catalog::$catalog[$str])) {
				if($count==1)
					return $str1===null?$str:$str1;

				return $str;
			}

			$c =& Catalog::$catalog[$str];
			$lkey = Catalog::$language_key;

			if(isset($c[$t = $lkey.'_'.$count]))
				return $c[$t];

			if(isset($c[$lkey]))
				return $c[$lkey];

			if($count==1 && $str1!==null)
				return $str1;

			return $str;
		}
	}

	function _T($str)
	{
		return Catalog::translate($str);
	}

	function _Tn($count, $str, $str1=null)
	{
		return Catalog::translate_n($count, $str, $str1);
	}

?>
