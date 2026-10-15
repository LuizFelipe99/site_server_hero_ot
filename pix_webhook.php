<?php require_once 'engine/init.php';
require_once 'engine/function/pix.php';

function send_pix_webhook_response($http_status, $response_message) {
	ob_end_clean();
	http_response_code($http_status);
	header('Content-Type: text/plain; charset=utf-8');
	echo $response_message;
	exit();
}

function read_pix_webhook_order_id() {
	if (isset($_GET['data_id'])) {
		return (string) $_GET['data_id'];
	}

	$notification_body = json_decode((string) file_get_contents('php://input'), true);

	if (isset($notification_body['data']['id'])) {
		return (string) $notification_body['data']['id'];
	}

	return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	send_pix_webhook_response(405, 'method not allowed');
}

$pix_settings = load_pix_settings($config);

if (!is_pix_available($pix_settings)) {
	send_pix_webhook_response(503, 'pix disabled');
}

$provider_order_id = read_pix_webhook_order_id();

if ($provider_order_id === '') {
	send_pix_webhook_response(200, 'ignored');
}

$signature_header = isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
$request_id_header = isset($_SERVER['HTTP_X_REQUEST_ID']) ? $_SERVER['HTTP_X_REQUEST_ID'] : '';

if (!is_pix_webhook_signature_valid($pix_settings, $provider_order_id, $signature_header, $request_id_header)) {
	send_pix_webhook_response(401, 'invalid signature');
}

ensure_pix_payments_table();
$pix_payment = fetch_pix_payment_by_provider_order($provider_order_id);

if ($pix_payment === null) {
	send_pix_webhook_response(200, 'unknown order');
}

try {
	synchronize_pix_payment($pix_settings, $pix_payment);
} catch (PixGatewayException $gateway_exception) {
	log_pix_error($gateway_exception->getMessage());
	send_pix_webhook_response(500, 'retry later');
}

send_pix_webhook_response(200, 'ok');
