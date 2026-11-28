<?php
	$home_rates = home_describe_rates();
	$home_next_server_save = home_next_server_save();
	$home_character_count = home_count_characters();
	$home_latest_news = array_slice(home_load_latest_news(), 0, 2);
	$home_updates = array_slice(home_load_site_updates(), 0, HOME_UPDATES_ON_HOME);
	$home_calendar = home_build_calendar_month();
	$home_active_events = list_active_server_events();
	$home_latest_deaths = home_list_latest_deaths();
	$home_newest_characters = home_list_newest_characters();
	$home_week_day_labels = array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb');

	$home_features = array(
		array('icon' => 'magic', 'title' => 'Helper próprio', 'text' => 'Cura, shooter, alvo, cavebot e posturas dentro do client. Sem bot externo.'),
		array('icon' => 'diamond', 'title' => 'Gem Bag', 'text' => 'Encaixe gemas com crítico, velocidade de ataque, vida, mana e skills, além do equipamento.'),
		array('icon' => 'fire', 'title' => 'Elemental Build', 'text' => 'Complete missões e distribua pontos por elemento para aumentar o seu dano.'),
		array('icon' => 'shield', 'title' => 'Sala de Bosses', 'text' => 'Bosses em luta direta, sem mecânicas de arena para atrapalhar.'),
		array('icon' => 'trophy', 'title' => 'Battle Pass', 'text' => 'Temporadas com recompensas para quem joga todo dia.'),
		array('icon' => 'list-alt', 'title' => 'Tasks', 'text' => 'Tasks de caça com recompensas que acompanham o nível dos monstros.'),
	);
