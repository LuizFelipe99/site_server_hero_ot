<?php require_once 'engine/init.php'; include 'layout/overall/header.php';

const MECHANICS_DATA_FILE = 'engine/data/mechanics.json';
const MECHANICS_ITEM_ATLAS = 'images/mechanics/items.png';
const MECHANICS_GEM_CATEGORY = 'atribute_gem';
const MECHANICS_VOCATION_FILTERS = array(
	'knight' => 'Knight',
	'paladin' => 'Paladin',
	'sorcerer' => 'Sorcerer',
	'druid' => 'Druid',
	'monk' => 'Monk'
);
const MECHANICS_GEM_TYPES = array(
	'health' => array('label' => 'Vida', 'unit' => ' HP', 'color' => '#ff5a4d'),
	'mana' => array('label' => 'Mana', 'unit' => ' MP', 'color' => '#4d9bff'),
	'critChance' => array('label' => 'Chance crítica', 'unit' => '%', 'color' => '#c07aff'),
	'critDamage' => array('label' => 'Dano crítico', 'unit' => '%', 'color' => '#b8bec8'),
	'attackSpeed' => array('label' => 'Velocidade de ataque', 'unit' => '', 'color' => '#7be08a')
);
const MECHANICS_ELEMENT_STYLES = array(
	'physical' => array('label' => 'Físico', 'icon' => 'fa-hand-rock-o', 'color' => '#d9c9b0'),
	'energy' => array('label' => 'Energia', 'icon' => 'fa-bolt', 'color' => '#c58cff'),
	'earth' => array('label' => 'Terra', 'icon' => 'fa-leaf', 'color' => '#7fd35a'),
	'fire' => array('label' => 'Fogo', 'icon' => 'fa-fire', 'color' => '#ff7a3d'),
	'ice' => array('label' => 'Gelo', 'icon' => 'fa-snowflake-o', 'color' => '#6fd2ff'),
	'holy' => array('label' => 'Sagrado', 'icon' => 'fa-sun-o', 'color' => '#ffe27a'),
	'death' => array('label' => 'Morte', 'icon' => 'fa-moon-o', 'color' => '#b07a9a')
);
const MECHANICS_GEM_CRAFT_TIERS = array(
	1 => array('cost' => 3000000, 'chance' => 25),
	2 => array('cost' => 3000000, 'chance' => 25),
	3 => array('cost' => 3000000, 'chance' => 25),
	4 => array('cost' => 3000000, 'chance' => 25),
	5 => array('cost' => 3000000, 'chance' => 25),
	6 => array('cost' => 10000000, 'chance' => 10),
	7 => array('cost' => 10000000, 'chance' => 10),
	8 => array('cost' => 10000000, 'chance' => 10),
	9 => array('cost' => 20000000, 'chance' => 5)
);

function load_mechanics_data() {
	if (!is_file(MECHANICS_DATA_FILE)) {
		return null;
	}

	$mechanics_data = json_decode(file_get_contents(MECHANICS_DATA_FILE), true);
	return is_array($mechanics_data) ? $mechanics_data : null;
}

function format_mechanics_gold($gold_amount) {
	if ($gold_amount >= 1000000) {
		return rtrim(rtrim(number_format($gold_amount / 1000000, 1, ',', ''), '0'), ',') . 'kk';
	}

	if ($gold_amount >= 1000) {
		return rtrim(rtrim(number_format($gold_amount / 1000, 1, ',', ''), '0'), ',') . 'k';
	}

	return (string) $gold_amount;
}

function describe_mechanics_custom_item_icon($item_name) {
	$lower_item_name = strtolower($item_name);
	$icons_by_keyword = array(
		'casket' => 'fa-archive',
		'backpack' => 'fa-briefcase',
		'bag' => 'fa-shopping-bag',
		'coin' => 'fa-circle',
		'token' => 'fa-circle',
		'scroll' => 'fa-file-text',
		'wheel' => 'fa-dot-circle-o'
	);

	foreach ($icons_by_keyword as $item_keyword => $item_icon) {
		if (strpos($lower_item_name, $item_keyword) !== false) {
			return $item_icon;
		}
	}

	return 'fa-diamond';
}

