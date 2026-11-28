<?php

const HOME_TIMEZONE = 'America/Sao_Paulo';
const HOME_SERVER_SAVE_HOUR = 3;
const HOME_ACTIVITY_LIMIT = 6;
const HOME_ACTIVITY_CACHE_SECONDS = 60;
const HOME_STATS_CACHE_SECONDS = 300;
const HOME_UPDATES_ON_HOME = 2;
const HOME_UPDATE_ITEMS_ON_HOME = 5;
const HOME_NEWS_PREVIEW_LENGTH = 320;

function home_timezone() {
	return new DateTimeZone(HOME_TIMEZONE);
}

function home_load_cached($cacheName, $lifetimeSeconds, $loader) {
	$cache = new Cache('engine/cache/' . $cacheName);
	$cache->setExpiration($lifetimeSeconds);
	if (!$cache->hasExpired()) {
		return $cache->load();
	}

	$content = $loader();
	$cache->setContent($content);
	$cache->save();
	return $content;
}

function home_player_visibility_sql() {
	return '`group_id` < ' . (int) config('highscore')['ignoreGroupId'] . ' AND ' . sample_characters_exclusion_sql();
}

function home_count_characters() {
	return home_load_cached('homeCharacterCount', HOME_STATS_CACHE_SECONDS, function () {
		$row = mysql_select_single('SELECT COUNT(*) AS `total` FROM `players` WHERE ' . home_player_visibility_sql() . ';');
		return $row ? (int) $row['total'] : 0;
	});
}

function home_list_latest_deaths() {
	return home_load_cached('homeLatestDeaths', HOME_ACTIVITY_CACHE_SECONDS, function () {
		$deaths = mysql_select_multi('SELECT `p`.`name`, `d`.`level`, `d`.`killed_by`, `d`.`is_player`, `d`.`time` FROM `player_deaths` AS `d` INNER JOIN `players` AS `p` ON `d`.`player_id` = `p`.`id` WHERE ' . home_player_visibility_sql_for_alias('p') . ' ORDER BY `d`.`time` DESC LIMIT ' . HOME_ACTIVITY_LIMIT . ';');
		return $deaths ? $deaths : array();
	});
}

function home_list_newest_characters() {
	return home_load_cached('homeNewestCharacters', HOME_ACTIVITY_CACHE_SECONDS, function () {
		$characters = mysql_select_multi('SELECT `name`, `level`, `vocation` FROM `players` WHERE ' . home_player_visibility_sql() . ' ORDER BY `id` DESC LIMIT ' . HOME_ACTIVITY_LIMIT . ';');
		return $characters ? $characters : array();
	});
}

function home_player_visibility_sql_for_alias($tableAlias) {
	return '`' . $tableAlias . '`.`group_id` < ' . (int) config('highscore')['ignoreGroupId'] . ' AND ' . sample_characters_exclusion_sql('`' . $tableAlias . '`.`name`');
}

function home_describe_time_ago($timestamp) {
	$elapsedSeconds = max(0, time() - (int) $timestamp);
	if ($elapsedSeconds < 60) {
		return 'agora mesmo';
	}
	if ($elapsedSeconds < 3600) {
		return 'há ' . floor($elapsedSeconds / 60) . ' min';
	}
	if ($elapsedSeconds < 86400) {
		return 'há ' . floor($elapsedSeconds / 3600) . ' h';
	}
	$elapsedDays = floor($elapsedSeconds / 86400);
	return $elapsedDays === 1.0 ? 'ontem' : 'há ' . $elapsedDays . ' dias';
}

function home_next_server_save() {
	$now = new DateTime('now', home_timezone());
	$nextServerSave = (clone $now)->setTime(HOME_SERVER_SAVE_HOUR, 0, 0);
	if ($nextServerSave <= $now) {
		$nextServerSave->modify('+1 day');
	}
	return $nextServerSave;
}

