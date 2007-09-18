<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<body>
<head>
<title>{block name="title"}{$title}{/block}</title>
{swisdk_libraries_html}
<style type="text/css">
{literal}
body { margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; font-size: 12px; }
#wrap { margin: 50px auto; padding: 0; width: 500px; background: #dfd8c7; border: 1px solid #cfc7b7; }
#head, #foot { position: relative; height: 11px; }
#head #version { position: absolute; top: 0px; right: 3px; font-size: 9px; }
#foot #date { position: absolute; bottom: 1px; left: 3px; font-size: 9px; }
#body { background: #efe8d7; margin: 1px; font-size: 12px; }
h1 { margin: 0; padding: 2px 5px 3px 4px; font-size: 16px; border-bottom: 1px solid #cfc7b7; }
fieldset { border: none; margin: 0; padding: 0; }
label { width: 220px; text-align: right; display: block; float: left; clear: left; margin: 0; padding: 7px 5px 3px 5px; }
input { float: left; border: 1px solid #cfc7b7; margin: 5px; padding: 3px; }
input[type="submit"] { clear: left; margin: 5px 5px 5px 236px; padding: 2px 10px; }
a { color: #000; text-decoration: none; }
a:hover { text-decoration: underline; }
{/literal}
</style>
</head>

<body>

<div id="wrap">

	<div id="head">
		<div id="version">
			<a href="http://spinlock.ch/projects/swisdk/">SWISDK v2.2</a>
		</div>
	</div>

	<div id="body">

		<h1>{block name="title"}{$title}{/block}</h1>

		{block name="content"}
		{$content}
		{/block}

		<br style="line-height:0;font-size:1px" />

	</div>

	<div id="foot">
		<div id="date">
			{$smarty.now|date_format:"%d. %B %Y"}
		</div>
	</div>

</div>

{block name="messages"}
{$messages}
{/block}

</body>
</html>
