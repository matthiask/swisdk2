<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>{swisdk_runtime_value key="website.title"}</title>
<script type="text/javascript" src="{webroot key=js}/util.js"></script>
<script type="text/javascript" src="{webroot key=js}/scriptaculous/prototype.js"></script>
<style type="text/css">
{literal}
body {
	font-family: Helvetica, Arial, sans-serif;
	font-size: 14px;
	margin: 0;
	padding: 0;
}

#wrap {
	background: #dfd8c7;
	border: 1px solid #cfc7b7;
	margin: 2px;
}

#head, #foot {
	position: relative;
	height: 13px;
}

#head #version {
	position: absolute;
	top: 0px;
	right: 3px;
	font-size: 9px;
}

#foot #date {
	position: absolute;
	bottom: 1px;
	left: 3px;
	font-size: 9px;
}

#body {
	background: #efe8d7;
	margin: 1px;
	position: relative;
}

#body #logout {
	position: absolute;
	top: 5px;
	right: 3px;
}

h1 {
	margin: 0;
	padding: 2px 5px 3px 4px;
	font-size: 16px;
	border-bottom: 1px solid #cfc7b7;
}

h2 {
	margin: 0;
	padding: 12px 5px 3px 5px;
	font-size: 15px;
}

h2 a {
	color: #000;
}

label.sf-label {
	width: 150px;
	text-align: right;
	display: block;
	float: left;
	clear: left;
	margin: 0;
	padding: 7px 5px 3px 5px;
}

div.sf-element-ext {
	margin-left: 160px;
}

div.sf-element.even {
	background: #dfd8c7;
}

input, button, select, textarea {
	margin: 5px;
	padding: 3px;
	width: 530px;
}

input[type="checkbox"] {
	width: auto;
}

input[type="submit"], button {
	width: auto;
	margin: 5px 5px 5px 3px;
	padding: 2px 10px;
}

textarea {
	width: 460px;
	height: 200px;
	font-size: 12px;
}

fieldset fieldset {
	margin-top: 10px;
}

#head a, #foot a {
	color: #000;
	text-decoration: none;
}

a:hover {
	text-decoration: underline;
}



#navigation {
	float: left;
	width: 150px;
}

#navigation ul {
	list-style: none;
	margin: 0;
	padding: 10px 0 0 0;
}

#navigation li {
	padding: 2px 0 2px 5px;
	margin: 0;
}



#content {
	margin-left: 150px;
	border-left: 1px solid #cfc7b7;
	padding: 0 10px;
}

a {
	color: #00f;
	text-decoration: none;
}

a:hover {
	text-decoration: underline;
}

a img {
	border: none;
}

hr {
	border: none;
	border-bottom: 1px solid #EDE8E2;
}

.s-table {
	width: 100%;
	padding-bottom: 10px;
}



tr.severity-critical {
	background: #f44;
}

tr.severity-major {
	background: #fe8;
}

tr.severity-normal {
}

tr.severity-minor {
	color: #888;
}

tr.severity-trivial {
	color: #888;
}

tr.severity-enhancement {
	color: #888;
}


.s-table tbody tr {
	background: #f7f0df;
}

.s-table tbody tr.odd {
	background: #fff;
}

.s-table tbody tr.checked {
	background: #dfd8c7;
}

.s-table thead th {
	background: #efebe7;
	border: 1px solid #cfc7b7;
	padding: 0;
}

.s-table thead th a {
	display: block;
	padding: 3px 1px;
}


.s-table tfoot {
	text-align: right;
}

.s-table tfoot td {
	background: #efebe7;
	border: 1px solid #cfc7b7;
}

.s-table tr.c-new {
	color: #080;
}

.s-table tr.c-maybe-spam {
	color: #800;
}

.s-table tr.c-spam {
	color: #888;
}

.s-table tr.c-moderated {
	color: #888;
}


{/literal}

</style>
{block name="head"}
{/block}
</head>

<body>

<div id="wrap">

	<div id="head">

		<div id="version">
			<a href="http://spinlock.ch/projects/swisdk/">SWISDK v2.2</a>
		</div>

	</div>

	<div id="body">
		{block name="body"}
		<div id="logout">
			<a href="?logout=1">Log out</a>
		</div>

		<h1>{swisdk_runtime_value key="website.title"}</h1>

		<div id="navigation">
			{literal}
			<script type="text/javascript">
			function n_toggle(elem)
			{
				var group = elem.parentNode.parentNode;
				var list_id = group.id+'-list';

				$A($('navigation').getElementsByTagName('ul')).each(function(elem){
					if(elem.id==list_id)
						elem.style.display = (elem.style.display=='block'?'none':'block');
					else
						elem.style.display = 'none';
				});

				return false;
			}
			</script>
			{/literal}

			{block name="navigation"}
			{/block}
		</div>

		<div id="content">
			{block name="content"}
			{$content}
			{/block}
		</div>

		<div style="clear:both;height:0px">&nbsp;</div>

		{/block}
	</div>

	<div id="foot">
		<div id="date">
			{$smarty.now|date_format:"%d. %B %Y"}
		</div>
	</div>

</div>

</body>
</html>
