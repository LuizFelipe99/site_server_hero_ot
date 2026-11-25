<?php
require_once 'engine/init.php';
logged_in_redirect();
include 'layout/overall/header.php';
require_once('config.countries.php');

if (empty($_POST) === false) {
	$required_fields = array('username', 'password', 'password_again', 'email', 'selected');
	foreach($_POST as $key=>$value) {
		if (empty($value) && in_array($key, $required_fields) === true) {
			$errors[] = 'Preencha todos os campos.';
			break 1;
		}
	}

	if (empty($errors) === true) {
		if (!Token::isValid($_POST['token'])) {
			$errors[] = 'Sua sessão expirou. Atualize a página e tente novamente.';
		}

		if ($config['use_captcha']) {
			if(!verifyGoogleReCaptcha($_POST['g-recaptcha-response'])) {
				$errors[] = 'Confirme que você não é um robô.';
			}
		}

		if (user_exist($_POST['username']) === true) {
			$errors[] = 'Esse nome de conta já está em uso.';
		}

		$isNoob = in_array(strtolower($_POST['username']), $config['page_admin_access']) ? true : false;
		if ($isNoob) {
			$errors[] = 'Esse nome de conta não pode ser registrado.';
		}
		if ($config['ServerEngine'] !== 'OTHIRE' && $config['client'] >= 830) {
			if (preg_match("/^[a-zA-Z0-9]+$/", $_POST['username']) == false) {
				$errors[] = 'O nome da conta só pode ter letras (a-z, A-Z) e números (0-9).';
			}
		} else {
			if (preg_match("/^[0-9]+$/", $_POST['username']) == false) {
				$errors[] = 'O número da conta só pode ter números (0-9).';
			}
			if ((int)$_POST['username'] < 100000 || (int)$_POST['username'] > 999999999) {
				$errors[] = 'O número da conta deve ter entre 6 e 9 dígitos.';
			}
		}
		$resname = explode(" ", $_POST['username']);
		foreach($resname as $res) {
			if(in_array(strtolower($res), $config['invalidNameTags'])) {
				$errors[] = 'O nome da conta contém uma palavra proibida.';
			}
			else if(strlen($res) == 1) {
				$errors[] = 'O nome da conta é curto demais.';
			}
		}
		if (strlen($_POST['username']) > 32) {
			$errors[] = 'O nome da conta deve ter no máximo 32 caracteres.';
		}
		if (strlen($_POST['password']) < 6) {
			$errors[] = 'A senha deve ter pelo menos 6 caracteres.';
		}
		if (strlen($_POST['password']) > 29) {
			$errors[] = 'A senha deve ter no máximo 29 caracteres.';
		}
		if ($_POST['password'] !== $_POST['password_again']) {
			$errors[] = 'As senhas não coincidem.';
		}
		if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) === false) {
			$errors[] = 'Informe um email válido.';
		}
		if (user_email_exist($_POST['email']) === true) {
			$errors[] = 'Esse email já está em uso.';
		}
		if ($_POST['selected'] != 1) {
			$errors[] = 'Para criar uma conta é preciso aceitar as regras do servidor.';
		}
		if ($config['validate_IP'] === true) {
			if (validate_ip(getIP()) === false) {
				$errors[] = 'Não foi possível reconhecer seu endereço IP (não é um IPv4 válido).';
			}
		}
		if (strlen($_POST['flag']) < 1) {
			$errors[] = 'Escolha o seu país.';
		}
	}
}

$register_uses_numeric_account = !($config['ServerEngine'] !== 'OTHIRE' && $config['client'] >= 830);
$register_submitted_username = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
$register_submitted_email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
$register_submitted_flag = isset($_POST['flag']) ? $_POST['flag'] : '';
$register_accepted_rules = isset($_POST['selected']) && $_POST['selected'] == 1;
$register_featured_countries = array('br', 'pt', 'us', 'gb', 'pl', 'se');

function render_register_country_option($country_code, $country_name, $selected_country_code) {
	$is_selected = $country_code !== '' && $country_code === $selected_country_code;
	echo '<option value="' . htmlspecialchars($country_code) . '"' . ($is_selected ? ' selected' : '') . '>' . htmlspecialchars($country_name) . '</option>';
}

