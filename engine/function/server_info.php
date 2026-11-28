<?php

function server_info_resolve_server_path($configuredPath) {
	$candidatePaths = array($configuredPath, '/home/noxum/server');
	foreach ($candidatePaths as $candidatePath) {
		if ($candidatePath && is_file(rtrim($candidatePath, '/\\') . '/config.lua')) {
			return rtrim($candidatePath, '/\\');
		}
	}
	return null;
}

function server_info_evaluate_number($expression) {
	$expression = trim($expression);
	if (!preg_match('/^[0-9.\s*]+$/', $expression)) {
		return null;
	}

	$result = 1;
	foreach (explode('*', $expression) as $factor) {
		$factor = trim($factor);
		if ($factor === '' || !is_numeric($factor)) {
			return null;
		}
		$result *= $factor + 0;
	}
	return $result;
}

function server_info_read_config_values($configPath) {
	$configValues = array();
	foreach (file($configPath, FILE_IGNORE_NEW_LINES) as $configLine) {
		if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+?)\s*(--.*)?$/', $configLine, $match)) {
			continue;
		}

		$rawValue = trim($match[2]);
		if (preg_match('/^"(.*)"$/', $rawValue, $stringMatch)) {
			$configValues[$match[1]] = $stringMatch[1];
			continue;
		}

		if ($rawValue === 'true' || $rawValue === 'false') {
			$configValues[$match[1]] = $rawValue === 'true';
			continue;
		}

		$numericValue = server_info_evaluate_number($rawValue);
		if ($numericValue !== null) {
			$configValues[$match[1]] = $numericValue;
		}
	}
	return $configValues;
}

function server_info_read_stage_tables($stagesPath) {
	$stageTables = array();
	$stagesContent = preg_replace('/--[^\n]*/', '', file_get_contents($stagesPath));
	if (!preg_match_all('/([A-Za-z_]+)\s*=\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s', $stagesContent, $tableMatches, PREG_SET_ORDER)) {
		return $stageTables;
	}

	foreach ($tableMatches as $tableMatch) {
		preg_match_all('/\{([^{}]*)\}/', $tableMatch[2], $stageMatches);
		$stages = array();
		foreach ($stageMatches[1] as $stageBody) {
			preg_match_all('/(minlevel|maxlevel|multiplier)\s*=\s*([0-9.]+)/', $stageBody, $fieldMatches, PREG_SET_ORDER);
			$stage = array();
			foreach ($fieldMatches as $fieldMatch) {
				$stage[$fieldMatch[1]] = $fieldMatch[2] + 0;
			}
			if (isset($stage['minlevel'], $stage['multiplier'])) {
				$stages[] = $stage;
			}
		}
		$stageTables[$tableMatch[1]] = $stages;
	}
	return $stageTables;
}

function server_info_load_snapshot() {
	global $config;
	$serverPath = server_info_resolve_server_path(isset($config['server_path']) ? $config['server_path'] : null);
	if ($serverPath === null) {
		return array('config' => array(), 'stages' => array());
	}

	$stagesPath = $serverPath . '/data/stages.lua';
	return array(
		'config' => server_info_read_config_values($serverPath . '/config.lua'),
		'stages' => is_file($stagesPath) ? server_info_read_stage_tables($stagesPath) : array()
	);
}
