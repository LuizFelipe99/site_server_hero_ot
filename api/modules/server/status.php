<?php require_once '../../module.php';

$response['version']['module'] = 1;

const SERVER_STATUS_CACHE_SECONDS = 30;
const SERVER_STATUS_SOCKET_TIMEOUT_SECONDS = 1;

function isGameServerReachable($statusConfig) {
	if (empty($statusConfig['status_check'])) {
		return null;
	}

	$serverSocket = @fsockopen($statusConfig['status_ip'], (int) $statusConfig['status_port'], $socketErrorNumber, $socketErrorMessage, SERVER_STATUS_SOCKET_TIMEOUT_SECONDS);

	if (!$serverSocket) {
		return false;
	}

	fclose($serverSocket);
	return true;
}

function countOnlinePlayers() {
	if (config('ServerEngine') === 'TFS_10') {
		$onlineRow = mysql_select_single("SELECT COUNT(`player_id`) AS `value` FROM `players_online`;");
		return $onlineRow !== false ? (int) $onlineRow['value'] : 0;
	}

	$onlineRow = mysql_select_single("SELECT COUNT(`id`) AS `value` FROM `players` WHERE `online` = 1;");
	return $onlineRow !== false ? (int) $onlineRow['value'] : 0;
}

function describeServerStatus($statusConfig) {
	$isServerOnline = isGameServerReachable($statusConfig);

	return array(
		'isOnline' => $isServerOnline,
		'playersOnline' => $isServerOnline === false ? 0 : countOnlinePlayers()
	);
}

function canUseCacheDirectory($cacheDirectory) {
	if (is_dir($cacheDirectory)) {
		return is_writable($cacheDirectory);
	}

	return @mkdir($cacheDirectory, 0755, true);
}

function loadServerStatus($statusConfig) {
	$cacheDirectory = __DIR__ . '/../../../engine/cache';

	if (!canUseCacheDirectory($cacheDirectory)) {
		return describeServerStatus($statusConfig);
	}

	$serverStatusCache = new Cache($cacheDirectory . '/launcherServerStatus');
	$serverStatusCache->setExpiration(SERVER_STATUS_CACHE_SECONDS);

	if (!$serverStatusCache->hasExpired()) {
		$cachedServerStatus = $serverStatusCache->load();

		if (is_array($cachedServerStatus)) {
			return $cachedServerStatus;
		}
	}

	$serverStatus = describeServerStatus($statusConfig);
	$serverStatusCache->setContent($serverStatus);
	$serverStatusCache->save();

	return $serverStatus;
}

$statusConfig = isset($config['status']) ? $config['status'] : array();
$serverStatus = loadServerStatus($statusConfig);

$response['data'] = $serverStatus;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
SendResponse($response);
?>
