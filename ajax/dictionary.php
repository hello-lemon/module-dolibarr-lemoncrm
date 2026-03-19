<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * AJAX endpoint for LemonCRM dictionary tables (sentiment, prospect_status)
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

header('Content-Type: application/json');

$action = GETPOST('action', 'alpha');
$type = GETPOST('type', 'alpha');

// Validate type parameter
$allowedTypes = array('sentiment', 'prospect_status');
if (!in_array($type, $allowedTypes)) {
	http_response_code(400);
	echo json_encode(array('error' => 'Invalid type parameter'));
	exit;
}

$tableName = MAIN_DB_PREFIX.'c_lemoncrm_'.$type;

/*
 * GET action=list : return active dictionary entries
 */
if ($action == 'list') {
	$sql = "SELECT rowid, code, label, color";
	$sql .= " FROM ".$tableName;
	$sql .= " WHERE active = 1";
	$sql .= " AND entity IN (0, ".((int) $conf->entity).")";
	$sql .= " ORDER BY position ASC";

	$resql = $db->query($sql);
	if (!$resql) {
		http_response_code(500);
		echo json_encode(array('error' => 'Database error'));
		exit;
	}

	$entries = array();
	while ($obj = $db->fetch_object($resql)) {
		$entries[] = array(
			'id' => (int) $obj->rowid,
			'code' => $obj->code,
			'label' => $obj->label,
			'color' => $obj->color,
		);
	}
	$db->free($resql);

	echo json_encode($entries);
	exit;
}

/*
 * POST action=add : create a new dictionary entry
 */
if ($action == 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	// CSRF check
	if (GETPOST('token', 'alpha') != newToken()) {
		http_response_code(403);
		echo json_encode(array('error' => 'Invalid CSRF token'));
		exit;
	}

	// Permission check
	if (!$user->hasRight('lemoncrm', 'interaction', 'write')) {
		http_response_code(403);
		echo json_encode(array('error' => 'Permission denied'));
		exit;
	}

	$label = GETPOST('label', 'alpha');
	$color = GETPOST('color', 'alpha');

	if (empty($label)) {
		http_response_code(400);
		echo json_encode(array('error' => 'Label is required'));
		exit;
	}

	// Generate code from label: lowercase, no accents, underscores
	$code = strtolower($label);
	if (function_exists('transliterator_transliterate')) {
		$code = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $code);
	} else {
		$code = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $code);
		$code = strtolower($code);
	}
	$code = preg_replace('/[^a-z0-9]+/', '_', $code);
	$code = trim($code, '_');
	$code = substr($code, 0, 32);

	// Get max position
	$sql = "SELECT MAX(position) as maxpos FROM ".$tableName;
	$sql .= " WHERE entity IN (0, ".((int) $conf->entity).")";
	$resql = $db->query($sql);
	$maxpos = 0;
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj && $obj->maxpos !== null) {
			$maxpos = (int) $obj->maxpos;
		}
		$db->free($resql);
	}
	$newpos = $maxpos + 1;

	// Insert
	$sql = "INSERT INTO ".$tableName." (code, label, color, position, active, entity)";
	$sql .= " VALUES (";
	$sql .= "'".$db->escape($code)."',";
	$sql .= " '".$db->escape($label)."',";
	$sql .= " '".$db->escape($color)."',";
	$sql .= " ".((int) $newpos).",";
	$sql .= " 1,";
	$sql .= " ".((int) $conf->entity);
	$sql .= ")";

	$resql = $db->query($sql);
	if (!$resql) {
		http_response_code(500);
		echo json_encode(array('error' => 'Database error'));
		exit;
	}

	$newId = $db->last_insert_id($tableName);

	echo json_encode(array(
		'success' => true,
		'id' => (int) $newId,
		'code' => $code,
		'label' => $label,
		'color' => $color,
	));
	exit;
}

/*
 * POST action=delete : soft-delete (set active=0)
 */
if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	// CSRF check
	if (GETPOST('token', 'alpha') != newToken()) {
		http_response_code(403);
		echo json_encode(array('error' => 'Invalid CSRF token'));
		exit;
	}

	// Admin only
	if (!$user->admin) {
		http_response_code(403);
		echo json_encode(array('error' => 'Permission denied'));
		exit;
	}

	$id = GETPOST('id', 'int');
	if (empty($id) || $id < 1) {
		http_response_code(400);
		echo json_encode(array('error' => 'Invalid id'));
		exit;
	}

	$sql = "UPDATE ".$tableName." SET active = 0";
	$sql .= " WHERE rowid = ".((int) $id);
	$sql .= " AND entity IN (0, ".((int) $conf->entity).")";

	$resql = $db->query($sql);
	if (!$resql) {
		http_response_code(500);
		echo json_encode(array('error' => 'Database error'));
		exit;
	}

	echo json_encode(array('success' => true));
	exit;
}

// Unknown action
http_response_code(400);
echo json_encode(array('error' => 'Unknown action'));
