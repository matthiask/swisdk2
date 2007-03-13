<div id="comments">
<a name="comments"></a>
{if count($comments)}
{foreach from=$comments item=c}
<div class="comment">
	<a name="comment{$c.comment_id}"></a>
	<div class="comment_title"><strong>
	{if $c.comment_author_url}
		<a href="{$c.comment_author_url}">{$c.comment_author}</a>
	{else}
		{$c.comment_author}
	{/if}
	</strong> <span>wrote on {$c.comment_creation_dttm|date_format:"%d.%m.%Y, %H:%M"}:</span></div>
	<div class="comment_text">{$c.comment_text|markdown}</div>
</div>
{/foreach}
{else}
<p>No comments yet!</p>
{/if}
{$commentform}
</div>
