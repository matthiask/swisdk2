<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>{$smarty.get.class} Picker</title>
<link rel="stylesheet" type="text/css" href="/media/admin/admin.css" />
{literal}
<style type="text/css">
#body .s-table tbody tr:hover {
	background: #657ba8;
	cursor: pointer;
}

#body label.sf-label {
	text-align: left;
}

#body form dt {
	width: 80px;
}

#body form dd {
	margin-left: 90px;
}

#body form input,
#body form button,
#body form select,
#body form textarea {
	width: 230px;
}
</style>
<script type="text/javascript">
//<![CDATA[
function do_select(elem, val, str)
{
	opener.select_{/literal}{$element}{literal}(val, str);
	this.close();
}
//]]>
</script>
{/literal}
{swisdk_libraries_html}
</head>

<body id="body">
{$content}
</body>
</html>
