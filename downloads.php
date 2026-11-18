<?php require_once 'engine/init.php'; include 'layout/overall/header.php';

const DOWNLOADS_LAUNCHER_PACKAGE_PATHS = array(
	'downloads/NoxumLauncher.zip',
	'downloads/launcher/NoxumLauncher.zip',
	'downloads/launcher/NoxumLauncher.exe'
);
const DOWNLOADS_CLIENT_MANIFEST_PATH = 'downloads/client/manifest.json';

function find_downloads_launcher_package() {
	foreach (DOWNLOADS_LAUNCHER_PACKAGE_PATHS as $package_path) {
		if (is_file($package_path)) {
			return array(
				'path' => $package_path,
				'size' => filesize($package_path),
				'is_zip' => strtolower(pathinfo($package_path, PATHINFO_EXTENSION)) === 'zip'
			);
		}
	}

	return null;
}

function read_downloads_client_manifest() {
	if (!is_file(DOWNLOADS_CLIENT_MANIFEST_PATH)) {
		return null;
	}

	$client_manifest = json_decode(file_get_contents(DOWNLOADS_CLIENT_MANIFEST_PATH), true);

	if (!is_array($client_manifest) || !isset($client_manifest['files']) || !is_array($client_manifest['files'])) {
		return null;
	}

	$client_size = 0;

	foreach ($client_manifest['files'] as $client_file) {
		$client_size += isset($client_file['size']) ? (int) $client_file['size'] : 0;
	}

	return array(
		'version' => isset($client_manifest['version']) ? $client_manifest['version'] : '',
		'size' => $client_size,
		'updated_at' => filemtime(DOWNLOADS_CLIENT_MANIFEST_PATH)
	);
}

function format_downloads_size($size_in_bytes) {
	if ($size_in_bytes >= 1073741824) {
		return number_format($size_in_bytes / 1073741824, 1, ',', '.') . ' GB';
	}

	if ($size_in_bytes >= 1048576) {
		return number_format($size_in_bytes / 1048576, 0, ',', '.') . ' MB';
	}

	return number_format(max(1, $size_in_bytes / 1024), 0, ',', '.') . ' KB';
}

