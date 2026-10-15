<?php

const PIX_PAYMENT_STATUS_PENDING = 'pending';
const PIX_PAYMENT_STATUS_CREDITED = 'credited';
const PIX_PAYMENT_STATUS_EXPIRED = 'expired';
const PIX_PAYMENT_STATUS_CANCELED = 'canceled';
const PIX_PAYMENT_STATUS_FAILED = 'failed';
const PIX_PAYMENT_STATUS_REFUNDED = 'refunded';

const PIX_PROVIDER_PAID_STATUS = 'processed';
const PIX_PROVIDER_PAID_STATUS_DETAIL = 'accredited';
const PIX_PROVIDER_CLOSED_STATUSES = array(
	'expired' => PIX_PAYMENT_STATUS_EXPIRED,
	'canceled' => PIX_PAYMENT_STATUS_CANCELED,
	'failed' => PIX_PAYMENT_STATUS_FAILED,
	'refunded' => PIX_PAYMENT_STATUS_REFUNDED
);

const PIX_DEFAULT_SETTINGS = array(
	'enabled' => false,
	'access_token' => '',
	'api_url' => 'https://api.mercadopago.com',
	'simulate_approval' => false,
	'webhook_secret' => '',
	'expiration_minutes' => 30,
	'timezone' => 'America/Sao_Paulo',
	'status_check_interval_seconds' => 5,
	'maximum_pending_payments' => 3,
	'packages' => array(
		10 => 100,
		20 => 200,
		30 => 300,
		40 => 400,
		50 => 600,
		60 => 700,
		70 => 800,
		80 => 900,
		90 => 1000,
		100 => 1200
	)
);

const PIX_PRIVATE_SETTINGS_FILE = __DIR__ . '/../../pix.config.php';
const PIX_EXTERNAL_REFERENCE_PREFIX = 'heroot-pix-';
const PIX_SIMULATED_APPROVAL_PAYER_NAME = 'APRO';
const PIX_HTTP_TIMEOUT_SECONDS = 15;
const PIX_MINIMUM_EXPIRATION_MINUTES = 30;
const PIX_EXPIRATION_GRACE_SECONDS = 600;

class PixGatewayException extends Exception {}

function load_pix_private_settings() {
	if (!is_file(PIX_PRIVATE_SETTINGS_FILE)) {
		return array();
	}

	$private_settings = include PIX_PRIVATE_SETTINGS_FILE;
	return is_array($private_settings) ? $private_settings : array();
}

function load_pix_settings($config) {
	$configured_settings = isset($config['pix']) && is_array($config['pix']) ? $config['pix'] : array();
	$pix_settings = array_merge(PIX_DEFAULT_SETTINGS, $configured_settings, load_pix_private_settings());
	$pix_settings['expiration_minutes'] = max(PIX_MINIMUM_EXPIRATION_MINUTES, (int) $pix_settings['expiration_minutes']);
	ksort($pix_settings['packages']);
	return $pix_settings;
}

function is_pix_available($pix_settings) {
	return $pix_settings['enabled'] === true && trim($pix_settings['access_token']) !== '' && extension_loaded('curl');
}

function find_pix_package_points($pix_settings, $package_price) {
	$package_price = (int) $package_price;
	return isset($pix_settings['packages'][$package_price]) ? (int) $pix_settings['packages'][$package_price] : null;
}

function calculate_pix_package_bonus_points($pix_settings, $package_price, $package_points) {
	$smallest_price = (int) array_key_first($pix_settings['packages']);
	$base_points_per_real = $pix_settings['packages'][$smallest_price] / max(1, $smallest_price);
	return max(0, (int) round($package_points - $package_price * $base_points_per_real));
}

function format_pix_amount($amount_in_cents) {
	return number_format($amount_in_cents / 100, 2, '.', '');
}

function format_pix_amount_for_display($amount_in_cents) {
	return 'R$ ' . number_format($amount_in_cents / 100, 2, ',', '.');
}

function format_pix_datetime($pix_settings, $unix_timestamp) {
	$payment_datetime = new DateTime('@' . (int) $unix_timestamp);
	$payment_datetime->setTimezone(new DateTimeZone($pix_settings['timezone']));
	return $payment_datetime->format('d/m/Y H:i');
}

