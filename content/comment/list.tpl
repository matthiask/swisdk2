<div id="comments">
<a name="comments"></a>
<h2>Comments on this item</h2>
{if count($comments)}
{foreach from=$comments item=c}
<div class="comment">
	<a name="comment{$c.comment_id}"></a>
	<p><strong>
	{if $c.comment_author_url}
		<a href="{$c.comment_author_url}">{$c.comment_author}</a>
	{else}
		{$c.comment_author}
	{/if}
	</strong> wrote on {$c.comment_creation_dttm|date_format:"%d.%m.%Y, %H:%M"}:</p>
	<p>{$c.comment_text|markdown}</p>
</div>
{/foreach}
{else}
<p>No comments yet!</p>
{/if}
{$commentform}
</div>
