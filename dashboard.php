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

// Delete action
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
$typeIcons = array(
	'AC_TEL' => 'fas fa-phone-alt', 'AC_EMAIL' => 'fas fa-envelope',
	'AC_LINKEDIN' => 'fas fa-share-alt', 'AC_TEAMS' => 'fas fa-video',
	'AC_RDV' => 'far fa-calendar-check', 'AC_MEETING_INPERSON' => 'fas fa-users',
	'AC_OTH' => 'far fa-comment',
);
$followup_modes = lemoncrm_get_followup_modes();

// SQL filter for socid
$socFilter = ($socid > 0) ? " AND i.fk_soc = ".(int)$socid : "";
$socFilterF = ($socid > 0) ? " AND f.fk_soc = ".(int)$socid : "";

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

// Overdue invoices count
$sql = "SELECT COUNT(*) as cnt, SUM(f.total_ttc) as total FROM ".MAIN_DB_PREFIX."facture as f WHERE f.entity = ".$conf->entity." AND f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement < '".$db->escape($today)."'".$socFilterF;
$resql = $db->query($sql);
$invoiceCount = 0; $invoiceTotal = 0;
if ($resql && ($o = $db->fetch_object($resql))) { $invoiceCount = $o->cnt; $invoiceTotal = $o->total; }

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

print '<div class="lemoncrm-stat-card'.($invoiceCount > 0 ? ' lemoncrm-stat-alert' : '').'">';
print '<div class="lemoncrm-stat-number">'.$invoiceCount.'</div>';
print '<div class="lemoncrm-stat-label">Factures impayees';
if ($invoiceTotal > 0) print '<br><small>'.price($invoiceTotal).' &euro;</small>';
print '</div></div>';

print '</div>'; // stats-bar

// ==================== RELANCES + IMPAYES (two columns) ====================
print '<div class="fichecenter"><div class="fichethirdleft">';

// --- Pending followups ---
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="3"><span class="fas fa-bell"></span> Relances a faire</th></tr>';

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
		print '<tr class="oddeven"><td colspan="3" class="opacitymedium">Aucune relance en attente</td></tr>';
	}
	while ($obj = $db->fetch_object($resql)) {
		$rowclass = 'oddeven';
		if ($obj->followup_date < $today) $rowclass = 'oddeven lemoncrm-overdue';
		elseif ($obj->followup_date == $today) $rowclass = 'oddeven lemoncrm-today';

		$icon = $typeIcons[$obj->interaction_type] ?? 'far fa-comment';
		print '<tr class="'.$rowclass.'">';
		print '<td><span class="'.$icon.'" style="color:#9ca3af;margin-right:4px"></span>';
		if ($obj->fk_soc > 0) {
			$soc = new Societe($db);
			$soc->id = $obj->fk_soc;
			$soc->name = $obj->thirdparty_name;
			print $soc->getNomUrl(1);
		}
		print '</td>';
		print '<td>'.$obj->followup_date;
		if (!empty($obj->followup_mode)) print ' <small>('.($followup_modes[$obj->followup_mode] ?? '').')</small>';
		print '</td>';
		print '<td style="font-size:0.88em;color:#6b7280">'.dol_trunc(dol_escape_htmltag($obj->followup_action), 40).'</td>';
		print '</tr>';
	}
}
print '</table>';

print '</div><div class="fichetwothirdright">';

// --- Overdue invoices ---
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="4"><span class="fas fa-exclamation-triangle"></span> Factures impayees</th></tr>';

$sql = "SELECT f.rowid, f.ref, f.total_ttc, f.date_lim_reglement,";
$sql .= " s.nom as thirdparty_name, s.rowid as socid,";
$sql .= " DATEDIFF(NOW(), f.date_lim_reglement) as days_overdue";
$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
$sql .= " WHERE f.entity = ".$conf->entity." AND f.paye = 0 AND f.fk_statut = 1 AND f.date_lim_reglement < '".$db->escape($today)."'".$socFilterF;
$sql .= " ORDER BY days_overdue DESC LIMIT 15";
$resql = $db->query($sql);
if ($resql) {
	$numi = $db->num_rows($resql);
	if ($numi == 0) {
		print '<tr class="oddeven"><td colspan="4" class="opacitymedium">Aucune facture impayee</td></tr>';
	}
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$obj->rowid.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
		$soc = new Societe($db);
		$soc->id = $obj->socid;
		$soc->name = $obj->thirdparty_name;
		print '<td>'.$soc->getNomUrl(1).'</td>';
		print '<td class="right nowraponall">'.price($obj->total_ttc).' &euro;</td>';
		print '<td><span class="badge badge-status8">'.$obj->days_overdue.'j</span></td>';
		print '</tr>';
	}
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

