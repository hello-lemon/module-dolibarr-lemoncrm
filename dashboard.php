<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * LemonCRM Dashboard - Vue unique
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/lemoncrm/class/lemoncrm_interaction.class.php');
dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');

$langs->loadLangs(array("lemoncrm@lemoncrm", "companies", "bills"));

if (!$user->hasRight('lemoncrm', 'interaction', 'read')) {
	accessforbidden();
}

$socid = GETPOSTINT('socid');
$contactid = GETPOSTINT('contactid');

// Close task (set progress to 100% + status closed)
if (GETPOST('action', 'alpha') == 'closetask' && $user->hasRight('projet', 'creer')) {
	$taskId = GETPOSTINT('taskid');
	if ($taskId > 0) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
		$task = new Task($db);
		if ($task->fetch($taskId) > 0) {
			$task->progress = 100;
			$task->status = 2; // Clôturée
			$task->fk_statut = 2;
			$task->update($user);
		}
		$redir = $_SERVER["PHP_SELF"];
		if ($socid) $redir .= '?socid='.$socid;
		header("Location: ".$redir);
		exit;
	}
}

// Mark followup as done
if (GETPOST('action', 'alpha') == 'followupdone' && $user->hasRight('lemoncrm', 'interaction', 'write')) {
	$doneId = GETPOSTINT('id');
	if ($doneId > 0) {
		$doneObj = new LemonCRMInteraction($db);
		if ($doneObj->fetch($doneId) > 0) {
			$doneObj->markFollowupDone($user);
		}
		$redir = $_SERVER["PHP_SELF"];
		if ($socid) $redir .= '?socid='.$socid;
		header("Location: ".$redir);
		exit;
	}
}

// Delete action (single)
if (GETPOST('action', 'alpha') == 'delete' && $user->hasRight('lemoncrm', 'interaction', 'delete')) {
	$delId = GETPOSTINT('id');
	if ($delId > 0) {
		$delObj = new LemonCRMInteraction($db);
		if ($delObj->fetch($delId) > 0) {
			$delObj->delete($user);
		}
		$redir = $_SERVER["PHP_SELF"];
		if ($socid) $redir .= '?socid='.$socid;
		header("Location: ".$redir);
		exit;
	}
}

// Mass delete action (Dolibarr standard: confirmmassaction + massaction=predelete)
$massaction = GETPOST('massaction', 'alpha');
if (GETPOST('confirmmassaction', 'alpha') && $massaction == 'predelete' && $user->hasRight('lemoncrm', 'interaction', 'delete')) {
	$toselect = GETPOST('toselect', 'array');
	if (is_array($toselect) && count($toselect) > 0) {
		foreach ($toselect as $delId) {
			$delObj = new LemonCRMInteraction($db);
			if ($delObj->fetch((int)$delId) > 0) {
				$delObj->delete($user);
			}
		}
		setEventMessages('Interactions supprimées', null, 'mesgs');
	}
	$redir = $_SERVER["PHP_SELF"];
	if ($socid) $redir .= '?socid='.$socid;
	header("Location: ".$redir);
	exit;
}
if (!GETPOST('confirmmassaction', 'alpha')) {
	$massaction = '';
}

$_GET['mainmenu'] = 'lemon';
$_GET['leftmenu'] = 'lemoncrm';

llxHeader('', $langs->trans('DashboardCRM'), '', '', 0, 0, '', '', '', 'mod-lemoncrm page-dashboard');

// If filtered by thirdparty, show thirdparty header with tabs
$thirdparty = null;
if ($socid > 0) {
	$thirdparty = new Societe($db);
	$thirdparty->fetch($socid);
	require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
	$head = societe_prepare_head($thirdparty);
	print dol_get_fiche_head($head, 'lemoncrm', $langs->trans("ThirdParty"), -1, 'company');
	dol_banner_tab($thirdparty, 'socid', '', ($user->socid ? 0 : 1), 'rowid', 'nom');
	print dol_get_fiche_end();
} else {
	print load_fiche_titre($langs->trans('DashboardCRM'), '', 'fa-comments');
}

$now = dol_now();
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));

$types = lemoncrm_get_interaction_types();
$typeIcons = lemoncrm_get_type_icons();
$followup_modes = lemoncrm_get_followup_modes();

// SQL filter for socid
$socFilter = ($socid > 0) ? " AND i.fk_soc = ".(int)$socid : "";


// ==================== STATS BAR ====================
print '<div class="lemoncrm-stats-bar">';

// Interactions this week
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i WHERE i.entity = ".$conf->entity." AND i.date_interaction >= '".$db->escape($weekStart)." 00:00:00'".$socFilter;
$resql = $db->query($sql);
$weekCount = ($resql && ($o = $db->fetch_object($resql))) ? $o->cnt : 0;

// Overdue followups
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i WHERE i.entity = ".$conf->entity." AND i.followup_done = 0 AND i.followup_date IS NOT NULL AND i.followup_date < '".$db->escape($today)."'".$socFilter;
$resql = $db->query($sql);
$overdueCount = ($resql && ($o = $db->fetch_object($resql))) ? $o->cnt : 0;

