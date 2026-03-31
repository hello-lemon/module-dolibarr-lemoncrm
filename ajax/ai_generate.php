<?php
/**
 * LemonCRM - AJAX : AI message generation
 * POST endpoint that calls Claude API to generate contextual messages
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die('Include of main fails');
}

header('Content-Type: application/json; charset=UTF-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(array('success' => false, 'error' => 'Method not allowed'));
	exit;
}

// CSRF check
if (GETPOST('token', 'alpha') != currentToken()) {
	echo json_encode(array('success' => false, 'error' => 'Invalid CSRF token'));
	exit;
}

// Permission check
if (!$user->hasRight('lemoncrm', 'interaction', 'write')) {
	echo json_encode(array('success' => false, 'error' => 'Permission denied'));
	exit;
}

// Check AI is enabled
if (!getDolGlobalInt('LEMONCRM_AI_ENABLED')) {
	echo json_encode(array('success' => false, 'error' => 'AI generation is not enabled'));
	exit;
}

dol_include_once('/lemoncrm/class/lemoncrm_ai.class.php');

$socId = GETPOSTINT('socid');
$contactId = GETPOSTINT('contactid');
$channel = GETPOST('channel', 'alpha');
$objective = GETPOST('objective', 'alpha');
$incomingMessage = GETPOST('incoming_message', 'restricthtml');

if (empty($channel)) {
	echo json_encode(array('success' => false, 'error' => 'Channel (type interaction) requis'));
	exit;
}
if (empty($objective)) {
	echo json_encode(array('success' => false, 'error' => 'Objectif requis'));
	exit;
}

$ai = new LemonCRMAI($db);
$result = $ai->generate($socId, $contactId, $channel, $objective, $incomingMessage);

echo json_encode($result);
