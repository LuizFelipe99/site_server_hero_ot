<?php require_once 'engine/init.php';
protect_page();
include 'layout/overall/header.php';

$account_notices = array();

function add_account_notice($notice_type, $notice_text, $notice_items = array()) {
	global $account_notices;
	$account_notices[] = array('type' => $notice_type, 'text' => $notice_text, 'items' => $notice_items);
}

#region CANCEL CHARACTER DELETE
$undelete_id = @$_GET['cancel_delete_id'];
if($undelete_id) {
	$undelete_id = (int)$undelete_id;
	$undelete_q1 = mysql_select_single('SELECT `character_name` FROM `znote_deleted_characters` WHERE `done` = 0 AND `id` = ' . $undelete_id . ' AND `original_account_id` = ' . $session_user_id . ' AND NOW() < `time`');
	if($undelete_q1) {
		mysql_delete('DELETE FROM `znote_deleted_characters` WHERE `id` = ' . $undelete_id);
		add_account_notice('success', 'A exclusão de ' . htmlspecialchars($undelete_q1['character_name']) . ' foi cancelada.');
	}
}
#endregion

$render_page = true;

if (isset($_GET['authenticate']) && $config['mailserver']['myaccount_verify_email']):
	// If we need to process email verification
	if (isset($_GET['u']) && isset($_GET['k'])) {
		// Authenticate user, fetch user id and activation key
		$auid = (isset($_GET['u']) && (int)$_GET['u'] > 0) ? (int)$_GET['u'] : false;
		$akey = (isset($_GET['k']) && (int)$_GET['k'] > 0) ? (int)$_GET['k'] : false;
		if ($auid !== false && $akey !== false) {
			// Find a match
			$user = mysql_select_single("SELECT `id`, `active`, `active_email` FROM `znote_accounts` WHERE `account_id`='{$auid}' AND `activekey`='{$akey}' LIMIT 1;");
			if ($user !== false) {
				$user = (int) $user['id'];
				$active = (int) $user['active'];
				$active_email = (int) $user['active_email'];
				$verify_points = ($active_email == 0 && $config['mailserver']['verify_email_points'] > 0)
					? ", `points` = `points` + {$config['mailserver']['verify_email_points']}"
					: '';
				// Enable the account to login
				if ($active == 0 || $active_email == 0) {
					$new_activeKey = rand(100000000, 999999999);
					mysql_update("UPDATE `znote_accounts` SET `active`='1', `active_email`='1', `activekey`='{$new_activeKey}' {$verify_points} WHERE `id`= {$user} LIMIT 1;");
				}
				echo '<h1>Congratulations!</h1> <p>Your email has been verified.</p>';
				if ($verify_points !== '') echo "<p>As thanks for having a verified email, you have received <a href='/shop.php'>{$config['mailserver']['verify_email_points']} shop points</a>!</p>";
				$user_znote_data['active_email'] = 1;
				// Todo: Bonus points as thanks for verifying email
			} else {
				echo '<h1>Authentication failed</h1> <p>Either the activation link is wrong, or your account is already activated.</p>';
			}
		} else {
			echo '<h1>Authentication failed</h1> <p>Either the activation link is wrong, or your account is already activated.</p>';
		}
	} else { // We need to send email verification
		$verify_account_id = (int)$session_user_id;
		$user = mysql_select_single("SELECT `id`, `activekey`, `active_email` FROM `znote_accounts` WHERE `account_id`='{$verify_account_id}' LIMIT 1;");
		if ($user !== false) {
			$thisurl = config('site_url') . "myaccount.php";
			$thisurl .= "?authenticate&u=".$verify_account_id."&k=".$user['activekey'];

			$mailer = new Mail($config['mailserver']);

			$title = "Please authenticate your email at {$_SERVER['HTTP_HOST']}.";

			$body = "<h1>Please click on the following link to authenticate your account:</h1>";
			$body .= "<p><a href='{$thisurl}'>{$thisurl}</a></p>";
			$body .= "<p>Thank you for verifying your email and enjoy your stay at {$config['mailserver']['fromName']}.</p>";
			$body .= "<hr><p>I am an automatic no-reply e-mail. Any emails sent back to me will be ignored.</p>";

			$user_name = ($config['ServerEngine'] !== 'OTHIRE') ? $user_data['name'] : $user_data['id'];
			//echo "<h1>" . $title . "<h1>" . $body;
			$mailer->sendMail($user_data['email'], $title, $body, $user_name);
			?>
			<h1>Email authentication sent</h1>
			<p>We have sent you an email with a verification link to your email address: <strong><?php echo $user_data['email']; ?></strong></p>
			<p>If you can't find the email within 5 minutes, check your <strong>junk/trash inbox (spam filter)</strong> as it may be misplaced there.</p>
			<?php
		} else {
			echo '<h1>Authentication failed</h1> <p>Failed to verify user when trying to send a verification email.</p>';
		}
	}
