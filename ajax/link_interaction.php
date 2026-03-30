<?php
/**
 * LemonCRM - AJAX : lister les interactions d'un tiers ou rattacher à un thread
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

dol_include_once('/lemoncrm/class/lemoncrm_interaction.class.php');
dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');

header('Content-Type: application/json; charset=UTF-8');

$action = GETPOST('action', 'alpha');

// List recent interactions for a thirdparty (for "attach to" UI)
if ($action === 'list' && $user->hasRight('lemoncrm', 'interaction', 'read')) {
	$socid = GETPOSTINT('socid');
	$excludeId = GETPOSTINT('exclude');

	if ($socid <= 0) {
		echo json_encode([]);
		exit;
	}

	$types = lemoncrm_get_interaction_types();

	$sql = "SELECT i.rowid, i.ref, i.interaction_type, i.direction, i.summary, i.date_interaction, i.fk_parent";
	$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
	$sql .= " WHERE i.entity = ".$conf->entity;
	$sql .= " AND i.fk_soc = ".(int)$socid;
	if ($excludeId > 0) {
		$sql .= " AND i.rowid != ".(int)$excludeId;
		// Exclure aussi les enfants de l'interaction exclue
		$sql .= " AND (i.fk_parent IS NULL OR i.fk_parent != ".(int)$excludeId.")";
	}
	$sql .= " ORDER BY i.date_interaction DESC LIMIT 20";

	$results = [];
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$typeLabel = $types[$obj->interaction_type] ?? $obj->interaction_type;
			$summary = strip_tags($obj->summary);
			$summary = str_replace(array("\\r\\n", "\\n", "\\r", "\r\n", "\r", "\n"), ' ', $summary);
			$summary = dol_trunc(trim(preg_replace('/\s+/', ' ', $summary)), 50);
			$dateFr = lemoncrm_format_date_fr($db->jdate($obj->date_interaction));
			$results[] = [
				'id' => (int)$obj->rowid,
				'ref' => $obj->ref,
				'type' => $typeLabel,
				'direction' => $obj->direction,
				'summary' => $summary,
				'date' => $dateFr,
				'is_parent' => ($obj->fk_parent === null || $obj->fk_parent == 0),
			];
		}
	}
	echo json_encode($results);
	exit;
}

// Attach an interaction to a parent (set fk_parent)
if ($action === 'attach' && $user->hasRight('lemoncrm', 'interaction', 'write')) {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		http_response_code(405);
		echo json_encode(['error' => 'Method not allowed']);
		exit;
	}
	if (GETPOST('token', 'alpha') != newToken()) {
		echo json_encode(['error' => 'Bad CSRF token']);
		exit;
	}
	$id = GETPOSTINT('id');
	$parentId = GETPOSTINT('parent_id');

	if ($id <= 0 || $parentId <= 0) {
		echo json_encode(['error' => 'Paramètres manquants']);
		exit;
	}

	// Vérifier que le parent n'est pas un enfant (garder plat)
	$sql = "SELECT fk_parent FROM ".MAIN_DB_PREFIX."lemoncrm_interaction WHERE rowid = ".(int)$parentId;
	$resql = $db->query($sql);
	if ($resql && ($obj = $db->fetch_object($resql)) && $obj->fk_parent > 0) {
		$parentId = (int)$obj->fk_parent; // remonter au vrai parent
	}

	$sql = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET fk_parent = ".(int)$parentId." WHERE rowid = ".(int)$id;
	if ($db->query($sql)) {
		echo json_encode(['success' => true, 'parent_id' => $parentId]);
	} else {
		echo json_encode(['error' => 'Erreur lors du rattachement']);
	}
	exit;
}

// List open tasks for a thirdparty (for "time spent" UI)
if ($action === 'tasks' && $user->hasRight('projet', 'lire')) {
	$socid = GETPOSTINT('socid');

	if ($socid <= 0) {
		echo json_encode([]);
		exit;
	}

	$sql = "SELECT t.rowid as task_id, t.ref as task_ref, t.label as task_label,";
	$sql .= " p.ref as project_ref, p.title as project_title";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet_task as t";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON t.fk_projet = p.rowid";
	$sql .= " WHERE p.fk_soc = ".(int)$socid;
	$sql .= " AND p.fk_statut = 1"; // projet ouvert
	$sql .= " AND t.progress < 100"; // tâche pas terminée
	$sql .= " AND p.entity IN (".getEntity('projet').")";
	$sql .= " ORDER BY p.ref DESC, t.ref ASC LIMIT 20";

	$results = [];
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$results[] = [
				'task_id' => (int)$obj->task_id,
				'task_ref' => $obj->task_ref,
				'task_label' => $obj->task_label,
				'project_ref' => $obj->project_ref,
				'project_title' => $obj->project_title,
			];
		}
	}
	echo json_encode($results);
	exit;
}

echo json_encode(['error' => 'Action inconnue']);
