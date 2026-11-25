<?php require_once 'engine/init.php';
protect_page();
include 'layout/overall/header.php';

const CREATE_CHARACTER_VOCATION_PROFILES = array(
	4 => array(
		'slug' => 'knight',
		'role' => 'Corpo a corpo · Defesa',
		'avatar' => 'Avatar of Steel',
		'accent' => '#5cc8ff',
		'description' => 'Guerreiro da linha de frente. Aguenta o dano pesado, protege o grupo e luta com espadas, machados e clavas.'
	),
	3 => array(
		'slug' => 'paladin',
		'role' => 'Distância · Versátil',
		'avatar' => 'Avatar of Light',
		'accent' => '#ffd36b',
		'description' => 'Mestre da distância. Usa arcos, bestas e lanças e mistura magia sagrada com muita mobilidade.'
	),
	1 => array(
		'slug' => 'sorcerer',
		'role' => 'Magia · Dano',
		'avatar' => 'Avatar of Storm',
		'accent' => '#b58cff',
		'description' => 'Domina fogo, energia e morte. Tem o maior dano em área do jogo, mas pouca vida para errar.'
	),
	2 => array(
		'slug' => 'druid',
		'role' => 'Magia · Cura',
		'avatar' => 'Avatar of Nature',
		'accent' => '#86e05a',
		'description' => 'Guardião da natureza. Cura os aliados, controla o gelo e a terra e é peça-chave em qualquer grupo.'
	),
	9 => array(
		'slug' => 'monk',
		'role' => 'Corpo a corpo · Combos',
		'avatar' => 'Avatar of Balance',
		'accent' => '#f29be0',
		'description' => 'Lutador ágil que vence com os punhos e o equilíbrio entre corpo e mente. Golpes rápidos em sequência e muita resistência.'
	)
);
const CREATE_CHARACTER_VOCATION_SPRITE_DIRECTORY = 'images/vocations/';
const CREATE_CHARACTER_MALE_GENDER = 1;
const CREATE_CHARACTER_FEMALE_GENDER = 0;

function build_create_character_vocation_options($available_vocation_ids, $vocation_gains) {
	$ordered_vocation_ids = array_merge(
		array_intersect(array_keys(CREATE_CHARACTER_VOCATION_PROFILES), $available_vocation_ids),
		array_diff($available_vocation_ids, array_keys(CREATE_CHARACTER_VOCATION_PROFILES))
	);

	$vocation_options = array();

	foreach ($ordered_vocation_ids as $vocation_id) {
		$vocation_profile = isset(CREATE_CHARACTER_VOCATION_PROFILES[$vocation_id]) ? CREATE_CHARACTER_VOCATION_PROFILES[$vocation_id] : null;
		$vocation_sprite_path = $vocation_profile !== null ? CREATE_CHARACTER_VOCATION_SPRITE_DIRECTORY . $vocation_profile['slug'] . '.png' : null;

		$vocation_options[] = array(
			'id' => (int) $vocation_id,
			'name' => vocation_id_to_name($vocation_id),
			'role' => $vocation_profile !== null ? $vocation_profile['role'] : '',
			'avatar' => $vocation_profile !== null ? $vocation_profile['avatar'] : '',
			'accent' => $vocation_profile !== null ? $vocation_profile['accent'] : '#e8b85a',
			'description' => $vocation_profile !== null ? $vocation_profile['description'] : '',
			'sprite' => $vocation_sprite_path !== null && is_file($vocation_sprite_path) ? $vocation_sprite_path : null,
			'gains' => isset($vocation_gains[$vocation_id]) ? $vocation_gains[$vocation_id] : null
		);
	}

	return $vocation_options;
}

function find_create_character_highest_gains($vocation_options) {
	$highest_gains = array('hp' => 1, 'mp' => 1, 'cap' => 1);

	foreach ($vocation_options as $vocation_option) {
		if ($vocation_option['gains'] === null) {
			continue;
		}

		foreach ($highest_gains as $gain_key => $highest_value) {
			$highest_gains[$gain_key] = max($highest_value, (int) $vocation_option['gains'][$gain_key]);
		}
	}

	return $highest_gains;
}