function ensure_pix_payments_table() {
	global $connect;

	mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `znote_pix_payments` (
		`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
		`account_id` INT UNSIGNED NOT NULL,
		`amount_cents` INT UNSIGNED NOT NULL,
		`points` INT UNSIGNED NOT NULL,
		`status` VARCHAR(20) NOT NULL DEFAULT 'pending',
		`provider_order_id` VARCHAR(64) NULL DEFAULT NULL,
		`provider_status` VARCHAR(40) NULL DEFAULT NULL,
		`provider_status_detail` VARCHAR(40) NULL DEFAULT NULL,
		`qr_code` TEXT NULL,
		`qr_code_base64` MEDIUMTEXT NULL,
		`ticket_url` VARCHAR(255) NULL DEFAULT NULL,
		`idempotency_key` CHAR(36) NOT NULL,
		`created_at` INT UNSIGNED NOT NULL,
		`expires_at` INT UNSIGNED NOT NULL,
		`last_checked_at` INT UNSIGNED NULL DEFAULT NULL,
		`credited_at` INT UNSIGNED NULL DEFAULT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `provider_order_id` (`provider_order_id`),
		KEY `account_created` (`account_id`, `created_at`),
		KEY `status` (`status`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function generate_pix_idempotency_key() {
	$random_bytes = random_bytes(16);
	$random_bytes[6] = chr((ord($random_bytes[6]) & 0x0f) | 0x40);
	$random_bytes[8] = chr((ord($random_bytes[8]) & 0x3f) | 0x80);
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($random_bytes), 4));
}

function build_pix_external_reference($payment_id) {
	return PIX_EXTERNAL_REFERENCE_PREFIX . (int) $payment_id;
}

function fetch_pix_payment($payment_id) {
	global $connect;

	$payment_statement = mysqli_prepare($connect, 'SELECT * FROM `znote_pix_payments` WHERE `id` = ?');
	mysqli_stmt_bind_param($payment_statement, 'i', $payment_id);
	mysqli_stmt_execute($payment_statement);
	$pix_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_statement));

	return $pix_payment ?: null;
}

function fetch_pix_payment_by_provider_order($provider_order_id) {
	global $connect;

	$payment_statement = mysqli_prepare($connect, 'SELECT * FROM `znote_pix_payments` WHERE `provider_order_id` = ?');
	mysqli_stmt_bind_param($payment_statement, 's', $provider_order_id);
	mysqli_stmt_execute($payment_statement);
	$pix_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_statement));

	return $pix_payment ?: null;
}

function fetch_account_pix_payments($account_id, $maximum_payments) {
	global $connect;

	$payments_statement = mysqli_prepare($connect, 'SELECT `id`, `amount_cents`, `points`, `status`, `created_at`, `credited_at` FROM `znote_pix_payments` WHERE `account_id` = ? AND `provider_order_id` IS NOT NULL ORDER BY `id` DESC LIMIT ?');
	mysqli_stmt_bind_param($payments_statement, 'ii', $account_id, $maximum_payments);
	mysqli_stmt_execute($payments_statement);
	return mysqli_fetch_all(mysqli_stmt_get_result($payments_statement), MYSQLI_ASSOC);
}

function fetch_recent_pix_payments($maximum_payments) {
	global $connect;

	$payments_statement = mysqli_prepare($connect, 'SELECT `pix`.*, `accounts`.`name` AS `account_name` FROM `znote_pix_payments` AS `pix` LEFT JOIN `accounts` ON `accounts`.`id` = `pix`.`account_id` ORDER BY `pix`.`id` DESC LIMIT ?');
	mysqli_stmt_bind_param($payments_statement, 'i', $maximum_payments);
	mysqli_stmt_execute($payments_statement);
	return mysqli_fetch_all(mysqli_stmt_get_result($payments_statement), MYSQLI_ASSOC);
}

function find_reusable_pix_payment($account_id, $amount_in_cents) {
	global $connect;

	$minimum_expiration = time() + 60;
	$payment_statement = mysqli_prepare($connect, "SELECT * FROM `znote_pix_payments` WHERE `account_id` = ? AND `amount_cents` = ? AND `status` = 'pending' AND `provider_order_id` IS NOT NULL AND `expires_at` > ? ORDER BY `id` DESC LIMIT 1");
	mysqli_stmt_bind_param($payment_statement, 'iii', $account_id, $amount_in_cents, $minimum_expiration);
	mysqli_stmt_execute($payment_statement);
	$pix_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($payment_statement));

	return $pix_payment ?: null;
}

function count_open_pix_payments($account_id) {
	global $connect;

	$current_time = time();
	$count_statement = mysqli_prepare($connect, "SELECT COUNT(*) AS `open_payments` FROM `znote_pix_payments` WHERE `account_id` = ? AND `status` = 'pending' AND `expires_at` > ?");
	mysqli_stmt_bind_param($count_statement, 'ii', $account_id, $current_time);
	mysqli_stmt_execute($count_statement);
	$count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($count_statement));

	return (int) $count_row['open_payments'];
}

function insert_pix_payment($account_id, $amount_in_cents, $points, $expiration_minutes) {
	global $connect;

	$idempotency_key = generate_pix_idempotency_key();
	$created_at = time();
	$expires_at = $created_at + $expiration_minutes * 60;
	$insert_statement = mysqli_prepare($connect, 'INSERT INTO `znote_pix_payments` (`account_id`, `amount_cents`, `points`, `idempotency_key`, `created_at`, `expires_at`) VALUES (?, ?, ?, ?, ?, ?)');
	mysqli_stmt_bind_param($insert_statement, 'iiisii', $account_id, $amount_in_cents, $points, $idempotency_key, $created_at, $expires_at);
	mysqli_stmt_execute($insert_statement);

	return fetch_pix_payment(mysqli_insert_id($connect));
}

function delete_unsent_pix_payment($payment_id) {
	global $connect;

	$delete_statement = mysqli_prepare($connect, 'DELETE FROM `znote_pix_payments` WHERE `id` = ? AND `provider_order_id` IS NULL');
	mysqli_stmt_bind_param($delete_statement, 'i', $payment_id);
	mysqli_stmt_execute($delete_statement);
}

function store_pix_provider_order($payment_id, $provider_order) {
	global $connect;

	$update_statement = mysqli_prepare($connect, 'UPDATE `znote_pix_payments` SET `provider_order_id` = ?, `provider_status` = ?, `provider_status_detail` = ?, `qr_code` = ?, `qr_code_base64` = ?, `ticket_url` = ?, `last_checked_at` = ? WHERE `id` = ?');
	$checked_at = time();
	mysqli_stmt_bind_param(
		$update_statement,
		'ssssssii',
		$provider_order['id'],
		$provider_order['status'],
		$provider_order['status_detail'],
		$provider_order['qr_code'],
		$provider_order['qr_code_base64'],
		$provider_order['ticket_url'],
		$checked_at,
		$payment_id
	);
	mysqli_stmt_execute($update_statement);
}

function store_pix_provider_status($payment_id, $provider_status, $provider_status_detail) {
	global $connect;

	$checked_at = time();
	$update_statement = mysqli_prepare($connect, 'UPDATE `znote_pix_payments` SET `provider_status` = ?, `provider_status_detail` = ?, `last_checked_at` = ? WHERE `id` = ?');
	mysqli_stmt_bind_param($update_statement, 'ssii', $provider_status, $provider_status_detail, $checked_at, $payment_id);
	mysqli_stmt_execute($update_statement);
}

function close_pix_payment($payment_id, $closed_status) {
	global $connect;

	$close_statement = mysqli_prepare($connect, "UPDATE `znote_pix_payments` SET `status` = ? WHERE `id` = ? AND `status` = 'pending'");
	mysqli_stmt_bind_param($close_statement, 'si', $closed_status, $payment_id);
	mysqli_stmt_execute($close_statement);
}

function credit_pix_payment($pix_payment) {
	global $connect;

	$credited_at = time();
	$payment_id = (int) $pix_payment['id'];

	mysqli_begin_transaction($connect);

	$claim_statement = mysqli_prepare($connect, "UPDATE `znote_pix_payments` SET `status` = 'credited', `credited_at` = ? WHERE `id` = ? AND `status` = 'pending'");
	mysqli_stmt_bind_param($claim_statement, 'ii', $credited_at, $payment_id);
	mysqli_stmt_execute($claim_statement);

	if (mysqli_stmt_affected_rows($claim_statement) !== 1) {
		mysqli_rollback($connect);
		return false;
	}

	$points_statement = mysqli_prepare($connect, 'UPDATE `znote_accounts` SET `points` = `points` + ? WHERE `account_id` = ?');
	mysqli_stmt_bind_param($points_statement, 'ii', $pix_payment['points'], $pix_payment['account_id']);
	mysqli_stmt_execute($points_statement);

	if (mysqli_stmt_affected_rows($points_statement) !== 1) {
		mysqli_rollback($connect);
		return false;
	}

	mysqli_commit($connect);
	return true;
}

function request_pix_provider($pix_settings, $http_method, $request_path, $request_body = null, $idempotency_key = null) {
	$request_headers = array(
		'Accept: application/json',
		'Content-Type: application/json',
		'Authorization: Bearer ' . trim($pix_settings['access_token'])
	);

	if ($idempotency_key !== null) {
		$request_headers[] = 'X-Idempotency-Key: ' . $idempotency_key;
	}

	$provider_request = curl_init(rtrim($pix_settings['api_url'], '/') . $request_path);
	curl_setopt_array($provider_request, array(
		CURLOPT_CUSTOMREQUEST => $http_method,
		CURLOPT_HTTPHEADER => $request_headers,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => PIX_HTTP_TIMEOUT_SECONDS,
		CURLOPT_TIMEOUT => PIX_HTTP_TIMEOUT_SECONDS
	));

	if ($request_body !== null) {
		curl_setopt($provider_request, CURLOPT_POSTFIELDS, json_encode($request_body));
	}

	$response_body = curl_exec($provider_request);
	$response_status = (int) curl_getinfo($provider_request, CURLINFO_HTTP_CODE);
	$transport_error = curl_error($provider_request);
	curl_close($provider_request);

	if ($response_body === false) {
		throw new PixGatewayException('Falha ao conectar no Mercado Pago: ' . $transport_error);
	}

	$decoded_response = json_decode($response_body, true);

	if ($response_status < 200 || $response_status >= 300 || !is_array($decoded_response)) {
		throw new PixGatewayException('Mercado Pago respondeu com erro ' . $response_status . ': ' . substr((string) $response_body, 0, 300));
	}

	return $decoded_response;
}

function extract_pix_provider_order($provider_response) {
	$provider_payment = isset($provider_response['transactions']['payments'][0]) ? $provider_response['transactions']['payments'][0] : array();
	$payment_method = isset($provider_payment['payment_method']) ? $provider_payment['payment_method'] : array();

	return array(
		'id' => isset($provider_response['id']) ? (string) $provider_response['id'] : '',
		'status' => isset($provider_response['status']) ? (string) $provider_response['status'] : '',
		'status_detail' => isset($provider_response['status_detail']) ? (string) $provider_response['status_detail'] : '',
		'external_reference' => isset($provider_response['external_reference']) ? (string) $provider_response['external_reference'] : '',
		'total_amount' => isset($provider_response['total_amount']) ? (string) $provider_response['total_amount'] : '',
		'qr_code' => isset($payment_method['qr_code']) ? (string) $payment_method['qr_code'] : '',
		'qr_code_base64' => isset($payment_method['qr_code_base64']) ? (string) $payment_method['qr_code_base64'] : '',
		'ticket_url' => isset($payment_method['ticket_url']) ? (string) $payment_method['ticket_url'] : ''
	);
}

function create_pix_provider_order($pix_settings, $pix_payment, $payer_email) {
	$expiration_duration = 'PT' . (int) $pix_settings['expiration_minutes'] . 'M';
	$formatted_amount = format_pix_amount($pix_payment['amount_cents']);
	$order_payer = array('email' => $payer_email);

	if ($pix_settings['simulate_approval'] === true) {
		$order_payer['first_name'] = PIX_SIMULATED_APPROVAL_PAYER_NAME;
	}

	$provider_response = request_pix_provider($pix_settings, 'POST', '/v1/orders', array(
		'type' => 'online',
		'processing_mode' => 'automatic',
		'total_amount' => $formatted_amount,
		'external_reference' => build_pix_external_reference($pix_payment['id']),
		'payer' => $order_payer,
		'transactions' => array(
			'payments' => array(
				array(
					'amount' => $formatted_amount,
					'payment_method' => array('id' => 'pix', 'type' => 'bank_transfer'),
					'expiration_time' => $expiration_duration
				)
			)
		)
	), $pix_payment['idempotency_key']);

	$provider_order = extract_pix_provider_order($provider_response);

	if ($provider_order['id'] === '' || $provider_order['qr_code'] === '') {
		throw new PixGatewayException('O Mercado Pago não retornou o QR Code do Pix.');
	}

	return $provider_order;
}

function start_pix_payment($pix_settings, $account_id, $payer_email, $package_price) {
	$package_points = find_pix_package_points($pix_settings, $package_price);

	if ($package_points === null) {
		throw new PixGatewayException('Pacote inválido.');
	}

	$amount_in_cents = (int) $package_price * 100;
	$reusable_payment = find_reusable_pix_payment($account_id, $amount_in_cents);

	if ($reusable_payment !== null) {
		return $reusable_payment;
	}

	if (count_open_pix_payments($account_id) >= (int) $pix_settings['maximum_pending_payments']) {
		throw new PixGatewayException('Você já tem muitos Pix aguardando pagamento. Pague ou aguarde eles expirarem.');
	}

	$pix_payment = insert_pix_payment($account_id, $amount_in_cents, $package_points, (int) $pix_settings['expiration_minutes']);

	try {
		$provider_order = create_pix_provider_order($pix_settings, $pix_payment, $payer_email);
	} catch (PixGatewayException $gateway_exception) {
		delete_unsent_pix_payment($pix_payment['id']);
		throw $gateway_exception;
	}

	store_pix_provider_order($pix_payment['id'], $provider_order);
	return fetch_pix_payment($pix_payment['id']);
}

function is_pix_provider_order_matching($pix_payment, $provider_order) {
	if ($provider_order['external_reference'] !== build_pix_external_reference($pix_payment['id'])) {
		return false;
	}

	return (int) round((float) $provider_order['total_amount'] * 100) === (int) $pix_payment['amount_cents'];
}

function synchronize_pix_payment($pix_settings, $pix_payment) {
	if ($pix_payment['status'] !== PIX_PAYMENT_STATUS_PENDING || empty($pix_payment['provider_order_id'])) {
		return $pix_payment;
	}

	$provider_order = extract_pix_provider_order(request_pix_provider($pix_settings, 'GET', '/v1/orders/' . rawurlencode($pix_payment['provider_order_id'])));

	if (!is_pix_provider_order_matching($pix_payment, $provider_order)) {
		throw new PixGatewayException('O pedido do Mercado Pago não corresponde ao pagamento ' . (int) $pix_payment['id'] . '.');
	}

	store_pix_provider_status($pix_payment['id'], $provider_order['status'], $provider_order['status_detail']);

	if ($provider_order['status'] === PIX_PROVIDER_PAID_STATUS && $provider_order['status_detail'] === PIX_PROVIDER_PAID_STATUS_DETAIL) {
		credit_pix_payment($pix_payment);
		return fetch_pix_payment($pix_payment['id']);
	}

	if (array_key_exists($provider_order['status'], PIX_PROVIDER_CLOSED_STATUSES)) {
		close_pix_payment($pix_payment['id'], PIX_PROVIDER_CLOSED_STATUSES[$provider_order['status']]);
		return fetch_pix_payment($pix_payment['id']);
	}

	if (time() > (int) $pix_payment['expires_at'] + PIX_EXPIRATION_GRACE_SECONDS) {
		close_pix_payment($pix_payment['id'], PIX_PAYMENT_STATUS_EXPIRED);
	}

	return fetch_pix_payment($pix_payment['id']);
}

function should_check_pix_provider($pix_settings, $pix_payment) {
	if ($pix_payment['status'] !== PIX_PAYMENT_STATUS_PENDING) {
		return false;
	}

	return time() - (int) $pix_payment['last_checked_at'] >= (int) $pix_settings['status_check_interval_seconds'];
}

function is_pix_webhook_signature_valid($pix_settings, $provider_order_id, $signature_header, $request_id_header) {
	$webhook_secret = trim($pix_settings['webhook_secret']);

	if ($webhook_secret === '') {
		return true;
	}

	$signature_parts = array();

	foreach (explode(',', $signature_header) as $signature_part) {
		$key_and_value = explode('=', trim($signature_part), 2);

		if (count($key_and_value) === 2) {
			$signature_parts[$key_and_value[0]] = $key_and_value[1];
		}
	}

	if (!isset($signature_parts['ts'], $signature_parts['v1'])) {
		return false;
	}

	$signed_manifest = 'id:' . strtolower($provider_order_id) . ';request-id:' . $request_id_header . ';ts:' . $signature_parts['ts'] . ';';
	$expected_signature = hash_hmac('sha256', $signed_manifest, $webhook_secret);

	return hash_equals($expected_signature, $signature_parts['v1']);
}

function describe_pix_payment_status($payment_status) {
	$status_labels = array(
		PIX_PAYMENT_STATUS_PENDING => 'Aguardando pagamento',
		PIX_PAYMENT_STATUS_CREDITED => 'Pago',
		PIX_PAYMENT_STATUS_EXPIRED => 'Expirado',
		PIX_PAYMENT_STATUS_CANCELED => 'Cancelado',
		PIX_PAYMENT_STATUS_FAILED => 'Falhou',
		PIX_PAYMENT_STATUS_REFUNDED => 'Reembolsado'
	);

	return isset($status_labels[$payment_status]) ? $status_labels[$payment_status] : $payment_status;
}

function log_pix_error($error_message) {
	error_log('[pix] ' . $error_message);
}
