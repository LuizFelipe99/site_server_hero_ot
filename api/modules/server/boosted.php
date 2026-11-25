<?php require_once '../../module.php';
require_once '../../launcher_support.php';
require_once '../../../engine/function/boosted.php';

$response['version']['module'] = 1;

function resolveSiteBaseUrl() {
	$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	$scheme = $isHttps ? 'https' : 'http';
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
	$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
	$siteRootPath = preg_replace('#/api/modules/server$#', '', $scriptDirectory);

	return $scheme . '://' . $host . rtrim($siteRootPath, '/');
}

function toAbsoluteSiteUrl($imageUrl) {
	if ($imageUrl === null || preg_match('#^https?://#i', $imageUrl)) {
		return $imageUrl;
	}

	return resolveSiteBaseUrl() . '/' . ltrim($imageUrl, '/');
}

function toLauncherBoosted($boostedCreature) {
	if ($boostedCreature === null) {
		return null;
	}

	$imageUrl = $boostedCreature['localImagePath'] !== null
		? resolveSiteBaseUrl() . '/' . $boostedCreature['localImagePath']
		: toAbsoluteSiteUrl($boostedCreature['externalImageUrl']);

	return array(
		'name' => $boostedCreature['name'],
		'raceId' => $boostedCreature['raceId'],
		'imageUrl' => $imageUrl
	);
}

$boostedCreatures = describeBoostedCreatures();

$response['data']['creature'] = toLauncherBoosted($boostedCreatures['creature']);
$response['data']['boss'] = toLauncherBoosted($boostedCreatures['boss']);

sendLauncherResponse($response);
?>
