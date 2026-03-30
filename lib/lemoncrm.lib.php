<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * Library for LemonCRM module
 */

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
 * Return list of interaction types for select
 *
 * @return array
 */
function lemoncrm_get_interaction_types()
{
	global $langs;

	return array(
		'AC_TEL' => $langs->trans('AC_TEL'),
		'AC_EMAIL' => $langs->trans('AC_EMAIL'),
		'AC_LINKEDIN' => $langs->trans('AC_LINKEDIN'),
		'AC_TEAMS' => $langs->trans('AC_TEAMS'),
		'AC_RDV' => $langs->trans('AC_RDV'),
		'AC_MEETING_INPERSON' => $langs->trans('AC_MEETING_INPERSON'),
		'AC_OTH' => $langs->trans('AC_OTH'),
	);
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
	return array(
		'AC_TEL' => 'fas fa-phone-alt',
		'AC_EMAIL' => 'fas fa-envelope',
		'AC_LINKEDIN' => 'fas fa-share-alt',
		'AC_TEAMS' => 'fas fa-video',
		'AC_RDV' => 'far fa-calendar-check',
		'AC_MEETING_INPERSON' => 'fas fa-users',
		'AC_OTH' => 'far fa-comment',
	);
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
