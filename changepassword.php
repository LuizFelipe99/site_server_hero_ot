<?php require_once 'engine/init.php';
protect_page();

const CHANGE_PASSWORD_MINIMUM_LENGTH = 6;
const CHANGE_PASSWORD_MAXIMUM_LENGTH = 100;
const CHANGE_PASSWORD_REDIRECT_SECONDS = 8;

function is_change_password_current_password_correct($session_user_id, $typed_current_password, $config) {
	$stored_password = user_data($session_user_id, 'password');

	if (sha1($typed_current_password) === $stored_password['password']) {
		return true;
	}

	if ($config['ServerEngine'] !== 'TFS_03' || $config['salt'] !== true) {
		return false;
	}

	$stored_salt = user_data($session_user_id, 'salt');
	return sha1($stored_salt['salt'] . $typed_current_password) === $stored_password['password'];
}

function validate_change_password_request($password_request, $session_user_id, $config) {
	if (!Token::isValid($password_request['token'])) {
		return array('Sua sessão expirou. Recarregue a página e tente novamente.');
	}

	foreach (array('current_password', 'new_password', 'new_password_again') as $required_field) {
		if (empty($password_request[$required_field])) {
			return array('Preencha todos os campos.');
		}
	}

	if (!is_change_password_current_password_correct($session_user_id, $password_request['current_password'], $config)) {
		return array('A senha atual está incorreta.');
	}

	if (trim($password_request['new_password']) !== trim($password_request['new_password_again'])) {
		return array('As novas senhas não coincidem.');
	}

	if (strlen($password_request['new_password']) < CHANGE_PASSWORD_MINIMUM_LENGTH) {
		return array('A nova senha precisa ter pelo menos ' . CHANGE_PASSWORD_MINIMUM_LENGTH . ' caracteres.');
	}

	if (strlen($password_request['new_password']) > CHANGE_PASSWORD_MAXIMUM_LENGTH) {
		return array('A nova senha pode ter no máximo ' . CHANGE_PASSWORD_MAXIMUM_LENGTH . ' caracteres.');
	}

	if ($password_request['new_password'] === $password_request['current_password']) {
		return array('A nova senha precisa ser diferente da atual.');
	}

	return array();
}

function save_change_password($session_user_id, $new_password, $config) {
	if ($config['ServerEngine'] === 'TFS_03') {
		user_change_password03($session_user_id, $new_password);
		return;
	}

	user_change_password($session_user_id, $new_password);
}

function end_change_password_session() {
	$_SESSION = array();
	session_destroy();
}

$change_password_errors = array();

if (empty($_POST) === false) {
	$change_password_errors = validate_change_password_request($_POST, $session_user_id, $config);
}

if (empty($_POST) === false && empty($change_password_errors)) {
	save_change_password($session_user_id, $_POST['new_password'], $config);
	header('Location: changepassword.php?success');
	exit();
}

$change_password_has_succeeded = isset($_GET['success']);

if ($change_password_has_succeeded) {
	end_change_password_session();
	header('refresh:' . CHANGE_PASSWORD_REDIRECT_SECONDS . ';url=index.php');
}

