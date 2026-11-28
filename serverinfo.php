<?php require_once 'engine/init.php'; include 'layout/overall/header.php';

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
