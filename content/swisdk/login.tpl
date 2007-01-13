{extends template="swisdk.box"}

{assign var="title" value="Login"}

{block name="content"}
<form action="." method="post">
	<fieldset>

		<label for="login_username">Username</label>
		<input type="text" name="login_username" id="login_username" />

		<label for="login_password">Password</label>
		<input type="password" name="login_password" id="login_password" />

		<input type="submit" value="Login" />
	</fieldset>
</form>
{/block}
