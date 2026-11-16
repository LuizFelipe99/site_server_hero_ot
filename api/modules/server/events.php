<?php require_once '../../module.php';
require_once '../../launcher_support.php';
require_once $filepath . 'engine/function/server_events.php';

$response['version']['module'] = 1;

const SERVER_EVENTS_CACHE_SECONDS = 300;

function format_server_event_for_launcher($server_event) {
	return array(
		'name' => $server_event['name'],
		'description' => $server_event['description'],
		'endDate' => $server_event['endDate']->format(SERVER_EVENT_API_DATE_FORMAT)
	);
}

$response['data'] = loadCachedLauncherData('launcherServerEvents', SERVER_EVENTS_CACHE_SECONDS, function () {
	return array('events' => array_map('format_server_event_for_launcher', list_active_server_events()));
});

sendLauncherResponse($response);
?>