function render_register_next_steps($active_step) {
	$register_steps = array(
		1 => array('icon' => 'fa-user-plus', 'title' => 'Criar conta'),
		2 => array('icon' => 'fa-download', 'title' => 'Baixar o launcher'),
		3 => array('icon' => 'fa-shield', 'title' => 'Criar personagem')
	);
	echo '<ol class="register-steps">';
	foreach ($register_steps as $step_number => $register_step) {
		$step_state = $step_number < $active_step ? 'is-done' : ($step_number === $active_step ? 'is-active' : '');
		echo '<li class="register-step ' . $step_state . '"><span class="register-step-badge"><i class="fa ' . $register_step['icon'] . '"></i></span><span class="register-step-title">' . $register_step['title'] . '</span></li>';
	}
	echo '</ol>';
}
?>
<section class="register-page">
<?php
if (isset($_GET['success']) && empty($_GET['success'])) {
	?>
	<div class="register-result register-result-success">
		<span class="register-result-icon"><i class="fa fa-check"></i></span>
		<?php if ($config['mailserver']['register']): ?>
			<h1>Confirme seu email</h1>
			<p>Enviamos um link de ativação para o email que você cadastrou.</p>
			<p>Se não encontrar a mensagem em 5 minutos, confira a <strong>caixa de spam ou lixo eletrônico</strong>.</p>
		<?php else: ?>
			<h1>Conta criada!</h1>
			<p>Tudo pronto. <?php echo is_launcher_download_enabled() ? 'Agora é só baixar o launcher, entrar com sua conta e criar seu personagem.' : 'Agora é só criar seu personagem. O launcher estará disponível para download em breve.'; ?></p>
			<div class="register-result-actions">
				<?php if (is_launcher_download_enabled()): ?>
					<a class="btn" href="downloads.php"><i class="fa fa-download"></i>&nbsp; Baixar o launcher</a>
				<?php endif; ?>
				<a class="btn btn-default" href="sub.php?page=loginhelp"><i class="fa fa-sign-in"></i>&nbsp; Entrar</a>
			</div>
		<?php endif; ?>
		<?php render_register_next_steps(2); ?>
	</div>
	<?php
} elseif (isset($_GET['authenticate']) && empty($_GET['authenticate'])) {
	$auid = (isset($_GET['u']) && (int)$_GET['u'] > 0) ? (int)$_GET['u'] : false;
	$akey = (isset($_GET['k']) && (int)$_GET['k'] > 0) ? (int)$_GET['k'] : false;
	$user = mysql_select_single("SELECT `id`, `active`, `active_email` FROM `znote_accounts` WHERE `account_id`='$auid' AND `activekey`='$akey' LIMIT 1;");
	if ($user !== false) {
		$user = (int) $user['id'];
		$active = (int) $user['active'];
		$active_email = (int) $user['active_email'];
		if ($active == 0 || $active_email == 0) {
			mysql_update("UPDATE `znote_accounts` SET `active`='1', `active_email`='1' WHERE `id`= $user LIMIT 1;");
		}
		?>
		<div class="register-result register-result-success">
			<span class="register-result-icon"><i class="fa fa-check"></i></span>
			<h1>Conta ativada!</h1>
			<p>Seu email foi confirmado. <?php echo is_launcher_download_enabled() ? 'Agora é só baixar o launcher, entrar com sua conta e criar seu personagem.' : 'Agora é só criar seu personagem. O launcher estará disponível para download em breve.'; ?></p>
			<div class="register-result-actions">
				<?php if (is_launcher_download_enabled()): ?>
					<a class="btn" href="downloads.php"><i class="fa fa-download"></i>&nbsp; Baixar o launcher</a>
				<?php endif; ?>
				<a class="btn btn-default" href="sub.php?page=loginhelp"><i class="fa fa-sign-in"></i>&nbsp; Entrar</a>
			</div>
		</div>
		<?php
	} else {
		?>
		<div class="register-result register-result-failure">
			<span class="register-result-icon"><i class="fa fa-times"></i></span>
			<h1>Não foi possível ativar</h1>
			<p>O link de ativação está incorreto ou a conta já foi ativada.</p>
		</div>
		<?php
	}
} else {
	if (empty($_POST) === false && empty($errors) === true) {
		if ($config['log_ip']) {
			znote_visitor_insert_detailed_data(1);
		}

		if ($config['ServerEngine'] !== 'OTHIRE') {
			$register_data = array(
				'name'		=>	$_POST['username'],
				'password'	=>	$_POST['password'],
				'email'		=>	$_POST['email'],
				'created'	=>	time(),
				'ip'		=>	getIPLong(),
				'flag'		=> 	$_POST['flag']
			);
		} else {
			$register_data = array(
				'id'		=>	$_POST['username'],
				'password'	=>	$_POST['password'],
				'email'		=>	$_POST['email'],
				'created'	=>	time(),
				'ip'		=>	getIPLong(),
				'flag'		=> 	$_POST['flag']
			);
		}

		user_create_account($register_data, $config['mailserver']);
		if (!$config['mailserver']['debug']) header('Location: register.php?success');
		exit();
	}
	?>
	<header class="register-hero">
		<span class="register-eyebrow"><i class="fa fa-star"></i> Nova aventura</span>
		<h1>Crie sua conta</h1>
		<p>Leva menos de um minuto. Depois é só baixar o launcher e escolher sua vocação.</p>
		<?php render_register_next_steps(1); ?>
	</header>

	<?php if (empty($errors) === false): ?>
		<div class="register-alert" role="alert">
			<i class="fa fa-exclamation-triangle"></i>
			<div>
				<strong>Não foi possível criar a conta</strong>
				<ul>
					<?php foreach ($errors as $register_error): ?>
						<li><?php echo htmlspecialchars($register_error); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>

	<form class="register-form" action="" method="post" novalidate data-numeric-account="<?php echo $register_uses_numeric_account ? '1' : '0'; ?>">
		<fieldset class="register-section">
			<legend><span class="register-section-index">1</span> Dados da conta</legend>

			<div class="register-field" data-field="username">
				<label for="register_username"><?php echo $register_uses_numeric_account ? 'Número da conta' : 'Nome da conta'; ?></label>
				<div class="register-input">
					<i class="fa fa-user"></i>
					<input type="text" id="register_username" name="username" value="<?php echo $register_submitted_username; ?>" maxlength="32" autocomplete="username" autocapitalize="off" spellcheck="false" placeholder="<?php echo $register_uses_numeric_account ? 'Ex.: 123456' : 'Ex.: heroi2026'; ?>">
				</div>
				<small class="register-hint"><?php echo $register_uses_numeric_account ? 'Somente números, de 6 a 9 dígitos.' : 'Apenas letras e números, até 32 caracteres. Ele serve só para entrar e não aparece no jogo.'; ?></small>
			</div>

			<div class="register-field" data-field="email">
				<label for="register_email">Email</label>
				<div class="register-input">
					<i class="fa fa-envelope"></i>
					<input type="email" id="register_email" name="email" value="<?php echo $register_submitted_email; ?>" autocomplete="email" placeholder="voce@exemplo.com">
				</div>
				<small class="register-hint">Usado para recuperar a conta caso você esqueça a senha.</small>
			</div>

			<div class="register-field" data-field="flag">
				<label for="register_flag">País</label>
				<div class="register-input">
					<i class="fa fa-globe"></i>
					<select id="register_flag" name="flag">
						<option value="">Escolha seu país</option>
						<?php
						foreach ($register_featured_countries as $featured_country_code) {
							render_register_country_option($featured_country_code, $config['countries'][$featured_country_code], $register_submitted_flag);
						}
						echo '<option value="" disabled>──────────</option>';
						foreach ($config['countries'] as $country_code => $country_name) {
							render_register_country_option($country_code, $country_name, $register_submitted_flag);
						}
						?>
					</select>
				</div>
				<small class="register-hint">Aparece como bandeira no seu perfil.</small>
			</div>
		</fieldset>

		<fieldset class="register-section">
			<legend><span class="register-section-index">2</span> Segurança</legend>

			<div class="register-grid">
				<div class="register-field" data-field="password">
					<label for="register_password">Senha</label>
					<div class="register-input">
						<i class="fa fa-lock"></i>
						<input type="password" id="register_password" name="password" maxlength="29" autocomplete="new-password" placeholder="Crie uma senha">
						<button type="button" class="register-password-toggle" data-toggle-password="register_password" aria-label="Mostrar senha"><i class="fa fa-eye"></i></button>
					</div>
					<div class="register-strength" aria-hidden="true">
						<span></span><span></span><span></span><span></span>
					</div>
					<small class="register-hint" data-strength-label>Entre 6 e 29 caracteres.</small>
				</div>

				<div class="register-field" data-field="password_again">
					<label for="register_password_again">Confirme a senha</label>
					<div class="register-input">
						<i class="fa fa-lock"></i>
						<input type="password" id="register_password_again" name="password_again" maxlength="29" autocomplete="new-password" placeholder="Repita a senha">
						<button type="button" class="register-password-toggle" data-toggle-password="register_password_again" aria-label="Mostrar senha"><i class="fa fa-eye"></i></button>
					</div>
					<small class="register-hint">Digite a mesma senha novamente.</small>
				</div>
			</div>

			<?php if ($config['use_captcha']): ?>
				<div class="register-captcha">
					<div class="g-recaptcha" data-sitekey="<?php echo $config['captcha_site_key']; ?>"></div>
				</div>
			<?php endif; ?>
		</fieldset>

		<fieldset class="register-section">
			<legend><span class="register-section-index">3</span> Regras do servidor</legend>

			<ul class="register-rules">
				<li><i class="fa fa-smile-o"></i> <span>A regra de ouro: divirta-se.</span></li>
				<li><i class="fa fa-trophy"></i> <span>Perdeu uma batalha? Faz parte do jogo, não desconte em ninguém.</span></li>
				<li><i class="fa fa-ban"></i> <span>Proibido usar <a href="https://pt.wikipedia.org/wiki/Trapa%C3%A7a_em_jogos_eletr%C3%B4nicos" target="_blank" rel="noopener">trapaças</a>.</span></li>
				<li><i class="fa fa-android"></i> <span>Proibido usar <a href="https://pt.wikipedia.org/wiki/Bot" target="_blank" rel="noopener">bots</a>.</span></li>
				<li><i class="fa fa-gavel"></i> <span>A equipe pode excluir, banir ou tomar qualquer medida sobre sua conta e os dados enviados, incluindo registrar e expor seu IP.</span></li>
			</ul>

			<div class="register-field" data-field="selected">
				<input type="hidden" name="selected" value="2">
				<label class="register-accept">
					<input type="checkbox" id="register_accept_rules" name="selected" value="1"<?php echo $register_accepted_rules ? ' checked' : ''; ?>>
					<span class="register-accept-box"><i class="fa fa-check"></i></span>
					<span>Li e aceito as regras do servidor</span>
				</label>
			</div>
		</fieldset>

		<?php Token::create(); ?>

		<div class="register-actions">
			<button type="submit" class="register-submit"><i class="fa fa-user-plus"></i>&nbsp; Criar minha conta</button>
			<p class="register-login">Já tem uma conta? <a href="sub.php?page=loginhelp">Entrar</a></p>
		</div>
	</form>

	<script>
		(function () {
			var registerForm = document.querySelector('.register-form');

			if (!registerForm) {
				return;
			}

			var usesNumericAccount = registerForm.getAttribute('data-numeric-account') === '1';
			var strengthLabels = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte'];
			var touchedFields = {};

			function readValue(fieldName) {
				var fieldInput = registerForm.querySelector('[name="' + fieldName + '"]:not([type="hidden"])');
				return fieldInput ? fieldInput.value : '';
			}

			function validateUsername(username) {
				if (username.length === 0) {
					return 'Informe o ' + (usesNumericAccount ? 'número' : 'nome') + ' da conta.';
				}

				if (usesNumericAccount) {
					if (!/^[0-9]+$/.test(username)) {
						return 'Use somente números.';
					}

					var accountNumber = parseInt(username, 10);
					return accountNumber < 100000 || accountNumber > 999999999 ? 'Use de 6 a 9 dígitos.' : '';
				}

				if (!/^[a-zA-Z0-9]+$/.test(username)) {
					return 'Use apenas letras e números, sem espaços ou acentos.';
				}

				return username.length < 2 ? 'Use pelo menos 2 caracteres.' : '';
			}

			function validateEmail(email) {
				if (email.length === 0) {
					return 'Informe seu email.';
				}

				return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? '' : 'Esse email não parece válido.';
			}

			function validatePassword(password) {
				if (password.length < 6) {
					return 'A senha precisa de pelo menos 6 caracteres.';
				}

				return password.length > 29 ? 'A senha pode ter no máximo 29 caracteres.' : '';
			}

			function validatePasswordConfirmation(passwordConfirmation) {
				if (passwordConfirmation.length === 0) {
					return 'Repita a senha.';
				}

				return passwordConfirmation === readValue('password') ? '' : 'As senhas não coincidem.';
			}

			function validateCountry(countryCode) {
				return countryCode.length === 0 ? 'Escolha seu país.' : '';
			}

			function validateRules() {
				return document.getElementById('register_accept_rules').checked ? '' : 'Aceite as regras para continuar.';
			}

			var fieldValidators = {
				username: function () { return validateUsername(readValue('username')); },
				email: function () { return validateEmail(readValue('email')); },
				flag: function () { return validateCountry(readValue('flag')); },
				password: function () { return validatePassword(readValue('password')); },
				password_again: function () { return validatePasswordConfirmation(readValue('password_again')); },
				selected: validateRules
			};

			function showFieldState(fieldName) {
				var fieldWrapper = registerForm.querySelector('.register-field[data-field="' + fieldName + '"]');

				if (!fieldWrapper || !touchedFields[fieldName]) {
					return '';
				}

				var validationMessage = fieldValidators[fieldName]();
				var messageElement = fieldWrapper.querySelector('.register-message');

				if (!messageElement) {
					messageElement = document.createElement('small');
					messageElement.className = 'register-message';
					fieldWrapper.appendChild(messageElement);
				}

				fieldWrapper.classList.toggle('is-invalid', validationMessage !== '');
				fieldWrapper.classList.toggle('is-valid', validationMessage === '');
				messageElement.textContent = validationMessage;
				return validationMessage;
			}

			function measurePasswordStrength(password) {
				if (password.length === 0) {
					return -1;
				}

				var strengthScore = 0;
				strengthScore += password.length >= 8 ? 1 : 0;
				strengthScore += password.length >= 12 ? 1 : 0;
				strengthScore += /[a-z]/.test(password) && /[A-Z]/.test(password) ? 1 : 0;
				strengthScore += /[0-9]/.test(password) ? 1 : 0;
				strengthScore += /[^a-zA-Z0-9]/.test(password) ? 1 : 0;
				return password.length < 6 ? 0 : Math.min(4, strengthScore);
			}

			function updatePasswordStrength() {
				var strengthMeter = registerForm.querySelector('.register-strength');
				var strengthLabel = registerForm.querySelector('[data-strength-label]');
				var strengthScore = measurePasswordStrength(readValue('password'));

				strengthMeter.setAttribute('data-strength', strengthScore);
				strengthLabel.textContent = strengthScore < 0 ? 'Entre 6 e 29 caracteres.' : 'Força da senha: ' + strengthLabels[strengthScore];
			}

			Object.keys(fieldValidators).forEach(function (fieldName) {
				var fieldInput = registerForm.querySelector('[name="' + fieldName + '"]:not([type="hidden"])');

				if (!fieldInput) {
					return;
				}

				var eventName = fieldInput.tagName === 'SELECT' || fieldInput.type === 'checkbox' ? 'change' : 'blur';

				fieldInput.addEventListener(eventName, function () {
					touchedFields[fieldName] = true;
					showFieldState(fieldName);
				});

				fieldInput.addEventListener('input', function () {
					showFieldState(fieldName);

					if (fieldName === 'password') {
						updatePasswordStrength();
						showFieldState('password_again');
					}
				});
			});

			Array.prototype.forEach.call(registerForm.querySelectorAll('[data-toggle-password]'), function (toggleButton) {
				toggleButton.addEventListener('click', function () {
					var passwordInput = document.getElementById(toggleButton.getAttribute('data-toggle-password'));
					var isHidden = passwordInput.type === 'password';

					passwordInput.type = isHidden ? 'text' : 'password';
					toggleButton.setAttribute('aria-label', isHidden ? 'Ocultar senha' : 'Mostrar senha');
					toggleButton.querySelector('i').className = isHidden ? 'fa fa-eye-slash' : 'fa fa-eye';
				});
			});

			registerForm.addEventListener('submit', function (submitEvent) {
				var firstInvalidField = null;

				Object.keys(fieldValidators).forEach(function (fieldName) {
					touchedFields[fieldName] = true;

					if (showFieldState(fieldName) !== '' && firstInvalidField === null) {
						firstInvalidField = fieldName;
					}
				});

				if (firstInvalidField === null) {
					registerForm.classList.add('is-submitting');
					return;
				}

				submitEvent.preventDefault();
				var invalidInput = registerForm.querySelector('[name="' + firstInvalidField + '"]:not([type="hidden"])');
				invalidInput.focus();
			});
		})();
	</script>
<?php
}
?>
</section>
<?php
include 'layout/overall/footer.php';
?>