$sql = "SELECT i.rowid, i.ref, i.interaction_type, i.fk_soc, i.fk_socpeople,";
$sql .= " i.date_interaction, i.duration_minutes, i.direction, i.summary,";
$sql .= " i.followup_date, i.followup_done, i.followup_action, i.followup_mode,";
$sql .= " i.sentiment, i.prospect_status,";
$sql .= " s.nom as thirdparty_name,";
$sql .= " CONCAT(sp.firstname, ' ', sp.lastname) as contact_name";
$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON i.fk_socpeople = sp.rowid";
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
$sql .= $db->order($sortfield, $sortorder);
$sql .= " LIMIT ".$limit;

$resql = $db->query($sql);
$num = $resql ? $db->num_rows($resql) : 0;

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

$colcount = $socid ? 6 : 7;

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
if ($socid > 0) print '<input type="hidden" name="socid" value="'.$socid.'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="'.$colcount.'"><span class="fas fa-comments"></span> Interactions ('.$num.')</th></tr>';

// Filter row
print '<tr class="liste_titre_filter">';

// Chevron
print '<td class="liste_titre"></td>';

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

// Thirdparty filter (only on global dashboard)
if (!$socid) {
	print '<td class="liste_titre">';
	print '<input type="text" name="search_thirdparty" class="flat maxwidth150" value="'.dol_escape_htmltag($search_thirdparty).'" placeholder="Tiers...">';
	print '</td>';
}

// Summary filter
print '<td class="liste_titre">';
print '<input type="text" name="search_summary" class="flat maxwidth200" value="'.dol_escape_htmltag($search_summary).'" placeholder="Recherche...">';
print '</td>';

// Followup filter
print '<td class="liste_titre">';
print '<select name="search_followup" class="flat">';
print '<option value="">--</option>';
print '<option value="pending"'.($search_followup == 'pending' ? ' selected' : '').'>A faire</option>';
print '<option value="overdue"'.($search_followup == 'overdue' ? ' selected' : '').'>En retard</option>';
print '<option value="done"'.($search_followup == 'done' ? ' selected' : '').'>Fait</option>';
print '</select>';
print '</td>';

// Search + reset buttons
print '<td class="liste_titre center" style="min-width:60px">';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"), 'search.png', '', 0, 1).'" title="'.$langs->trans("Search").'">';
print ' <input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("RemoveFilter"), 'searchclear.png', '', 0, 1).'" title="'.$langs->trans("RemoveFilter").'">';
print '</td>';

print '</tr>';