// Max days without contact
if ($socid > 0) {
	$sql = "SELECT DATEDIFF(NOW(), MAX(i.date_interaction)) as days_since FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i WHERE i.entity = ".$conf->entity." AND i.fk_soc = ".(int)$socid;
	$resql = $db->query($sql);
	$maxDays = 0; $maxDaysCompany = '';
	if ($resql && $db->num_rows($resql)) { $o = $db->fetch_object($resql); $maxDays = (int)$o->days_since; }
} else {
	$sql = "SELECT s.nom, DATEDIFF(NOW(), MAX(i.date_interaction)) as days_since FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid WHERE i.entity = ".$conf->entity." AND i.fk_soc > 0 GROUP BY i.fk_soc, s.nom ORDER BY days_since DESC LIMIT 1";
	$resql = $db->query($sql);
	$maxDays = 0; $maxDaysCompany = '';
	if ($resql && $db->num_rows($resql)) { $o = $db->fetch_object($resql); $maxDays = $o->days_since; $maxDaysCompany = $o->nom; }
}

// Open tasks count
$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."projet_task as t INNER JOIN ".MAIN_DB_PREFIX."projet as p ON t.fk_projet = p.rowid WHERE p.entity IN (".getEntity('projet').") AND p.fk_statut = 1 AND (t.progress IS NULL OR t.progress < 100)";
if ($socid > 0) $sql .= " AND p.fk_soc = ".(int)$socid;
$resql = $db->query($sql);
$taskCount = 0;
if ($resql && ($o = $db->fetch_object($resql))) { $taskCount = $o->cnt; }

print '<div class="lemoncrm-stat-card">';
print '<div class="lemoncrm-stat-number">'.$weekCount.'</div>';
print '<div class="lemoncrm-stat-label">Interactions cette semaine</div>';
print '</div>';

print '<div class="lemoncrm-stat-card'.($overdueCount > 0 ? ' lemoncrm-stat-alert' : '').'">';
print '<div class="lemoncrm-stat-number">'.$overdueCount.'</div>';
print '<div class="lemoncrm-stat-label">Relances en retard</div>';
print '</div>';

print '<div class="lemoncrm-stat-card">';
print '<div class="lemoncrm-stat-number">'.$maxDays.'<small style="font-size:0.5em">j</small></div>';
print '<div class="lemoncrm-stat-label">Sans contact';
if ($maxDaysCompany) print '<br><small>'.dol_escape_htmltag(dol_trunc($maxDaysCompany, 25)).'</small>';
print '</div></div>';

print '<div class="lemoncrm-stat-card">';
print '<div class="lemoncrm-stat-number">'.$taskCount.'</div>';
print '<div class="lemoncrm-stat-label">Tâches en cours</div>';
print '</div>';

print '</div>'; // stats-bar

// ==================== RELANCES + IMPAYES (two columns) ====================
print '<div class="fichecenter"><div class="fichethirdleft">';

// --- Pending followups ---
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="5"><span class="fas fa-bell"></span> Relances à faire</th></tr>';

$sql = "SELECT i.rowid, i.ref, i.followup_action, i.followup_date, i.followup_mode, i.interaction_type,";
$sql .= " s.nom as thirdparty_name, i.fk_soc";
$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid";
$sql .= " WHERE i.entity = ".$conf->entity." AND i.followup_done = 0 AND i.followup_date IS NOT NULL".$socFilter;
$sql .= " ORDER BY i.followup_date ASC LIMIT 15";
$resql = $db->query($sql);
if ($resql) {
	$numf = $db->num_rows($resql);
	if ($numf == 0) {
		print '<tr class="oddeven"><td colspan="5" class="opacitymedium">Aucune relance en attente</td></tr>';
	}
	$socHelper = new Societe($db);
	while ($obj = $db->fetch_object($resql)) {
		$rowclass = 'oddeven';
		if ($obj->followup_date < $today) $rowclass = 'oddeven lemoncrm-overdue';
		elseif ($obj->followup_date == $today) $rowclass = 'oddeven lemoncrm-today';

		$icon = $typeIcons[$obj->interaction_type] ?? 'far fa-comment';
		print '<tr class="'.$rowclass.'">';
		print '<td><span class="'.$icon.'" style="color:#9ca3af;margin-right:4px"></span>';
		if ($obj->fk_soc > 0) {
			$socHelper->id = $obj->fk_soc;
			$socHelper->name = $obj->thirdparty_name;
			print $socHelper->getNomUrl(1);
		}
		print '</td>';
		// Formatted date
		print '<td>'.lemoncrm_format_date_fr($obj->followup_date);
		if (!empty($obj->followup_mode)) print ' <small>('.($followup_modes[$obj->followup_mode] ?? '').')</small>';
		print '</td>';
		print '<td style="font-size:0.88em;color:#6b7280">'.dol_trunc(dol_escape_htmltag($obj->followup_action), 40).'</td>';
		// Create task button
		print '<td style="width:20px">';
		if ($obj->fk_soc > 0) {
			$taskUrl = dol_buildpath('/lemoncrm/ajax/create_document.php', 1).'?type=projet&interaction_id='.$obj->rowid.'&token='.newToken();
			print '<a href="'.$taskUrl.'" title="Créer une tâche"><span class="fas fa-tasks" style="color:#6b7280"></span></a>';
		}
		print '</td>';
		// Done button
		print '<td style="width:20px">';
		$doneUrl = $_SERVER["PHP_SELF"].'?action=followupdone&id='.$obj->rowid.'&token='.newToken();
		if ($socid > 0) $doneUrl .= '&socid='.$socid;
		print '<a href="'.$doneUrl.'" title="Marquer comme fait"><span class="fas fa-check" style="color:#38A169"></span></a>';
		print '</td>';
		print '</tr>';
	}
}
print '</table>';

print '</div><div class="fichetwothirdright">';

// --- Open tasks ---
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="5"><span class="fas fa-tasks"></span> Tâches en cours</th></tr>';

