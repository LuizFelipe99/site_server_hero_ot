<div class="well loginContainer widget" id="loginContainer">
	<div class="header">
		Entrar
	</div>
	<div class="body">
		<form class="loginForm" action="login.php" method="post">
			<div class="well">
				<label for="login_username">Usuário</label> <input type="text" name="username" id="login_username">
			</div>
			<div class="well">
				<label for="login_password">Senha</label> <input type="password" name="password" id="login_password">
			</div>
			<?php if ($config['twoFactorAuthenticator']): ?>
				<div class="well">
					<label for="login_password">Token</label> <input type="password" name="authcode">
				</div>
			<?php endif; ?>
			<div class="well">
				<input type="submit" value="Entrar" class="submitButton">
			</div>
			<?php
				/* Form file */
				Token::create();
			?>
			<center>
				<h3><a href="register.php">Criar nova conta</a></h3>
				<p>Esqueceu o <a href="recovery.php?mode=username">usuário</a> ou a <a href="recovery.php?mode=password">senha</a>?</p>
			</center>
		</form>
	</div>
</div>