function home_describe_time_until($targetDate) {
	$now = new DateTime('now', home_timezone());
	$remainingMinutes = (int) floor(($targetDate->getTimestamp() - $now->getTimestamp()) / 60);
	$remainingHours = intdiv($remainingMinutes, 60);
	$remainingMinutesInHour = $remainingMinutes % 60;
	if ($remainingHours === 0) {
		return 'em ' . $remainingMinutesInHour . ' min';
	}
	return 'em ' . $remainingHours . 'h' . str_pad((string) $remainingMinutesInHour, 2, '0', STR_PAD_LEFT);
}

function home_describe_rates() {
	$snapshot = server_info_load_snapshot();
	$serverConfig = $snapshot['config'];
	$experienceStages = isset($snapshot['stages']['experienceStages']) ? $snapshot['stages']['experienceStages'] : array();

	$rates = array('loot' => null, 'experienceStart' => null);
	if (isset($serverConfig['rateLoot'])) {
		$rates['loot'] = $serverConfig['rateLoot'];
	}
	if (!empty($serverConfig['rateUseStages']) && !empty($experienceStages)) {
		$rates['experienceStart'] = $experienceStages[0]['multiplier'];
		return $rates;
	}
	if (isset($serverConfig['rateExp'])) {
		$rates['experienceStart'] = $serverConfig['rateExp'];
	}
	return $rates;
}

function home_load_site_updates() {
	$updates = include __DIR__ . '/../data/site_updates.php';
	return is_array($updates) ? $updates : array();
}

function home_format_update_date($isoDate) {
	$date = DateTime::createFromFormat('!Y-m-d', $isoDate, home_timezone());
	return $date ? $date->format('d/m/Y') : $isoDate;
}

function home_load_latest_news() {
	return home_load_cached('news', 60, function () {
		$news = fetchAllNews();
		return $news ? $news : array();
	});
}

function home_strip_news_markup($text) {
	$withoutTags = preg_replace('/\[(\/?)[a-z*]+(=[^\]]*)?\]/i', '', $text);
	return trim(strip_tags($withoutTags));
}

function home_text_length($text) {
	return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function home_text_prefix($text, $length) {
	return function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
}

function home_preview_news_text($text) {
	$plainText = home_strip_news_markup($text);
	if (home_text_length($plainText) <= HOME_NEWS_PREVIEW_LENGTH) {
		return $plainText;
	}
	return rtrim(home_text_prefix($plainText, HOME_NEWS_PREVIEW_LENGTH)) . '…';
}

function home_build_calendar_month() {
	$today = new DateTime('today', home_timezone());
	$firstDay = (clone $today)->modify('first day of this month');
	$daysInMonth = (int) $firstDay->format('t');
	$leadingBlankDays = (int) $firstDay->format('w');

	$monthEvents = array();
	$monthStart = (clone $firstDay)->setTime(0, 0, 0);
	$monthEnd = (clone $firstDay)->modify('last day of this month')->setTime(23, 59, 59);
	foreach (list_server_events() as $serverEvent) {
		if ($serverEvent['endDate'] < $monthStart || $serverEvent['startDate'] > $monthEnd) {
			continue;
		}
		$monthEvents[] = $serverEvent;
	}

	$days = array();
	for ($dayNumber = 1; $dayNumber <= $daysInMonth; $dayNumber++) {
		$dayDate = DateTime::createFromFormat('!Y-m-d', $firstDay->format('Y-m-') . str_pad((string) $dayNumber, 2, '0', STR_PAD_LEFT));
		$dayEvents = array();
		foreach ($monthEvents as $serverEvent) {
			if ($serverEvent['startDate'] <= $dayDate && $dayDate <= $serverEvent['endDate']) {
				$dayEvents[] = $serverEvent;
			}
		}
		$days[] = array(
			'number' => $dayNumber,
			'isToday' => $dayDate->format('Y-m-d') === $today->format('Y-m-d'),
			'isPast' => $dayDate < $today,
			'events' => $dayEvents,
		);
	}

	return array(
		'title' => home_month_name((int) $firstDay->format('n')) . ' de ' . $firstDay->format('Y'),
		'leadingBlankDays' => $leadingBlankDays,
		'days' => $days,
		'events' => $monthEvents,
	);
}

function home_month_name($monthNumber) {
	$monthNames = array(1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro');
	return $monthNames[$monthNumber];
}
