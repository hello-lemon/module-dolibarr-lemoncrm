<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * Library for LemonCRM module
 */

/**
 *  Vérifie si une version plus récente du module existe sur GitHub.
 *
 *  Appel de l'API publique GitHub releases/latest, mise en cache 24h dans une
 *  constante Dolibarr pour ne pas marteler l'API à chaque ouverture de la page
 *  admin. Retourne silencieusement null si l'API est inaccessible.
 *
 *  @param  DoliDB  $db              Handle BDD Dolibarr
 *  @param  string  $currentVersion  Version actuelle du module
 *  @return array|null               ['version' => 'x.y.z', 'url' => '...'] ou null
 */
function lemoncrm_check_latest_release($db, $currentVersion)
{
	$now = time();
	$cacheRaw = getDolGlobalString('LEMONCRM_UPDATE_CHECK_CACHE', '');
	$cache = !empty($cacheRaw) ? json_decode($cacheRaw, true) : null;

	$latest = null;
	$htmlUrl = '';
	if (is_array($cache) && isset($cache['ts']) && ($now - (int) $cache['ts']) < 86400) {
		$latest  = $cache['version'] ?? null;
		$htmlUrl = $cache['url']     ?? '';
	} else {
		$url = 'https://api.github.com/repos/hello-lemon/module-dolibarr-lemoncrm/releases/latest';
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'LemonCRM-UpdateCheck');
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		$json = @curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200 || empty($json)) {
			return null;
		}
		$data = json_decode($json, true);
		if (!is_array($data) || empty($data['tag_name'])) {
			return null;
		}
		$latest  = ltrim($data['tag_name'], 'v');
		$htmlUrl = $data['html_url'] ?? '';
		if (!preg_match('#^https://github\.com/hello-lemon/module-dolibarr-lemoncrm/#', $htmlUrl)) {
			$htmlUrl = 'https://github.com/hello-lemon/module-dolibarr-lemoncrm/releases';
		}

		dolibarr_set_const($db, 'LEMONCRM_UPDATE_CHECK_CACHE', json_encode([
			'ts'      => $now,
			'version' => $latest,
			'url'     => $htmlUrl,
		]), 'chaine', 0, '', 0);
	}

	if (!empty($latest) && version_compare($latest, $currentVersion, '>')) {
		return ['version' => $latest, 'url' => $htmlUrl];
	}
	return null;
}


/**
 * Prepare admin pages header (tabs)
 *
 * @return array
 */
function lemoncrm_admin_prepare_head()
{
	global $langs, $conf;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/lemoncrm/admin/setup.php', 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/lemoncrm/admin/setup.php?mode=about', 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'lemoncrm@lemoncrm');

	return $head;
}

/**
 * Return list of interaction types from Dolibarr agenda dictionary (llx_c_actioncomm)
 * Filters out systemauto types and Event types (AC_EO_*)
 *
 * @param bool $activeOnly If true (default), only return active types
 * @return array code => label
 */
function lemoncrm_get_interaction_types($activeOnly = true)
{
	global $db, $langs;

	static $cacheAll = null;
	static $cacheActive = null;

	if ($activeOnly && $cacheActive !== null) return $cacheActive;
	if (!$activeOnly && $cacheAll !== null) return $cacheAll;

	$result = array();

	$sql = "SELECT code, libelle FROM ".MAIN_DB_PREFIX."c_actioncomm";
	$sql .= " WHERE code LIKE 'LCRM_%'";
	if ($activeOnly) $sql .= " AND active = 1";
	$sql .= " ORDER BY position ASC, code ASC";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$trans = $langs->trans($obj->code);
			$result[$obj->code] = ($trans !== $obj->code) ? $trans : $obj->libelle;
		}
	}

	if ($activeOnly) {
		$cacheActive = $result;
	} else {
		$cacheAll = $result;
	}

	return $result;
}

/**
 * Return list of directions
 *
 * @return array
 */
function lemoncrm_get_directions()
{
	global $langs;

	return array(
		'IN' => $langs->trans('DirectionIN'),
		'OUT' => $langs->trans('DirectionOUT'),
	);
}

/**
 * Return list of sentiments
 *
 * @return array
 */
function lemoncrm_get_sentiments()
{
	global $langs;

	return array(
		'' => '',
		'positive' => $langs->trans('SentimentPositive'),
		'neutral' => $langs->trans('SentimentNeutral'),
		'negative' => $langs->trans('SentimentNegative'),
	);
}

