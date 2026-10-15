<?php require_once '../../module.php';
require_once '../../launcher_support.php';

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

$statusConfig = isset($config['status']) ? $config['status'] : array();

$response['data'] = loadCachedLauncherData('launcherServerStatus', SERVER_STATUS_CACHE_SECONDS, function () use ($statusConfig) {
	return describeServerStatus($statusConfig);
});

sendLauncherResponse($response);
?>