?>
<section class="home">
	<div class="home-stats">
		<div class="home-stat">
			<span class="home-stat-value"><span class="status-dot"></span><?php echo (int) $players_online_count; ?></span>
			<span class="home-stat-label">Jogadores online</span>
		</div>
		<div class="home-stat">
			<span class="home-stat-value"><?php echo number_format($home_character_count, 0, ',', '.'); ?></span>
			<span class="home-stat-label">Personagens criados</span>
		</div>
		<?php if ($home_rates['experienceStart'] !== null): ?>
			<div class="home-stat">
				<span class="home-stat-value"><?php echo $home_rates['experienceStart']; ?>x</span>
				<span class="home-stat-label">Exp inicial</span>
			</div>
		<?php endif; ?>
		<?php if ($home_rates['loot'] !== null): ?>
			<div class="home-stat">
				<span class="home-stat-value"><?php echo $home_rates['loot']; ?>x</span>
				<span class="home-stat-label">Loot</span>
			</div>
		<?php endif; ?>
		<div class="home-stat">
			<span class="home-stat-value"><?php echo $home_next_server_save->format('H:i'); ?></span>
			<span class="home-stat-label">Server save <?php echo home_describe_time_until($home_next_server_save); ?></span>
		</div>
	</div>

	<div class="home-section">
		<div class="home-section-header">
			<h2><i class="fa fa-bullhorn"></i> Novidades</h2>
			<a class="home-section-link" href="novidades.php">Ver todas <i class="fa fa-angle-right"></i></a>
		</div>

		<?php foreach ($home_latest_news as $home_news_post): ?>
			<article class="home-update home-update-news">
				<header>
					<span class="home-update-tag">Notícia</span>
					<time><?php echo getClock($home_news_post['date'], true); ?></time>
				</header>
				<h3><a href="index.php?view=<?php echo (int) $home_news_post['id']; ?>"><?php echo htmlspecialchars(home_strip_news_markup($home_news_post['title'])); ?></a></h3>
				<p><?php echo htmlspecialchars(home_preview_news_text($home_news_post['text'])); ?></p>
				<a class="home-update-more" href="index.php?view=<?php echo (int) $home_news_post['id']; ?>">Ler mais <i class="fa fa-angle-right"></i></a>
			</article>
		<?php endforeach; ?>

		<?php foreach ($home_updates as $home_update_index => $home_update): ?>
			<article class="home-update<?php if ($home_update_index === 0) echo ' is-latest'; ?>">
				<header>
					<span class="home-update-tag"><?php echo htmlspecialchars($home_update['tag']); ?></span>
					<time><?php echo home_format_update_date($home_update['date']); ?></time>
				</header>
				<h3><?php echo htmlspecialchars($home_update['title']); ?></h3>
				<ul>
					<?php foreach (array_slice($home_update['items'], 0, HOME_UPDATE_ITEMS_ON_HOME) as $home_update_item): ?>
						<li><?php echo htmlspecialchars($home_update_item); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php $home_hidden_item_count = count($home_update['items']) - HOME_UPDATE_ITEMS_ON_HOME; ?>
				<?php if ($home_hidden_item_count > 0): ?>
					<a class="home-update-more" href="novidades.php">+<?php echo $home_hidden_item_count; ?> <?php echo $home_hidden_item_count === 1 ? 'mudança' : 'mudanças'; ?> <i class="fa fa-angle-right"></i></a>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="home-section">
		<div class="home-section-header">
			<h2><i class="fa fa-calendar"></i> Calendário</h2>
			<a class="home-section-link" href="events.php">Eventos <i class="fa fa-angle-right"></i></a>
		</div>

		<div class="home-calendar-layout">
			<div class="home-calendar">
				<div class="home-calendar-title"><?php echo $home_calendar['title']; ?></div>
				<div class="home-calendar-grid">
					<?php foreach ($home_week_day_labels as $home_week_day_label): ?>
						<span class="home-calendar-weekday"><?php echo $home_week_day_label; ?></span>
					<?php endforeach; ?>
					<?php for ($home_blank_day = 0; $home_blank_day < $home_calendar['leadingBlankDays']; $home_blank_day++): ?>
						<span class="home-calendar-day is-blank"></span>
					<?php endfor; ?>
					<?php foreach ($home_calendar['days'] as $home_calendar_day): ?>
						<?php
							$home_day_classes = 'home-calendar-day';
							if ($home_calendar_day['isToday']) $home_day_classes .= ' is-today';
							if ($home_calendar_day['isPast']) $home_day_classes .= ' is-past';
							if (!empty($home_calendar_day['events'])) $home_day_classes .= ' has-event';
							$home_day_titles = array();
							foreach ($home_calendar_day['events'] as $home_day_event) $home_day_titles[] = $home_day_event['name'];
						?>
						<span class="<?php echo $home_day_classes; ?>"<?php if (!empty($home_day_titles)): ?> title="<?php echo htmlspecialchars(implode(', ', $home_day_titles)); ?>"<?php endif; ?>>
							<?php echo $home_calendar_day['number']; ?>
							<?php if (!empty($home_calendar_day['events'])): ?>
								<span class="home-calendar-dots">
									<?php foreach (array_slice($home_calendar_day['events'], 0, 3) as $home_day_event): ?>
										<i style="background: <?php echo htmlspecialchars($home_day_event['color']); ?>;"></i>
									<?php endforeach; ?>
								</span>
							<?php endif; ?>
						</span>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="home-calendar-agenda">
				<h3>Todo dia</h3>
				<ul class="home-agenda-list">
					<li><i class="fa fa-refresh"></i><div><b>03:00 · Server save</b><span>O servidor reinicia e aplica as atualizações.</span></div></li>
					<li><i class="fa fa-star"></i><div><b>03:00 · Novos boostados</b><span>Criatura e boss boostados trocam a cada server save.</span></div></li>
				</ul>

				<h3>Eventos do mês</h3>
				<?php if (empty($home_calendar['events'])): ?>
					<p class="home-muted">Nenhum evento de bônus marcado neste mês. Os eventos são anunciados no <a href="<?php echo htmlspecialchars($layout_discord_url); ?>" target="_blank" rel="noopener noreferrer">Discord</a>.</p>
				<?php else: ?>
					<ul class="home-agenda-list">
						<?php foreach ($home_calendar['events'] as $home_month_event): ?>
							<?php $home_event_is_active = in_array($home_month_event, $home_active_events, false); ?>
							<li>
								<i class="fa fa-circle" style="color: <?php echo htmlspecialchars($home_month_event['color']); ?>;"></i>
								<div>
									<b><?php echo htmlspecialchars($home_month_event['name']); ?><?php if ($home_event_is_active): ?> <span class="home-live">ao vivo</span><?php endif; ?></b>
									<span><?php echo $home_month_event['startDate']->format('d/m'); ?> a <?php echo $home_month_event['endDate']->format('d/m'); ?><?php if ($home_month_event['description'] !== ''): ?> · <?php echo htmlspecialchars($home_month_event['description']); ?><?php endif; ?></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="home-section">
		<div class="home-section-header">
			<h2><i class="fa fa-bolt"></i> Acontecendo no servidor</h2>
		</div>

		<div class="home-activity">
			<div class="home-activity-column">
				<h3><i class="fa fa-user-plus"></i> Novos aventureiros</h3>
				<?php if (empty($home_newest_characters)): ?>
					<p class="home-muted">Seja o primeiro a criar um personagem.</p>
				<?php else: ?>
					<ul class="home-activity-list">
						<?php foreach ($home_newest_characters as $home_new_character): ?>
							<li>
								<a href="characterprofile.php?name=<?php echo urlencode($home_new_character['name']); ?>"><?php echo htmlspecialchars($home_new_character['name']); ?></a>
								<span><?php echo htmlspecialchars(vocation_id_to_name($home_new_character['vocation'])); ?> · level <?php echo (int) $home_new_character['level']; ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="home-activity-column">
				<h3><i class="fa fa-crosshairs"></i> Últimas mortes</h3>
				<?php if (empty($home_latest_deaths)): ?>
					<p class="home-muted">Ninguém morreu ainda. Por enquanto.</p>
				<?php else: ?>
					<ul class="home-activity-list">
						<?php foreach ($home_latest_deaths as $home_death): ?>
							<li>
								<a href="characterprofile.php?name=<?php echo urlencode($home_death['name']); ?>"><?php echo htmlspecialchars($home_death['name']); ?></a>
								<span>level <?php echo (int) $home_death['level']; ?> · por <?php echo htmlspecialchars($home_death['killed_by']); ?> · <?php echo home_describe_time_ago($home_death['time']); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<a class="home-section-link" href="deaths.php">Ver todas <i class="fa fa-angle-right"></i></a>
			</div>
		</div>
	</div>

	<div class="home-section">
		<div class="home-section-header">
			<h2><i class="fa fa-compass"></i> O que te espera no Noxum</h2>
			<a class="home-section-link" href="serverinfo.php">Rates e stages <i class="fa fa-angle-right"></i></a>
		</div>
		<div class="home-features">
			<?php foreach ($home_features as $home_feature): ?>
				<div class="home-feature">
					<i class="fa fa-<?php echo $home_feature['icon']; ?>"></i>
					<h3><?php echo $home_feature['title']; ?></h3>
					<p><?php echo $home_feature['text']; ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