endif;

if (!empty($_POST['selected_character'])) {
	if (!empty($_POST['action'])) {
		if (!Token::isValid($_POST['token'])) {
			exit();
		}
		$action = getValue($_POST['action']);
		$char_name = getValue($_POST['selected_character']);
		$safe_char_name = htmlspecialchars($char_name);

		switch($action) {
			case 'update_comment':
				if (user_character_account_id($char_name) === $session_user_id) {
					user_update_comment(user_character_id($char_name), getValue($_POST['comment']));
					add_account_notice('success', 'Comentário de ' . $safe_char_name . ' atualizado.');
				}
				break;

			case 'toggle_hide':
				$hide = (user_character_hide($char_name) == 1 ? 0 : 1);
				if (user_character_account_id($char_name) === $session_user_id) {
					user_character_set_hide(user_character_id($char_name), $hide);
					add_account_notice('success', $safe_char_name . ($hide == 1 ? ' agora está oculto nas listas públicas.' : ' voltou a aparecer nas listas públicas.'));
				}
				break;

			case 'delete_character':
				if (user_character_account_id($char_name) === $session_user_id) {
					$charid = user_character_id($char_name);
					if ($charid !== false) {
						if ($config['ServerEngine'] === 'TFS_10') {
							if (!user_is_online_10($charid)) {
								if (guild_leader_gid($charid) === false) user_delete_character_soft($charid);
								else add_account_notice('error', $safe_char_name . ' é líder de uma guild. Desfaça a guild ou passe a liderança antes de excluir o personagem.');
							} else add_account_notice('error', $safe_char_name . ' precisa estar offline para ser excluído.');
						} else {
							$chr_data = user_character_data($charid, 'online');
							if ($chr_data['online'] != 1) {
								if (guild_leader_gid($charid) === false) user_delete_character_soft($charid);
								else add_account_notice('error', $safe_char_name . ' é líder de uma guild. Desfaça a guild ou passe a liderança antes de excluir o personagem.');
							} else add_account_notice('error', $safe_char_name . ' precisa estar offline para ser excluído.');
						}
					}
				}
				break;

			case 'change_name':
				$oldname = $char_name;
				$newname = isset($_POST['newName']) ? getValue($_POST['newName']) : '';

				$player = false;
				if ($config['ServerEngine'] === 'TFS_10') {
					$player = mysql_select_single("SELECT `id`, `account_id` FROM `players` WHERE `name` = '$oldname'");
					$player['online'] = (user_is_online_10($player['id'])) ? 1 : 0;
				} else $player = mysql_select_single("SELECT `id`, `account_id`, `online` FROM `players` WHERE `name` = '$oldname'");

				if ($player['online'] == 1) {
					$errors[] = 'O personagem precisa estar offline.';
				}

				$accountId = $player['account_id'];
				$order = mysql_select_single("SELECT `id`, `account_id` FROM `znote_shop_orders` WHERE `type`='4' AND `account_id` = '$accountId' LIMIT 1;");
				if ($order === false) {
					$errors[] = 'Você não tem tickets de troca de nome. Eles estão disponíveis na <a href="shop.php">loja</a>.';
				}

				if ($session_user_id != $accountId || $session_user_id != $order['account_id']) {
					if (empty($errors)) {
						$errors[] = 'Não foi possível confirmar sua conta. Entre novamente e tente outra vez.';
					}
				}

				$newname = validate_name($newname);
				if ($newname === false) {
					$errors[] = 'O nome pode ter no máximo 2 palavras.';
				} else {
					if (empty($newname)) {
						$errors[] = 'Digite o novo nome.';
					} else if (user_character_exist($newname) !== false) {
						$errors[] = 'Já existe um personagem com esse nome.';
					} else if (!preg_match("/^[a-zA-Z_ ]+$/", $newname)) {
						$errors[] = 'O nome só pode ter letras (a-z, A-Z) e espaços.';
					} else if (strlen($newname) < $config['minL'] || strlen($newname) > $config['maxL']) {
						$errors[] = 'O nome deve ter entre ' . $config['minL'] . ' e ' . $config['maxL'] . ' caracteres.';
					} else if (!ctype_upper($newname[0])) {
						$errors[] = 'A primeira letra do nome precisa ser maiúscula.';
					}

					$resname = explode(" ", $_POST['newName']);
					foreach($resname as $res) {
						if(in_array(strtolower($res), $config['invalidNameTags'])) {
							$errors[] = 'O nome contém uma palavra proibida.';
						} else if(strlen($res) == 1) {
							$errors[] = 'O nome tem palavras curtas demais.';
						}
					}
				}

				if (!empty($newname) && empty($errors)) {
					add_account_notice('success', 'Pronto! ' . $safe_char_name . ' agora se chama ' . htmlspecialchars($newname) . '.');
					mysql_update("UPDATE `players` SET `name`='$newname' WHERE `id`='".$player['id']."' LIMIT 1;");
					mysql_delete("DELETE FROM `znote_shop_orders` WHERE `id`='".$order['id']."' LIMIT 1;");

				} else if (!empty($errors)) {
					add_account_notice('error', 'Não foi possível trocar o nome de ' . $safe_char_name . '.', $errors);
				}

				break;

			case 'change_gender':
				if (user_character_account_id($char_name) === $session_user_id) {
					$char_id = (int)user_character_id($char_name);
					$account_id = user_character_account_id($char_name);

					if ($config['ServerEngine'] == 'TFS_10') {
						$chr_data['online'] = user_is_online_10($char_id) ? 1 : 0;
					} else $chr_data = user_character_data($char_id, 'online');
					if ($chr_data['online'] != 1) {
						if ($account_id != $user_data['id']) die("wtf? Something went wrong, try relogging.");

						$tickets = shop_account_gender_tickets($account_id);
						if ($tickets !== false || $config['free_sex_change'] == true) {
							$last = false;
							$infinite = false;
							$tks = 0;
							foreach ($tickets as $ticket) {
								if ($ticket['count'] == 0) $infinite = true;
								else if ($ticket > 0 && $infinite === false) $tks += (int)$ticket['count'];
							}
							if ($infinite === true) $tks = 0;
							$dbid = (int)$tickets[0]['id'];
							if ($tickets[0]['count'] > 1) {
								$tks--;
								$tkr = ((int)$tickets[0]['count'] - 1);
								shop_update_row_count($dbid, $tkr);
							} else if ($tickets[0]['count'] == 1) {
								shop_delete_row_order($dbid);
								$tks--;
							}

							user_character_change_gender($char_name);
							$gender_ticket_message = '';
							if ($tks > 0) $gender_ticket_message = ' Você ainda tem ' . $tks . ' ticket(s) de troca de sexo.';
							else if ($infinite !== true) $gender_ticket_message = ' Seus tickets de troca de sexo acabaram.';
							add_account_notice('success', 'O sexo de ' . $safe_char_name . ' foi trocado.' . $gender_ticket_message);
						} else add_account_notice('error', 'Você não tem tickets de troca de sexo. Eles estão disponíveis na <a href="shop.php">loja</a>.');
					} else add_account_notice('error', $safe_char_name . ' precisa estar offline para trocar de sexo.');
				}
				break;

			case 'change_comment':
				$render_page = false;
				if (user_character_account_id($char_name) === $session_user_id) {
					$comment_data = user_znote_character_data(user_character_id($char_name), 'comment');
					?>
					<section class="account-page">
						<a class="account-back" href="myaccount.php"><i class="fa fa-arrow-left"></i> Voltar para minha conta</a>
						<div class="account-panel account-comment">
							<h1>Comentário do personagem</h1>
							<p>Esse texto aparece no perfil público de <strong><?php echo $safe_char_name; ?></strong>.</p>
							<form action="" method="post">
								<input name="action" type="hidden" value="update_comment">
								<input name="selected_character" type="hidden" value="<?php echo $safe_char_name; ?>">
								<label for="account_comment">Comentário</label>
								<textarea id="account_comment" name="comment" rows="8" placeholder="Conte um pouco sobre seu personagem..."><?php echo $comment_data['comment']; ?></textarea>
								<?php Token::create(); ?>
								<div class="account-comment-actions">
									<button type="submit"><i class="fa fa-check"></i>&nbsp; Salvar comentário</button>
									<a class="btn btn-default" href="myaccount.php">Cancelar</a>
								</div>
							</form>
						</div>
					</section>
					<?php
				}
				break;
		}
	}
}

