<?php require_once 'engine/init.php';
require_once 'engine/function/pix.php';
protect_page();

const BUYPOINTS_HISTORY_SIZE = 8;

$pix_settings = load_pix_settings($config);
$pix_is_available = is_pix_available($pix_settings);
$buypoints_errors = array();

if ($pix_is_available) {
	ensure_pix_payments_table();
}

function find_buypoints_checkout_payment($pix_settings, $session_user_id) {
	if (!isset($_GET['pix'])) {
		return null;
	}

	$pix_payment = fetch_pix_payment((int) $_GET['pix']);

	if ($pix_payment === null || (int) $pix_payment['account_id'] !== (int) $session_user_id || empty($pix_payment['provider_order_id'])) {
		return null;
	}

	if (!should_check_pix_provider($pix_settings, $pix_payment)) {
		return $pix_payment;
	}

	try {
		return synchronize_pix_payment($pix_settings, $pix_payment);
	} catch (PixGatewayException $gateway_exception) {
		log_pix_error($gateway_exception->getMessage());
		return $pix_payment;
	}
}

if ($pix_is_available && isset($_POST['package_price'])) {
	if (!Token::isValid($_POST['token'])) {
		$buypoints_errors[] = 'Sua sessão expirou. Recarregue a página e tente novamente.';
	}

	if (empty($buypoints_errors)) {
		try {
			$started_payment = start_pix_payment($pix_settings, (int) $session_user_id, $user_data['email'], (int) $_POST['package_price']);
			header('Location: buypoints.php?pix=' . (int) $started_payment['id']);
			exit();
		} catch (PixGatewayException $gateway_exception) {
			log_pix_error($gateway_exception->getMessage());
			$buypoints_errors[] = strpos($gateway_exception->getMessage(), 'Mercado Pago') === false ? $gateway_exception->getMessage() : 'Não foi possível gerar o Pix agora. Tente novamente em alguns minutos.';
		}
	}
}

$checkout_payment = $pix_is_available ? find_buypoints_checkout_payment($pix_settings, $session_user_id) : null;
$buypoints_balance = read_account_tibia_coins((int) $session_user_id);
$buypoints_history = $pix_is_available ? fetch_account_pix_payments((int) $session_user_id, BUYPOINTS_HISTORY_SIZE) : array();
$legacy_payment_methods_enabled = $config['paypal']['enabled'] || $config['pagseguro']['enabled'] || $config['paygol']['enabled'];