// Column headers (sortable)
print '<tr class="liste_titre">';
print '<th width="20"></th>';
print_liste_field_titre('Date', $_SERVER["PHP_SELF"], 'i.date_interaction', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Type', $_SERVER["PHP_SELF"], 'i.interaction_type', '', $param, '', $sortfield, $sortorder);
if (!$socid) print_liste_field_titre('Tiers', $_SERVER["PHP_SELF"], 's.nom', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('', '', '', '', '', '', '', '');
print_liste_field_titre('Relance', $_SERVER["PHP_SELF"], 'i.followup_date', '', $param, '', $sortfield, $sortorder);
print '<th></th>';
print '</tr>';

// Rows
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rowclass = 'oddeven';
		if (!$obj->followup_done && !empty($obj->followup_date)) {
			if ($obj->followup_date < $today) $rowclass = 'oddeven lemoncrm-overdue';
			elseif ($obj->followup_date == $today) $rowclass = 'oddeven lemoncrm-today';
		}
		if ($obj->followup_done) $rowclass = 'oddeven lemoncrm-done';

		$icon = $typeIcons[$obj->interaction_type] ?? 'far fa-comment';
		$typeLabel = $types[$obj->interaction_type] ?? $obj->interaction_type;
		$dirBadge = ($obj->direction == 'IN') ? '<span class="badge badge-status4" style="font-size:0.7em">IN</span>' : '<span class="badge badge-status1" style="font-size:0.7em">OUT</span>';

		$previewSummary = str_replace(array("\r\n", "\r", "\n"), ' ', $obj->summary);
		$previewSummary = preg_replace('/\\\\[rn]/', ' ', $previewSummary);
		$previewSummary = preg_replace('/\s+/', ' ', trim($previewSummary));

		// Main row
		print '<tr class="'.$rowclass.' lcrm-row-toggle" data-target="lcrm-d-'.$obj->rowid.'" style="cursor:pointer">';
		print '<td class="lcrm-expand-cell"><span class="fa fa-chevron-right lcrm-chevron" id="ch-'.$obj->rowid.'"></span></td>';
		print '<td style="white-space:nowrap">'.dol_print_date($db->jdate($obj->date_interaction), 'dayhour').'</td>';
		print '<td><span class="'.$icon.'" style="margin-right:4px;color:#6b7280"></span>'.$typeLabel.' '.$dirBadge.'</td>';
		if (!$socid) {
			print '<td>';
			if ($obj->fk_soc > 0) {
				$soc = new Societe($db);
				$soc->id = $obj->fk_soc;
				$soc->name = $obj->thirdparty_name;
				print $soc->getNomUrl(1);
			}
			print '</td>';
		}
		print '<td class="tdoverflowmax300" style="color:#6b7280;font-size:0.88em">'.dol_trunc(dol_escape_htmltag($previewSummary), 70).'</td>';
		print '<td>';
		if (!empty($obj->followup_date)) {
			$interaction = new LemonCRMInteraction($db);
			$interaction->followup_done = $obj->followup_done;
			$interaction->followup_date = $obj->followup_date;
			print $obj->followup_date.' '.$interaction->getFollowupBadge();
		}
		print '</td>';
		print '<td></td>'; // actions column (for filter buttons alignment)
		print '</tr>';

		// Detail row
		print '<tr class="lcrm-detail-row" id="lcrm-d-'.$obj->rowid.'" style="display:none">';
		print '<td colspan="'.$colcount.'"><div class="lcrm-detail-content">';

		// Left: summary + tags
		print '<div class="lcrm-detail-left">';
		if (!empty($obj->summary)) {
			print '<div class="lcrm-detail-summary">'.nl2br(htmlspecialchars(trim($obj->summary), ENT_QUOTES, 'UTF-8')).'</div>';
		}
		$tags = array();
		if (!empty($obj->sentiment) && isset($sentimentDict[$obj->sentiment])) {
			$s = $sentimentDict[$obj->sentiment];
			$tags[] = '<span class="lcrm-tag" style="--tag-color:'.$s->color.'">'.$s->label.'</span>';
		}
		if (!empty($obj->prospect_status) && isset($prospectDict[$obj->prospect_status])) {
			$p = $prospectDict[$obj->prospect_status];
			$tags[] = '<span class="lcrm-tag" style="--tag-color:'.$p->color.'">'.$p->label.'</span>';
		}
		if ($tags) print '<div class="lcrm-detail-tags">'.implode(' ', $tags).'</div>';
		print '</div>';

		// Right: ref + actions + followup
		print '<div class="lcrm-detail-right">';
		$interaction = new LemonCRMInteraction($db);
		$interaction->id = $obj->rowid;
		$interaction->ref = $obj->ref;
		print '<div class="lcrm-detail-ref">'.$interaction->getNomUrl(1);
		if ($obj->duration_minutes > 0) print ' &middot; '.$obj->duration_minutes.'min';
		if (!empty($obj->contact_name) && trim($obj->contact_name)) print ' &middot; '.dol_escape_htmltag(trim($obj->contact_name));
		if ($user->hasRight('lemoncrm', 'interaction', 'write')) {
			print ' <a href="'.dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$obj->rowid.'&action=edit" title="Modifier"><span class="fas fa-pencil-alt" style="color:#6b7280;font-size:0.85em"></span></a>';
		}
		if ($user->hasRight('lemoncrm', 'interaction', 'delete')) {
			$delUrl = $_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'&token='.newToken();
			if ($socid > 0) $delUrl .= '&socid='.$socid;
			print ' <a href="'.$delUrl.'" title="Supprimer" onclick="return confirm(\'Supprimer ?\')"><span class="fas fa-trash-alt" style="color:#ef4444;font-size:0.85em"></span></a>';
		}
		print '</div>';

		if (!empty($obj->followup_action) || !empty($obj->followup_date)) {
			print '<div class="lcrm-detail-followup">';
			print '<strong><span class="fas fa-bell"></span> Suivi</strong><br>';
			if (!empty($obj->followup_action)) print htmlspecialchars($obj->followup_action, ENT_QUOTES, 'UTF-8').'<br>';
			if (!empty($obj->followup_date)) {
				print $obj->followup_date;
				if (!empty($obj->followup_mode)) print ' ('.($followup_modes[$obj->followup_mode] ?? $obj->followup_mode).')';
			}
			print '</div>';
		}
		print '</div>';

		print '</div></td></tr>';
	}
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="'.$colcount.'" class="opacitymedium">Aucune interaction</td></tr>';
}

print '</table>';
print '</form>';

// Accordion JS
print '<script>
$(function() {
	$(".lcrm-row-toggle").click(function(e) {
		if ($(e.target).closest("a").length) return;
		var target = $(this).data("target");
		var $detail = $("#" + target);
		var $chev = $("#ch-" + target.replace("lcrm-d-", ""));
		$detail.slideToggle(150);
		$chev.toggleClass("open");
	});
});
</script>';

llxFooter();
$db->close();
