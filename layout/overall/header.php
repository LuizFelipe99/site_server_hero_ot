<?php
	require_once 'layout/layout_config.php';
	$launch_seconds = (strtotime($countDown) - time());
	$delay_hide = $launch_seconds + $countDown_hide;
	$current_page_name = isset($page_filename) ? $page_filename : '';
	$is_home_page = ($current_page_name === '' || $current_page_name === 'index');
	$players_online_count = user_count_online();
?>
<!DOCTYPE html>
<html lang="pt-BR" dir="ltr">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo $config['site_title']; ?></title>
		<link rel="icon" href="layout/img/favicon.ico?v=2">

		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap">
		<link rel="stylesheet" href="layout/css/style.css?aio=6">
		<link rel="stylesheet" href="layout/fontawesome/css/font-awesome.min.css?aio=1">
		<link rel="stylesheet" href="layout/css/resp.css?aio=2">

		<script src="layout/js/jquery.js" charset="utf-8"></script>

		<?php if ($delay_hide > 0): ?>
			<script src="layout/js/countdown.js" charset="utf-8"></script>
		<?php endif; ?>

		<script type="text/javascript">
			$(document).ready(function(){
				<?php if ($delay_hide > 0): ?>
					countDown("countDownTimer", <?php echo $launch_seconds; ?>, "<?php echo $countDown_complete; ?>");
				<?php endif; ?>

				$('.loginBtn').click(function(){
					$('.loginContainer input:first-of-type').focus();
				});
				$('#accountLink').click(function(e) {
					if (this.href.indexOf('#') >= 0) {
						$('.loginContainer input:first-of-type').focus();
					}
				});
			});
		</script>
	</head>
	<body<?php if (strlen($current_page_name) > 0) echo " class='page_{$current_page_name}'"; ?>>
		<?php include 'layout/menu.php'; ?>

		<header class="hero<?php if (!$is_home_page) echo ' hero-compact'; ?>">
			<div class="hero-backdrop"></div>
			<div class="hero-content">
				<span class="hero-eyebrow"><span class="status-dot"></span><?php echo $players_online_count; ?> jogadores online</span>
				<h1 class="hero-title"><img class="hero-logo" src="layout/img/noxum-logo.webp?v=1" alt="<?php echo $layout_server_name; ?>" width="960" height="822"></h1>
				<?php if ($is_home_page): ?>
					<p class="hero-tagline"><?php echo $layout_server_tagline; ?></p>
					<div class="hero-actions">
						<a class="button-play" href="downloads.php"><i class="fa fa-play"></i> Jogar agora</a>
						<a class="button-ghost" href="register.php"><i class="fa fa-user-plus"></i> Criar conta</a>
						<a class="button-discord" href="<?php echo htmlspecialchars($layout_discord_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo render_discord_icon(); ?> Entrar no Discord</a>
					</div>
				<?php endif; ?>
			</div>
		</header>

		<div class="main">
			<div class="well feedContainer preventCollapse">
				<?php if ($delay_hide > 0): ?>
					<div class="well topPane preventCollapse">
						<div class="well pull-left">
							<div id="countDownTimer" data-date="<?=$countDown?>"></div>
						</div>
					</div>
				<?php endif; ?>
				<div class="pull-left leftPane">