function describe_mechanics_custom_item_tone($item_name) {
	$lower_item_name = strtolower($item_name);

	if (strpos($lower_item_name, 'golden') !== false) {
		return 'golden';
	}

	if (strpos($lower_item_name, 'platinum') !== false) {
		return 'platinum';
	}

	foreach (array('health', 'mana', 'critical damage', 'critical chance', 'attack speed', 'primal', 'scroll', 'roulete', 'casket', 'backpack', 'bag', 'wheel') as $item_tone) {
		if (strpos($lower_item_name, $item_tone) !== false) {
			return str_replace(' ', '-', $item_tone);
		}
	}

	return 'material';
}

function render_mechanics_item_icon($sprite_atlas, $client_id, $item_name) {
	$escaped_item_name = htmlspecialchars(ucwords($item_name));

	if (!isset($sprite_atlas['positions'][(string) $client_id])) {
		return '<span class="mechanics-item mechanics-item-custom mechanics-tone-' . describe_mechanics_custom_item_tone($item_name) . '" title="' . $escaped_item_name . '"><i class="fa ' . describe_mechanics_custom_item_icon($item_name) . '"></i></span>';
	}

	return '<span class="mechanics-item" title="' . $escaped_item_name . '" style="' . build_mechanics_sprite_style($sprite_atlas, $client_id) . '"></span>';
}

function build_mechanics_sprite_style($sprite_atlas, $client_id) {
	if (!isset($sprite_atlas['positions'][(string) $client_id])) {
		return '';
	}

	$atlas_position = $sprite_atlas['positions'][(string) $client_id];
	$background_x = $sprite_atlas['columns'] > 1 ? $atlas_position[0] / ($sprite_atlas['columns'] - 1) * 100 : 0;
	$background_y = $sprite_atlas['rows'] > 1 ? $atlas_position[1] / ($sprite_atlas['rows'] - 1) * 100 : 0;

	return 'background-size: ' . ($sprite_atlas['columns'] * 100) . '% ' . ($sprite_atlas['rows'] * 100) . '%; background-position: ' . round($background_x, 4) . '% ' . round($background_y, 4) . '%;';
}

function build_mechanics_gem_sprite_styles($sprite_atlas, $gems) {
	$gem_sprite_styles = array();

	foreach ($gems as $gem) {
		$gem_sprite_styles[$gem['type']][] = build_mechanics_sprite_style($sprite_atlas, $gem['id']);
	}

	return $gem_sprite_styles;
}

function filter_mechanics_equipment_recipes($craft_recipes) {
	return array_values(array_filter($craft_recipes, function ($craft_recipe) {
		return $craft_recipe['vocation'] !== MECHANICS_GEM_CATEGORY;
	}));
}

function group_mechanics_gems_by_type($gems) {
	$gems_by_type = array();

	foreach ($gems as $gem) {
		$gems_by_type[$gem['type']][] = (int) $gem['value'];
	}

	return $gems_by_type;
}

function describe_mechanics_ingredient_amount($ingredient) {
	if ($ingredient['minAmount'] !== null && $ingredient['maxAmount'] !== null) {
		return $ingredient['minAmount'] . '–' . $ingredient['maxAmount'] . 'x';
	}

	return (int) $ingredient['amount'] . 'x';
}

