<?php
require_once __DIR__ . '/config.php';

const OUTFIT_CACHE_DIRECTORY = __DIR__ . '/engine/cache/outfits/';
const OUTFIT_BROWSER_CACHE_SECONDS = 86400;
const OUTFIT_MAXIMUM_LOOK_ID = 100000;
const OUTFIT_MAXIMUM_COLOR = 132;
const OUTFIT_MAXIMUM_ADDONS = 3;
const OUTFIT_RENDER_TIMEOUT_SECONDS = 10;
const OUTFIT_TRANSPARENT_PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function read_outfit_parameter($parameter_name, $maximum_value) {
	if (!isset($_GET[$parameter_name])) {
		return 0;
	}

	$parameter_value = filter_var($_GET[$parameter_name], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => $maximum_value)));

	return $parameter_value === false ? 0 : $parameter_value;
}

function send_outfit_png($png_bytes) {
	header('Content-Type: image/png');
	header('Cache-Control: public, max-age=' . OUTFIT_BROWSER_CACHE_SECONDS);
	header('Content-Length: ' . strlen($png_bytes));
	echo $png_bytes;
	exit();
}

function send_transparent_placeholder() {
	header('Content-Type: image/png');
	header('Cache-Control: public, max-age=300');
	echo base64_decode(OUTFIT_TRANSPARENT_PIXEL);
	exit();
}

function build_renderer_command($renderer_settings, $outfit) {
	$command_parts = array(
		escapeshellarg($renderer_settings['binary']),
		'--things', escapeshellarg($renderer_settings['things'])
	);

	foreach ($outfit as $option_name => $option_value) {
		$command_parts[] = '--' . $option_name;
		$command_parts[] = (string) (int) $option_value;
	}

	return 'timeout ' . OUTFIT_RENDER_TIMEOUT_SECONDS . ' ' . implode(' ', $command_parts);
}

function render_outfit_png($renderer_settings, $outfit) {
	if (empty($renderer_settings['binary']) || !is_executable($renderer_settings['binary'])) {
		return null;
	}

	$process = proc_open(build_renderer_command($renderer_settings, $outfit), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	if (!is_resource($process)) {
		return null;
	}

	$png_bytes = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit_code = proc_close($process);

	return ($exit_code === 0 && $png_bytes !== '') ? $png_bytes : null;
}

$outfit = array(
	'looktype' => read_outfit_parameter('id', OUTFIT_MAXIMUM_LOOK_ID),
	'looktypeex' => read_outfit_parameter('ex', OUTFIT_MAXIMUM_LOOK_ID),
	'head' => read_outfit_parameter('head', OUTFIT_MAXIMUM_COLOR),
	'body' => read_outfit_parameter('body', OUTFIT_MAXIMUM_COLOR),
	'legs' => read_outfit_parameter('legs', OUTFIT_MAXIMUM_COLOR),
	'feet' => read_outfit_parameter('feet', OUTFIT_MAXIMUM_COLOR),
	'addons' => read_outfit_parameter('addons', OUTFIT_MAXIMUM_ADDONS)
);

if ($outfit['looktype'] <= 0 && $outfit['looktypeex'] <= 0) {
	send_transparent_placeholder();
}

$cache_file_path = OUTFIT_CACHE_DIRECTORY . implode('-', $outfit) . '.png';

if (is_file($cache_file_path)) {
	send_outfit_png(file_get_contents($cache_file_path));
}

$renderer_settings = isset($config['outfit_renderer']) ? $config['outfit_renderer'] : array();
$png_bytes = render_outfit_png($renderer_settings, $outfit);

if ($png_bytes === null) {
	send_transparent_placeholder();
}

if (is_dir(OUTFIT_CACHE_DIRECTORY) || @mkdir(OUTFIT_CACHE_DIRECTORY, 0775, true)) {
	@file_put_contents($cache_file_path, $png_bytes);
}

send_outfit_png($png_bytes);
