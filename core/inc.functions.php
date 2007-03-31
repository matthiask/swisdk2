<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch> and
	*			    Moritz Zumbühl <mail@momoetomo.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * shortcut accessor functions
	 */
	function s_get(&$array, $var, $default=null)
	{
		return isset($array[$var])?$array[$var]:$default;
	}

	function s_set(&$array, $var, $value)
	{
		if(!isset($array[$var]))
			$array[$var] = $value;
	}

	function s_test(&$array, $var)
	{
		return isset($array[$var]) && $array[$var];
	}

	function s_unset(&$array, $var)
	{
		if(isset($array[$var]))
			unset($array[$var]);
	}

	/**
	* redirects the client browser to the new location
	*/
	function redirect($url)
	{
		if(strpos($url, "\n")===false) {
			session_write_close();
			header('Location: '.$url);
			Swisdk::shutdown();
		} else
			SwisdkError::handle(new FatalError(sprintf(
				dgettext('swisdk', 'Invalid location specification: %s'), $url)));
	}

	/**
	* Returns the REQUEST value given by $var
	*/
	function getInputRaw($var, $default = null)
	{
		return s_get($_REQUEST, $var, $default);
	}

	/**
	 * Returns the REQUEST value given by $var, cleaning it to disable
		 * XSS attacks if value is a string or an array of strings
	 */
	function getInput($var, $default = null)
	{
		$value = getInputRaw($var, $default);
		if($value) {
			if(is_array($value))
				array_walk_recursive($value, 'cleanInputRef');
			else
				cleanInputRef($value);
		}
		return $value;
	}

	/**
	 * Clean HTML, hopefully disabling XSS attack vectors
	 */
	function cleanInput($value, $xss_protection=true)
	{
		if(!$value || is_numeric($value))
			return $value;
		if(!utf8_is_valid($value)) {
			require_once UTF8.'/utils/bad.php';
			$value = utf8_bad_replace($value);
		}
		if(!$xss_protection)
			return $value;
		require_once SWISDK_ROOT.'lib/contrib/externalinput.php';
		return popoon_classes_externalinput::basicClean($value);
	}

	function cleanInputRef(&$var)
	{
		$var = cleanInput($var);
	}

	/**
	 * Generates a ASCII-clean representation from any string passed in
	 *
	 * Good for generating slugs from article titles
	 *
	 * Don't rely on the implementation staying the same
	 */
	function slugify($string)
	{
		$fr = array('ä', 'ö', 'ü', 'ß', 'à','á','è','é','î','ô','ç');
		$to = array('ae','oe','ue','ss','a','a','e','e','i','o','c');

		return preg_replace('/[-\s]+/', '-',
			trim(
				preg_replace('/[^a-z0-9\s-]/', '',
					str_replace($fr, $to,
						utf8_strtolower($string)))));
	}

	/**
	 * generates a css class from a string (request uri, controller uri
	 * or whatever)
	 */
	function cssClassify($string)
	{
		$res = str_replace(' ', '-', trim(preg_replace(
			'/[^A-Za-z]+/', ' ', $string)));
		if($res)
			return $res;
		return 'root';
	}

	/**
	* Generates a string of random numbers and characters.
	*/
	function randomKeys($length)
  	{
		$key = '';
		$pattern = '1234567890abcdefghijklmnopqrstuvwxyz';
		for($i=0; $i<$length; $i++)
			$key .= $pattern{rand(0,35)};
		return $key;
	}

	/**
	 * generates a unique ID which may be used to guard against CSRF attacks
	 *
	 * http://en.wikipedia.org/wiki/Cross-site_request_forgery
	 */
	function guardToken($token = null)
	{
		if(!session_id())
			session_start();
		return sha1(session_id().Swisdk::config_value('core.token').$token);
	}

	/**
	 * return the backtrace as a string
	 */
	function backtrace($limit=false)
	{
		$bt = debug_backtrace();
		$str = "<pre><b>Backtrace:</b>\n";
		foreach($bt as $frame) {
			if($limit) {
				if(isset($frame['class'])
						&& $frame['class']=='SwisdkError'
						&& $frame['function']=='handle')
					$limit = false;
				else
					continue;
			}
			$str .= sprintf("%s() called at [%s]\n",
				(isset($frame['class'])?
				$frame['class'].$frame['type']:'').
				$frame['function'],
				isset($frame['file'])?$frame['file'].':'
				.$frame['line']:'');
		}
		$str .= '</pre>';
		return $str;
	}

	/**
	 * pipe a file to the browser
	 */
	function sendFile($path, $name, $mime='application/binary')
	{
		if(!file_exists($path))
			die('Invalid path given');

		if( preg_match( '/^(text|image)/i', $mime )  ) {
			$disposition = "inline";
		} else {
			$disposition = "attachment";
		}

		session_write_close();
		if (isset($_SERVER["HTTPS"])) {
			/**
			* We need to set the following headers to make downloads work using IE in HTTPS mode.
			*/
			header("Pragma: ");
			header("Cache-Control: ");
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
			header("Cache-Control: post-check=0, pre-check=0", false);
		} else if ($disposition == "attachment") {
			header("Cache-control: private");
		} else {
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");
		}
		header("Content-Type: $mime");
		header("Content-Disposition:$disposition; filename=\"".trim($name)."\"");
		header("Content-Description: ".trim($name));
		header("Content-Length: ".(string)(filesize($path)));
		header("Connection: close");
	
		$fp = fopen( $path, 'rb' );
		fpassthru( $fp );
		fclose( $fp );
		Swisdk::shutdown();
	}

	/**
	 * convert a timespan in seconds to a textual representation
	 */
	function stringifyTimespan($seconds)
	{
		static $timespans = null;

		$template = dgettext('swisdk', '%s ago');
		if($seconds>0)
			$template = dgettext('swisdk', 'in %s');
		$seconds = abs($seconds);

		if(!$timespans)
			$timespans = array_combine(
				explode(',',
					dgettext('swisdk', 'years,months,weeks,days,hours,min,sec')),
				array(86400*365, 86400*365/12, 86400*7, 86400, 3600, 60, 1));

		foreach($timespans as $type => $span)
			if($seconds>2*$span)
				return sprintf($template,
					intval($seconds/$span).' '.$type);

		return dgettext('swisdk', 'right now');
	}

	/**
	 * return a unique filenames for related files
	 *
	 * f.e.
	 * img.jpg => img_32kjh321kjh.jpg
	 * img.jpg (thumb) => img_thumb_3k3213kj1j2h.jpg
	 *
	 * If the filename has no extension, the uniquification token is simply appended
	 */
	function uniquifyFilename($fname, $token=null)
	{
		if($token)
			$token = '_'.$token.'_'.uniqid();
		else
			$token = '_'.uniqid();

		$pos = strrpos($fname, '.');
		if($pos===false)
			return $fname.$token;
		else
			return substr($fname, 0, $pos).$token.substr($fname, $pos);
	}

	/**
	 * sanitize a filename
	 */
	function sanitizeFilename($fname)
	{
		$fname = trim(preg_replace('/[^A-Za-z0-9\.\-_+]/', '_', $fname), '._');
		if(!$fname)
			$fname = 'none';
		return $fname;
	}

	/**
	 * add ellipsis to a string if it is too long
	 */
	function ellipsize($string, $len=100, $ellipsis=' &hellip;')
	{
		if(strlen($string)>$len)
			return substr($string, 0, $len).$ellipsis;
		return $string;
	}

	function dttmRange($dbobj)
	{
		$fmt = '%d.%m.%Y : %H:%M';

		if($dbobj->all_day)
			$fmt = '%d.%m.%Y';

		$start = strftime($fmt, $dbobj->start_dttm);

		if($dbobj->openend) {
			if($dbobj->all_day)
				return $start;
			else
				return $start.' &ndash; openend';
		} else if($dbobj->endless)
			return $start.' &ndash; endless';

		$end = strftime($fmt, $dbobj->end_dttm);

		if(substr($start, 0, 10)==substr($end, 0, 10))
			$end = substr($end, 13);

		return $start.($end?' &ndash; '.$end:'');
	}

	/**
	 * Pluralize a string
	 *
	 * Thanks to http://paulosman.com/node/23 and the Rails Inflector class
	 */
	function pluralize($string, $count=2)
	{
		if(intval($count)==1)
			return $string;

		$plural = array(
			array('/(quiz)$/i',			"$1zes"		),
			array('/^(ox)$/i',			"$1en"		),
			array('/([m|l])ouse$/i',		"$1ice"		),
			array('/(matr|vert|ind)ix|ex$/i',	"$1ices"	),
			array('/(x|ch|ss|sh)$/i',		"$1es"		),
			array('/([^aeiouy]|qu)y$/i',		"$1ies"		),
			array('/([^aeiouy]|qu)ies$/i',		"$1y"		),
			array('/(hive)$/i',			"$1s"		),
			array('/(?:([^f])fe|([lr])f)$/i',	"$1$2ves"	),
			array('/sis$/i',			"ses"		),
			array('/([ti])um$/i',			"$1a"		),
			array('/(buffal|tomat)o$/i',		"$1oes"		),
			array('/(bu)s$/i',			"$1ses"		),
			array('/(alias|status)$/i',		"$1es"		),
			array('/(octop|vir)us$/i',		"$1i"		),
			array('/(ax|test)is$/i',		"$1es"		),
			array('/s$/i',				"s"		),
			array('/$/',				"s"		)
		);

		$irregular = array(
			array('move',	'moves'),
			array('sex',	'sexes'),
			array('child',	'children'),
			array('man',	'men'),
			array('person',	'people')
		);

		$uncountable = array(
			'sheep',
			'fish',
			'series',
			'species',
			'money',
			'rice',
			'information',
			'equipment'
		);

		// save some time in the case that singular and plural are the same
		if(in_array(strtolower($string), $uncountable))
			return $string;

		// check for irregular singular forms
		foreach($irregular as $noun) {
			if(strtolower($string)==$noun[0])
				return $noun[1];
		}

		// check for matches using regular expressions
		foreach($plural as $pattern) {
			if(preg_match($pattern[0], $string))
				return preg_replace($pattern[0], $pattern[1], $string);
		}

		return $string;
	}

?>
