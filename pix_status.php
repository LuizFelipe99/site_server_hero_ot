<?php require_once 'engine/init.php';
require_once 'engine/function/pix.php';

function send_pix_status_response($http_status, $response_payload) {
	ob_end_clean();
	http_response_code($http_status);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($response_payload);
	exit();
}

if (user_logged_in() !== true) {
	send_pix_status_response(401, array('error' => 'not_logged_in'));
}

$pix_settings = load_pix_settings($config);

if (!is_pix_available($pix_settings) || !isset($_GET['payment'])) {
	send_pix_status_response(404, array('error' => 'not_found'));
}

$pix_payment = fetch_pix_payment((int) $_GET['payment']);

if ($pix_payment === null || (int) $pix_payment['account_id'] !== (int) $session_user_id) {
	send_pix_status_response(404, array('error' => 'not_found'));
}

if (should_check_pix_provider($pix_settings, $pix_payment)) {
	try {
		$pix_payment = synchronize_pix_payment($pix_settings, $pix_payment);
	} catch (PixGatewayException $gateway_exception) {
		log_pix_error($gateway_exception->getMessage());
	}
}

send_pix_status_response(200, array(
	'status' => $pix_payment['status'],
	'label' => describe_pix_payment_status($pix_payment['status']),
	'points' => (int) $pix_payment['points']
));