$sql = "SELECT t.rowid as task_id, t.ref as task_ref, t.label as task_label, t.dateo as date_start,";
$sql .= " p.ref as project_ref, p.title as project_title,";
$sql .= " s.nom as thirdparty_name, s.rowid as socid";
$sql .= " FROM ".MAIN_DB_PREFIX."projet_task as t";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON t.fk_projet = p.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON p.fk_soc = s.rowid";
$sql .= " WHERE p.entity IN (".getEntity('projet').")";
$sql .= " AND p.fk_statut = 1 AND (t.progress IS NULL OR t.progress < 100)";
if ($socid > 0) $sql .= " AND p.fk_soc = ".(int)$socid;
$sql .= " ORDER BY t.rowid DESC LIMIT 7";
$resql = $db->query($sql);
if ($resql) {
	$numt = $db->num_rows($resql);
	if ($numt == 0) {
		print '<tr class="oddeven"><td colspan="5" class="opacitymedium">Aucune tâche en cours</td></tr>';
	}
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$obj->task_id.'">'.dol_escape_htmltag(dol_trunc($obj->task_label, 30)).'</a></td>';
		print '<td style="color:#6b7280;font-size:0.9em">'.dol_escape_htmltag(dol_trunc($obj->thirdparty_name ?: $obj->project_ref, 20)).'</td>';
		print '<td style="width:20px">';
		print '<a href="'.DOL_URL_ROOT.'/projet/tasks/time.php?id='.$obj->task_id.'&action=createtime" title="Temps consommé"><span class="fas fa-stopwatch" style="color:#6b7280"></span></a>';
		print '</td>';
		print '<td style="width:20px">';
		$closUrl = $_SERVER["PHP_SELF"].'?action=closetask&taskid='.$obj->task_id.'&token='.newToken();
		if ($socid > 0) $closUrl .= '&socid='.$socid;
		print '<a href="'.$closUrl.'" title="Terminer" onclick="return confirm(\'Terminer cette tâche ?\')"><span class="fas fa-check" style="color:#38A169"></span></a>';
		print '</td>';
		print '</tr>';
	}
}
if ($taskCount > 7) {
	print '<tr class="oddeven"><td colspan="5" class="center"><a href="'.DOL_URL_ROOT.'/projet/tasks/list.php?leftmenu=tasks">Voir les '.$taskCount.' tâches</a></td></tr>';
}
print '</table>';

print '</div></div>'; // columns

// ==================== INTERACTIONS LIST WITH ACCORDION ====================
print '<br>';

// Filters
$search_type = GETPOST('search_type', 'alpha');
$search_followup = GETPOST('search_followup', 'alpha');
$search_thirdparty = GETPOST('search_thirdparty', 'alpha');
$search_date_start = GETPOST('search_date_start', 'alpha');
$search_date_end = GETPOST('search_date_end', 'alpha');
$search_summary = GETPOST('search_summary', 'alpha');
$search_direction = GETPOST('search_direction', 'alpha');
$sortfield = GETPOST('sortfield', 'alpha') ?: 'i.date_interaction';
$sortorder = GETPOST('sortorder', 'alpha') ?: 'DESC';
$limit = 30;

// Reset filters
if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha')) {
	$search_type = ''; $search_followup = ''; $search_thirdparty = '';
	$search_date_start = ''; $search_date_end = ''; $search_summary = ''; $search_direction = '';
}

$sql = "SELECT i.rowid, i.ref, i.interaction_type, i.fk_soc, i.fk_socpeople, i.fk_actioncomm, i.fk_user_author,";
$sql .= " i.date_interaction, i.duration_minutes, i.direction, i.summary,";
$sql .= " i.followup_date, i.followup_done, i.followup_action, i.followup_mode,";
$sql .= " i.sentiment, i.prospect_status, i.fk_parent,";
$sql .= " s.nom as thirdparty_name,";
$sql .= " CONCAT(sp.firstname, ' ', sp.lastname) as contact_name,";
$sql .= " CONCAT(u.firstname, ' ', u.lastname) as author_name";
$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON i.fk_socpeople = sp.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON i.fk_user_author = u.rowid";
$sql .= " WHERE i.entity = ".$conf->entity;
if ($socid > 0) $sql .= " AND i.fk_soc = ".(int)$socid;
if (!empty($search_type)) $sql .= " AND i.interaction_type = '".$db->escape($search_type)."'";
if (!empty($search_thirdparty)) $sql .= " AND s.nom LIKE '%".$db->escape($search_thirdparty)."%'";
if (!empty($search_summary)) $sql .= " AND i.summary LIKE '%".$db->escape($search_summary)."%'";
if (!empty($search_direction)) $sql .= " AND i.direction = '".$db->escape($search_direction)."'";
if (!empty($search_date_start)) $sql .= " AND i.date_interaction >= '".$db->escape($search_date_start)." 00:00:00'";
if (!empty($search_date_end)) $sql .= " AND i.date_interaction <= '".$db->escape($search_date_end)." 23:59:59'";
if ($search_followup == 'pending') $sql .= " AND i.followup_done = 0 AND i.followup_date IS NOT NULL";
elseif ($search_followup == 'overdue') $sql .= " AND i.followup_done = 0 AND i.followup_date < '".$db->escape($today)."'";
elseif ($search_followup == 'done') $sql .= " AND i.followup_done = 1";
// Thread grouping: parent first, then children by date
// Thread grouping: group threads together, sort groups by most recent interaction
$threadSortOrder = ($sortorder == 'ASC') ? 'ASC' : 'DESC';
$sql .= " ORDER BY COALESCE(i.fk_parent, i.rowid) ".$threadSortOrder.", i.fk_parent IS NOT NULL ASC, i.date_interaction ASC";
$sql .= " LIMIT ".$limit;