/**
 * Return list of prospect statuses
 *
 * @return array
 */
function lemoncrm_get_prospect_statuses()
{
	global $langs;

	return array(
		'' => '',
		'cold' => $langs->trans('ProspectCold'),
		'warm' => $langs->trans('ProspectWarm'),
		'hot' => $langs->trans('ProspectHot'),
		'negotiation' => $langs->trans('ProspectNegotiation'),
		'won' => $langs->trans('ProspectWon'),
		'lost' => $langs->trans('ProspectLost'),
	);
}

/**
 * Return list of followup modes
 *
 * @return array
 */
function lemoncrm_get_followup_modes()
{
	global $langs;

	return array(
		'' => '',
		'phone' => $langs->trans('FollowupPhone'),
		'email' => $langs->trans('FollowupEmail'),
		'linkedin' => $langs->trans('FollowupLinkedIn'),
	);
}

/**
 * Generate next ref for interaction
 *
 * @param DoliDB $db Database handler
 * @return string Next ref LCI-YYYYMMDD-XXXX
 */
function lemoncrm_get_next_ref($db)
{
	$prefix = 'LCI-'.date('Ymd').'-';

	$sql = "SELECT MAX(ref) as maxref FROM ".MAIN_DB_PREFIX."lemoncrm_interaction";
	$sql .= " WHERE ref LIKE '".$db->escape($prefix)."%'";
	$resql = $db->query($sql);

	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj->maxref) {
			$num = (int)substr($obj->maxref, -4) + 1;
		} else {
			$num = 1;
		}
	} else {
		$num = 1;
	}

	return $prefix.sprintf('%04d', $num);
}

/**
 * Prepare thirdparty card tabs
 *
 * @param Societe $object Thirdparty object
 * @return array Tabs
 */
function lemoncrm_thirdparty_prepare_head($object)
{
	// Tabs are handled via module descriptor $this->tabs
	return array();
}

/**
 * Format a date in French (short or long)
 *
 * @param int|string $timestamp_or_string Timestamp or date string
 * @param string $format 'short' or 'long'
 * @return string Formatted date in French
 */
function lemoncrm_format_date_fr($timestamp_or_string, $format = 'short')
{
	$joursLong = array('Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi');
	$joursCourt = array('Dim','Lun','Mar','Mer','Jeu','Ven','Sam');
	$moisLong = array('','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre');
	$moisCourt = array('','jan','fév','mars','avr','mai','juin','juil','août','sept','oct','nov','déc');

	if (is_numeric($timestamp_or_string)) {
		$dt = new DateTime();
		$dt->setTimestamp((int)$timestamp_or_string);
	} else {
		$dt = new DateTime($timestamp_or_string);
	}

	$w = (int)$dt->format('w');
	$j = (int)$dt->format('j');
	$n = (int)$dt->format('n');

	if ($format === 'long') {
		return $joursLong[$w].' '.$j.' '.$moisLong[$n];
	}
	return $joursCourt[$w].' '.$j.' '.$moisCourt[$n];
}

/**
 * Return icon CSS classes for each interaction type
 *
 * @return array type_code => icon CSS class
 */
function lemoncrm_get_type_icons()
{
	$defaults = array(
		'LCRM_TEL' => 'fas fa-phone-alt',
		'LCRM_EMAIL' => 'fas fa-envelope',
		'LCRM_LINKEDIN' => 'fab fa-linkedin',
		'LCRM_TEAMS' => 'fas fa-video',
		'LCRM_RDV' => 'far fa-calendar-check',
		'LCRM_WHATSAPP' => 'fab fa-whatsapp',
		'LCRM_NOTE' => 'far fa-comment',
		'LCRM_RELANCE' => 'fas fa-bell',
	);

	// Override with saved config
	$saved = json_decode(getDolGlobalString('LEMONCRM_TYPE_ICONS', '{}'), true);
	if (is_array($saved)) {
		$defaults = array_merge($defaults, $saved);
	}

	// Fallback for types not in the list
	$types = lemoncrm_get_interaction_types(false);
	foreach ($types as $code => $label) {
		if (!isset($defaults[$code])) {
			$defaults[$code] = 'far fa-comment';
		}
	}

	return $defaults;
}

/**
 * Return call outcome labels
 *
 * @return array code => French label
 */
function lemoncrm_get_call_outcomes()
{
	return array('connected' => 'Joint', 'voicemail' => 'Messagerie', 'no_answer' => 'Pas de réponse', 'busy' => 'Occupé');
}
