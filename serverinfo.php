<?php require_once 'engine/init.php'; include 'layout/overall/header.php';

function server_info_resolve_server_path($configuredPath) {
	$candidatePaths = array($configuredPath, '/home/noxum/server');
	foreach ($candidatePaths as $candidatePath) {
		if ($candidatePath && is_file(rtrim($candidatePath, '/\\') . '/config.lua')) {
			return rtrim($candidatePath, '/\\');
		}
	}
	return null;
}

function server_info_evaluate_number($expression) {
	$expression = trim($expression);
	if (!preg_match('/^[0-9.\s*]+$/', $expression)) {
		return null;
	}

	$result = 1;
	foreach (explode('*', $expression) as $factor) {
		$factor = trim($factor);
		if ($factor === '' || !is_numeric($factor)) {
			return null;
		}
		$result *= $factor + 0;
	}
	return $result;
}

function server_info_read_config_values($configPath) {
	$configValues = array();
	foreach (file($configPath, FILE_IGNORE_NEW_LINES) as $configLine) {
		if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+?)\s*(--.*)?$/', $configLine, $match)) {
			continue;
		}

		$rawValue = trim($match[2]);
		if (preg_match('/^"(.*)"$/', $rawValue, $stringMatch)) {
			$configValues[$match[1]] = $stringMatch[1];
			continue;
		}

		if ($rawValue === 'true' || $rawValue === 'false') {
			$configValues[$match[1]] = $rawValue === 'true';
			continue;
		}

		$numericValue = server_info_evaluate_number($rawValue);
		if ($numericValue !== null) {
			$configValues[$match[1]] = $numericValue;
		}
	}
	return $configValues;
}

function server_info_read_stage_tables($stagesPath) {
	$stageTables = array();
	$stagesContent = preg_replace('/--[^\n]*/', '', file_get_contents($stagesPath));
	if (!preg_match_all('/([A-Za-z_]+)\s*=\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s', $stagesContent, $tableMatches, PREG_SET_ORDER)) {
		return $stageTables;
	}

	foreach ($tableMatches as $tableMatch) {
		preg_match_all('/\{([^{}]*)\}/', $tableMatch[2], $stageMatches);
		$stages = array();
		foreach ($stageMatches[1] as $stageBody) {
			preg_match_all('/(minlevel|maxlevel|multiplier)\s*=\s*([0-9.]+)/', $stageBody, $fieldMatches, PREG_SET_ORDER);
			$stage = array();
			foreach ($fieldMatches as $fieldMatch) {
				$stage[$fieldMatch[1]] = $fieldMatch[2] + 0;
			}
			if (isset($stage['minlevel'], $stage['multiplier'])) {
				$stages[] = $stage;
			}
		}
		$stageTables[$tableMatch[1]] = $stages;
	}
	return $stageTables;
}

function server_info_format_minutes_rate($secondsPerMinute) {
	$minutes = $secondsPerMinute / 60;
	return '1 min a cada ' . rtrim(rtrim(number_format($minutes, 1, ',', ''), '0'), ',') . ' min';
}