$resql = $db->query($sql);
$num = $resql ? $db->num_rows($resql) : 0;

// Pre-load rows, group threads, reorder for display
$rawRows = array();
$parentsWithChildren = array();
$threads = array(); // parentId => array of rows (parent + children)
$soloRows = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rawRows[] = $obj;
		if ($obj->fk_parent > 0) {
			$parentsWithChildren[$obj->fk_parent] = true;
			$threads[$obj->fk_parent][] = $obj;
		}
	}
}
// Build ordered list: newest first, then older below (hidden)
$allRows = array();
$processedThreads = array();
foreach ($rawRows as $obj) {
	$threadId = $obj->fk_parent > 0 ? $obj->fk_parent : (isset($parentsWithChildren[$obj->rowid]) ? $obj->rowid : 0);
	if ($threadId > 0) {
		if (isset($processedThreads[$threadId])) continue;
		$processedThreads[$threadId] = true;
		$threadRows = array();
		foreach ($rawRows as $r) {
			if ($r->rowid == $threadId) $threadRows[] = $r;
		}
		if (isset($threads[$threadId])) {
			foreach ($threads[$threadId] as $child) {
				$threadRows[] = $child;
			}
		}
		// Newest first, oldest last
		$threadRows = array_reverse($threadRows);
		foreach ($threadRows as $tr) {
			$allRows[] = $tr;
		}
	} else {
		$allRows[] = $obj;
	}
}

// Load dictionaries
$sentimentDict = array();
$sqlsd = "SELECT code, label, color FROM ".MAIN_DB_PREFIX."c_lemoncrm_sentiment WHERE active = 1";
$ressd = $db->query($sqlsd);
if ($ressd) { while ($o = $db->fetch_object($ressd)) $sentimentDict[$o->code] = $o; }

$prospectDict = array();
$sqlpd = "SELECT code, label, color FROM ".MAIN_DB_PREFIX."c_lemoncrm_prospect_status WHERE active = 1";
$respd = $db->query($sqlpd);
if ($respd) { while ($o = $db->fetch_object($respd)) $prospectDict[$o->code] = $o; }

$param = '';
if ($socid > 0) $param .= '&socid='.$socid;
if ($search_type) $param .= '&search_type='.$search_type;
if ($search_thirdparty) $param .= '&search_thirdparty='.urlencode($search_thirdparty);
if ($search_date_start) $param .= '&search_date_start='.$search_date_start;
if ($search_date_end) $param .= '&search_date_end='.$search_date_end;
if ($search_summary) $param .= '&search_summary='.urlencode($search_summary);
if ($search_direction) $param .= '&search_direction='.$search_direction;
if ($search_followup) $param .= '&search_followup='.$search_followup;

$colcount = $socid ? 8 : 9;

// Mass action button (Dolibarr standard)
$arrayofmassactions = array();
if ($user->hasRight('lemoncrm', 'interaction', 'delete')) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').'Supprimer';
}
$massactionbutton = $form->selectMassAction(GETPOST('massaction', 'alpha'), $arrayofmassactions);

// Single POST form for everything
print '<form method="POST" name="formlist" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="massaction" id="massaction" value="'.GETPOST('massaction', 'alpha').'">';
if ($socid > 0) print '<input type="hidden" name="socid" value="'.$socid.'">';

print_barre_liste('Interactions', 0, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $num, 'fa-comments', 0, '', '', 0, 0, 0, 1);

print '<table class="noborder centpercent">';

// Filter row
print '<tr class="liste_titre_filter">';

// Checkbox
print '<td class="liste_titre"></td>';

// Chevron
print '<td class="liste_titre"></td>';

// Tiers filter (only on global dashboard)
if (!$socid) {
	print '<td class="liste_titre">';
	print '<input type="text" name="search_thirdparty" class="flat maxwidth150" value="'.dol_escape_htmltag($search_thirdparty).'" placeholder="Tiers...">';
	print '</td>';
}

// Date filter
print '<td class="liste_titre">';
print '<input type="date" name="search_date_start" class="flat maxwidth100" value="'.dol_escape_htmltag($search_date_start).'">';
print '<br><input type="date" name="search_date_end" class="flat maxwidth100" value="'.dol_escape_htmltag($search_date_end).'">';
print '</td>';

// Type filter
print '<td class="liste_titre">';
print '<select name="search_type" class="flat maxwidth150">';
print '<option value="">--</option>';
foreach ($types as $code => $label) {
	print '<option value="'.$code.'"'.($search_type == $code ? ' selected' : '').'>'.$label.'</option>';
}
print '</select>';
print '</td>';

// Direction filter
print '<td class="liste_titre">';
print '<select name="search_direction" class="flat">';
print '<option value="">--</option>';
print '<option value="IN"'.($search_direction == 'IN' ? ' selected' : '').'>IN</option>';
print '<option value="OUT"'.($search_direction == 'OUT' ? ' selected' : '').'>OUT</option>';
print '</select>';
print '</td>';

// Summary filter
print '<td class="liste_titre">';
print '<input type="text" name="search_summary" class="flat maxwidth200" value="'.dol_escape_htmltag($search_summary).'" placeholder="Recherche...">';
print '</td>';