function find_create_character_vocation_option($vocation_options, $vocation_id) {
	foreach ($vocation_options as $vocation_option) {
		if ($vocation_option['id'] === (int) $vocation_id) {
			return $vocation_option;
		}
	}

	return null;
}

function has_reached_create_character_limit($session_user_id, $user_data, $maximum_characters) {
	if (is_admin($user_data)) {
		return false;
	}

	return user_character_list_count($session_user_id) >= $maximum_characters;
}

function validate_create_character_request($character_request, $config, $session_user_id, $user_data) {
	$required_fields = array('name', 'selected_town');

	foreach ($required_fields as $required_field) {
		if (empty($character_request[$required_field])) {
			return array('Preencha todos os campos.');
		}
	}

	if (!Token::isValid($character_request['token'])) {
		return array('Sua sessão expirou. Recarregue a página e tente novamente.');
	}

	$character_name = validate_name($character_request['name']);

	if ($character_name === false) {
		return array('O nome pode ter no máximo ' . $config['maxW'] . ' palavras.');
	}

	$validation_errors = array();

	if (user_character_exist($character_name) !== false) {
		$validation_errors[] = 'Já existe um personagem com esse nome.';
	}

	if (!preg_match("/^[a-zA-Z ]+$/", $character_name)) {
		$validation_errors[] = 'O nome só pode ter letras (a-z, A-Z) e espaços.';
	}

	if (strlen($character_name) < $config['minL'] || strlen($character_name) > $config['maxL']) {
		$validation_errors[] = 'O nome deve ter entre ' . $config['minL'] . ' e ' . $config['maxL'] . ' letras.';
	}

	foreach (explode(' ', $character_name) as $name_word) {
		if (in_array(strtolower($name_word), $config['invalidNameTags'])) {
			$validation_errors[] = 'O nome contém uma palavra proibida.';
		}

		if (strlen($name_word) == 1) {
			$validation_errors[] = 'O nome tem palavras curtas demais.';
		}
	}

	if (in_array(strtolower($character_name), $config['creatureNameTags'])) {
		$validation_errors[] = 'O nome não pode ser o de uma criatura do jogo.';
	}

	$selected_vocation = isset($character_request['selected_vocation']) ? $character_request['selected_vocation'] : '';
	$selected_gender = isset($character_request['selected_gender']) ? $character_request['selected_gender'] : '';
	$selected_town = $character_request['selected_town'];

	if (!in_array((int) $selected_vocation, $config['available_vocations']) || vocation_id_to_name($selected_vocation) === false) {
		$validation_errors[] = 'Escolha uma vocação válida.';
	}

	if (!in_array((int) $selected_town, $config['available_towns']) || town_id_to_name($selected_town) === false) {
		$validation_errors[] = 'Escolha uma cidade válida.';
	}

	if (!in_array((int) $selected_gender, array(CREATE_CHARACTER_FEMALE_GENDER, CREATE_CHARACTER_MALE_GENDER)) || gender_exist($selected_gender) === false) {
		$validation_errors[] = 'Escolha um sexo válido.';
	}

	if (has_reached_create_character_limit($session_user_id, $user_data, $config['max_characters'])) {
		$validation_errors[] = 'Sua conta já tem o máximo de ' . $config['max_characters'] . ' personagens.';
	}

	if (validate_ip(getIP()) === false && $config['validate_IP'] === true) {
		$validation_errors[] = 'Não foi possível reconhecer seu endereço IP.';
	}

	return $validation_errors;
}

function read_created_character_summary($character_name, $session_user_id) {
	if ($character_name === '' || user_character_account_id($character_name) !== $session_user_id) {
		return null;
	}

	$created_character = user_character_data(user_character_id($character_name), 'name', 'vocation', 'level', 'sex');

	if (!is_array($created_character)) {
		return null;
	}

	return $created_character;
}

