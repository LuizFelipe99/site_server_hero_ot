<?php

function canUseLauncherCacheDirectory($cacheDirectory) {
	if (is_dir($cacheDirectory)) {
		return is_writable($cacheDirectory);
	}

	return @mkdir($cacheDirectory, 0755, true);
}

function loadCachedLauncherData($cacheName, $lifespanSeconds, $produceData) {
	$cacheDirectory = __DIR__ . '/../engine/cache';

	if (!canUseLauncherCacheDirectory($cacheDirectory)) {
		return $produceData();
	}

	$launcherCache = new Cache($cacheDirectory . '/' . $cacheName);
	$launcherCache->setExpiration($lifespanSeconds);

	if (!$launcherCache->hasExpired()) {
		$cachedData = $launcherCache->load();

		if (is_array($cachedData)) {
			return $cachedData;
		}
	}

	$freshData = $produceData();
	$launcherCache->setContent($freshData);
	$launcherCache->save();

	return $freshData;
}

function sendLauncherResponse($response) {
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-cache, no-store, must-revalidate');
	SendResponse($response);
}
?>