function build_account_outfit_url($outfit_row) {
	$show_outfits = config('show_outfits');

	if (empty($show_outfits['characterprofile']) || empty($show_outfits['imageServer']) || (int) $outfit_row['looktype'] <= 0) {
		return null;
	}

	return $show_outfits['imageServer'] . '?' . http_build_query(array(
		'id' => (int) $outfit_row['looktype'],
		'addons' => (int) $outfit_row['lookaddons'],
		'head' => (int) $outfit_row['lookhead'],
		'body' => (int) $outfit_row['lookbody'],
		'legs' => (int) $outfit_row['looklegs'],
		'feet' => (int) $outfit_row['lookfeet']
	));
}

function load_account_outfits($account_id) {
	$outfit_rows = mysql_select_multi("SELECT `name`, `looktype`, `lookaddons`, `lookhead`, `lookbody`, `looklegs`, `lookfeet` FROM `players` WHERE `account_id` = " . (int) $account_id . ";");
	$outfit_urls_by_name = array();

	if (empty($outfit_rows)) {
		return $outfit_urls_by_name;
	}

	foreach ($outfit_rows as $outfit_row) {
		$outfit_urls_by_name[$outfit_row['name']] = build_account_outfit_url($outfit_row);
	}

	return $outfit_urls_by_name;
}

