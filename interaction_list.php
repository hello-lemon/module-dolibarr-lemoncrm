<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * Interaction list with filters
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
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
dol_include_once('/lemoncrm/class/lemoncrm_interaction.class.php');
dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');

$langs->loadLangs(array("lemoncrm@lemoncrm", "companies"));

if (!$user->hasRight('lemoncrm', 'interaction', 'read')) {
	accessforbidden();
}

// Filters
$socid = GETPOSTINT('socid');
$contactid = GETPOSTINT('contactid');
$search_type = GETPOST('search_type', 'alpha');
$search_followup = GETPOST('search_followup', 'alpha');
$search_date_start = GETPOST('search_date_start', 'alpha');
$search_date_end = GETPOST('search_date_end', 'alpha');

// Sort
$sortfield = GETPOST('sortfield', 'alpha') ?: 'i.date_interaction';
$sortorder = GETPOST('sortorder', 'alpha') ?: 'DESC';

// Pagination
$limit = GETPOSTINT('limit') ?: 25;
$page = GETPOSTINT('page') ?: 0;
$offset = $limit * $page;

// Reset filters
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_type = '';
	$search_followup = '';
	$search_date_start = '';
	$search_date_end = '';
	if (!$socid && !$contactid) {
		// Keep socid/contactid if tab context
	}
}

/*
 * Actions
 */

// Delete
if (GETPOST('action', 'alpha') == 'delete' && $user->hasRight('lemoncrm', 'interaction', 'delete')) {
	$delId = GETPOSTINT('id');
	if ($delId > 0) {
		$delObj = new LemonCRMInteraction($db);
		if ($delObj->fetch($delId) > 0) {
			$result = $delObj->delete($user);
			if ($result > 0) {
				setEventMessages('Interaction supprimee', null, 'mesgs');
			} else {
				setEventMessages($delObj->error, $delObj->errors, 'errors');
			}
		}
		// Redirect to clean URL
		$redirectUrl = $_SERVER["PHP_SELF"];
		$params = array();
		if ($socid > 0) $params[] = 'socid='.$socid;
		if ($contactid > 0) $params[] = 'contactid='.$contactid;
		if ($params) $redirectUrl .= '?'.implode('&', $params);
		header("Location: ".$redirectUrl);
		exit;
	}
}

/*
 * Display
 */

// If called from thirdparty tab, show thirdparty header
$thirdparty = null;
$contactobj = null;
if ($socid > 0 && !$contactid) {
	$thirdparty = new Societe($db);
	$thirdparty->fetch($socid);
}
if ($contactid > 0) {
	$contactobj = new Contact($db);
	$contactobj->fetch($contactid);
	if (empty($socid) && $contactobj->socid > 0) {
		$socid = $contactobj->socid;
	}
}

$title = $langs->trans('Interactions');
if ($thirdparty) {
	$title .= ' - '.$thirdparty->name;
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lemoncrm page-list');

// Thirdparty card header with tabs if in tab context
if ($thirdparty) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
	$head = societe_prepare_head($thirdparty);
	print dol_get_fiche_head($head, 'lemoncrm', $langs->trans("ThirdParty"), -1, 'company');
	dol_banner_tab($thirdparty, 'socid', '', ($user->socid ? 0 : 1), 'rowid', 'nom');
	print dol_get_fiche_end();
}

if ($contactobj && !$thirdparty) {
	require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
	$head = contact_prepare_head($contactobj);
	print dol_get_fiche_head($head, 'lemoncrm', $langs->trans("ContactAddress"), -1, 'contact');
	dol_banner_tab($contactobj, 'contactid', '', 1, 'rowid', 'name');
	print dol_get_fiche_end();
}

$types = lemoncrm_get_interaction_types();

// Build SQL
$sql = "SELECT i.rowid, i.ref, i.interaction_type, i.fk_soc, i.fk_socpeople,";
$sql .= " i.date_interaction, i.duration_minutes, i.direction, i.summary,";
$sql .= " i.followup_date, i.followup_done, i.followup_mode, i.sentiment,";
$sql .= " i.prospect_status,";
$sql .= " s.nom as thirdparty_name,";
$sql .= " CONCAT(sp.firstname, ' ', sp.lastname) as contact_name";
$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON i.fk_socpeople = sp.rowid";
$sql .= " WHERE i.entity = ".$conf->entity;

