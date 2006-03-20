<?php	
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >
	*	Copyright (c) 2005, ZuKe Software
	*	Distributed under the GNU Lesser General Public License or, at your opinion,
	*	under the same license as Sajax itself.
	*	Read the entire LGPL license text here: http://www.gnu.org/licenses/lgpl.html
	*	Read about the Sajax license: http://www.modernmethod.com/sajax/faq.phtml
	*								  (how much does it cost)
	*/


	// Many thanks to the sajax project http://www.modernmethod.com/sajax/
	// This is basically a copy-shuffle-pasted and objectified version of sajax v0.10
	// which was licensed under the new BSD license. 

	/*
	Example usage:
	
	// interface declaration
	interface ITest {
		// NOTE! the "method_"-prefix is mandatory for ajax request method
		// interface definitions and implementations!
		// All methods starting with method_ are added to the list of available
		// ajax requests.
		public function method_hello_world($your_name);
	}
	
	// server class
	class TestImpl extends Ajax_Server implements ITest {
		public function method_hello_world($your_name) {
			return "Hello, $your_name!";
		}
	}
	
	// usage on server side
	$server = new TestImpl();
	$server->handle_request();
	
	// usage on client side
	$client = new Ajax_Client(ITest);
	// or
	$client = new Ajax_Client(ITest, 'http://host/path/to/server.php');
	
	then, inside the <head> section of your html page:
	
	<script type="text/javascript">
	// show main ajax code
	<?php $client->show_javascript(); ?>
	
	function hello_world_callback(retval)
	{
		// do something with the returned value
	}
	
	function hello_world()
	{
		// do something, f.e. get values from a form
		
		// execute the ajax request passing the parameter as specified
		// in the interface.
		// The callback which will be called upon completion of the ajax
		// request must be passed as the last parameter.
		// NOTE! the "x_"-prefixed function is generated automatically
		x_hello_world(name, hello_world_callback);
	}
	</script> 
		
	*/

	
	class Ajax_Server {
		// updates the method list using introspection
		protected function update_export_list()
		{
			$this->export_list = array();
			$methods = get_class_methods(get_class($this));
			
			foreach($methods as &$method) {
				if(substr($method, 0, 7)=='method_' && $m = substr($method, 7)) {
					$this->export_list[] = $m;
				}
			}
		}

		public function handle_request()
		{
			/*preg_match_all('/O:[0-9]+:"(.*)"/U', $string, $matches, PREG_PATTERN_ORDER);
   			if(preg_match('(^|:|\{)O:\d+:(.*?):', $serializedString)) {
				echo "-:cannot execute request: forbidden strings found";
				exit();
			}*/
			
			$func_name = $_POST['rs'];
			$args = $_POST['rsargs'];
			if(empty($args)) {
				$args = array();
			}
			
			if($this->export_list===null) {
				$this->update_export_list();
			}

			if(!in_array($func_name, $this->export_list)) {
				echo "-:$func_name not callable";
			} else {
				$method = 'method_' . $func_name;
				echo "+:" . call_user_func_array(array(&$this, $method), $args);
			}
			exit();
		}
	}

	class Ajax_Client {

		public function __construct($interface, $remote_uri = null, $debug_mode = 0)
		{
			$this->export_list = array();
			$methods = get_class_methods($interface);
			
			foreach($methods as &$method) {
				if(substr($method, 0, 7)=='method_' && $m = substr($method, 7)) {
					$this->export_list[] = $m;
				}
			}
			
			if($remote_uri==null) {
				$this->remote_uri = $_SERVER['REQUEST_URI'];
			} else {
				$this->remote_uri = $remote_uri;
			}
			
			$this->debug_mode = $debug_mode;
		}
		
		protected $remote_uri = null;
		protected $debug_mode = 0;
		
		// javascript escape a value
		public function escape($val)
		{
			return str_replace('"', '\\\\"', $val);
		}

		public function show_javascript()
		{
			echo $this->get_javascript();
		}

		public function get_javascript()
		{
			$html = '';
			if (!$this->javascript_shown) {
				$html .= $this->get_common_js();
				$this->javascript_shown = true;
			}

			foreach ($this->export_list as $func_name) {
				$html .= <<<EOD
// wrapper for {$func_name}
function x_{$func_name}() {
	sajax_do_call('{$func_name}',x_{$func_name}.arguments);
}
EOD;
			}
			return $html;
		}

		protected $export_list = null;
		protected $javascript_shown = false;
		
		protected function get_common_js() {
			
			
			$debug_mode_bool = $this->debug_mode?'true':'false';
			
			return <<<EOD
// remote scripting library
// (c) copyright 2005 modernmethod, inc
var sajax_debug_mode = {$debug_mode_bool};

function sajax_debug(text){if (sajax_debug_mode)alert("RSD: " + text);}

function sajax_init_object() {
	sajax_debug("sajax_init_object() called..")
	var A;
	try{A=new ActiveXObject("Msxml2.XMLHTTP");}catch (e){try{A=new ActiveXObject("Microsoft.XMLHTTP");
	}catch(oc){A=null;}}
	if(!A && typeof XMLHttpRequest!='undefined')A=new XMLHttpRequest();
	if(!A)sajax_debug("Could not create connection object.");
	return A;
}

function sajax_do_call(func_name, args) {
	var i,x,n,uri,post_data;

	uri='{$this->remote_uri}';
	post_data="rs="+escape(func_name);
	for(i=0;i<args.length-1;++i){post_data=post_data+"&rsargs[]="+escape(args[i]);}
	
	x = sajax_init_object();
	x.open('POST', uri, true);
	x.setRequestHeader("Method", "POST "+uri+" HTTP/1.1");
	x.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

	x.onreadystatechange = function() {
		if (x.readyState != 4)return;
		sajax_debug("received " + x.responseText);
		var status,data;
		status=x.responseText.charAt(0);
		data=x.responseText.substring(2);
		if(status=="-")
			alert("Error: " + data);
		else
			args[args.length-1](data);
	}
	x.send(post_data);
	sajax_debug(func_name + " uri = " + uri + "/post = " + post_data);
	sajax_debug(func_name + " waiting..");
	delete x;
}
EOD;
		}
	}
		
		
?>
