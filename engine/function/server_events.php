<?php

const SERVER_EVENTS_RELATIVE_PATH = '/data/json/eventscheduler/events.json';
const SERVER_EVENT_DATE_FORMAT = 'm/d/Y';
const SERVER_EVENT_API_DATE_FORMAT = 'Y-m-d';
const SERVER_EVENT_DEFAULT_COLOR = '#3d2a2a';

function resolve_server_events_file_path() {
	$server_path = config('server_path');

	if (empty($server_path)) {
		return null;
	}

	return rtrim($server_path, '/\\') . SERVER_EVENTS_RELATIVE_PATH;
}

function read_server_event_definitions() {
	$server_events_file_path = resolve_server_events_file_path();

	if ($server_events_file_path === null || !is_readable($server_events_file_path)) {
		return array();
	}

	$decoded_events = json_decode(file_get_contents($server_events_file_path), true);

	if (!is_array($decoded_events) || !isset($decoded_events['events']) || !is_array($decoded_events['events'])) {
		return array();
	}

	return $decoded_events['events'];
}

function parse_server_event_date($server_event_date) {
	if (empty($server_event_date)) {
		return null;
	}

	$parsed_date = DateTime::createFromFormat('!' . SERVER_EVENT_DATE_FORMAT, $server_event_date);

	return $parsed_date === false ? null : $parsed_date;
}

function describe_server_event($server_event) {
	$start_date = parse_server_event_date(isset($server_event['startdate']) ? $server_event['startdate'] : null);
	$end_date = parse_server_event_date(isset($server_event['enddate']) ? $server_event['enddate'] : null);

	if (empty($server_event['name']) || $start_date === null || $end_date === null) {
		return null;
	}

	return array(
		'name' => $server_event['name'],
		'description' => isset($server_event['description']) ? $server_event['description'] : '',
		'startDate' => $start_date,
		'endDate' => $end_date,
		'color' => isset($server_event['colors']['colorlight']) ? $server_event['colors']['colorlight'] : SERVER_EVENT_DEFAULT_COLOR
	);
}

function list_server_events() {
	$server_events = array();

	foreach (read_server_event_definitions() as $server_event_definition) {
		$server_event = describe_server_event($server_event_definition);

		if ($server_event !== null) {
			$server_events[] = $server_event;
		}
	}

	usort($server_events, function ($first_event, $second_event) {
		return $first_event['startDate']->getTimestamp() - $second_event['startDate']->getTimestamp();
	});

	return $server_events;
}

function is_server_event_active($server_event, $today) {
	return $server_event['startDate'] <= $today && $today <= $server_event['endDate'];
}

function is_server_event_upcoming($server_event, $today) {
	return $server_event['startDate'] > $today;
}

function filter_server_events($server_events, $event_filter) {
	$today = new DateTime('today');

	return array_values(array_filter($server_events, function ($server_event) use ($event_filter, $today) {
		return $event_filter($server_event, $today);
	}));
}

function list_active_server_events() {
	return filter_server_events(list_server_events(), 'is_server_event_active');
}

function list_upcoming_server_events() {
	return filter_server_events(list_server_events(), 'is_server_event_upcoming');
}