// Followup filter + search buttons
print '<td class="liste_titre">';
print '<select name="search_followup" class="flat">';
print '<option value="">--</option>';
print '<option value="pending"'.($search_followup == 'pending' ? ' selected' : '').'>À faire</option>';
print '<option value="overdue"'.($search_followup == 'overdue' ? ' selected' : '').'>En retard</option>';
print '<option value="done"'.($search_followup == 'done' ? ' selected' : '').'>Fait</option>';
print '</select>';
print ' <input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"), 'search.png', '', 0, 1).'" title="'.$langs->trans("Search").'">';
print ' <input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("RemoveFilter"), 'searchclear.png', '', 0, 1).'" title="'.$langs->trans("RemoveFilter").'">';
print '</td>';

print '<td class="liste_titre"></td>';

print '</tr>';

// Column headers: Chevron | Tiers | Date | Type | Dir | Message | Relance | Actions
print '<tr class="liste_titre">';
print '<th width="20" class="center">'.$form->showCheckAddButtons('checkforselect', 1).'</th>';
print '<th width="20"></th>';
if (!$socid) print_liste_field_titre('Tiers', $_SERVER["PHP_SELF"], 's.nom', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Date', $_SERVER["PHP_SELF"], 'i.date_interaction', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Type', $_SERVER["PHP_SELF"], 'i.interaction_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Dir.', $_SERVER["PHP_SELF"], 'i.direction', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('', '', '', '', '', '', '', '');
print_liste_field_titre('Relance', $_SERVER["PHP_SELF"], 'i.followup_date', '', $param, '', $sortfield, $sortorder);
print '<th></th>';
print '</tr>';

// Detect first row of each thread (newest, always visible, has chevron)
$threadFirstRow = array();
foreach ($allRows as $obj) {
	$threadId = $obj->fk_parent > 0 ? $obj->fk_parent : (isset($parentsWithChildren[$obj->rowid]) ? $obj->rowid : 0);
	if ($threadId > 0 && !isset($threadFirstRow[$threadId])) {
		$threadFirstRow[$threadId] = $obj->rowid;
	}
}

// Rows
if ($num > 0) {
	$socHelper3 = new Societe($db);
	$interactionHelper = new LemonCRMInteraction($db);
	foreach ($allRows as $obj) {
		$rowclass = 'oddeven';

		$icon = $typeIcons[$obj->interaction_type] ?? 'far fa-comment';
		$typeLabel = $types[$obj->interaction_type] ?? $obj->interaction_type;
		$dirBadge = ($obj->direction == 'IN') ? '<span class="badge badge-status4" style="font-size:0.8em">IN</span>' : '<span class="badge badge-status1" style="font-size:0.8em">OUT</span>';

		$previewSummary = strip_tags($obj->summary);
		$previewSummary = str_replace(array("\\r\\n", "\\n", "\\r", "\r\n", "\r", "\n"), ' ', $previewSummary);
		$previewSummary = preg_replace('/\s+/', ' ', trim($previewSummary));

		$isChild = ($obj->fk_parent > 0);
		$threadRoot = $isChild ? $obj->fk_parent : $obj->rowid;
		$hasChildren = isset($parentsWithChildren[$obj->rowid]);

		// Thread membership
		$threadId = $isChild ? $obj->fk_parent : ($hasChildren ? $obj->rowid : 0);
		$isFirstOfThread = ($threadId > 0 && isset($threadFirstRow[$threadId]) && $threadFirstRow[$threadId] == $obj->rowid);
		$isOlderInThread = ($threadId > 0 && !$isFirstOfThread);

		// Row classes
		$trClass = $rowclass;
		if ($isOlderInThread) {
			$trClass .= ' lcrm-older-of-'.$threadId;
		}
		// Hidden by default if not the first (newest) of thread
		$trStyle = $isOlderInThread ? ' style="display:none"' : '';
		print '<tr class="'.$trClass.'"'.$trStyle.'>';

		// Checkbox
		print '<td class="nowrap center" style="vertical-align:middle"><input type="checkbox" class="flat checkforselect" name="toselect[]" value="'.$obj->rowid.'"></td>';

		// Chevron: on the first (newest) row of a thread
		print '<td class="lcrm-expand-cell'.($isFirstOfThread ? ' lcrm-toggle-cell' : '').'"'.($isFirstOfThread ? ' data-thread="'.$threadId.'"' : '').'>';
		if ($isFirstOfThread) {
			print '<span class="fa fa-chevron-right lcrm-chevron" id="ch-'.$threadId.'"></span>';
		}
		print '</td>';

		// Tiers
		if (!$socid) {
			print '<td>';
			if (!$isOlderInThread && $obj->fk_soc > 0) {
				$socHelper3->id = $obj->fk_soc;
				$socHelper3->name = $obj->thirdparty_name;
				print $socHelper3->getNomUrl(1);
			}
			print '</td>';
		}

		// Date: "Lundi 30 mars" + heure a la ligne
		$dateTs = $db->jdate($obj->date_interaction);
		$dateFr = lemoncrm_format_date_fr($dateTs, 'long');
		$dateObj = new DateTime();
		$dateObj->setTimestamp($dateTs);
		$timeStr = $dateObj->format('H:i');
		print '<td>';
		print '<span style="white-space:nowrap">'.$dateFr.'</span>';
		print '<br><span style="color:#9ca3af;font-size:0.85em">'.$timeStr.'</span>';
		print '</td>';

		// Type (icon) - bigger
		print '<td style="text-align:center"><span class="'.$icon.'" style="color:#6b7280;font-size:1.15em" title="'.dol_escape_htmltag($typeLabel).'"></span></td>';

		// Direction
		print '<td>'.$dirBadge.'</td>';

		// Message: extrait cliquable → modale
		$interactionHelper->id = $obj->rowid;
		$interactionHelper->ref = $obj->ref;

		// Build modal content as hidden div
		$modalId = 'lcrm-modal-'.$obj->rowid;
		$cleanSummary = $obj->summary;
		$cleanSummary = str_replace(array("\\r\\n", "\\n", "\\r"), "\n", $cleanSummary);
		$cleanSummary = trim($cleanSummary);
		// If it looks like HTML (from DolEditor), keep it; otherwise nl2br
		if (strip_tags($cleanSummary) == $cleanSummary) {
			$modalSummary = nl2br(dol_escape_htmltag($cleanSummary));
		} else {
			$modalSummary = dol_htmlwithnojs($cleanSummary);
		}

		print '<td class="lcrm-msg-cell lcrm-modal-trigger" data-modal="'.$modalId.'" style="cursor:pointer">';
		print dol_trunc(dol_escape_htmltag($previewSummary), 60);
		print ' <span class="fas fa-info-circle" style="color:#c4c4c4;font-size:0.8em"></span>';
		print '</td>';

		// Relance column
		print '<td>';
		if (!empty($obj->followup_date)) {
			$interactionHelper->followup_done = $obj->followup_done;
			$interactionHelper->followup_date = $obj->followup_date;
			// Formatted date
			print lemoncrm_format_date_fr($obj->followup_date);
			print '<br>'.$interactionHelper->getFollowupBadge();
			if (!$obj->followup_done && $user->hasRight('lemoncrm', 'interaction', 'write')) {
				$doneUrl = $_SERVER["PHP_SELF"].'?action=followupdone&id='.$obj->rowid.'&token='.newToken();
				if ($socid > 0) $doneUrl .= '&socid='.$socid;
				print ' <a href="'.$doneUrl.'" title="Marquer comme fait" onclick="event.stopPropagation()"><span class="fas fa-check" style="color:#38A169;font-size:0.8em"></span></a>';
			}
		}
		print '</td>';

		// Reply button
		print '<td style="text-align:center">';
		if ($user->hasRight('lemoncrm', 'interaction', 'write')) {
			print '<a href="#" class="lcrm-chain-btn" title="Enchaîner" onclick="event.stopPropagation();lcrm_open_drawer(null, '.((int)$obj->fk_soc).', 0, '.((int)$threadRoot).');return false;"><span class="fas fa-reply"></span></a>';
		}
		print '</td>';

		print '</tr>';

		// Hidden modal content for this interaction
		print '<div class="lcrm-modal-content" id="'.$modalId.'" style="display:none">';
		// Header
		print '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">';
		print '<span class="'.$icon.'" style="font-size:1.3em;color:#6b7280"></span>';
		print '<strong style="font-size:1.1em">'.$typeLabel.'</strong> '.$dirBadge;
		if (!empty($obj->contact_name) && trim($obj->contact_name)) {
			print ' <span style="color:#6b7280">· <span class="fas fa-user" style="margin-right:2px"></span>'.dol_escape_htmltag(trim($obj->contact_name)).'</span>';
		}
		if ($obj->duration_minutes > 0) {
			print ' <span style="color:#6b7280">· '.$obj->duration_minutes.' min</span>';
		}
		print '</div>';
		// Date
		print '<div style="color:#9ca3af;font-size:0.9em;margin-bottom:12px">';
		print lemoncrm_format_date_fr($db->jdate($obj->date_interaction), 'long');
		$dtObj = new DateTime(); $dtObj->setTimestamp($db->jdate($obj->date_interaction));
		print ' à '.$dtObj->format('H:i');
		print ' · <span style="color:#9ca3af">'.$obj->ref.'</span>';
		if (!empty($obj->author_name) && trim($obj->author_name)) {
			print ' · <span style="color:#9ca3af"><span class="fas fa-user-edit" style="margin-right:2px"></span>'.dol_escape_htmltag(trim($obj->author_name)).'</span>';
		}
		print '</div>';
		// Summary
		if (!empty($modalSummary)) {
			print '<div style="margin-bottom:12px;line-height:1.6">'.$modalSummary.'</div>';
		}
		// Tags
		$tags = array();
		if (!empty($obj->sentiment) && isset($sentimentDict[$obj->sentiment])) {
			$s = $sentimentDict[$obj->sentiment];
			$tags[] = '<span class="lcrm-tag" style="--tag-color:'.$s->color.'">'.$s->label.'</span>';
		}
		if (!empty($obj->prospect_status) && isset($prospectDict[$obj->prospect_status])) {
			$p = $prospectDict[$obj->prospect_status];
			$tags[] = '<span class="lcrm-tag" style="--tag-color:'.$p->color.'">'.$p->label.'</span>';
		}
		if ($tags) print '<div style="margin-bottom:12px">'.implode(' ', $tags).'</div>';
		// Followup
		if (!empty($obj->followup_action) || !empty($obj->followup_date)) {
			print '<div style="padding:8px 12px;background:#FFFBEB;border-radius:6px;margin-bottom:12px;font-size:0.9em">';
			print '<span class="fas fa-bell" style="color:#92400E;margin-right:4px"></span>';
			if (!empty($obj->followup_action)) print dol_escape_htmltag($obj->followup_action).' ';
			if (!empty($obj->followup_date)) {
				print lemoncrm_format_date_fr($obj->followup_date);
				if (!empty($obj->followup_mode)) print ' ('.($followup_modes[$obj->followup_mode] ?? $obj->followup_mode).')';
			}
			print '</div>';
		}
		// Action links - row 1: business actions (only if thirdparty set)
		if ($obj->fk_soc > 0) {
			print '<div style="display:flex;gap:20px;align-items:center;border-top:1px solid #e5e7eb;padding-top:12px;font-size:1em">';
			$createUrl = dol_buildpath('/lemoncrm/ajax/create_document.php', 1).'?interaction_id='.$obj->rowid.'&token='.newToken();
			if ($user->hasRight('propal', 'creer')) {
				print '<a href="'.$createUrl.'&type=propal" style="color:#374151;text-decoration:none;font-weight:500"><span class="fas fa-file-signature" style="margin-right:5px;color:#6b7280"></span>Devis</a>';
			}
			if ($user->hasRight('facture', 'creer')) {
				print '<a href="'.$createUrl.'&type=facture" style="color:#374151;text-decoration:none;font-weight:500"><span class="fas fa-file-invoice-dollar" style="margin-right:5px;color:#6b7280"></span>Facture</a>';
			}
			if ($user->hasRight('projet', 'creer')) {
				print '<a href="'.$createUrl.'&type=projet" style="color:#374151;text-decoration:none;font-weight:500"><span class="fas fa-project-diagram" style="margin-right:5px;color:#6b7280"></span>Tâche projet</a>';
			}
			if ($user->hasRight('projet', 'time')) {
				print '<a href="#" class="lcrm-timespent-btn" data-id="'.$obj->rowid.'" data-socid="'.$obj->fk_soc.'" data-minutes="'.(int)$obj->duration_minutes.'" style="color:#374151;text-decoration:none;font-weight:500"><span class="fas fa-stopwatch" style="margin-right:5px;color:#6b7280"></span>Temps consommé</a>';
			}
			print '</div>';
		}
		// Action links - row 2: CRM actions
		print '<div style="display:flex;gap:16px;align-items:center;border-top:1px solid #f3f4f6;padding-top:8px;margin-top:8px;font-size:0.85em">';
		if ($user->hasRight('lemoncrm', 'interaction', 'write')) {
			print '<a href="'.dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$obj->rowid.'&action=edit&popup=1" onclick="lcrm_open_drawer_url(this.href);return false;" style="color:#6b7280;text-decoration:none"><span class="fas fa-pencil-alt" style="margin-right:4px"></span>Modifier</a>';
			print '<a href="#" onclick="lcrm_open_drawer(null, '.((int)$obj->fk_soc).', 0, '.((int)$threadRoot).');$(\'.lcrm-modal-overlay\').fadeOut(150);return false;" style="color:#6b7280;text-decoration:none"><span class="fas fa-reply" style="margin-right:4px"></span>Enchaîner</a>';
		}
		if ($obj->fk_actioncomm > 0) {
			print '<a href="'.DOL_URL_ROOT.'/comm/action/card.php?id='.$obj->fk_actioncomm.'" style="color:#6b7280;text-decoration:none"><span class="fas fa-calendar-alt" style="margin-right:4px"></span>Agenda</a>';
		}
		if ($obj->fk_soc > 0) {
			print '<a href="#" class="lcrm-attach-btn" data-id="'.$obj->rowid.'" data-socid="'.$obj->fk_soc.'" style="color:#6b7280;text-decoration:none"><span class="fas fa-link" style="margin-right:4px"></span>Rattacher</a>';
		}
		if ($user->hasRight('lemoncrm', 'interaction', 'delete')) {
			$delUrl = $_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'&token='.newToken();
			if ($socid > 0) $delUrl .= '&socid='.$socid;
			print '<a href="'.$delUrl.'" onclick="return confirm(\'Supprimer cette interaction ?\')" style="color:#ef4444;text-decoration:none;margin-left:auto"><span class="fas fa-trash-alt" style="margin-right:4px"></span>Supprimer</a>';
		}
		print '</div>';
		print '</div>';
	}
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="'.$colcount.'" class="opacitymedium">Aucune interaction</td></tr>';
}

print '</table>';
print '</form>';

// Modal overlay
print '<div class="lcrm-modal-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:none;justify-content:center;align-items:center">';
print '<div class="lcrm-modal-box" style="background:#fff;border-radius:10px;padding:24px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.15)">';
print '<div id="lcrm-modal-body"></div>';
print '</div>';
print '</div>';

// JS
print '<script>
$(function() {
	// Open modal on message click
	$(document).on("click", ".lcrm-modal-trigger", function(e) {
		if ($(e.target).closest("a,input").length) return;
		e.stopPropagation();
		var modalId = $(this).data("modal");
		var content = $("#" + modalId).html();
		if (content) {
			$("#lcrm-modal-body").html(content);
			$(".lcrm-modal-overlay").css("display", "flex").hide().fadeIn(150);
		}
	});

	// Close modal on overlay click (outside the box)
	$(".lcrm-modal-overlay").on("click", function(e) {
		if ($(e.target).hasClass("lcrm-modal-overlay")) {
			$(this).fadeOut(150);
		}
	});

	// Helper to open drawer from modal
	window.lcrm_open_drawer_url = function(url) {
		$(".lcrm-modal-overlay").fadeOut(150);
		var w = 560, h = Math.min(750, screen.availHeight - 50);
		var left = screen.availWidth - w - 10;
		window.open(url, "lcrm_popup", "width=" + w + ",height=" + h + ",left=" + left + ",top=30,resizable=yes,scrollbars=yes");
	};

	// Click on first row of thread: toggle older interactions below
	$(document).on("click", "tr:has(.lcrm-toggle-cell)", function(e) {
		if ($(e.target).closest("a,input,.lcrm-modal-trigger").length) return;
		var threadId = $(this).find(".lcrm-toggle-cell").data("thread");
		if (!threadId) return;
		var $older = $(".lcrm-older-of-" + threadId);
		var $chev = $("#ch-" + threadId);
		if ($chev.hasClass("fa-chevron-down")) {
			$older.slideUp(150);
			$chev.removeClass("fa-chevron-down").addClass("fa-chevron-right");
			$older.removeClass("lcrm-thread-open");
			$(this).removeClass("lcrm-thread-open");
		} else {
			$older.slideDown(150);
			$chev.removeClass("fa-chevron-right").addClass("fa-chevron-down");
			$older.addClass("lcrm-thread-open");
			$(this).addClass("lcrm-thread-open");
		}
	});


	// Reload page when interaction saved from popup
	$(window).on("message", function(e) {
		if (e.originalEvent.data === "lcrm_saved") {
			window.location.reload();
		}
	});

	// Rattacher à un thread
	$(document).on("click", ".lcrm-attach-btn", function(e) {
		e.preventDefault();
		e.stopPropagation();
		var id = $(this).data("id");
		var socid = $(this).data("socid");
		var $btn = $(this);

		// Charger les interactions du tiers
		$.get("'.dol_buildpath('/lemoncrm/ajax/link_interaction.php', 1).'", {action: "list", socid: socid, exclude: id}, function(data) {
			if (!data || !data.length) {
				alert("Aucune autre interaction pour ce tiers");
				return;
			}
			// Construire la liste dans la modale
			var html = "<div style=\"margin-top:12px;border-top:1px solid #e5e7eb;padding-top:12px\">";
			html += "<strong>Rattacher à :</strong>";
			html += "<div style=\"max-height:200px;overflow-y:auto;margin-top:8px\">";
			$.each(data, function(i, item) {
				html += "<a href=\"#\" class=\"lcrm-attach-choice\" data-parent=\"" + item.id + "\" data-child=\"" + id + "\" style=\"display:block;padding:8px 10px;border-bottom:1px solid #f3f4f6;text-decoration:none;color:#374151\">";
				html += "<strong>" + item.date + "</strong> " + item.type + " <span style=\"color:#9ca3af\">" + item.direction + "</span>";
				if (item.summary) html += "<br><span style=\"color:#6b7280;font-size:0.9em\">" + item.summary + "</span>";
				html += "</a>";
			});
			html += "</div></div>";
			$btn.closest("div").after(html);
		}, "json");
	});

	// Temps consommé : afficher les tâches du tiers
	$(document).on("click", ".lcrm-timespent-btn", function(e) {
		e.preventDefault();
		e.stopPropagation();
		var socid = $(this).data("socid");
		var minutes = $(this).data("minutes");
		var $btn = $(this);

		$.get("'.dol_buildpath('/lemoncrm/ajax/link_interaction.php', 1).'", {action: "tasks", socid: socid}, function(data) {
			if (!data || !data.length) {
				alert("Aucune tâche ouverte pour ce tiers. Créez d\'abord un projet.");
				return;
			}
			var html = "<div style=\"margin-top:12px;border-top:1px solid #e5e7eb;padding-top:12px\">";
			html += "<strong>Saisir du temps sur :</strong>";
			html += "<input type=\"text\" class=\"flat\" id=\"lcrm-task-filter\" placeholder=\"Filtrer...\" style=\"width:100%;padding:6px 8px;margin:8px 0;border:1px solid #e5e7eb;border-radius:4px;font-size:0.9em\">";
			html += "<div style=\"max-height:200px;overflow-y:auto\" id=\"lcrm-task-list\">";
			$.each(data, function(i, item) {
				var url = "'.DOL_URL_ROOT.'/projet/tasks/time.php?id=" + item.task_id + "&action=createtime";
				var label = item.project_ref + " " + item.project_title + " " + item.task_ref + " " + item.task_label;
				html += "<a href=\"" + url + "\" class=\"lcrm-task-item\" data-search=\"" + label.toLowerCase() + "\" style=\"display:block;padding:8px 10px;border-bottom:1px solid #f3f4f6;text-decoration:none;color:#374151\">";
				html += "<strong>" + item.project_ref + "</strong> " + item.project_title;
				html += "<br><span style=\"color:#6b7280;font-size:0.9em\">" + item.task_ref + " - " + item.task_label + "</span>";
				if (minutes > 0) html += " <span style=\"color:#9ca3af;font-size:0.85em\">(" + minutes + " min)</span>";
				html += "</a>";
			});
			html += "</div></div>";
			$btn.closest("div").after(html);
		}, "json");
	});

	// Filtre tâches temps consommé
	$(document).on("input", "#lcrm-task-filter", function() {
		var q = $(this).val().toLowerCase();
		$(".lcrm-task-item").each(function() {
			$(this).toggle($(this).data("search").indexOf(q) !== -1);
		});
	});

	// Clic sur une interaction pour rattacher
	$(document).on("click", ".lcrm-attach-choice", function(e) {
		e.preventDefault();
		var parentId = $(this).data("parent");
		var childId = $(this).data("child");
		$.get("'.dol_buildpath('/lemoncrm/ajax/link_interaction.php', 1).'", {action: "attach", id: childId, parent_id: parentId, token: "'.newToken().'"}, function(data) {
			if (data.success) {
				window.location.reload();
			} else {
				alert(data.error || "Erreur");
			}
		}, "json");
	});
});
</script>';

llxFooter();
$db->close();
