{{ extends template="swisdk.box" }}

{{ assign var="title" value="Unauthorized" }}

{{ block name="content" }}
<p>
You can try going <a href="javascript:back(-1)">one step back</a> in your history.
</p>
{{ /block }}