include 'layout/overall/header.php'; ?>
<section class="register-page password-page">
	<?php if ($change_password_has_succeeded): ?>
		<div class="register-result register-result-success">
			<span class="register-result-icon"><i class="fa fa-check"></i></span>
			<h1>Senha alterada!</h1>
			<p>Por segurança, sua sessão foi encerrada. Entre novamente usando a nova senha.</p>
			<p class="password-redirect">Você será levado para a página inicial em <?php echo CHANGE_PASSWORD_REDIRECT_SECONDS; ?> segundos.</p>
			<div class="register-result-actions">
				<a class="btn btn-primary" href="index.php"><i class="fa fa-sign-in"></i>&nbsp; Entrar novamente</a>
			</div>
		</div>
	<?php else: ?>
		<div class="register-hero">
			<span class="register-eyebrow"><i class="fa fa-shield"></i> Segurança da conta</span>
			<h1>Alterar senha</h1>
			<p>Confirme sua senha atual e escolha uma nova. Depois de salvar, você vai precisar entrar de novo.</p>
		</div>

		<?php if (!empty($change_password_errors)): ?>
			<div class="register-alert" role="alert">
				<i class="fa fa-exclamation-triangle"></i>
				<div>
					<strong>Não foi possível alterar a senha</strong>
					<ul>
						<?php foreach ($change_password_errors as $change_password_error): ?>
							<li><?php echo $change_password_error; ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<form class="register-form password-form" action="" method="post" novalidate>
			<fieldset class="register-section">
				<legend><span class="register-section-index">1</span> Senha atual</legend>
				<div class="register-field" data-field="current_password">
					<label for="password_current">Senha atual</label>
					<div class="register-input">
						<i class="fa fa-key"></i>
						<input type="password" id="password_current" name="current_password" autocomplete="current-password" placeholder="Digite sua senha atual">
						<button type="button" class="register-password-toggle" data-toggle-password="password_current" aria-label="Mostrar senha"><i class="fa fa-eye"></i></button>
					</div>
				</div>
			</fieldset>

			<fieldset class="register-section">
				<legend><span class="register-section-index">2</span> Nova senha</legend>
				<div class="register-grid">
					<div class="register-field" data-field="new_password">
						<label for="password_new">Nova senha</label>
						<div class="register-input">
							<i class="fa fa-lock"></i>
							<input type="password" id="password_new" name="new_password" maxlength="<?php echo CHANGE_PASSWORD_MAXIMUM_LENGTH; ?>" autocomplete="new-password" placeholder="Crie uma nova senha">
							<button type="button" class="register-password-toggle" data-toggle-password="password_new" aria-label="Mostrar senha"><i class="fa fa-eye"></i></button>
						</div>
						<div class="register-strength" aria-hidden="true">
							<span></span><span></span><span></span><span></span>
						</div>
						<small class="register-hint" data-strength-label>Pelo menos <?php echo CHANGE_PASSWORD_MINIMUM_LENGTH; ?> caracteres.</small>
					</div>

					<div class="register-field" data-field="new_password_again">
						<label for="password_new_again">Confirme a nova senha</label>
						<div class="register-input">
							<i class="fa fa-lock"></i>
							<input type="password" id="password_new_again" name="new_password_again" maxlength="<?php echo CHANGE_PASSWORD_MAXIMUM_LENGTH; ?>" autocomplete="new-password" placeholder="Repita a nova senha">
							<button type="button" class="register-password-toggle" data-toggle-password="password_new_again" aria-label="Mostrar senha"><i class="fa fa-eye"></i></button>
						</div>
						<small class="register-hint">Digite a mesma senha novamente.</small>
					</div>
				</div>

				<ul class="register-rules password-tips">
					<li><i class="fa fa-random"></i><span>Misture letras maiúsculas, minúsculas, números e símbolos.</span></li>
					<li><i class="fa fa-ban"></i><span>Não use a mesma senha de outros sites ou do seu email.</span></li>
					<li><i class="fa fa-user-secret"></i><span>Nenhum membro da equipe vai pedir sua senha. Nunca passe ela para ninguém.</span></li>
				</ul>
			</fieldset>

			<?php Token::create(); ?>
			<div class="register-actions">
				<button type="submit" class="register-submit"><i class="fa fa-check"></i>&nbsp; Salvar nova senha</button>
				<p class="register-login"><a href="myaccount.php">Cancelar e voltar para minha conta</a></p>
			</div>
		</form>

		<script>
		(function () {
			var passwordForm = document.querySelector('.password-form');

			if (!passwordForm) {
				return;
			}

			var minimumPasswordLength = <?php echo CHANGE_PASSWORD_MINIMUM_LENGTH; ?>;
			var strengthLabels = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte'];
			var touchedFields = {};

			function readValue(fieldName) {
				return passwordForm.querySelector('[name="' + fieldName + '"]').value;
			}

			function validateCurrentPassword() {
				return readValue('current_password').length === 0 ? 'Digite sua senha atual.' : '';
			}

			function validateNewPassword() {
				var newPassword = readValue('new_password');

				if (newPassword.length < minimumPasswordLength) {
					return 'A nova senha precisa de pelo menos ' + minimumPasswordLength + ' caracteres.';
				}

				return newPassword === readValue('current_password') ? 'A nova senha precisa ser diferente da atual.' : '';
			}

			function validateNewPasswordConfirmation() {
				var newPasswordConfirmation = readValue('new_password_again');

				if (newPasswordConfirmation.length === 0) {
					return 'Repita a nova senha.';
				}

				return newPasswordConfirmation === readValue('new_password') ? '' : 'As senhas não coincidem.';
			}

			var fieldValidators = {
				current_password: validateCurrentPassword,
				new_password: validateNewPassword,
				new_password_again: validateNewPasswordConfirmation
			};

			function showFieldState(fieldName) {
				var fieldWrapper = passwordForm.querySelector('.register-field[data-field="' + fieldName + '"]');

				if (!touchedFields[fieldName]) {
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
				return password.length < minimumPasswordLength ? 0 : Math.min(4, strengthScore);
			}

			function updatePasswordStrength() {
				var strengthScore = measurePasswordStrength(readValue('new_password'));

				passwordForm.querySelector('.register-strength').setAttribute('data-strength', strengthScore);
				passwordForm.querySelector('[data-strength-label]').textContent = strengthScore < 0 ? 'Pelo menos ' + minimumPasswordLength + ' caracteres.' : 'Força da senha: ' + strengthLabels[strengthScore];
			}

			Object.keys(fieldValidators).forEach(function (fieldName) {
				var fieldInput = passwordForm.querySelector('[name="' + fieldName + '"]');

				fieldInput.addEventListener('blur', function () {
					touchedFields[fieldName] = true;
					showFieldState(fieldName);
				});

				fieldInput.addEventListener('input', function () {
					showFieldState(fieldName);

					if (fieldName === 'new_password') {
						updatePasswordStrength();
						showFieldState('new_password_again');
					}

					if (fieldName === 'current_password') {
						showFieldState('new_password');
					}
				});
			});

			Array.prototype.forEach.call(passwordForm.querySelectorAll('[data-toggle-password]'), function (toggleButton) {
				toggleButton.addEventListener('click', function () {
					var passwordInput = document.getElementById(toggleButton.getAttribute('data-toggle-password'));
					var isHidden = passwordInput.type === 'password';

					passwordInput.type = isHidden ? 'text' : 'password';
					toggleButton.setAttribute('aria-label', isHidden ? 'Ocultar senha' : 'Mostrar senha');
					toggleButton.querySelector('i').className = isHidden ? 'fa fa-eye-slash' : 'fa fa-eye';
				});
			});

			passwordForm.addEventListener('submit', function (submitEvent) {
				var firstInvalidField = null;

				Object.keys(fieldValidators).forEach(function (fieldName) {
					touchedFields[fieldName] = true;

					if (showFieldState(fieldName) !== '' && firstInvalidField === null) {
						firstInvalidField = fieldName;
					}
				});

				if (firstInvalidField === null) {
					passwordForm.classList.add('is-submitting');
					return;
				}

				submitEvent.preventDefault();
				passwordForm.querySelector('[name="' + firstInvalidField + '"]').focus();
			});
		})();
		</script>
	<?php endif; ?>
</section>
<?php include 'layout/overall/footer.php'; ?>