$downloads_server_name = isset($layout_server_name) ? $layout_server_name : $config['site_title'];
$downloads_launcher_package = find_downloads_launcher_package();
$downloads_client_manifest = read_downloads_client_manifest();
?>
<section class="downloads-page">
	<div class="downloads-hero">
		<img class="downloads-hero-icon" src="images/launcher-icon.jpg" alt="" width="96" height="96">
		<div class="downloads-hero-content">
			<span class="downloads-eyebrow"><i class="fa fa-windows"></i> Launcher oficial</span>
			<h1>Baixe e comece a jogar</h1>
			<p>O launcher do <?php echo htmlspecialchars($downloads_server_name); ?> instala o jogo, mantém tudo atualizado sozinho e abre o cliente com um clique.</p>

			<?php if ($downloads_launcher_package !== null): ?>
				<a class="downloads-button" href="<?php echo htmlspecialchars($downloads_launcher_package['path']); ?>" download>
					<i class="fa fa-download"></i>
					<span>
						<strong>Baixar launcher</strong>
						<small>Windows · <?php echo format_downloads_size($downloads_launcher_package['size']); ?></small>
					</span>
				</a>
			<?php else: ?>
				<p class="downloads-unavailable"><i class="fa fa-clock-o"></i> O launcher estará disponível para download em breve.</p>
			<?php endif; ?>

			<ul class="downloads-facts">
				<li><i class="fa fa-desktop"></i> Windows 10 ou 11 (64 bits)</li>
				<?php if ($downloads_client_manifest !== null): ?>
					<li><i class="fa fa-cube"></i> Cliente <?php echo htmlspecialchars($downloads_client_manifest['version']); ?> · <?php echo format_downloads_size($downloads_client_manifest['size']); ?></li>
					<li><i class="fa fa-refresh"></i> Atualizado em <?php echo date('d/m/Y', $downloads_client_manifest['updated_at']); ?></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>

	<h2 class="downloads-heading">Como começar</h2>
	<ol class="downloads-steps">
		<li class="downloads-step">
			<span class="downloads-step-number">1</span>
			<h3>Baixe o launcher</h3>
			<p>Clique no botão acima e salve o arquivo no seu computador.</p>
		</li>
		<li class="downloads-step">
			<span class="downloads-step-number">2</span>
			<h3>Abra o NoxumLauncher</h3>
			<p><?php echo $downloads_launcher_package !== null && $downloads_launcher_package['is_zip'] ? 'Extraia o zip numa pasta e abra o <strong>NoxumLauncher.exe</strong>.' : 'Abra o <strong>NoxumLauncher.exe</strong> que você baixou.'; ?> Não precisa instalar nada antes.</p>
		</li>
		<li class="downloads-step">
			<span class="downloads-step-number">3</span>
			<h3>Aguarde a instalação</h3>
			<p>Na primeira vez o launcher baixa o jogo<?php echo $downloads_client_manifest !== null ? ' (cerca de ' . format_downloads_size($downloads_client_manifest['size']) . ')' : ''; ?>. Depois, só o que mudar.</p>
		</li>
		<li class="downloads-step">
			<span class="downloads-step-number">4</span>
			<h3>Clique em Jogar</h3>
			<p>Entre com sua conta e crie seu personagem. <?php if (user_logged_in() !== true): ?>Ainda não tem conta? <a href="register.php">Crie agora</a>.<?php endif; ?></p>
		</li>
	</ol>

	<h2 class="downloads-heading">O que o launcher faz por você</h2>
	<div class="downloads-features">
		<div class="downloads-feature">
			<i class="fa fa-bolt"></i>
			<h3>Atualização automática</h3>
			<p>Ao abrir, ele confere a versão e baixa apenas os arquivos que mudaram.</p>
		</div>
		<div class="downloads-feature">
			<i class="fa fa-wrench"></i>
			<h3>Reparo de arquivos</h3>
			<p>O botão "Verificar arquivos" encontra e corrige arquivos corrompidos ou apagados.</p>
		</div>
		<div class="downloads-feature">
			<i class="fa fa-fire"></i>
			<h3>Novidades do servidor</h3>
			<p>Mostra os boostados do dia, os jogadores online e o top 3 do ranking.</p>
		</div>
	</div>

	<h2 class="downloads-heading">Dúvidas comuns</h2>
	<div class="downloads-faq">
		<details>
			<summary>O Windows disse que protegeu o computador. O que faço?</summary>
			<p>É o aviso do SmartScreen, que aparece para programas novos sem assinatura digital. Clique em <strong>Mais informações</strong> e depois em <strong>Executar assim mesmo</strong>.</p>
		</details>
		<details>
			<summary>Meu antivírus bloqueou o launcher.</summary>
			<p>Alguns antivírus desconfiam de programas que baixam arquivos. Adicione o NoxumLauncher às exceções do antivírus e abra novamente.</p>
		</details>
		<details>
			<summary>Onde o jogo fica instalado?</summary>
			<p>Em <code>%LOCALAPPDATA%\<?php echo htmlspecialchars($downloads_server_name); ?>\Client</code>. Suas configurações e hotkeys ficam salvas separadamente e não se perdem nas atualizações.</p>
		</details>
		<details>
			<summary>O jogo está fechando ou faltando imagens.</summary>
			<p>Abra o launcher e clique em <strong>Verificar arquivos</strong>. Ele confere todos os arquivos e baixa novamente o que estiver com problema.</p>
		</details>
		<details>
			<summary>Preciso desinstalar o jogo antigo para atualizar?</summary>
			<p>Não. O launcher cuida das atualizações sozinho sempre que você o abre.</p>
		</details>
	</div>
</section>
<?php
include 'layout/overall/footer.php'; ?>
