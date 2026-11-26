<?php
	$top_players_cache = new Cache('engine/cache/topPlayersWithOutfits');
	if ($top_players_cache->hasExpired()) {
		$top_players = mysql_select_multi('SELECT `name`, `level`, `experience`, `looktype`, `lookaddons`, `lookhead`, `lookbody`, `looklegs`, `lookfeet` FROM `players` WHERE `group_id` < ' . $config['highscore']['ignoreGroupId'] . ' AND ' . sample_characters_exclusion_sql() . ' ORDER BY `level` DESC, `experience` DESC LIMIT 5;');
		$top_players_cache->setContent($top_players);
		$top_players_cache->save();
	} else {
		$top_players = $top_players_cache->load();
	}

	$should_show_outfits = (bool) $config['show_outfits']['highscores'];

	$build_top_player_outfit_url = function ($outfit_image_server, $player) {
		$outfit_parameters = array(
			'id' => $player['looktype'],
			'addons' => $player['lookaddons'],
			'head' => $player['lookhead'],
			'body' => $player['lookbody'],
			'legs' => $player['looklegs'],
			'feet' => $player['lookfeet']
		);
		return $outfit_image_server . '?' . http_build_query($outfit_parameters);
	};
?>
<div class="well widget top_players_widget">
	<div class="header">
		Top 5 jogadores
	</div>
	<div class="body">
		<?php if (!$top_players): ?>
			<p class="top-players-empty">Nenhum jogador no ranking ainda.</p>
		<?php else: ?>
			<ol class="top-players-list">
				<?php foreach ($top_players as $player_index => $player): ?>
					<?php
						$player_rank = $player_index + 1;
						$player_profile_url = 'characterprofile.php?name=' . urlencode($player['name']);
						$player_display_name = htmlspecialchars($player['name']);
					?>
					<li class="top-player top-player-rank-<?php echo $player_rank; ?>">
						<span class="top-player-rank"><?php echo $player_rank; ?></span>
						<?php if ($should_show_outfits): ?>
							<a class="top-player-outfit" href="<?php echo $player_profile_url; ?>">
								<img src="<?php echo htmlspecialchars($build_top_player_outfit_url($config['show_outfits']['imageServer'], $player)); ?>" alt="" loading="lazy">
							</a>
						<?php endif; ?>
						<span class="top-player-info">
							<a class="top-player-name" href="<?php echo $player_profile_url; ?>"><?php echo $player_display_name; ?></a>
							<span class="top-player-level">Level <?php echo $player['level']; ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>
</div>
