<?php
/**
 * LemonCRM - AJAX : infos contact (téléphone, email)
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

$contactId = GETPOSTINT('id');
if ($contactId <= 0 || !$user->hasRight('societe', 'contact', 'lire')) {
	echo json_encode([]);
	exit;
}

$sql = "SELECT phone_mobile, phone_perso, phone, email FROM ".MAIN_DB_PREFIX."socpeople WHERE rowid = ".(int)$contactId;
$resql = $db->query($sql);
if ($resql && ($obj = $db->fetch_object($resql))) {
	echo json_encode([
		'phone_mobile' => $obj->phone_mobile,
		'phone' => $obj->phone,
		'phone_perso' => $obj->phone_perso,
		'email' => $obj->email,
	]);
} else {
	echo json_encode([]);
}
