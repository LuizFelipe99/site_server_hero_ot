<?php require_once '../../module.php';

$response['version']['module'] = 1;

function fetchBoostedRow($boostedTableName) {
	$boostedRow = mysql_select_single("SELECT * FROM `{$boostedTableName}` ORDER BY CAST(`date` AS UNSIGNED) DESC LIMIT 1;");

	if ($boostedRow === false) {
		return null;
	}

	if (empty($boostedRow['boostname']) || $boostedRow['boostname'] === 'default') {
		return null;
	}

	return $boostedRow;
}

function buildBoostedOutfitImageUrl($outfitImageServer, $boostedRow) {
	if (empty($outfitImageServer) || (int) $boostedRow['looktype'] <= 0) {
		return null;
	}

	$outfitParameters = array(
		'id' => (int) $boostedRow['looktype'],
		'addons' => (int) $boostedRow['lookaddons'],
		'head' => (int) $boostedRow['lookhead'],
		'body' => (int) $boostedRow['lookbody'],
		'legs' => (int) $boostedRow['looklegs'],
		'feet' => (int) $boostedRow['lookfeet']
	);

	return $outfitImageServer . '?' . http_build_query($outfitParameters);
}

function describeBoosted($boostedTableName, $outfitImageServer) {
	$boostedRow = fetchBoostedRow($boostedTableName);

	if ($boostedRow === null) {
		return null;
	}

	return array(
		'name' => $boostedRow['boostname'],
		'raceId' => (int) $boostedRow['raceid'],
		'imageUrl' => buildBoostedOutfitImageUrl($outfitImageServer, $boostedRow)
	);
}

$outfitImageServer = isset($config['show_outfits']['imageServer']) ? $config['show_outfits']['imageServer'] : '';

$response['data']['creature'] = describeBoosted('boosted_creature', $outfitImageServer);
$response['data']['boss'] = describeBoosted('boosted_boss', $outfitImageServer);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
SendResponse($response);
?>
