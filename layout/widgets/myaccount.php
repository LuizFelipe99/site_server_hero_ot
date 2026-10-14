<div class="well myaccount_widget widget" id="loginContainer">
	<div class="header">
		Bem-vindo, <?php if ($config['ServerEngine'] !== 'OTHIRE') echo $user_data['name']; else echo $user_data['id'];?>.
	</div>
	<div class="body">
		<ul class="linkbuttons">
			<li>
				<a href='myaccount.php'>Minha conta</a>
			</li>
			<li>
				<a href='createcharacter.php'>Criar personagem</a>
			</li>
			<li>
				<a href='changepassword.php'>Alterar senha</a>
			</li>
			<li>
				<a href='settings.php'>Configurações</a>
			</li>
			<li>
				<a href='logout.php'>Sair</a>
			</li>
		</ul>
	</div>
</div>