if ($socid > 0) {
	$sql .= " AND i.fk_soc = ".((int)$socid);
}
if ($contactid > 0) {
	$sql .= " AND i.fk_socpeople = ".((int)$contactid);
}
if (!empty($search_type)) {
	$sql .= " AND i.interaction_type = '".$db->escape($search_type)."'";
}
if ($search_followup == 'pending') {
	$sql .= " AND i.followup_done = 0 AND i.followup_date IS NOT NULL";
} elseif ($search_followup == 'overdue') {
	$sql .= " AND i.followup_done = 0 AND i.followup_date < '".$db->idate(dol_now())."'";
} elseif ($search_followup == 'done') {
	$sql .= " AND i.followup_done = 1";
}
if (!empty($search_date_start)) {
	$sql .= " AND i.date_interaction >= '".$db->escape($search_date_start)." 00:00:00'";
}
if (!empty($search_date_end)) {
	$sql .= " AND i.date_interaction <= '".$db->escape($search_date_end)." 23:59:59'";
}

// Count
$sqlcount = preg_replace('/SELECT[\s\S]*? FROM/', 'SELECT COUNT(*) as total FROM', $sql, 1);
$resqlcount = $db->query($sqlcount);
$totalrows = 0;
if ($resqlcount) {
	$objcount = $db->fetch_object($resqlcount);
	$totalrows = $objcount->total;
}

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	print '<div style="color:red;padding:10px;">SQL ERROR: '.$db->lasterror().'</div>';
	print '<pre>'.dol_escape_htmltag($sql).'</pre>';
	exit;
}
$num = $db->num_rows($resql);


// New button
$newurl = dol_buildpath('/lemoncrm/interaction_card.php', 1).'?action=create';
if ($socid > 0) $newurl .= '&socid='.$socid;
if ($contactid > 0) $newurl .= '&contactid='.$contactid;
$newcardbutton = '';
if ($user->hasRight('lemoncrm', 'interaction', 'write')) {
	$newcardbutton = '<a class="butActionNew" href="'.$newurl.'"><span class="fa fa-plus-circle valignmiddle btnTitle-icon"></span> '.$langs->trans('NewInteraction').'</a>';
}

print_barre_liste($langs->trans('Interactions'), $page, $_SERVER["PHP_SELF"], '', $sortfield, $sortorder, '', $num, $totalrows, 'object_lemoncrm@lemoncrm', 0, $newcardbutton, '', $limit);

// Filter form
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
if ($socid > 0) print '<input type="hidden" name="socid" value="'.$socid.'">';
if ($contactid > 0) print '<input type="hidden" name="contactid" value="'.$contactid.'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';

print '<td class="liste_titre"></td>'; // expand

// Date
print '<td class="liste_titre">';
print '<input type="date" name="search_date_start" value="'.dol_escape_htmltag($search_date_start).'" class="flat maxwidth100">';
print ' - <input type="date" name="search_date_end" value="'.dol_escape_htmltag($search_date_end).'" class="flat maxwidth100">';
print '</td>';

// Type filter
print '<td class="liste_titre">';
print '<select name="search_type" class="flat maxwidth150">';
print '<option value="">'.$langs->trans('All').'</option>';
foreach ($types as $code => $label) {
	$sel = ($search_type == $code) ? ' selected' : '';
	print '<option value="'.$code.'"'.$sel.'>'.$label.'</option>';
}
print '</select></td>';

// Thirdparty (if not filtered)
if (!$socid) print '<td class="liste_titre"></td>';

// Summary
print '<td class="liste_titre"></td>';

// Followup
print '<td class="liste_titre">';
print '<select name="search_followup" class="flat">';
print '<option value="">'.$langs->trans('All').'</option>';
print '<option value="pending"'.($search_followup == 'pending' ? ' selected' : '').'>'.$langs->trans('PendingFollowups').'</option>';
print '<option value="overdue"'.($search_followup == 'overdue' ? ' selected' : '').'>'.$langs->trans('OverdueFollowups').'</option>';
print '<option value="done"'.($search_followup == 'done' ? ' selected' : '').'>'.$langs->trans('DoneFollowups').'</option>';
print '</select>';
print ' <input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"), 'search.png', '', 0, 1).'" value="1" title="'.$langs->trans("Search").'">';
print ' <input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("RemoveFilter"), 'searchclear.png', '', 0, 1).'" value="1" title="'.$langs->trans("RemoveFilter").'">';
print '</td>';