$create_character_vocations = build_create_character_vocation_options($config['available_vocations'], $config['vocations_gain']);
$create_character_highest_gains = find_create_character_highest_gains($create_character_vocations);
$create_character_errors = array();
$create_character_is_submitted = empty($_POST) === false;

if ($create_character_is_submitted) {
	$create_character_errors = validate_create_character_request($_POST, $config, $session_user_id, $user_data);
}

if ($create_character_is_submitted && empty($create_character_errors)) {
	if ($config['log_ip']) {
		znote_visitor_insert_detailed_data(2);
	}

	$created_character_name = format_character_name(validate_name($_POST['name']));

	user_create_character(array(
		'name' => $created_character_name,
		'account_id' => $session_user_id,
		'vocation' => $_POST['selected_vocation'],
		'town_id' => $_POST['selected_town'],
		'sex' => $_POST['selected_gender'],
		'lastip' => getIPLong(),
		'created' => time()
	));

	header('Location: createcharacter.php?success=' . urlencode($created_character_name));
	exit();
}

$create_character_has_succeeded = isset($_GET['success']);
$created_character_summary = $create_character_has_succeeded ? read_created_character_summary((string) $_GET['success'], $session_user_id) : null;
$created_character_vocation = $created_character_summary !== null ? find_create_character_vocation_option($create_character_vocations, $created_character_summary['vocation']) : null;
$create_character_limit_reached = has_reached_create_character_limit($session_user_id, $user_data, $config['max_characters']);
$create_character_count = user_character_list_count($session_user_id);

