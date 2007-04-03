<div id="comments">
<a name="comments"></a>
{if count($comments)}

<ul class="pagelinks">
{foreach from=$paging.links key=page item=link}
	<li>
		<a style="border:1px solid #000;padding:5px"
			href="?{$link}"{if $paging.current==$page} class="current"{/if}>{$page}</a>
	</li>
{/foreach}
</ul>
<br style="clear:both" />

{foreach from=$comments item=c}
<div class="comment">
	<a name="comment{$c->id}"></a>
	<div class="comment_title"><strong>
	{if $c->author_url}
		<a href="{$c->author_url}">{$c->author}</a>
	{else}
		{$c->author}
	{/if}
	</strong> <span>wrote on {$c->creation_dttm|date_format:"%d.%m.%Y, %H:%M"}:</span></div>
	<div class="comment_text">{$c->text|markdown}</div>
</div>
{/foreach}
{else}
<p>No comments yet!</p>
{/if}

{$commentform}

{if $mode=='maybe-spam'}
	<p>Your message has been classified as spam. A moderator will aprove it if it's not.</p>
{elseif $mode=='accepted'}
	<p>Thanks!</p>
{/if}
</div>
