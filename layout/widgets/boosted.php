<?php
	require_once __DIR__ . '/../../engine/function/boosted.php';

	$boosted_cache = new Cache('engine/cache/boostedCreatures');
	$boosted_cache->setExpiration(120);

	if ($boosted_cache->hasExpired()) {
		$boosted_creatures = describeBoostedCreatures();
		$boosted_cache->setContent($boosted_creatures);
		$boosted_cache->save();
	} else {
		$boosted_creatures = $boosted_cache->load();
	}

	$boosted_card_labels = array(
		'creature' => 'Criatura',
		'boss' => 'Boss'
	);

	$has_boosted_creatures = is_array($boosted_creatures) && ($boosted_creatures['creature'] !== null || $boosted_creatures['boss'] !== null);
?>
<?php if ($has_boosted_creatures): ?>
<div class="well widget boosted_widget">
	<div class="header">
		Boostados de hoje
	</div>
	<div class="body">
		<div class="boosted-grid">
			<?php foreach ($boosted_card_labels as $boosted_kind => $boosted_card_label): ?>
				<?php
					$boosted_entry = $boosted_creatures[$boosted_kind];

					if ($boosted_entry === null) {
						continue;
					}

					$boosted_image_url = $boosted_entry['localImagePath'] !== null ? $boosted_entry['localImagePath'] : $boosted_entry['externalImageUrl'];
					$boosted_image_size = calculateBoostedImageDisplaySize($boosted_entry['localImagePath']);
					$boosted_display_name = htmlspecialchars($boosted_entry['name']);
				?>
				<figure class="boosted-card boosted-card-<?php echo $boosted_kind; ?>">
					<figcaption class="boosted-card-label"><?php echo $boosted_card_label; ?></figcaption>
					<div class="boosted-card-portrait">
						<?php if ($boosted_image_url !== null): ?>
							<img src="<?php echo htmlspecialchars($boosted_image_url); ?>" alt="<?php echo $boosted_display_name; ?>" loading="lazy"<?php if ($boosted_image_size !== null): ?> width="<?php echo $boosted_image_size['width']; ?>" height="<?php echo $boosted_image_size['height']; ?>"<?php endif; ?>>
						<?php endif; ?>
					</div>
					<span class="boosted-card-name"><?php echo $boosted_display_name; ?></span>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php endif; ?>