$selected_vocation_id = isset($_POST['selected_vocation']) ? (int) $_POST['selected_vocation'] : (count($create_character_vocations) > 0 ? $create_character_vocations[0]['id'] : 0);
$selected_gender_id = isset($_POST['selected_gender']) ? (int) $_POST['selected_gender'] : CREATE_CHARACTER_MALE_GENDER;
$selected_town_id = isset($_POST['selected_town']) ? (int) $_POST['selected_town'] : (int) reset($config['available_towns']);
$typed_character_name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
?>
<section class="register-page create-page">
	<?php if ($create_character_has_succeeded): ?>
		<div class="register-result register-result-success create-result">
			<?php if ($created_character_vocation !== null && $created_character_vocation['sprite'] !== null): ?>
				<div class="create-result-sprite create-sprite" style="--vocation-accent: <?php echo $created_character_vocation['accent']; ?>; background-image: url('<?php echo $created_character_vocation['sprite']; ?>');" role="img" aria-label="<?php echo htmlspecialchars($created_character_vocation['avatar']); ?>"></div>
			<?php else: ?>
				<span class="register-result-icon"><i class="fa fa-check"></i></span>
			<?php endif; ?>
			<h1><?php echo $created_character_summary !== null ? htmlspecialchars($created_character_summary['name']) . ((int) $created_character_summary['sex'] === CREATE_CHARACTER_FEMALE_GENDER ? ' está pronta!' : ' está pronto!') : 'Personagem criado!'; ?></h1>
			<p>
				<?php if ($created_character_summary !== null): ?>
					Seu <?php echo htmlspecialchars(vocation_id_to_name($created_character_summary['vocation'])); ?> começa no level <?php echo (int) $created_character_summary['level']; ?>. Abra o launcher, entre com sua conta e boa caçada!
				<?php else: ?>
					Abra o launcher, entre com sua conta e boa caçada!
				<?php endif; ?>
			</p>
			<div class="register-result-actions">
				<a class="btn btn-primary" href="myaccount.php"><i class="fa fa-user"></i>&nbsp; Ver minha conta</a>
				<?php if (is_launcher_download_enabled()): ?>
					<a class="btn btn-default" href="downloads.php"><i class="fa fa-download"></i>&nbsp; Baixar launcher</a>
				<?php endif; ?>
				<?php if (!$create_character_limit_reached): ?>
					<a class="btn btn-default" href="createcharacter.php"><i class="fa fa-plus"></i>&nbsp; Criar outro</a>
				<?php endif; ?>
			</div>
		</div>
	<?php elseif ($create_character_limit_reached): ?>
		<div class="register-result register-result-failure">
			<span class="register-result-icon"><i class="fa fa-users"></i></span>
			<h1>Limite de personagens</h1>
			<p>Sua conta já tem <?php echo $create_character_count; ?> de <?php echo (int) $config['max_characters']; ?> personagens. Exclua um personagem em Minha conta para criar outro.</p>
			<div class="register-result-actions">
				<a class="btn btn-primary" href="myaccount.php"><i class="fa fa-user"></i>&nbsp; Ir para minha conta</a>
			</div>
		</div>
	<?php else: ?>
		<div class="register-hero">
			<span class="register-eyebrow"><i class="fa fa-user-plus"></i> Novo personagem · <?php echo $create_character_count; ?> de <?php echo (int) $config['max_characters']; ?></span>
			<h1>Escolha seu caminho</h1>
			<p>Cada vocação joga de um jeito. Escolha a sua, dê um nome ao herói e comece a aventura.</p>
		</div>

		<?php if (!empty($create_character_errors)): ?>
			<div class="register-alert" role="alert">
				<i class="fa fa-exclamation-triangle"></i>
				<div>
					<strong>Não foi possível criar o personagem</strong>
					<ul>
						<?php foreach ($create_character_errors as $create_character_error): ?>
							<li><?php echo $create_character_error; ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<form class="register-form create-form" action="" method="post">
			<fieldset class="register-section">
				<legend><span class="register-section-index">1</span> Vocação</legend>
				<div class="create-vocations" role="radiogroup" aria-label="Vocação">
					<?php foreach ($create_character_vocations as $vocation_option): ?>
						<label class="create-vocation" style="--vocation-accent: <?php echo $vocation_option['accent']; ?>;">
							<input type="radio" name="selected_vocation" value="<?php echo $vocation_option['id']; ?>" <?php echo $vocation_option['id'] === $selected_vocation_id ? 'checked' : ''; ?>>
							<span class="create-vocation-card">
								<?php if ($vocation_option['sprite'] !== null): ?>
									<span class="create-vocation-sprite create-sprite" style="background-image: url('<?php echo $vocation_option['sprite']; ?>');"></span>
								<?php else: ?>
									<span class="create-vocation-sprite create-vocation-fallback"><i class="fa fa-user"></i></span>
								<?php endif; ?>
								<strong><?php echo htmlspecialchars($vocation_option['name']); ?></strong>
								<?php if ($vocation_option['role'] !== ''): ?>
									<small><?php echo $vocation_option['role']; ?></small>
								<?php endif; ?>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<?php foreach ($create_character_vocations as $vocation_option): ?>
					<div class="create-vocation-detail" data-vocation="<?php echo $vocation_option['id']; ?>" style="--vocation-accent: <?php echo $vocation_option['accent']; ?>;" <?php echo $vocation_option['id'] === $selected_vocation_id ? '' : 'hidden'; ?>>
						<div class="create-vocation-detail-text">
							<h3><?php echo htmlspecialchars($vocation_option['name']); ?></h3>
							<?php if ($vocation_option['role'] !== ''): ?>
								<span class="create-vocation-role"><?php echo $vocation_option['role']; ?></span>
							<?php endif; ?>
							<?php if ($vocation_option['description'] !== ''): ?>
								<p><?php echo $vocation_option['description']; ?></p>
							<?php endif; ?>
							<?php if ($vocation_option['avatar'] !== ''): ?>
								<span class="create-vocation-avatar"><i class="fa fa-bolt"></i> Forma suprema: <?php echo $vocation_option['avatar']; ?></span>
							<?php endif; ?>
						</div>
						<?php if ($vocation_option['gains'] !== null): ?>
							<dl class="create-vocation-stats">
								<?php foreach (array('hp' => 'Vida', 'mp' => 'Mana', 'cap' => 'Capacidade') as $gain_key => $gain_label): ?>
									<div class="create-vocation-stat create-vocation-stat-<?php echo $gain_key; ?>">
										<dt><?php echo $gain_label; ?></dt>
										<dd>
											<span class="create-vocation-bar"><span style="width: <?php echo round((int) $vocation_option['gains'][$gain_key] / $create_character_highest_gains[$gain_key] * 100); ?>%;"></span></span>
											<span class="create-vocation-value">+<?php echo (int) $vocation_option['gains'][$gain_key]; ?>/nível</span>
										</dd>
									</div>
								<?php endforeach; ?>
							</dl>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</fieldset>

			<fieldset class="register-section">
				<legend><span class="register-section-index">2</span> Identidade</legend>
				<div class="register-grid create-identity">
					<div class="register-field">
						<label for="create_name">Nome do personagem</label>
						<div class="register-input">
							<i class="fa fa-id-badge"></i>
							<input id="create_name" type="text" name="name" value="<?php echo $typed_character_name; ?>" maxlength="<?php echo (int) $config['maxL']; ?>" autocomplete="off" spellcheck="false" placeholder="Ex: Sir Galahad" required>
						</div>
						<span class="register-hint">De <?php echo (int) $config['minL']; ?> a <?php echo (int) $config['maxL']; ?> letras, apenas letras e espaços.</span>
					</div>
					<div class="register-field">
						<span class="create-field-label">Sexo</span>
						<div class="create-choices" role="radiogroup" aria-label="Sexo">
							<label class="create-choice">
								<input type="radio" name="selected_gender" value="<?php echo CREATE_CHARACTER_MALE_GENDER; ?>" <?php echo $selected_gender_id === CREATE_CHARACTER_MALE_GENDER ? 'checked' : ''; ?>>
								<span><i class="fa fa-mars"></i> Masculino</span>
							</label>
							<label class="create-choice">
								<input type="radio" name="selected_gender" value="<?php echo CREATE_CHARACTER_FEMALE_GENDER; ?>" <?php echo $selected_gender_id === CREATE_CHARACTER_FEMALE_GENDER ? 'checked' : ''; ?>>
								<span><i class="fa fa-venus"></i> Feminino</span>
							</label>
						</div>
					</div>
				</div>
			</fieldset>

			<?php if (count($config['available_towns']) > 1): ?>
				<fieldset class="register-section">
					<legend><span class="register-section-index">3</span> Cidade natal</legend>
					<div class="create-choices create-towns" role="radiogroup" aria-label="Cidade natal">
						<?php foreach ($config['available_towns'] as $available_town_id): ?>
							<label class="create-choice">
								<input type="radio" name="selected_town" value="<?php echo (int) $available_town_id; ?>" <?php echo (int) $available_town_id === $selected_town_id ? 'checked' : ''; ?>>
								<span><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars(town_id_to_name($available_town_id)); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php else: ?>
				<input type="hidden" name="selected_town" value="<?php echo (int) end($config['available_towns']); ?>">
			<?php endif; ?>

			<?php Token::create(); ?>
			<div class="register-actions">
				<button type="submit" class="register-submit"><i class="fa fa-magic"></i>&nbsp; Criar personagem</button>
				<p class="register-login"><a href="myaccount.php">Voltar para minha conta</a></p>
			</div>
		</form>

		<script>
		(function () {
			var createForm = document.querySelector('.create-form');

			if (!createForm) {
				return;
			}

			var vocationDetails = createForm.querySelectorAll('.create-vocation-detail');

			createForm.addEventListener('change', function (changeEvent) {
				if (changeEvent.target.name !== 'selected_vocation') {
					return;
				}

				Array.prototype.forEach.call(vocationDetails, function (vocationDetail) {
					vocationDetail.hidden = vocationDetail.getAttribute('data-vocation') !== changeEvent.target.value;
				});
			});

			createForm.addEventListener('submit', function () {
				createForm.classList.add('is-submitting');
			});
		})();
		</script>
	<?php endif; ?>
</section>
<?php include 'layout/overall/footer.php'; ?>
