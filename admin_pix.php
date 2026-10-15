<?php require_once 'engine/init.php';
require_once 'engine/function/pix.php';
protect_page();
admin_only($user_data);

const ADMIN_PIX_PAYMENTS_LIMIT = 100;

function summarize_admin_pix_payments($pix_payments) {
	$payments_summary = array('credited_count' => 0, 'credited_cents' => 0, 'credited_points' => 0, 'pending_count' => 0);

	foreach ($pix_payments as $pix_payment) {
		if ($pix_payment['status'] === PIX_PAYMENT_STATUS_PENDING) {
			$payments_summary['pending_count']++;
			continue;
		}

		if ($pix_payment['status'] !== PIX_PAYMENT_STATUS_CREDITED) {
			continue;
		}

		$payments_summary['credited_count']++;
		$payments_summary['credited_cents'] += (int) $pix_payment['amount_cents'];
		$payments_summary['credited_points'] += (int) $pix_payment['points'];
	}

	return $payments_summary;
}

$pix_settings = load_pix_settings($config);
ensure_pix_payments_table();
$admin_pix_payments = fetch_recent_pix_payments(ADMIN_PIX_PAYMENTS_LIMIT);
$admin_pix_summary = summarize_admin_pix_payments($admin_pix_payments);

include 'layout/overall/header.php';
?>
<section class="admin-pix-page">
	<div class="register-hero">
		<span class="register-eyebrow"><i class="fa fa-qrcode"></i> Administração</span>
		<h1>Pagamentos Pix</h1>
		<p>Últimos <?php echo ADMIN_PIX_PAYMENTS_LIMIT; ?> compras de Tibia Coins via Pix.<?php echo $pix_settings['simulate_approval'] === true ? ' <strong>Modo de teste ativo: abra o link do Mercado Pago de um Pix para simular o pagamento.</strong>' : ''; ?></p>
	</div>

	<?php if (!is_pix_available($pix_settings)): ?>
		<div class="account-notice account-notice-warning">
			<i class="fa fa-exclamation-triangle"></i>
			<div>O Pix está desativado. Crie o arquivo <code>pix.config.php</code> (veja o <code>pix.config.example.php</code>) com <code>'enabled' => true</code> e o Access Token do Mercado Pago.</div>
		</div>
	<?php endif; ?>

	<div class="admin-pix-stats">
		<div><strong><?php echo format_pix_amount_for_display($admin_pix_summary['credited_cents']); ?></strong><span>recebidos</span></div>
		<div><strong><?php echo $admin_pix_summary['credited_count']; ?></strong><span>pagamentos confirmados</span></div>
		<div><strong><?php echo number_format($admin_pix_summary['credited_points'], 0, ',', '.'); ?></strong><span>coins entregues</span></div>
		<div><strong><?php echo $admin_pix_summary['pending_count']; ?></strong><span>aguardando</span></div>
	</div>

	<?php if (empty($admin_pix_payments)): ?>
		<p class="admin-pix-empty">Nenhum pagamento ainda.</p>
	<?php else: ?>
		<div class="admin-pix-table-wrapper">
			<table class="admin-pix-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Conta</th>
						<th>Valor</th>
						<th>Coins</th>
						<th>Status</th>
						<th>Criado</th>
						<th>Pago</th>
						<th>Status no Mercado Pago</th>
						<th>Última consulta</th>
						<th>Pedido Mercado Pago</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($admin_pix_payments as $admin_pix_payment): ?>
						<tr>
							<td><?php echo (int) $admin_pix_payment['id']; ?></td>
							<td><?php echo htmlspecialchars($admin_pix_payment['account_name'] !== null ? $admin_pix_payment['account_name'] : '#' . $admin_pix_payment['account_id']); ?></td>
							<td><?php echo format_pix_amount_for_display($admin_pix_payment['amount_cents']); ?></td>
							<td><?php echo number_format($admin_pix_payment['points'], 0, ',', '.'); ?></td>
							<td><span class="buypoints-status buypoints-status-<?php echo htmlspecialchars($admin_pix_payment['status']); ?>"><?php echo describe_pix_payment_status($admin_pix_payment['status']); ?></span></td>
							<td><?php echo format_pix_datetime($pix_settings, $admin_pix_payment['created_at']); ?></td>
							<td><?php echo $admin_pix_payment['credited_at'] !== null ? format_pix_datetime($pix_settings, $admin_pix_payment['credited_at']) : '—'; ?></td>
							<td class="admin-pix-order"><?php echo $admin_pix_payment['provider_status'] !== null ? htmlspecialchars($admin_pix_payment['provider_status'] . ' / ' . $admin_pix_payment['provider_status_detail']) : '—'; ?></td>
							<td><?php echo $admin_pix_payment['last_checked_at'] !== null ? format_pix_datetime($pix_settings, $admin_pix_payment['last_checked_at']) : '—'; ?></td>
							<td class="admin-pix-order"><?php echo $admin_pix_payment['provider_order_id'] !== null ? htmlspecialchars($admin_pix_payment['provider_order_id']) : '<em>não enviado</em>'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
<?php include 'layout/overall/footer.php'; ?>
