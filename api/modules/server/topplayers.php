<?php require_once '../../module.php';
require_once '../../launcher_support.php';

$response['version']['module'] = 1;

const TOP_PLAYERS_CACHE_SECONDS = 60;
const TOP_PLAYERS_LIMIT = 3;

function resolveIgnoredGroupId() {
	$highscoreConfig = config('highscore');

	return isset($highscoreConfig['ignoreGroupId']) ? (int) $highscoreConfig['ignoreGroupId'] : 2;
}

function resolveVocationName($vocationId) {
	$vocations = config('vocations');

	if (!isset($vocations[$vocationId]['name'])) {
		return null;
	}

	return $vocations[$vocationId]['name'];
}

function describeTopPlayer($playerRow, $rank) {
	return array(
		'rank' => $rank,
		'name' => $playerRow['name'],
		'level' => (int) $playerRow['level'],
		'vocation' => resolveVocationName((int) $playerRow['vocation']),
		'outfit' => array(
			'lookType' => (int) $playerRow['looktype'],
			'lookHead' => (int) $playerRow['lookhead'],
			'lookBody' => (int) $playerRow['lookbody'],
			'lookLegs' => (int) $playerRow['looklegs'],
			'lookFeet' => (int) $playerRow['lookfeet'],
			'lookAddons' => (int) $playerRow['lookaddons']
		)
	);
}

function fetchTopPlayers() {
	$ignoredGroupId = resolveIgnoredGroupId();
	$playerRows = mysql_select_multi(
		"SELECT `name`, `level`, `vocation`, `looktype`, `lookhead`, `lookbody`, `looklegs`, `lookfeet`, `lookaddons` " .
		"FROM `players` WHERE `group_id` < {$ignoredGroupId} ORDER BY `level` DESC, `experience` DESC LIMIT " . TOP_PLAYERS_LIMIT . ";"
	);

	if (empty($playerRows)) {
		return array('players' => array());
	}

	$topPlayers = array();

	foreach (array_values($playerRows) as $playerIndex => $playerRow) {
		$topPlayers[] = describeTopPlayer($playerRow, $playerIndex + 1);
	}

	return array('players' => $topPlayers);
}

$response['data'] = loadCachedLauncherData('launcherTopPlayers', TOP_PLAYERS_CACHE_SECONDS, function () {
	return fetchTopPlayers();
});

sendLauncherResponse($response);
?>
