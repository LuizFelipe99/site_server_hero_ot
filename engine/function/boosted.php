<?php

const BOOSTED_CREATURE_IMAGE_DIRECTORY = 'images/creatures/';
const BOOSTED_PIXEL_ART_UPSCALE_LIMIT = 48;
const BOOSTED_PIXEL_ART_UPSCALE_FACTOR = 2;

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

function slugifyCreatureName($creatureName) {
	$creatureSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($creatureName));

	return trim($creatureSlug, '-');
}

function resolveSiteRootDirectory() {
	return dirname(__DIR__, 2) . '/';
}

function findLocalCreatureImagePath($raceId, $creatureName) {
	if ($raceId <= 0) {
		return null;
	}

	$candidateImagePaths = array(
		BOOSTED_CREATURE_IMAGE_DIRECTORY . $raceId . '-' . slugifyCreatureName($creatureName) . '.png',
		BOOSTED_CREATURE_IMAGE_DIRECTORY . $raceId . '.png'
	);

	foreach ($candidateImagePaths as $relativeImagePath) {
		if (is_file(resolveSiteRootDirectory() . $relativeImagePath)) {
			return $relativeImagePath;
		}
	}

	return null;
}

function buildExternalOutfitImageUrl($outfitImageServer, $boostedRow) {
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

function resolveOutfitImageServer() {
	$showOutfitsConfig = config('show_outfits');

	return isset($showOutfitsConfig['imageServer']) ? $showOutfitsConfig['imageServer'] : '';
}

function describeBoosted($boostedTableName) {
	$boostedRow = fetchBoostedRow($boostedTableName);

	if ($boostedRow === null) {
		return null;
	}

	$raceId = (int) $boostedRow['raceid'];

	return array(
		'name' => $boostedRow['boostname'],
		'raceId' => $raceId,
		'localImagePath' => findLocalCreatureImagePath($raceId, $boostedRow['boostname']),
		'externalImageUrl' => buildExternalOutfitImageUrl(resolveOutfitImageServer(), $boostedRow)
	);
}

function describeBoostedCreatures() {
	return array(
		'creature' => describeBoosted('boosted_creature'),
		'boss' => describeBoosted('boosted_boss')
	);
}

function prepareSiteCacheDirectory() {
	$cacheDirectory = resolveSiteRootDirectory() . 'engine/cache';

	if (!is_dir($cacheDirectory) && !@mkdir($cacheDirectory, 0755, true)) {
		return null;
	}

	return is_writable($cacheDirectory) ? $cacheDirectory : null;
}

function loadBoostedCreaturesWithCache($cacheLifespanSeconds) {
	$cacheDirectory = prepareSiteCacheDirectory();

	if ($cacheDirectory === null) {
		return describeBoostedCreatures();
	}

	$boostedCache = new Cache($cacheDirectory . '/boostedCreatures');
	$boostedCache->setExpiration($cacheLifespanSeconds);

	if (!$boostedCache->hasExpired()) {
		$cachedBoostedCreatures = $boostedCache->load();

		if (is_array($cachedBoostedCreatures)) {
			return $cachedBoostedCreatures;
		}
	}

	$boostedCreatures = describeBoostedCreatures();
	$boostedCache->setContent($boostedCreatures);
	$boostedCache->save();

	return $boostedCreatures;
}

function calculateBoostedImageDisplaySize($localImagePath) {
	if ($localImagePath === null) {
		return null;
	}

	$imageSize = @getimagesize(resolveSiteRootDirectory() . $localImagePath);

	if ($imageSize === false) {
		return null;
	}

	$largestSide = max($imageSize[0], $imageSize[1]);
	$scaleFactor = $largestSide <= BOOSTED_PIXEL_ART_UPSCALE_LIMIT ? BOOSTED_PIXEL_ART_UPSCALE_FACTOR : 1;

	return array('width' => $imageSize[0] * $scaleFactor, 'height' => $imageSize[1] * $scaleFactor);
}
?>