function server_info_render_stage_table($title, $levelLabel, $stages) {
	if (empty($stages)) {
		return;
	}
	?>
	<h2><?php echo $title; ?></h2>
	<table class="table tbl-hover">
		<tbody>
			<tr class="yellow">
				<td><?php echo $levelLabel; ?></td>
				<td>Multiplicador</td>
			</tr>
			<?php foreach ($stages as $stage): ?>
				<tr>
					<td><?php echo isset($stage['maxlevel']) ? $stage['minlevel'] . ' - ' . $stage['maxlevel'] : $stage['minlevel'] . '+'; ?></td>
					<td><?php echo $stage['multiplier']; ?>x</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

$serverPath = server_info_resolve_server_path(isset($config['server_path']) ? $config['server_path'] : null);
$serverConfig = $serverPath ? server_info_read_config_values($serverPath . '/config.lua') : array();
$stageTables = $serverPath && is_file($serverPath . '/data/stages.lua') ? server_info_read_stage_tables($serverPath . '/data/stages.lua') : array();
$useStages = !empty($serverConfig['rateUseStages']);
$configValue = function ($key, $default = null) use ($serverConfig) {
	return array_key_exists($key, $serverConfig) ? $serverConfig[$key] : $default;
};
?>

<h1>Informações do servidor</h1>
<p>Rates, stages e regras do <b><?php echo $config['site_title']; ?></b>.</p>

<?php if (empty($serverConfig)): ?>
	<p>As informações do servidor ainda não estão disponíveis.</p>
<?php else: ?>
	<?php if ($useStages): ?>
		<?php server_info_render_stage_table('Experiência', 'Level', isset($stageTables['experienceStages']) ? $stageTables['experienceStages'] : array()); ?>
		<?php server_info_render_stage_table('Skills', 'Skill', isset($stageTables['skillsStages']) ? $stageTables['skillsStages'] : array()); ?>
		<?php server_info_render_stage_table('Magic Level', 'Magic level', isset($stageTables['magicLevelStages']) ? $stageTables['magicLevelStages'] : array()); ?>
	<?php endif; ?>

	<h2>Outras rates</h2>
	<table class="table tbl-hover">
		<tbody>
			<?php if (!$useStages): ?>
				<tr><td>Experiência</td><td><?php echo $configValue('rateExp'); ?>x</td></tr>
				<tr><td>Skills</td><td><?php echo $configValue('rateSkill'); ?>x</td></tr>
				<tr><td>Magic level</td><td><?php echo $configValue('rateMagic'); ?>x</td></tr>
			<?php endif; ?>
			<tr><td>Loot</td><td><?php echo $configValue('rateLoot'); ?>x</td></tr>
			<tr><td>Respawn</td><td><?php echo $configValue('rateSpawn'); ?>x</td></tr>
		</tbody>
	</table>

	<?php if ($configValue('staminaSystem')): ?>
		<h2>Stamina</h2>
		<table class="table tbl-hover">
			<tbody>
				<tr class="yellow"><td>Situação</td><td>Recuperação</td></tr>
				<tr><td>Offline (até 39:00)</td><td><?php echo server_info_format_minutes_rate($configValue('timeToRegenMinuteStamina', 180)); ?></td></tr>
				<tr><td>Offline (39:00 a 42:00)</td><td><?php echo server_info_format_minutes_rate($configValue('timeToRegenMinutePremiumStamina', 360)); ?></td></tr>
				<?php if ($configValue('staminaTrainer')): ?>
					<tr><td>Treinando (exercise weapons)</td><td><?php echo $configValue('staminaTrainerGain', 1); ?> min a cada <?php echo $configValue('staminaTrainerDelay', 1); ?> min</td></tr>
				<?php endif; ?>
				<?php if ($configValue('staminaPz')): ?>
					<tr><td>Parado em protection zone</td><td><?php echo $configValue('staminaPzGain', 1); ?> min a cada <?php echo $configValue('staminaOrangeDelay', 1); ?> min</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<p>Os primeiros 10 minutos offline não contam para a recuperação.</p>
	<?php endif; ?>

	<h2>Regras gerais</h2>
	<table class="table tbl-hover">
		<tbody>
			<tr><td>Tipo de mundo</td><td><?php echo strtoupper((string) $configValue('worldType', '-')); ?></td></tr>
			<tr><td>Protection level</td><td><?php echo $configValue('protectionLevel', '-'); ?></td></tr>
			<tr><td>Frags para red skull (dia / semana / mês)</td><td><?php echo $configValue('dayKillsToRedSkull', '-'); ?> / <?php echo $configValue('weekKillsToRedSkull', '-'); ?> / <?php echo $configValue('monthKillsToRedSkull', '-'); ?></td></tr>
			<tr><td>Personagens online por conta</td><td><?php echo $configValue('maxPlayersOnlinePerAccount', 1); ?></td></tr>
			<tr><td>Premium grátis</td><td><?php echo $configValue('freePremium') ? 'Sim' : 'Não'; ?></td></tr>
			<tr><td>Shared experience automático na party</td><td><?php echo $configValue('partyAutoShareExperience') ? 'Sim' : 'Não'; ?></td></tr>
			<tr><td>Kick por inatividade</td><td><?php echo $configValue('kickIdlePlayerAfterMinutes', '-'); ?> min</td></tr>
		</tbody>
	</table>
<?php endif;
include 'layout/overall/footer.php'; ?>
