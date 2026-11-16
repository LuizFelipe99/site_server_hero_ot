<?php require_once 'engine/init.php'; include 'layout/overall/header.php';

const UPCOMING_EVENTS_LIMIT = 6;
const EVENT_DISPLAY_DATE_FORMAT = 'd/m';

function count_server_event_remaining_days($server_event) {
	$today = new DateTime('today');
	return (int) $today->diff($server_event['endDate'])->days + 1;
}

function describe_server_event_remaining_time($server_event) {
	$remaining_days = count_server_event_remaining_days($server_event);

	if ($remaining_days <= 1) {
		return 'Último dia';
	}

	return 'Faltam ' . $remaining_days . ' dias';
}

function render_server_event_card($server_event, $is_active) {
	$event_period = $is_active
		? 'Até ' . $server_event['endDate']->format(EVENT_DISPLAY_DATE_FORMAT)
		: $server_event['startDate']->format(EVENT_DISPLAY_DATE_FORMAT) . ' a ' . $server_event['endDate']->format(EVENT_DISPLAY_DATE_FORMAT);
	?>
	<article class="event-card<?php echo $is_active ? ' is-active' : ''; ?>" style="--event-color: <?php echo htmlspecialchars($server_event['color']); ?>;">
		<header class="event-card-header">
			<h3><?php echo htmlspecialchars($server_event['name']); ?></h3>
			<?php if ($is_active): ?>
				<span class="event-card-status"><span class="event-card-pulse"></span> <?php echo describe_server_event_remaining_time($server_event); ?></span>
			<?php endif; ?>
		</header>
		<?php if ($server_event['description'] !== ''): ?>
			<p><?php echo htmlspecialchars($server_event['description']); ?></p>
		<?php endif; ?>
		<footer class="event-card-period"><i class="fa fa-calendar"></i> <?php echo $event_period; ?></footer>
	</article>
	<?php
}

$active_server_events = list_active_server_events();
$upcoming_server_events = array_slice(list_upcoming_server_events(), 0, UPCOMING_EVENTS_LIMIT);
?>
<section class="events-page">
	<div class="register-hero">
		<span class="register-eyebrow"><i class="fa fa-calendar"></i> Eventos</span>
		<h1>Eventos do Noxum OT</h1>
		<p>Bônus que estão valendo agora no servidor e os próximos que vêm por aí. Os eventos também aparecem na aba Eventos do launcher.</p>
	</div>

	<h2 class="events-section-title">Acontecendo agora</h2>
	<?php if (empty($active_server_events)): ?>
		<div class="account-notice account-notice-info"><i class="fa fa-info-circle"></i><div>Nenhum evento ativo no momento. Fique de olho nos próximos.</div></div>
	<?php else: ?>
		<div class="events-grid">
			<?php foreach ($active_server_events as $active_server_event): ?>
				<?php render_server_event_card($active_server_event, true); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<h2 class="events-section-title">Próximos eventos</h2>
	<?php if (empty($upcoming_server_events)): ?>
		<div class="account-notice account-notice-info"><i class="fa fa-info-circle"></i><div>Nenhum evento agendado por enquanto.</div></div>
	<?php else: ?>
		<div class="events-grid">
			<?php foreach ($upcoming_server_events as $upcoming_server_event): ?>
				<?php render_server_event_card($upcoming_server_event, false); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
<?php include 'layout/overall/footer.php'; ?>