include 'layout/overall/header.php';
?>
<section class="register-page buypoints-page">
	<?php if ($checkout_payment !== null && $checkout_payment['status'] === PIX_PAYMENT_STATUS_CREDITED): ?>
		<div class="register-result register-result-success buypoints-result">
			<span class="register-result-icon"><i class="fa fa-check"></i></span>
			<h1>Pagamento confirmado!</h1>
			<p><strong class="buypoints-highlight">+<?php echo number_format($checkout_payment['points'], 0, ',', '.'); ?> Tibia Coins</strong> foram adicionadas à sua conta.</p>
			<p>Seu saldo agora é de <strong><?php echo number_format($buypoints_balance, 0, ',', '.'); ?> Tibia Coins</strong>. Abra a Store dentro do jogo para usar.</p>
			<div class="register-result-actions">
				<a class="btn btn-primary" href="buypoints.php"><i class="fa fa-plus"></i>&nbsp; Comprar mais coins</a>
				<a class="btn btn-default" href="myaccount.php"><i class="fa fa-user"></i>&nbsp; Minha conta</a>
			</div>
		</div>
	<?php elseif ($checkout_payment !== null && $checkout_payment['status'] === PIX_PAYMENT_STATUS_PENDING): ?>
		<div class="register-hero">
			<span class="register-eyebrow"><i class="fa fa-qrcode"></i> Pagamento via Pix</span>
			<h1>Pague com Pix</h1>
			<p>Escaneie o QR Code ou use o Pix Copia e Cola no app do seu banco. As Tibia Coins entram sozinhas assim que o pagamento for confirmado.</p>
		</div>

		<div class="buypoints-checkout" data-pix-payment="<?php echo (int) $checkout_payment['id']; ?>" data-pix-expires="<?php echo (int) $checkout_payment['expires_at']; ?>">
			<div class="buypoints-qr">
				<?php if ($checkout_payment['qr_code_base64'] !== ''): ?>
					<img src="data:image/png;base64,<?php echo htmlspecialchars($checkout_payment['qr_code_base64']); ?>" alt="QR Code do Pix" width="220" height="220">
				<?php endif; ?>
				<span class="buypoints-waiting"><span class="buypoints-waiting-dot"></span> Aguardando pagamento</span>
			</div>

			<div class="buypoints-checkout-details">
				<dl class="buypoints-summary">
					<div><dt>Pacote</dt><dd><?php echo number_format($checkout_payment['points'], 0, ',', '.'); ?> coins</dd></div>
					<div><dt>Valor</dt><dd><?php echo format_pix_amount_for_display($checkout_payment['amount_cents']); ?></dd></div>
					<div><dt>Expira em</dt><dd data-pix-countdown><?php echo format_pix_datetime($pix_settings, $checkout_payment['expires_at']); ?></dd></div>
				</dl>

				<label class="create-field-label" for="buypoints_copy_code">Pix Copia e Cola</label>
				<div class="buypoints-copy">
					<input id="buypoints_copy_code" type="text" value="<?php echo htmlspecialchars($checkout_payment['qr_code']); ?>" readonly>
					<button type="button" class="buypoints-copy-button" data-copy-target="buypoints_copy_code"><i class="fa fa-clone"></i>&nbsp; Copiar</button>
				</div>

				<ol class="buypoints-steps">
					<li>Abra o app do seu banco e entre na área Pix.</li>
					<li>Escolha <strong>Pagar com QR Code</strong> ou <strong>Pix Copia e Cola</strong>.</li>
					<li>Confira o valor e confirme. Esta página atualiza sozinha.</li>
				</ol>

				<?php if ($pix_settings['simulate_approval'] === true): ?>
					<p class="buypoints-test-hint"><i class="fa fa-flask"></i> Modo de teste: clique em <strong>Abrir instruções no Mercado Pago</strong> para simular o pagamento.</p>
				<?php endif; ?>

				<?php if ($checkout_payment['ticket_url'] !== ''): ?>
					<a class="buypoints-ticket" href="<?php echo htmlspecialchars($checkout_payment['ticket_url']); ?>" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> Abrir instruções no Mercado Pago</a>
				<?php endif; ?>
			</div>
		</div>

		<p class="register-login buypoints-back"><a href="buypoints.php">Escolher outro pacote</a></p>

		<script>
		(function () {
			var checkoutPanel = document.querySelector('.buypoints-checkout');

			if (!checkoutPanel) {
				return;
			}

			var paymentId = checkoutPanel.getAttribute('data-pix-payment');
			var expiresAt = parseInt(checkoutPanel.getAttribute('data-pix-expires'), 10) * 1000;
			var countdownElement = checkoutPanel.querySelector('[data-pix-countdown]');
			var statusCheckDelay = <?php echo max(3, (int) $pix_settings['status_check_interval_seconds']) * 1000; ?>;

			function formatRemainingTime(remainingMilliseconds) {
				var remainingSeconds = Math.max(0, Math.floor(remainingMilliseconds / 1000));
				var minutes = Math.floor(remainingSeconds / 60);
				var seconds = remainingSeconds % 60;
				return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
			}

			function updateCountdown() {
				countdownElement.textContent = formatRemainingTime(expiresAt - Date.now());
			}

			function checkPaymentStatus() {
				fetch('pix_status.php?payment=' + encodeURIComponent(paymentId), { credentials: 'same-origin', cache: 'no-store' })
					.then(function (statusResponse) {
						return statusResponse.ok ? statusResponse.json() : null;
					})
					.then(function (paymentStatus) {
						if (paymentStatus && paymentStatus.status !== 'pending') {
							window.location.reload();
							return;
						}

						window.setTimeout(checkPaymentStatus, statusCheckDelay);
					})
					.catch(function () {
						window.setTimeout(checkPaymentStatus, statusCheckDelay * 2);
					});
			}

			Array.prototype.forEach.call(document.querySelectorAll('[data-copy-target]'), function (copyButton) {
				copyButton.addEventListener('click', function () {
					var copyInput = document.getElementById(copyButton.getAttribute('data-copy-target'));
					copyInput.select();

					var copyPromise = navigator.clipboard ? navigator.clipboard.writeText(copyInput.value) : Promise.resolve(document.execCommand('copy'));

					copyPromise.then(function () {
						copyButton.classList.add('is-copied');
						copyButton.innerHTML = '<i class="fa fa-check"></i>&nbsp; Copiado!';

						window.setTimeout(function () {
							copyButton.classList.remove('is-copied');
							copyButton.innerHTML = '<i class="fa fa-clone"></i>&nbsp; Copiar';
						}, 2500);
					});
				});
			});

			updateCountdown();
			window.setInterval(updateCountdown, 1000);
			window.setTimeout(checkPaymentStatus, statusCheckDelay);
		})();
		</script>
	<?php elseif ($checkout_payment !== null): ?>
		<div class="register-result register-result-failure">
			<span class="register-result-icon"><i class="fa fa-clock-o"></i></span>
			<h1>Pix <?php echo strtolower(describe_pix_payment_status($checkout_payment['status'])); ?></h1>
			<p>Esse Pix não pode mais ser pago. Gere um novo para comprar suas Tibia Coins.</p>
			<div class="register-result-actions">
				<a class="btn btn-primary" href="buypoints.php"><i class="fa fa-qrcode"></i>&nbsp; Gerar novo Pix</a>
			</div>
		</div>
	<?php else: ?>
		<div class="register-hero">
			<span class="register-eyebrow"><span class="buypoints-coin" aria-hidden="true"></span> Seu saldo: <?php echo number_format($buypoints_balance, 0, ',', '.'); ?> Tibia Coins</span>
			<h1>Doar</h1>
			<p>Pague com Pix e receba as coins na hora, sem precisar enviar comprovante. Use na Store dentro do jogo.</p>
		</div>

		<?php if (!empty($buypoints_errors)): ?>
			<div class="register-alert" role="alert">
				<i class="fa fa-exclamation-triangle"></i>
				<div>
					<strong>Não foi possível gerar o Pix</strong>
					<ul>
						<?php foreach ($buypoints_errors as $buypoints_error): ?>
							<li><?php echo $buypoints_error; ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ($pix_is_available): ?>
			<form class="buypoints-packages" action="buypoints.php" method="post">
				<?php Token::create(); ?>
				<?php foreach ($pix_settings['packages'] as $package_price => $package_points): ?>
					<?php $package_bonus_points = calculate_pix_package_bonus_points($pix_settings, $package_price, $package_points); ?>
					<button type="submit" name="package_price" value="<?php echo (int) $package_price; ?>" class="buypoints-package<?php echo $package_bonus_points > 0 ? ' has-bonus' : ''; ?>">
						<?php if ($package_bonus_points > 0): ?>
							<span class="buypoints-bonus">+<?php echo number_format($package_bonus_points, 0, ',', '.'); ?> bônus</span>
						<?php endif; ?>
						<span class="buypoints-package-icon buypoints-coin" aria-hidden="true"></span>
						<strong><?php echo number_format($package_points, 0, ',', '.'); ?></strong>
						<span class="buypoints-package-unit">Tibia Coins</span>
						<span class="buypoints-package-price">R$ <?php echo number_format($package_price, 2, ',', '.'); ?></span>
					</button>
				<?php endforeach; ?>
			</form>

			<ul class="register-rules buypoints-notes">
				<li><i class="fa fa-bolt"></i><span>As coins entram automaticamente assim que o banco confirma o Pix, normalmente em segundos. Se estiver online, é só reabrir a Store.</span></li>
				<li><i class="fa fa-clock-o"></i><span>Cada Pix vale por <?php echo (int) $pix_settings['expiration_minutes']; ?> minutos. Se expirar, é só gerar outro.</span></li>
				<li><i class="fa fa-lock"></i><span>O pagamento é processado pelo Mercado Pago. Nós nunca vemos os dados do seu banco.</span></li>
			</ul>
		<?php else: ?>
			<div class="account-notice account-notice-info">
				<i class="fa fa-info-circle"></i>
				<div>A compra de Tibia Coins está indisponível no momento.<?php if (is_admin($user_data)): ?> Para ativar, crie o arquivo <code>pix.config.php</code> (veja o <code>pix.config.example.php</code>) com o Access Token do Mercado Pago.<?php endif; ?></div>
			</div>
		<?php endif; ?>

		<?php if (!empty($buypoints_history)): ?>
			<h2 class="downloads-heading">Suas compras</h2>
			<div class="buypoints-history">
				<?php foreach ($buypoints_history as $history_payment): ?>
					<a class="buypoints-history-row" href="buypoints.php?pix=<?php echo (int) $history_payment['id']; ?>">
						<span class="buypoints-history-points"><span class="buypoints-coin" aria-hidden="true"></span> <?php echo number_format($history_payment['points'], 0, ',', '.'); ?> coins</span>
						<span class="buypoints-history-amount"><?php echo format_pix_amount_for_display($history_payment['amount_cents']); ?></span>
						<span class="buypoints-history-date"><?php echo format_pix_datetime($pix_settings, $history_payment['created_at']); ?></span>
						<span class="buypoints-status buypoints-status-<?php echo htmlspecialchars($history_payment['status']); ?>"><?php echo describe_pix_payment_status($history_payment['status']); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ($legacy_payment_methods_enabled): ?>
			<h2 class="downloads-heading">Outras formas de pagamento</h2>
			<div class="buypoints-legacy">
				<?php if ($config['paypal']['enabled']): ?>
					<?php $paypal = $config['paypal']; ?>
					<h3>PayPal</h3>
					<table id="buypointsTable" class="table table-striped table-hover">
						<tr class="yellow">
							<th>Preço</th>
							<th>Pontos</th>
							<?php if ($paypal['showBonus']): ?><th>Bônus</th><?php endif; ?>
							<th></th>
						</tr>
						<?php foreach ($config['paypal_prices'] as $paypal_price => $paypal_points): ?>
							<tr class="special">
								<td><?php echo $paypal_price . ' (' . hhb_tohtml($paypal['currency']) . ')'; ?></td>
								<td><?php echo $paypal_points; ?></td>
								<?php if ($paypal['showBonus']): ?><td><?php echo calculate_discount(($paypal['points_per_currency'] * $paypal_price), $paypal_points); ?> bônus</td><?php endif; ?>
								<td>
									<form action="https://www.paypal.com/cgi-bin/webscr" method="POST">
										<input type="hidden" name="cmd" value="_xclick">
										<input type="hidden" name="business" value="<?php echo hhb_tohtml($paypal['email']); ?>">
										<input type="hidden" name="item_name" value="<?php echo $paypal_points . ' shop points on ' . hhb_tohtml($config['site_title']); ?>">
										<input type="hidden" name="item_number" value="1">
										<input type="hidden" name="amount" value="<?php echo $paypal_price; ?>">
										<input type="hidden" name="no_shipping" value="1">
										<input type="hidden" name="no_note" value="1">
										<input type="hidden" name="currency_code" value="<?php echo hhb_tohtml($paypal['currency']); ?>">
										<input type="hidden" name="lc" value="GB">
										<input type="hidden" name="bn" value="PP-BuyNowBF">
										<input type="hidden" name="return" value="<?php echo hhb_tohtml($paypal['success']); ?>">
										<input type="hidden" name="cancel_return" value="<?php echo hhb_tohtml($paypal['failed']); ?>">
										<input type="hidden" name="rm" value="2">
										<input type="hidden" name="notify_url" value="<?php echo hhb_tohtml($paypal['ipn']); ?>" />
										<input type="hidden" name="custom" value="<?php echo (int) $session_user_id; ?>">
										<input type="submit" value="Comprar">
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>

				<?php if ($config['pagseguro']['enabled']): ?>
					<?php $pagseguro = $config['pagseguro']; ?>
					<h3>PagSeguro</h3>
					<form target="pagseguro" action="https://<?php echo hhb_tohtml($pagseguro['urls']['www']); ?>/checkout/checkout.jhtml" method="post">
						<input type="hidden" name="email_cobranca" value="<?php echo hhb_tohtml($pagseguro['email']); ?>">
						<input type="hidden" name="tipo" value="CP">
						<input type="hidden" name="moeda" value="<?php echo hhb_tohtml($pagseguro['currency']); ?>">
						<input type="hidden" name="ref_transacao" value="<?php echo (int) $session_user_id; ?>">
						<input type="hidden" name="item_id_1" value="1">
						<input type="hidden" name="item_descr_1" value="<?php echo hhb_tohtml($pagseguro['product_name']); ?>">
						<input type="number" name="item_quant_1" min="1" step="4" value="1">
						<input type="hidden" name="item_peso_1" value="0">
						<input type="hidden" name="item_valor_1" value="<?php echo $pagseguro['price']; ?>">
						<input type="submit" value="Comprar">
					</form>
				<?php endif; ?>

				<?php if ($config['paygol']['enabled']): ?>
					<?php $paygol = $config['paygol']; ?>
					<h3>PayGol</h3>
					<p><?php echo $paygol['price'] . ' ' . hhb_tohtml($paygol['currency']) . ' por ' . $paygol['points'] . ' pontos:'; ?></p>
					<form name="pg_frm" method="post" action="http://www.paygol.com/micropayment/paynow">
						<input type="hidden" name="pg_serviceid" value="<?php echo hhb_tohtml($paygol['serviceID']); ?>">
						<input type="hidden" name="pg_currency" value="<?php echo hhb_tohtml($paygol['currency']); ?>">
						<input type="hidden" name="pg_name" value="<?php echo hhb_tohtml($paygol['name']); ?>">
						<input type="hidden" name="pg_custom" value="<?php echo hhb_tohtml($session_user_id); ?>">
						<input type="hidden" name="pg_price" value="<?php echo $paygol['price']; ?>">
						<input type="hidden" name="pg_return_url" value="<?php echo hhb_tohtml($paygol['returnURL']); ?>">
						<input type="hidden" name="pg_cancel_url" value="<?php echo hhb_tohtml($paygol['cancelURL']); ?>">
						<input type="image" name="pg_button" src="https://www.paygol.com/micropayment/img/buttons/150/black_en_pbm.png" border="0" alt="PayGol">
					</form>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>
<?php include 'layout/overall/footer.php'; ?>