$mechanics_data = load_mechanics_data();
?>
<section class="mechanics-page">
	<div class="register-hero">
		<span class="register-eyebrow"><i class="fa fa-book"></i> Biblioteca</span>
		<h1>Mecânicas do Hero OT</h1>
		<p>Os sistemas exclusivos do servidor, explicados de forma rápida. Mexa nos simuladores para entender como cada um funciona.</p>
	</div>

	<?php if ($mechanics_data === null): ?>
		<div class="account-notice account-notice-info"><i class="fa fa-info-circle"></i><div>As informações das mecânicas ainda não estão disponíveis.</div></div>
	<?php else: ?>
		<?php
		$sprite_atlas = $mechanics_data['spriteAtlas'];
		$equipment_recipes = filter_mechanics_equipment_recipes($mechanics_data['craft']);
		$gem_recipe_count = count($mechanics_data['craft']) - count($equipment_recipes);
		$gems_by_type = group_mechanics_gems_by_type($mechanics_data['gems']);
		$gem_sprite_styles = build_mechanics_gem_sprite_styles($sprite_atlas, $mechanics_data['gems']);
		$elemental_missions = $mechanics_data['elemental']['missions'];
		$elemental_points_available = array_sum(array_column($elemental_missions, 'points'));
		?>
		<nav class="mechanics-tabs" role="tablist" aria-label="Mecânicas">
			<button type="button" role="tab" class="mechanics-tab" data-mechanics-tab="forja"><i class="fa fa-gavel"></i> Forja</button>
			<button type="button" role="tab" class="mechanics-tab" data-mechanics-tab="gemas"><i class="fa fa-diamond"></i> Bag Gem</button>
			<button type="button" role="tab" class="mechanics-tab" data-mechanics-tab="elemental"><i class="fa fa-fire"></i> Build Elemental</button>
			<button type="button" role="tab" class="mechanics-tab" data-mechanics-tab="battlepass"><i class="fa fa-trophy"></i> Battle Pass</button>
		</nav>

		<section class="mechanics-panel" id="forja" role="tabpanel">
			<header class="mechanics-panel-header">
				<h2>Forja</h2>
				<p>Na Bancada de Criação você cria equipamentos de alto nível, desmonta o que não usa mais e funde materiais em versões mais raras. A bancada é liberada depois de completar a quest de acesso.</p>
			</header>

			<div class="mechanics-stats">
				<div><strong><?php echo count($equipment_recipes); ?></strong><span>equipamentos para criar</span></div>
				<div><strong><?php echo $gem_recipe_count; ?></strong><span>receitas de gemas</span></div>
				<div><strong><?php echo count($mechanics_data['dismantle']); ?></strong><span>itens desmontáveis</span></div>
				<div><strong><?php echo count($mechanics_data['fusion']); ?></strong><span>fusões de crafter</span></div>
			</div>

			<div class="mechanics-subtabs" role="tablist">
				<button type="button" class="mechanics-subtab is-active" data-mechanics-subtab="craft">Craft</button>
				<button type="button" class="mechanics-subtab" data-mechanics-subtab="dismantle">Desmanche</button>
				<button type="button" class="mechanics-subtab" data-mechanics-subtab="fusion">Fusão</button>
			</div>

			<div class="mechanics-subpanel" data-mechanics-subpanel="craft">
				<p class="mechanics-lead">Junte crafters e materiais, pague o custo em gold e tente a sorte. Se a criação falhar, os materiais usados são perdidos.</p>
				<div class="mechanics-filters">
					<div class="mechanics-chips" role="group" aria-label="Filtrar por vocação">
						<button type="button" class="mechanics-chip is-active" data-vocation-filter="all">Todas</button>
						<?php foreach (MECHANICS_VOCATION_FILTERS as $vocation_key => $vocation_label): ?>
							<button type="button" class="mechanics-chip" data-vocation-filter="<?php echo $vocation_key; ?>"><?php echo $vocation_label; ?></button>
						<?php endforeach; ?>
					</div>
					<label class="mechanics-search"><i class="fa fa-search"></i><input type="search" placeholder="Buscar item..." data-recipe-search></label>
				</div>

				<div class="mechanics-recipes">
					<?php foreach ($equipment_recipes as $equipment_recipe): ?>
						<article class="mechanics-recipe" data-vocation="<?php echo htmlspecialchars($equipment_recipe['vocation']); ?>" data-name="<?php echo htmlspecialchars(strtolower($equipment_recipe['name'])); ?>">
							<div class="mechanics-recipe-result">
								<?php echo render_mechanics_item_icon($sprite_atlas, $equipment_recipe['clientId'], $equipment_recipe['name']); ?>
								<div>
									<h3><?php echo htmlspecialchars($equipment_recipe['name']); ?></h3>
									<span class="mechanics-recipe-vocation"><?php echo isset(MECHANICS_VOCATION_FILTERS[$equipment_recipe['vocation']]) ? MECHANICS_VOCATION_FILTERS[$equipment_recipe['vocation']] : ''; ?></span>
								</div>
							</div>
							<ul class="mechanics-ingredients">
								<?php foreach ($equipment_recipe['ingredients'] as $recipe_ingredient): ?>
									<li><?php echo render_mechanics_item_icon($sprite_atlas, $recipe_ingredient['clientId'], $recipe_ingredient['name']); ?><span><?php echo describe_mechanics_ingredient_amount($recipe_ingredient); ?> <?php echo htmlspecialchars(ucwords($recipe_ingredient['name'])); ?></span></li>
								<?php endforeach; ?>
							</ul>
							<footer class="mechanics-recipe-footer">
								<span><i class="fa fa-database"></i> <?php echo format_mechanics_gold($equipment_recipe['goldCost']); ?> gold</span>
								<span class="mechanics-chance" style="--chance: <?php echo (int) $equipment_recipe['successChance']; ?>%;"><?php echo (int) $equipment_recipe['successChance']; ?>% de sucesso</span>
							</footer>
						</article>
					<?php endforeach; ?>
				</div>
				<p class="mechanics-empty" data-recipe-empty hidden>Nenhum item encontrado.</p>
			</div>

			<div class="mechanics-subpanel" data-mechanics-subpanel="dismantle" hidden>
				<p class="mechanics-lead">Equipamentos que você não usa podem virar matéria-prima. Cada item desmontado rende de <strong>1 a 3 Platinum Ruby Crafter Nv. 1</strong>, que você usa para criar e fundir.</p>
				<div class="mechanics-dismantle">
					<?php foreach ($mechanics_data['dismantle'] as $dismantle_recipe): ?>
						<div class="mechanics-dismantle-item" title="<?php echo htmlspecialchars($dismantle_recipe['name']); ?> → <?php echo describe_mechanics_ingredient_amount($dismantle_recipe['ingredients'][0]); ?> <?php echo htmlspecialchars(ucwords($dismantle_recipe['ingredients'][0]['name'])); ?>">
							<?php echo render_mechanics_item_icon($sprite_atlas, $dismantle_recipe['clientId'], $dismantle_recipe['name']); ?>
							<span><?php echo htmlspecialchars($dismantle_recipe['name']); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="mechanics-subpanel" data-mechanics-subpanel="fusion" hidden>
				<p class="mechanics-lead">Funda <strong>4 crafters</strong> de um nível em <strong>1 crafter</strong> do nível seguinte. Quanto mais alto o nível, mais caro e mais arriscado. Se a fusão falhar, os crafters são perdidos.</p>
				<div class="mechanics-fusion" data-fusion-simulator>
					<div class="mechanics-chips" role="group" aria-label="Escolha a fusão">
						<?php foreach ($mechanics_data['fusion'] as $fusion_index => $fusion_recipe): ?>
							<button type="button" class="mechanics-chip<?php echo $fusion_index === 0 ? ' is-active' : ''; ?>" data-fusion-index="<?php echo $fusion_index; ?>"><?php echo htmlspecialchars(str_replace('Ruby Crafter ', '', $fusion_recipe['name'])); ?></button>
						<?php endforeach; ?>
					</div>
					<?php foreach ($mechanics_data['fusion'] as $fusion_index => $fusion_recipe): ?>
						<div class="mechanics-fusion-stage" data-fusion-stage="<?php echo $fusion_index; ?>" data-fusion-chance="<?php echo (int) $fusion_recipe['successChance']; ?>"<?php echo $fusion_index === 0 ? '' : ' hidden'; ?>>
							<div class="mechanics-fusion-inputs">
								<?php for ($input_index = 0; $input_index < (int) $fusion_recipe['ingredients'][0]['amount']; $input_index++): ?>
									<?php echo render_mechanics_item_icon($sprite_atlas, $fusion_recipe['ingredients'][0]['clientId'], $fusion_recipe['ingredients'][0]['name']); ?>
								<?php endfor; ?>
							</div>
							<i class="fa fa-long-arrow-right mechanics-fusion-arrow"></i>
							<div class="mechanics-fusion-output">
								<?php echo render_mechanics_item_icon($sprite_atlas, $fusion_recipe['clientId'], $fusion_recipe['name']); ?>
								<strong><?php echo htmlspecialchars($fusion_recipe['name']); ?></strong>
							</div>
							<div class="mechanics-fusion-info">
								<span><i class="fa fa-database"></i> <?php echo format_mechanics_gold($fusion_recipe['goldCost']); ?> gold</span>
								<span class="mechanics-chance" style="--chance: <?php echo (int) $fusion_recipe['successChance']; ?>%;"><?php echo (int) $fusion_recipe['successChance']; ?>% de sucesso</span>
							</div>
						</div>
					<?php endforeach; ?>
					<div class="mechanics-fusion-actions">
						<button type="button" class="mechanics-try" data-fusion-try><i class="fa fa-magic"></i>&nbsp; Simular fusão</button>
						<span class="mechanics-fusion-result" data-fusion-result aria-live="polite"></span>
					</div>
				</div>
			</div>
		</section>

		<section class="mechanics-panel" id="gemas" role="tabpanel" hidden>
			<header class="mechanics-panel-header">
				<h2>Bag Gem</h2>
				<p>A Bag Gem tem <strong>9 espaços</strong> para gemas de atributo. Arraste uma gema para um espaço e o bônus vale na hora. Clique com o botão direito para tirar a gema, sem perder nada. Você pode encaixar no máximo <strong>2 gemas do mesmo tipo</strong>.</p>
			</header>

			<div class="mechanics-gem-builder" data-gem-builder>
				<div class="mechanics-gem-palette">
					<h3>Escolha uma gema</h3>
					<?php foreach (MECHANICS_GEM_TYPES as $gem_type_key => $gem_type): ?>
						<?php if (!isset($gems_by_type[$gem_type_key])) { continue; } ?>
						<div class="mechanics-gem-option" style="--gem-color: <?php echo $gem_type['color']; ?>;" data-gem-type="<?php echo $gem_type_key; ?>">
							<span class="mechanics-gem-icon"><span class="mechanics-item" data-gem-sprite style="<?php echo $gem_sprite_styles[$gem_type_key][0]; ?>"></span></span>
							<div class="mechanics-gem-option-text">
								<strong><?php echo $gem_type['label']; ?></strong>
								<small>Nível <output data-gem-level-label>1</output> · +<output data-gem-value-label><?php echo $gems_by_type[$gem_type_key][0]; ?></output><?php echo $gem_type['unit']; ?></small>
							</div>
							<input type="range" min="1" max="<?php echo count($gems_by_type[$gem_type_key]); ?>" value="1" aria-label="Nível da gema de <?php echo $gem_type['label']; ?>" data-gem-level>
							<button type="button" class="mechanics-gem-add" data-gem-add aria-label="Encaixar gema de <?php echo $gem_type['label']; ?>"><i class="fa fa-plus"></i></button>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="mechanics-gem-bag">
					<h3>Sua Bag Gem</h3>
					<div class="mechanics-gem-rows">
						<?php foreach (array('Elmo' => 'fa-shield', 'Armadura' => 'fa-male', 'Arma' => 'fa-crosshairs') as $row_label => $row_icon): ?>
							<div class="mechanics-gem-row">
								<span class="mechanics-gem-row-label"><i class="fa <?php echo $row_icon; ?>"></i> <?php echo $row_label; ?></span>
								<?php for ($slot_index = 0; $slot_index < 3; $slot_index++): ?>
									<button type="button" class="mechanics-gem-slot" data-gem-slot aria-label="Espaço vazio"></button>
								<?php endfor; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="mechanics-gem-message" data-gem-message aria-live="polite">Clique no <i class="fa fa-plus"></i> de uma gema para encaixar. Clique numa gema encaixada para tirar.</p>
					<dl class="mechanics-gem-summary" data-gem-summary></dl>
				</div>
			</div>

			<h3 class="mechanics-subheading">Como evoluir as gemas</h3>
			<p class="mechanics-lead">As gemas são criadas na aba Craft da Forja. O nível 1 usa 3 materiais de gema, e cada nível seguinte usa <strong>3 gemas do nível anterior</strong>. Se der errado, você perde só parte das gemas usadas.</p>
			<div class="mechanics-table-wrapper">
				<table class="mechanics-table" data-gem-table>
					<thead>
						<tr>
							<th>Nível</th>
							<?php foreach (MECHANICS_GEM_TYPES as $gem_type_key => $gem_type): ?>
								<th style="color: <?php echo $gem_type['color']; ?>;"><?php echo $gem_type['label']; ?></th>
							<?php endforeach; ?>
							<th>Custo</th>
							<th>Chance</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach (MECHANICS_GEM_CRAFT_TIERS as $gem_level => $gem_tier): ?>
							<tr>
								<td><strong>Nv. <?php echo $gem_level; ?></strong></td>
								<?php foreach (MECHANICS_GEM_TYPES as $gem_type_key => $gem_type): ?>
									<td><span class="mechanics-table-gem"><span class="mechanics-item" style="<?php echo isset($gem_sprite_styles[$gem_type_key][$gem_level - 1]) ? $gem_sprite_styles[$gem_type_key][$gem_level - 1] : ''; ?>"></span>+<?php echo isset($gems_by_type[$gem_type_key][$gem_level - 1]) ? number_format($gems_by_type[$gem_type_key][$gem_level - 1], 0, ',', '.') . $gem_type['unit'] : '—'; ?></span></td>
								<?php endforeach; ?>
								<td><?php echo format_mechanics_gold($gem_tier['cost']); ?></td>
								<td><span class="mechanics-chance" style="--chance: <?php echo $gem_tier['chance']; ?>%;"><?php echo $gem_tier['chance']; ?>%</span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<section class="mechanics-panel" id="elemental" role="tabpanel" hidden>
			<header class="mechanics-panel-header">
				<h2>Build Elemental</h2>
				<p>Complete missões entregando equipamentos e ganhe pontos elementais. Distribua os pontos entre os 7 elementos: <strong>cada ponto dá +1% de dano</strong> no elemento escolhido, valendo para magias, runas e ataques daquele tipo.</p>
			</header>

			<div class="mechanics-elemental" data-elemental-simulator data-elemental-points="<?php echo (int) $elemental_points_available; ?>">
				<div class="mechanics-elemental-header">
					<h3>Monte sua build</h3>
					<span class="mechanics-elemental-points">Pontos livres: <strong data-elemental-free><?php echo (int) $elemental_points_available; ?></strong> de <?php echo (int) $elemental_points_available; ?></span>
					<button type="button" class="mechanics-link-button" data-elemental-reset><i class="fa fa-undo"></i> Resetar</button>
				</div>
				<div class="mechanics-elements">
					<?php foreach ($mechanics_data['elemental']['elements'] as $element): ?>
						<?php $element_style = isset(MECHANICS_ELEMENT_STYLES[$element['key']]) ? MECHANICS_ELEMENT_STYLES[$element['key']] : array('label' => $element['name'], 'icon' => 'fa-circle', 'color' => '#e8b85a'); ?>
						<div class="mechanics-element" style="--element-color: <?php echo $element_style['color']; ?>;" data-element="<?php echo htmlspecialchars($element['key']); ?>">
							<span class="mechanics-element-icon"><i class="fa <?php echo $element_style['icon']; ?>"></i></span>
							<div class="mechanics-element-text">
								<strong><?php echo $element_style['label']; ?></strong>
								<span class="mechanics-element-bar"><span data-element-bar></span></span>
							</div>
							<div class="mechanics-element-controls">
								<button type="button" data-element-step="-1" aria-label="Tirar ponto de <?php echo $element_style['label']; ?>"><i class="fa fa-minus"></i></button>
								<output data-element-value>+0%</output>
								<button type="button" data-element-step="1" aria-label="Colocar ponto em <?php echo $element_style['label']; ?>"><i class="fa fa-plus"></i></button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="mechanics-elemental-example" data-elemental-example>Coloque pontos em um elemento para ver o efeito no dano.</p>
			</div>

			<h3 class="mechanics-subheading">Missões (<?php echo count($elemental_missions); ?>)</h3>
			<p class="mechanics-lead">Cada missão pede <strong>20 unidades</strong> de cada um dos 5 itens e dá <strong>1 ponto elemental</strong>.</p>
			<div class="mechanics-missions">
				<?php foreach ($elemental_missions as $mission_index => $elemental_mission): ?>
					<details class="mechanics-mission">
						<summary>
							<span class="mechanics-mission-number"><?php echo $mission_index + 1; ?></span>
							<strong><?php echo htmlspecialchars($elemental_mission['name']); ?></strong>
							<span class="mechanics-mission-reward">+<?php echo (int) $elemental_mission['points']; ?> ponto</span>
						</summary>
						<p><?php echo htmlspecialchars($elemental_mission['description']); ?></p>
						<ul class="mechanics-mission-items">
							<?php foreach ($elemental_mission['items'] as $mission_item): ?>
								<li><?php echo render_mechanics_item_icon($sprite_atlas, $mission_item['clientId'], $mission_item['name']); ?><span><?php echo (int) $mission_item['amount']; ?>x <?php echo htmlspecialchars($mission_item['name']); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="mechanics-panel" id="battlepass" role="tabpanel" hidden>
			<?php
			$battle_pass = isset($mechanics_data['battlePass']) ? $mechanics_data['battlePass'] : null;
			$battle_pass_days = $battle_pass !== null ? $battle_pass['days'] : array();
			?>
			<header class="mechanics-panel-header">
				<h2>Battle Pass<?php echo $battle_pass !== null ? ' · ' . htmlspecialchars($battle_pass['season']['name']) : ''; ?></h2>
				<p>Todo dia uma nova missão: entregue os itens pedidos e receba a recompensa do dia. O dia seguinte libera à <strong>meia-noite</strong>.<?php if ($battle_pass !== null): ?> Esta temporada tem <strong><?php echo (int) $battle_pass['season']['totalDays']; ?> dias</strong> e, ao completar todos, você ganha a recompensa final.<?php endif; ?></p>
			</header>

			<?php if ($battle_pass !== null): ?>
				<div class="mechanics-pass" data-pass-demo>
					<div class="mechanics-pass-header">
						<h3>Escolha um dia</h3>
						<label class="mechanics-toggle"><input type="checkbox" data-pass-pro> <span class="mechanics-toggle-track"><span></span></span> Mostrar como PRO</label>
					</div>

					<div class="mechanics-pass-days" role="tablist" aria-label="Dias do Battle Pass">
						<?php foreach ($battle_pass_days as $day_index => $battle_pass_day): ?>
							<button type="button" class="mechanics-pass-day-button<?php echo $day_index === 0 ? ' is-active' : ''; ?>" data-pass-day="<?php echo $day_index; ?>">
								<?php echo render_mechanics_item_icon($sprite_atlas, $battle_pass_day['itemId'], $battle_pass_day['description']); ?>
								<span>Dia <?php echo $day_index + 1; ?></span>
							</button>
						<?php endforeach; ?>
						<button type="button" class="mechanics-pass-day-button mechanics-pass-final-button" data-pass-day="final">
							<?php echo render_mechanics_item_icon($sprite_atlas, $battle_pass['finalReward']['clientId'], $battle_pass['finalReward']['label']); ?>
							<span>Final</span>
						</button>
					</div>

					<?php foreach ($battle_pass_days as $day_index => $battle_pass_day): ?>
						<div class="mechanics-pass-detail" data-pass-detail="<?php echo $day_index; ?>"<?php echo $day_index === 0 ? '' : ' hidden'; ?>>
							<div class="mechanics-pass-mission">
								<?php echo render_mechanics_item_icon($sprite_atlas, $battle_pass_day['itemId'], $battle_pass_day['description']); ?>
								<div>
									<span class="mechanics-pass-mission-label">Missão do dia <?php echo $day_index + 1; ?></span>
									<strong><?php echo htmlspecialchars($battle_pass_day['description']); ?></strong>
								</div>
							</div>
							<div class="mechanics-pass-rewards">
								<div class="mechanics-pass-column mechanics-pass-column-free">
									<h4><i class="fa fa-gift"></i> Grátis</h4>
									<ul>
										<?php foreach ($battle_pass_day['freeRewards'] as $free_reward): ?>
											<li><?php echo render_mechanics_item_icon($sprite_atlas, $free_reward['clientId'], $free_reward['label']); ?><span><?php echo number_format($free_reward['amount'], 0, ',', '.'); ?>x <?php echo htmlspecialchars(preg_replace('/^\d+x\s*/', '', $free_reward['label'])); ?></span></li>
										<?php endforeach; ?>
									</ul>
								</div>
								<div class="mechanics-pass-column mechanics-pass-column-pro">
									<h4><i class="fa fa-star"></i> PRO <small data-pass-lock><i class="fa fa-lock"></i> precisa do pergaminho PRO</small></h4>
									<ul>
										<?php foreach ($battle_pass_day['proRewards'] as $pro_reward): ?>
											<li><?php echo render_mechanics_item_icon($sprite_atlas, $pro_reward['clientId'], $pro_reward['label']); ?><span><?php echo number_format($pro_reward['amount'], 0, ',', '.'); ?>x <?php echo htmlspecialchars(preg_replace('/^\d+x\s*/', '', $pro_reward['label'])); ?></span></li>
										<?php endforeach; ?>
									</ul>
								</div>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="mechanics-pass-detail mechanics-pass-final" data-pass-detail="final" hidden>
						<?php echo render_mechanics_item_icon($sprite_atlas, $battle_pass['finalReward']['clientId'], $battle_pass['finalReward']['label']); ?>
						<div>
							<span class="mechanics-pass-mission-label">Recompensa final</span>
							<strong><?php echo htmlspecialchars($battle_pass['finalReward']['label']); ?></strong>
							<p>Entregue a missão de todos os <?php echo count($battle_pass_days); ?> dias da temporada para receber.</p>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="mechanics-steps">
				<div><span>1</span><h4>Missão do dia</h4><p>Abra o Battle Pass no jogo, veja o item pedido e junte a quantidade necessária.</p></div>
				<div><span>2</span><h4>Entregue e receba</h4><p>Entregue os itens para ganhar a recompensa grátis. O próximo dia libera à meia-noite.</p></div>
				<div><span>3</span><h4>PRO em dobro</h4><p>Com o pergaminho PRO da temporada ativado, cada dia entregue libera também a recompensa PRO.</p></div>
			</div>
		</section>

		<script>
		window.heroMechanicsGems = <?php echo json_encode($gems_by_type); ?>;
		window.heroMechanicsGemTypes = <?php echo json_encode(MECHANICS_GEM_TYPES); ?>;
		window.heroMechanicsGemSprites = <?php echo json_encode($gem_sprite_styles); ?>;
		</script>
		<script src="layout/js/mechanics.js"></script>
	<?php endif; ?>
</section>
<?php include 'layout/overall/footer.php'; ?>