function describe_account_vocation($vocation_name) {
	return preg_match('/^\d+ - Unknown$/', $vocation_name) ? null : $vocation_name;
}

function describe_account_last_login($last_login) {
	return $last_login === 'Never.' ? 'Nunca entrou' : $last_login;
}

function mask_account_email($email) {
	$email_parts = explode('@', $email, 2);

	if (count($email_parts) !== 2 || $email_parts[0] === '') {
		return $email;
	}

	$visible_start = substr($email_parts[0], 0, min(2, strlen($email_parts[0])));
	return $visible_start . str_repeat('•', max(3, strlen($email_parts[0]) - 2)) . '@' . $email_parts[1];
}

if ($render_page) {
	$char_count = user_character_list_count($session_user_id);
	$pending_delete = user_pending_deletes($session_user_id);
	$account_pending_deletions = array();
	if ($pending_delete) {
		foreach($pending_delete as $delete) {
			if(new DateTime($delete['time']) > new DateTime())
				$account_pending_deletions[] = $delete;
			else {
				user_delete_character(user_character_id($delete['character_name']));
				mysql_update('UPDATE `znote_deleted_characters` SET `done` = 1 WHERE `id` = '. $delete['id']. '');
				add_account_notice('info', htmlspecialchars($delete['character_name']) . ' foi excluído, como solicitado.');
				$char_count--;
			}
		}
	}

	$account_display_name = ($config['ServerEngine'] !== 'OTHIRE') ? $user_data['name'] : $user_data['id'];
	$account_is_premium = ($config['ServerEngine'] !== 'OTHIRE') ? $user_data['premdays'] != 0 : $user_data['premend'] != 0;
	$account_premium_text = ($config['ServerEngine'] !== 'OTHIRE')
		? $user_data['premdays'] . ' dia' . ($user_data['premdays'] == 1 ? '' : 's') . ' de premium'
		: 'Premium até ' . date('d/m/Y', (int) $user_data['premend']);
	$account_created_at = isset($user_znote_data['created']) ? (int) $user_znote_data['created'] : 0;
	$account_coin_balance = user_data($session_user_id, 'coins', 'coins_transferable');
	$account_tibia_coins = (int) $account_coin_balance['coins'] + (int) $account_coin_balance['coins_transferable'];
	$account_verifies_email = $config['mailserver']['myaccount_verify_email'];
	$account_uses_two_factor = $config['ServerEngine'] === 'TFS_10' && $config['twoFactorAuthenticator'];
	$account_two_factor_enabled = false;
	if ($account_uses_two_factor) {
		$query = mysql_select_single("SELECT `secret` FROM `accounts` WHERE `id`='".(int)$session_user_id."' LIMIT 1;");
		$account_two_factor_enabled = ($query['secret'] === NULL) ? false : true;
	}
	$char_array = user_character_list($user_data['id']);
	$account_outfit_urls = $char_array ? load_account_outfits($user_data['id']) : array();
	?>
	<section class="account-page" id="myaccount">
		<div class="account-hero">
			<span class="account-avatar"><?php echo htmlspecialchars(strtoupper(substr((string) $account_display_name, 0, 1))); ?></span>
			<div class="account-hero-content">
				<span class="account-eyebrow">Minha conta</span>
				<h1>Olá, <?php echo htmlspecialchars($account_display_name); ?></h1>
				<ul class="account-badges">
					<?php if ($account_is_premium): ?>
						<li class="account-badge account-badge-premium"><i class="fa fa-star"></i> <?php echo $account_premium_text; ?></li>
					<?php else: ?>
						<li class="account-badge"><i class="fa fa-star-o"></i> Conta grátis</li>
					<?php endif; ?>
					<li class="account-badge"><a href="buypoints.php"><span class="buypoints-coin" aria-hidden="true"></span> <?php echo number_format($account_tibia_coins, 0, ',', '.'); ?> Tibia Coins</a></li>
					<?php if ($account_created_at > 0): ?>
						<li class="account-badge"><i class="fa fa-calendar"></i> Desde <?php echo date('d/m/Y', $account_created_at); ?></li>
					<?php endif; ?>
				</ul>
				<p class="account-email">
					<i class="fa fa-envelope"></i> <?php echo htmlspecialchars(mask_account_email($user_data['email'])); ?>
					<?php if ($account_verifies_email): ?>
						<?php if ($user_znote_data['active_email'] == 1): ?>
							<span class="account-status account-status-ok"><i class="fa fa-check"></i> verificado</span>
						<?php else: ?>
							<a class="account-status account-status-warning" href="?authenticate"><i class="fa fa-exclamation-circle"></i> verificar email</a>
						<?php endif; ?>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<?php foreach ($account_notices as $account_notice): ?>
			<div class="account-notice account-notice-<?php echo $account_notice['type']; ?>" role="status">
				<i class="fa <?php echo $account_notice['type'] === 'error' ? 'fa-exclamation-triangle' : ($account_notice['type'] === 'success' ? 'fa-check-circle' : 'fa-info-circle'); ?>"></i>
				<div>
					<span><?php echo $account_notice['text']; ?></span>
					<?php if (!empty($account_notice['items'])): ?>
						<ul>
							<?php foreach ($account_notice['items'] as $account_notice_item): ?>
								<li><?php echo $account_notice_item; ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<?php foreach ($account_pending_deletions as $account_pending_deletion): ?>
			<div class="account-notice account-notice-warning" role="alert">
				<i class="fa fa-clock-o"></i>
				<div>
					<span><strong><?php echo htmlspecialchars($account_pending_deletion['character_name']); ?></strong> será excluído em <?php echo date('d/m/Y \à\s H:i', strtotime($account_pending_deletion['time'])); ?>.</span>
				</div>
				<a class="btn btn-default" href="myaccount.php?cancel_delete_id=<?php echo (int) $account_pending_deletion['id']; ?>">Cancelar exclusão</a>
			</div>
		<?php endforeach; ?>

		<nav class="account-shortcuts" aria-label="Atalhos da conta">
			<a class="account-shortcut" href="createcharacter.php"><i class="fa fa-user-plus"></i><span>Criar personagem</span></a>
			<?php if (is_launcher_download_enabled()): ?>
				<a class="account-shortcut" href="downloads.php"><i class="fa fa-download"></i><span>Baixar launcher</span></a>
			<?php endif; ?>
			<a class="account-shortcut" href="buypoints.php"><i class="fa fa-heart"></i><span>Doar</span></a>
			<a class="account-shortcut" href="changepassword.php"><i class="fa fa-key"></i><span>Alterar senha</span></a>
			<a class="account-shortcut" href="settings.php"><i class="fa fa-cog"></i><span>Configurações</span></a>
			<?php if ($account_uses_two_factor): ?>
				<a class="account-shortcut" href="twofa.php"><i class="fa fa-shield"></i><span>Verificação em 2 etapas</span><small class="<?php echo $account_two_factor_enabled ? 'is-on' : 'is-off'; ?>"><?php echo $account_two_factor_enabled ? 'Ativada' : 'Desativada'; ?></small></a>
			<?php endif; ?>
		</nav>

		<div class="account-section-header">
			<h2>Seus personagens <span><?php echo max(0, (int) $char_count); ?></span></h2>
			<a class="btn" href="createcharacter.php"><i class="fa fa-plus"></i>&nbsp; Novo personagem</a>
		</div>

		<?php if ($char_array): ?>
			<div class="account-characters">
				<?php foreach ($char_array as $character): ?>
					<?php
						$character_name = $character['name'];
						$character_safe_name = htmlspecialchars($character_name);
						$character_is_online = strtoupper(trim(strip_tags($character['online']))) === 'ONLINE';
						$character_is_hidden = user_character_hide($character_name) == 1;
						$character_vocation = describe_account_vocation($character['vocation']);
						$character_outfit_url = isset($account_outfit_urls[$character_name]) ? $account_outfit_urls[$character_name] : null;
					?>
					<article class="account-character<?php echo $character_is_online ? ' is-online' : ''; ?>">
						<div class="account-character-main">
							<div class="account-character-outfit">
								<?php if ($character_outfit_url !== null): ?>
									<img src="<?php echo htmlspecialchars($character_outfit_url); ?>" alt="" loading="lazy" onerror="this.remove()">
								<?php endif; ?>
								<i class="fa fa-user"></i>
							</div>
							<div class="account-character-info">
								<a class="account-character-name" href="characterprofile.php?name=<?php echo urlencode($character_name); ?>"><?php echo $character_safe_name; ?></a>
								<span class="account-character-level">Level <strong><?php echo (int) $character['level']; ?></strong><?php echo $character_vocation !== null ? ' · ' . htmlspecialchars($character_vocation) : ''; ?></span>
								<ul class="account-character-meta">
									<li class="account-presence<?php echo $character_is_online ? ' is-online' : ''; ?>"><span></span><?php echo $character_is_online ? 'Online' : 'Offline'; ?></li>
									<li><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($character['town_id']); ?></li>
									<li><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars(describe_account_last_login($character['lastlogin'])); ?></li>
									<?php if ($character_is_hidden): ?>
										<li class="account-hidden-tag"><i class="fa fa-eye-slash"></i> Oculto</li>
									<?php endif; ?>
								</ul>
							</div>
						</div>
						<form class="account-character-actions" action="myaccount.php" method="post">
							<input type="hidden" name="selected_character" value="<?php echo $character_safe_name; ?>">
							<?php Token::create(); ?>
							<button type="submit" name="action" value="change_comment" class="account-action"><i class="fa fa-comment"></i> Comentário</button>
							<button type="submit" name="action" value="toggle_hide" class="account-action"><i class="fa <?php echo $character_is_hidden ? 'fa-eye' : 'fa-eye-slash'; ?>"></i> <?php echo $character_is_hidden ? 'Mostrar' : 'Ocultar'; ?></button>
							<button type="submit" name="action" value="change_gender" class="account-action"><i class="fa fa-venus-mars"></i> Trocar sexo</button>
							<details class="account-rename">
								<summary class="account-action"><i class="fa fa-pencil"></i> Trocar nome</summary>
								<div class="account-rename-form">
									<input type="text" name="newName" placeholder="Novo nome" autocomplete="off">
									<button type="submit" name="action" value="change_name">Confirmar</button>
								</div>
							</details>
							<button type="submit" name="action" value="delete_character" class="account-action account-action-danger" data-confirm="Excluir <?php echo $character_safe_name; ?>? A exclusão fica agendada e pode ser cancelada até a data marcada."><i class="fa fa-trash"></i> Excluir</button>
						</form>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="account-empty">
				<i class="fa fa-user-plus"></i>
				<h3>Você ainda não tem personagens</h3>
				<p>Crie seu primeiro personagem para começar a aventura.</p>
				<a class="btn" href="createcharacter.php">Criar personagem</a>
			</div>
		<?php endif; ?>
	</section>
	<script>
		(function () {
			Array.prototype.forEach.call(document.querySelectorAll('.account-character-actions'), function (actionForm) {
				actionForm.addEventListener('submit', function (submitEvent) {
					var submitter = submitEvent.submitter;

					if (!submitter) {
						return;
					}

					var confirmation = submitter.getAttribute('data-confirm');

					if (confirmation && !window.confirm(confirmation)) {
						submitEvent.preventDefault();
						return;
					}

					if (submitter.value !== 'change_name') {
						var renameInput = actionForm.querySelector('input[name="newName"]');

						if (renameInput) {
							renameInput.disabled = true;
						}
					}
				});
			});
		})();
	</script>
	<?php
}
include 'layout/overall/footer.php';
?>
