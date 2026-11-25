<nav class="site-nav">
	<div class="container">
		<a class="nav-brand" href="/"><img src="layout/img/favicon.ico?v=2" alt=""><?php echo $layout_server_name; ?></a>

		<input type="checkbox" id="navToggle" class="nav-toggle-input">
		<label for="navToggle" class="nav-toggle-button" aria-label="Abrir menu"><i class="fa fa-bars"></i></label>

		<div class="nav-collapse">
			<div class="pull-left">
				<ul>
					<li><a href="/"><i class="fa fa-home"></i> Início</a>
						<ul>
							<li><a href="/">Notícias</a> </li>
							<li><a href="events.php">Eventos</a> </li>
							<li><a href="changelog.php">Changelog</a> </li>
						</ul>
					</li>
					<li><a id="accountLink" href="myaccount.php"><i class="fa fa-user-circle"></i> Conta</a>
						<ul>
							<li><a href="downloads.php">Downloads</a> </li>
							<li><a href="sub.php?page=recover">Recuperar conta</a> </li>
							<li><a href="myaccount.php">Minha conta</a> </li>
							<li><a href="register.php">Criar conta</a> </li>
						</ul>
					</li>
					<li><a href="onlinelist.php"><i class="fa fa-users"></i> Comunidade</a>
						<ul>
							<li><a href="sub.php?page=search">Personagens</a> </li>
							<li><a href="forum.php">Fórum</a> </li>
							<li><a href="guilds.php">Guilds</a> </li>
							<li><a href="highscores.php">Highscores</a> </li>
							<li><a href="houses.php">Casas</a> </li>
							<li><a href="killers.php">Top killers</a> </li>
							<li><a href="deaths.php">Últimas mortes</a> </li>
							<li><a href="onlinelist.php">Quem está online</a> </li>
						</ul>
					</li>
					<li><a href="serverinfo.php"><i class="fa fa-book"></i> Biblioteca</a>
						<ul>
							<?php if ($config['items'] == true): ?>
								<li><a href="items.php">Itens</a> </li>
							<?php endif; ?>
							<li><a href="serverinfo.php">Informações do servidor</a> </li>
							<li><a href="spells.php">Magias</a> </li>
							<li><a href="mechanics.php">Mecânicas</a> </li>
						</ul>
					</li>
					<li><a href="support.php"><i class="fa fa-info-circle"></i> Suporte</a>
						<ul>
							<li><a href="helpdesk.php">Helpdesk</a> </li>
							<li><a href="sub.php?page=recover">Recuperar conta</a> </li>
						</ul>
					</li>
					<li><a href="buypoints.php"><i class="fa fa-heart"></i> Doar</a>
						<?php if ($config['shop_auction']['characterAuction']): ?>
							<ul>
								<li><a href="buypoints.php">Doar</a> </li>
								<li><a href="auctionChar.php">Leilão de personagens</a> </li>
							</ul>
						<?php endif; ?>
					</li>
				</ul>
			</div>
			<div class="pull-right nav-account">
				<ul>
					<li><a href="<?php echo htmlspecialchars($layout_discord_url); ?>" class="nav-discord" target="_blank" rel="noopener noreferrer"><?php echo render_discord_icon(); ?> Discord</a> </li>
					<?php if (user_logged_in() === true): ?>
						<li><a href="myaccount.php" class="nav-account-link"><i class="fa fa-user"></i> Minha conta</a> </li>
						<li><a href="logout.php" class="nav-cta-ghost"><i class="fa fa-sign-out"></i> Sair</a> </li>
					<?php else: ?>
						<li><a href="sub.php?page=loginhelp" class="modIcon loginBtn"><i class="fa fa-lock"></i><i class="fa fa-unlock"></i> Entrar</a> </li>
						<li><a href="register.php" class="nav-cta"><i class="fa fa-key"></i> Criar conta</a> </li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	</div>
</nav>
