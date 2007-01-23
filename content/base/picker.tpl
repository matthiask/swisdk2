{extends file="base.admin"}

{block name="head"}
{literal}
<style type="text/css">
.s-table tbody tr:hover {
	background: #cfc7b7;
	cursor: pointer;
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
{/block}

{block name="body"}
{$content}
{/block}