print '</tr>';

// Header
print '<tr class="liste_titre">';
print_liste_field_titre('', '', '', '', '', 'width="20"', '', ''); // expand icon
print_liste_field_titre('DateInteraction', $_SERVER["PHP_SELF"], 'i.date_interaction', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('InteractionType', $_SERVER["PHP_SELF"], 'i.interaction_type', '', '', '', $sortfield, $sortorder);
if (!$socid) print_liste_field_titre('ThirdParty', $_SERVER["PHP_SELF"], 's.nom', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('', '', '', '', '', '', '', ''); // summary preview
print_liste_field_titre('FollowupDate', $_SERVER["PHP_SELF"], 'i.followup_date', '', '', '', $sortfield, $sortorder);
print '</tr>';

// Load dictionaries for display
$sentimentDict = array();
$sqlsd = "SELECT code, label, color FROM ".MAIN_DB_PREFIX."c_lemoncrm_sentiment WHERE active = 1";
$ressd = $db->query($sqlsd);
if ($ressd) { while ($o = $db->fetch_object($ressd)) $sentimentDict[$o->code] = $o; }

$prospectDict = array();
$sqlpd = "SELECT code, label, color FROM ".MAIN_DB_PREFIX."c_lemoncrm_prospect_status WHERE active = 1";
$respd = $db->query($sqlpd);
if ($respd) { while ($o = $db->fetch_object($respd)) $prospectDict[$o->code] = $o; }

$followup_modes = lemoncrm_get_followup_modes();

// Rows
$i = 0;
$colcount = $socid ? 5 : 6;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);

	// Row color
	$rowclass = 'oddeven';
	if (!$obj->followup_done && !empty($obj->followup_date)) {
		$today = date('Y-m-d');
		if ($obj->followup_date < $today) $rowclass = 'oddeven lemoncrm-overdue';
		elseif ($obj->followup_date == $today) $rowclass = 'oddeven lemoncrm-today';
	}
	if ($obj->followup_done) $rowclass = 'oddeven lemoncrm-done';

	$typeLabel = $types[$obj->interaction_type] ?? $obj->interaction_type;
	$typeIcon = array(
		'AC_TEL' => 'fas fa-phone-alt', 'AC_EMAIL' => 'fas fa-envelope',
		'AC_LINKEDIN' => 'fas fa-share-alt', 'AC_TEAMS' => 'fas fa-video',
		'AC_RDV' => 'far fa-calendar-check', 'AC_MEETING_INPERSON' => 'fas fa-users',
		'AC_OTH' => 'far fa-comment',
	);
	$icon = $typeIcon[$obj->interaction_type] ?? 'far fa-comment';
	$dirLabel = ($obj->direction == 'IN') ? '<span class="badge badge-status4" style="font-size:0.75em">IN</span>' : '<span class="badge badge-status1" style="font-size:0.75em">OUT</span>';

	// Main row (clickable)
	print '<tr class="'.$rowclass.' lcrm-row-toggle" data-target="lcrm-detail-'.$obj->rowid.'" style="cursor:pointer">';

	// Expand icon
	print '<td class="lcrm-expand-cell"><span class="fa fa-chevron-right lcrm-chevron" id="chev-'.$obj->rowid.'"></span></td>';

	// Date
	print '<td>'.dol_print_date($db->jdate($obj->date_interaction), 'dayhour').'</td>';

	// Type + direction
	print '<td><span class="fa '.$icon.'" style="margin-right:5px;color:#6b7280"></span>'.$typeLabel.' '.$dirLabel.'</td>';

	// Thirdparty (hidden if filtered by socid)
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

	// Summary preview (one-line, strip all newline types)
	$previewSummary = str_replace(array("\r\n", "\r", "\n"), ' ', $obj->summary);
	$previewSummary = preg_replace('/\\\\[rn]/', ' ', $previewSummary); // also strip literal \r \n
	$previewSummary = preg_replace('/\s+/', ' ', trim($previewSummary));
	print '<td class="tdoverflowmax300" style="color:#6b7280;font-size:0.9em">';
	print dol_trunc(dol_escape_htmltag(trim($previewSummary)), 80);
	print '</td>';

	// Followup
	print '<td>';
	if (!empty($obj->followup_date)) {
		$interaction = new LemonCRMInteraction($db);
		$interaction->followup_done = $obj->followup_done;
		$interaction->followup_date = $obj->followup_date;
		print $obj->followup_date.' '.$interaction->getFollowupBadge();
	}
	print '</td>';

	print '</tr>';

	// Detail row (hidden, accordion)
	print '<tr class="lcrm-detail-row" id="lcrm-detail-'.$obj->rowid.'" style="display:none">';
	print '<td colspan="'.$colcount.'">';
	print '<div class="lcrm-detail-content">';

	// Left: summary
	print '<div class="lcrm-detail-left">';
	if (!empty($obj->summary)) {
		print '<div class="lcrm-detail-summary">'.nl2br(htmlspecialchars(trim($obj->summary), ENT_QUOTES, 'UTF-8')).'</div>';
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
	if ($tags) {
		print '<div class="lcrm-detail-tags">'.implode(' ', $tags).'</div>';
	}
	print '</div>';

	// Right: meta + followup
	print '<div class="lcrm-detail-right">';
	// Ref + edit link
	$interaction = new LemonCRMInteraction($db);
	$interaction->id = $obj->rowid;
	$interaction->ref = $obj->ref;
	print '<div class="lcrm-detail-ref">'.$interaction->getNomUrl(1);
	if ($obj->duration_minutes > 0) print ' &middot; '.$obj->duration_minutes.' min';
	if (!empty($obj->contact_name) && trim($obj->contact_name)) print ' &middot; '.dol_escape_htmltag(trim($obj->contact_name));
	// Edit + Delete buttons
	if ($user->hasRight('lemoncrm', 'interaction', 'write')) {
		$editUrl = dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$obj->rowid.'&action=edit';
		print ' <a href="'.$editUrl.'" title="Modifier" style="margin-left:8px"><span class="fas fa-pencil-alt" style="color:#6b7280;font-size:0.85em"></span></a>';
	}
	if ($user->hasRight('lemoncrm', 'interaction', 'delete')) {
		$delUrl = $_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'&token='.newToken();
		if ($socid > 0) $delUrl .= '&socid='.$socid;
		if ($contactid > 0) $delUrl .= '&contactid='.$contactid;
		print ' <a href="'.$delUrl.'" title="Supprimer" onclick="return confirm(\'Supprimer cette interaction ?\')"><span class="fas fa-trash-alt" style="color:#ef4444;font-size:0.85em"></span></a>';
	}
	print '</div>';

	// Followup
	if (!empty($obj->followup_action) || !empty($obj->followup_date)) {
		print '<div class="lcrm-detail-followup">';
		print '<strong><span class="fa fa-bell-o"></span> Suivi</strong><br>';
		if (!empty($obj->followup_action)) print dol_escape_htmltag($obj->followup_action).'<br>';
		if (!empty($obj->followup_date)) {
			print $obj->followup_date;
			if (!empty($obj->followup_mode)) {
				print ' ('.($followup_modes[$obj->followup_mode] ?? $obj->followup_mode).')';
			}
		}
		print '</div>';
	}
	print '</div>';

	print '</div>'; // detail-content
	print '</td>';
	print '</tr>';

	$i++;
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="'.$colcount.'" class="opacitymedium">'.$langs->trans('NoInteractions').'</td></tr>';
}

print '</table>';
print '</form>';

// Accordion JS
print '<script>
$(function() {
	$(".lcrm-row-toggle").click(function(e) {
		// Don\'t toggle if clicking a link
		if ($(e.target).closest("a").length) return;
		var target = $(this).data("target");
		var $detail = $("#" + target);
		var $chev = $("#chev-" + target.replace("lcrm-detail-", ""));
		$detail.slideToggle(150);
		$chev.toggleClass("open");
	});
});
</script>';

llxFooter();
$db->close();
