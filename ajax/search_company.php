<?php
/**
 * LemonCRM - AJAX search for thirdparties
 * Returns JSON array of {id, name} matching the search term
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

if (!$user->hasRight('societe', 'lire')) {
	echo json_encode([]);
	exit;
}

$term = GETPOST('term', 'alpha');
if (strlen($term) < 2) {
	echo json_encode([]);
	exit;
}

$sql = "SELECT s.rowid as id, s.nom as name FROM ".MAIN_DB_PREFIX."societe as s";
$sql .= " WHERE s.entity IN (".getEntity('societe').")";
$sql .= " AND (s.nom LIKE '%".$db->escape($term)."%'";
$sql .= " OR s.name_alias LIKE '%".$db->escape($term)."%')";
$sql .= " AND s.status = 1";
$sql .= " ORDER BY s.nom ASC LIMIT 15";

$results = [];
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$results[] = ['id' => (int)$obj->id, 'name' => $obj->name];
	}
}

echo json_encode($results);
