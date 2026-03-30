<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * Interaction card : create / edit / view
 * UX: contextual, adaptive, progressive disclosure
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
dol_include_once('/lemoncrm/class/lemoncrm_interaction.class.php');
dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');

$langs->loadLangs(array("lemoncrm@lemoncrm", "companies", "commercial"));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$drawerMode = GETPOST('drawer', 'int') ? true : false;
$popupMode = GETPOST('popup', 'int') ? true : false;
if ($popupMode) $drawerMode = true; // reuse same lightweight rendering
$confirm = GETPOST('confirm', 'alpha');

$socid = GETPOSTINT('socid');
$contactid = GETPOSTINT('contactid');

if (!$user->hasRight('lemoncrm', 'interaction', 'read')) {
	accessforbidden();
}

$object = new LemonCRMInteraction($db);
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}

$form = new Form($db);

/*
 * Actions
 */

// Create
if ($action == 'add' && $user->hasRight('lemoncrm', 'interaction', 'write')) {
	if (GETPOST('token', 'alpha') != newToken()) {
		accessforbidden('Bad value for CSRF token');
	}

	$object->interaction_type = GETPOST('interaction_type', 'alpha');
	// Fallback: try reading from hidden backup field
	if (empty($object->interaction_type)) {
		$object->interaction_type = GETPOST('interaction_type_backup', 'alpha');
	}
	$object->fk_soc = GETPOSTINT('fk_soc');
	$object->fk_socpeople = GETPOSTINT('fk_socpeople');
	$object->direction = GETPOST('direction', 'alpha');
	if (empty($object->direction)) $object->direction = 'OUT';
	$object->date_interaction = dol_mktime(
		GETPOSTINT('date_interactionhour'), GETPOSTINT('date_interactionmin'), 0,
		GETPOSTINT('date_interactionmonth'), GETPOSTINT('date_interactionday'), GETPOSTINT('date_interactionyear')
	);
	$object->duration_minutes = GETPOSTINT('duration_minutes');
	$object->summary = GETPOST('summary', 'restricthtml');
	$object->followup_action = GETPOST('followup_action', 'restricthtml');
	$object->followup_date = GETPOST('followup_date', 'alpha');
	$object->followup_time = GETPOST('followup_time', 'alpha');
	$object->followup_mode = GETPOST('followup_mode', 'alpha');
	$object->sentiment = GETPOST('sentiment', 'alpha');
	$object->prospect_status = GETPOST('prospect_status', 'alpha');
	$fkParent = GETPOSTINT('fk_parent');
	if ($fkParent > 0) {
		// Keep flat: if parent is itself a child, use its parent instead
		$parentCheck = new LemonCRMInteraction($db);
		if ($parentCheck->fetch($fkParent) > 0 && $parentCheck->fk_parent > 0) {
			$fkParent = $parentCheck->fk_parent;
		}
		$object->fk_parent = $fkParent;
	}

	// Call outcome stored in summary prefix
	$call_outcome = GETPOST('call_outcome', 'alpha');
	if (!empty($call_outcome) && $object->interaction_type == 'LCRM_TEL') {
		$outcomeLabels = lemoncrm_get_call_outcomes();
		$prefix = '['.($outcomeLabels[$call_outcome] ?? $call_outcome).'] ';
		if (strpos($object->summary, '[') !== 0) {
			$object->summary = $prefix.$object->summary;
		}
	}

	$formError = '';
	if (empty($object->interaction_type)) {
		$formError = 'Choisissez un type d\'interaction';
		$action = 'create';
	} elseif (empty($object->date_interaction)) {
		$formError = 'La date est obligatoire';
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			if ($popupMode) {
				// Close popup window after save
				print '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
				print '<div style="padding:40px;text-align:center;font-family:sans-serif;">';
				print '<div style="font-size:2em;margin-bottom:10px;">&#10003;</div>';
				print '<div style="font-weight:600;">Interaction enregistr&eacute;e</div>';
				print '</div>';
				print '<script>if(window.opener){window.opener.postMessage("lcrm_saved","*");}setTimeout(function(){ window.close(); }, 800);</script>';
				print '</body></html>';
				exit;
			}
			if ($drawerMode) {
				print '<html><body><script>parent.postMessage("lcrm_saved", "*");</script></body></html>';
				exit;
			}
			setEventMessages($langs->trans('InteractionCreated'), null, 'mesgs');
			header("Location: ".dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$result);
			exit;
		} else {
			$formError = $object->error;
			$action = 'create';
		}
	}
}

// Update
if ($action == 'update' && $user->hasRight('lemoncrm', 'interaction', 'write')) {
	if (GETPOST('token', 'alpha') != newToken()) {
		accessforbidden('Bad value for CSRF token');
	}

	$object->interaction_type = GETPOST('interaction_type', 'alpha');
	$object->fk_soc = GETPOSTINT('fk_soc');
	$object->fk_socpeople = GETPOSTINT('fk_socpeople');
	$object->direction = GETPOST('direction', 'aZ');
	$object->date_interaction = dol_mktime(
		GETPOSTINT('date_interactionhour'), GETPOSTINT('date_interactionmin'), 0,
		GETPOSTINT('date_interactionmonth'), GETPOSTINT('date_interactionday'), GETPOSTINT('date_interactionyear')
	);
	$object->duration_minutes = GETPOSTINT('duration_minutes');
	$object->summary = GETPOST('summary', 'restricthtml');
	$object->followup_action = GETPOST('followup_action', 'restricthtml');
	$object->followup_date = GETPOST('followup_date', 'alpha');
	$object->followup_time = GETPOST('followup_time', 'alpha');
	$object->followup_mode = GETPOST('followup_mode', 'alpha');
	$object->sentiment = GETPOST('sentiment', 'alpha');
	$object->prospect_status = GETPOST('prospect_status', 'alpha');

	$result = $object->update($user);
	if ($result > 0) {
		if ($popupMode || $drawerMode) {
			print '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
			print '<div style="padding:40px;text-align:center;font-family:sans-serif;">';
			print '<div style="font-size:2em;margin-bottom:10px;">&#10003;</div>';
			print '<div style="font-weight:600;">Interaction mise à jour</div>';
			print '</div>';
			print '<script>if(window.opener){window.opener.postMessage("lcrm_saved","*");}setTimeout(function(){ window.close(); }, 800);</script>';
			print '</body></html>';
			exit;
		}
		setEventMessages($langs->trans('InteractionUpdated'), null, 'mesgs');
		header("Location: ".dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$object->id);
		exit;
	} else {
		$formError = $object->error;
		$action = 'edit';
	}
}

// Delete
if ($action == 'confirm_delete' && $confirm == 'yes' && $user->hasRight('lemoncrm', 'interaction', 'delete')) {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		accessforbidden('Method not allowed');
	}
	if (GETPOST('token', 'alpha') != newToken()) {
		accessforbidden('Bad value for CSRF token');
	}
	$result = $object->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('InteractionDeleted'), null, 'mesgs');
		header("Location: ".dol_buildpath('/lemoncrm/interaction_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Mark followup done
if ($action == 'followup_done' && $user->hasRight('lemoncrm', 'interaction', 'write')) {
	$result = $object->markFollowupDone($user);
	if ($result > 0) {
		setEventMessages($langs->trans('FollowupDone'), null, 'mesgs');
		header("Location: ".dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$object->id);
		exit;
	}
}

/*
 * Load dictionaries from DB
 */
function lemoncrm_load_dict($db, $table) {
	$items = array();
	$sql = "SELECT rowid, code, label, color FROM ".MAIN_DB_PREFIX.$table;
	$sql .= " WHERE active = 1 ORDER BY position ASC, rowid ASC";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$items[] = array('id' => $obj->rowid, 'code' => $obj->code, 'label' => $obj->label, 'color' => $obj->color);
		}
	}
	return $items;
}

$sentimentList = lemoncrm_load_dict($db, 'c_lemoncrm_sentiment');
$prospectStatusList = lemoncrm_load_dict($db, 'c_lemoncrm_prospect_status');

/*
 * View
 */

$title = $langs->trans('InteractionCard');
if ($drawerMode) {
	// Popup mode: use llxHeader with hidden menus for full Dolibarr JS support (CKEditor etc.)
	if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
	$_GET['dol_hide_topmenu'] = 1;
	$_GET['dol_hide_leftmenu'] = 1;
	$_GET['mainmenu'] = 'lemon';
	llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lemoncrm page-card');
	print '<style>#id-left{display:none!important}#id-right{margin:0!important}div.fiche{margin:8px!important}.side-nav{display:none!important}#tmenu_tooltip{display:none!important}</style>';
} else {
	$_GET['mainmenu'] = 'lemon';
	$_GET['leftmenu'] = 'lemoncrm';
	llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-lemoncrm page-card');
}

$typeIcons = lemoncrm_get_type_icons();
$typeLabels = lemoncrm_get_interaction_types(); // active (form)
$typeLabelsAll = lemoncrm_get_interaction_types(false); // all (display)
$directions = lemoncrm_get_directions();
$followup_modes = lemoncrm_get_followup_modes();

// ==================== CREATE / EDIT ====================
if ($action == 'create' || ($action == 'edit' && $id > 0)) {

	$isEdit = ($action == 'edit');
	$formAction = $isEdit ? 'update' : 'add';

	// Current values
	$curType = $isEdit ? $object->interaction_type : (GETPOST('interaction_type', 'alpha') ?: '');
	$curSoc = $isEdit ? $object->fk_soc : ($socid ?: GETPOSTINT('fk_soc'));
	$curContact = $isEdit ? $object->fk_socpeople : ($contactid ?: GETPOSTINT('fk_socpeople'));
	$curDir = $isEdit ? $object->direction : (GETPOST('direction', 'aZ') ?: 'OUT');
	$curDate = $isEdit ? $object->date_interaction : dol_now();
	$curDuration = $isEdit ? $object->duration_minutes : GETPOSTINT('duration_minutes');
	$curSummary = $isEdit ? $object->summary : GETPOST('summary', 'restricthtml');
	// LinkedIn extension: pre-fill summary from URL param
	if (!$isEdit && empty($curSummary) && GETPOST('linkedin_summary', 'restricthtml')) {
		$curSummary = GETPOST('linkedin_summary', 'restricthtml');
	}
	// LinkedIn extension: try to match contact name to a thirdparty
	if (!$isEdit && empty($curSoc) && GETPOST('linkedin_contact', 'alpha')) {
		$linkedinContact = GETPOST('linkedin_contact', 'alpha');
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE nom LIKE '%".$db->escape($linkedinContact)."%' AND entity = ".$conf->entity." LIMIT 1";
		$resql = $db->query($sql);
		if ($resql && ($obj = $db->fetch_object($resql))) {
			$curSoc = (int)$obj->rowid;
		}
		// Also try contacts (socpeople)
		if (empty($curContact)) {
			$parts = explode(' ', $linkedinContact);
			if (count($parts) >= 2) {
				$sql2 = "SELECT rowid, fk_soc FROM ".MAIN_DB_PREFIX."socpeople WHERE";
				$sql2 .= " (firstname LIKE '%".$db->escape($parts[0])."%' AND lastname LIKE '%".$db->escape($parts[count($parts)-1])."%')";
				$sql2 .= " OR (firstname LIKE '%".$db->escape($parts[count($parts)-1])."%' AND lastname LIKE '%".$db->escape($parts[0])."%')";
				$sql2 .= " AND entity = ".$conf->entity." LIMIT 1";
				$resql2 = $db->query($sql2);
				if ($resql2 && ($obj2 = $db->fetch_object($resql2))) {
					$curContact = (int)$obj2->rowid;
					if (empty($curSoc) && $obj2->fk_soc > 0) {
						$curSoc = (int)$obj2->fk_soc;
					}
				}
			}
		}
	}
	$curFollowAction = $isEdit ? $object->followup_action : GETPOST('followup_action', 'restricthtml');
	$curFollowDate = $isEdit ? $object->followup_date : GETPOST('followup_date', 'alpha');
	$curFollowMode = $isEdit ? $object->followup_mode : GETPOST('followup_mode', 'alpha');
	$curSentiment = $isEdit ? $object->sentiment : GETPOST('sentiment', 'alpha');
	$curProspect = $isEdit ? $object->prospect_status : GETPOST('prospect_status', 'alpha');

	// Thread parent
	$curFkParent = $isEdit ? $object->fk_parent : GETPOSTINT('fk_parent');
	$parentObj = null;
	if ($curFkParent > 0) {
		$parentObj = new LemonCRMInteraction($db);
		if ($parentObj->fetch($curFkParent) <= 0) {
			$parentObj = null;
			$curFkParent = 0;
		} else {
			// Pre-fill thirdparty from parent if not set
			if (empty($curSoc) && $parentObj->fk_soc > 0) {
				$curSoc = $parentObj->fk_soc;
			}
		}
	}

	// Thirdparty name for display
	$socName = '';
	if ($curSoc > 0) {
		$tmpSoc = new Societe($db);
		$tmpSoc->fetch($curSoc);
		$socName = $tmpSoc->name;
	}

	$pageTitle = $isEdit ? 'Modifier '.$object->ref : 'Nouvelle interaction';
	if ($socName) $pageTitle .= ' - '.$socName;

	if (!$drawerMode) {
		print load_fiche_titre($pageTitle, '', 'fa-comments');
	}

	print '<div class="lemoncrm-form">';

	if (!empty($formError)) {
		print '<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-bottom:12px;color:#991b1b;font-size:0.9em">'.$formError.'</div>';
	}

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'" id="lemoncrm-main-form" novalidate>';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$formAction.'">';
	if ($isEdit) print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($drawerMode) print '<input type="hidden" name="drawer" value="1">';
	if ($popupMode) print '<input type="hidden" name="popup" value="1">';
	if ($curFkParent > 0) print '<input type="hidden" name="fk_parent" value="'.$curFkParent.'">';
	// Direction: hidden with default, updated by JS if available
	print '<input type="hidden" name="direction" id="h_direction" value="'.dol_escape_htmltag($curDir ?: 'OUT').'">';
	print '<input type="hidden" name="sentiment" id="h_sentiment" value="'.dol_escape_htmltag($curSentiment).'">';
	print '<input type="hidden" name="prospect_status" id="h_prospect" value="'.dol_escape_htmltag($curProspect).'">';
	print '<input type="hidden" name="followup_mode" id="h_followup_mode" value="'.dol_escape_htmltag($curFollowMode).'">';
	print '<input type="hidden" name="call_outcome" id="h_call_outcome" value="">';

	// ===== TIER 1 : Essential (always visible) =====

	// Thread banner
	if ($parentObj) {
		$parentType = $typeLabelsAll[$parentObj->interaction_type] ?? $parentObj->interaction_type;
		$parentSummary = dol_trunc(str_replace(array("\r\n", "\n", "\r"), ' ', $parentObj->summary), 50);
		print '<div class="lcrm-thread-banner">';
		print '<span class="fas fa-link" style="margin-right:6px;color:#F7C948"></span>';
		print 'Suite de <strong>'.$parentObj->ref.'</strong> : '.$parentType;
		if ($parentSummary) print ' - '.dol_escape_htmltag($parentSummary);
		print '</div>';
	}

	// Type pills (native radio buttons, no JS dependency)
	print '<div class="lcrm-type-bar">';
	foreach ($typeLabels as $code => $label) {
		$icon = $typeIcons[$code] ?? 'far fa-comment';
		$checked = ($curType == $code) ? ' checked' : '';
		print '<label class="lcrm-type-pill">';
		print '<input type="radio" name="interaction_type" value="'.$code.'"'.$checked.'>';
		print '<span class="'.$icon.'"></span><span class="lcrm-type-label">'.$label.'</span>';
		print '</label>';
	}
	print '</div>';

	// Context bar (who + when) - compact single line
	print '<div class="lcrm-context">';
	print '<div class="lcrm-context-who">';
	$socEvents = array();
	$socEvents[] = array('method' => 'getContacts', 'url' => dol_buildpath('/core/ajax/contacts.php?showempty=1&token='.currentToken(), 1), 'htmlname' => 'fk_socpeople', 'params' => array('add-resolve' => 'disabled'));
	print $form->select_company($curSoc, 'fk_soc', '', 1, 0, 0, $socEvents, 0, 'lcrm-select');
	print '</div>';
	print '<div class="lcrm-context-who" style="margin-top:6px;">';
	print '<select name="fk_socpeople" id="fk_socpeople" class="lcrm-select">';
	print '<option value="0">Contact...</option>';
	if ($curSoc > 0) {
		$sql = "SELECT rowid, firstname, lastname FROM ".MAIN_DB_PREFIX."socpeople WHERE fk_soc = ".((int)$curSoc)." ORDER BY lastname";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$sel = ($curContact == $obj->rowid) ? ' selected' : '';
				print '<option value="'.$obj->rowid.'"'.$sel.'>'.dol_escape_htmltag($obj->firstname.' '.$obj->lastname).'</option>';
			}
		}
	}
	print '</select>';
	print '</div>';
	print '<div class="lcrm-context-when">';
	print $form->selectDate($curDate, 'date_interaction', 1, 1, 0, '', 1, 1);
	print '</div>';
	print '</div>';

	// Adaptive zone (changes based on type)
	print '<div class="lcrm-adaptive" id="lcrm-adaptive">';

	// Call-specific: direction + outcome
	print '<div class="lcrm-adaptive-call" style="display:none">';
	print '<div class="lcrm-pill-row">';
	print '<span class="lcrm-pill-label">Direction</span>';
	print '<button type="button" class="lcrm-pill lcrm-dir'.($curDir == 'OUT' ? ' active' : '').'" data-dir="OUT"><span class="fa fa-arrow-up"></span> Sortant</button>';
	print '<button type="button" class="lcrm-pill lcrm-dir'.($curDir == 'IN' ? ' active' : '').'" data-dir="IN"><span class="fa fa-arrow-down"></span> Entrant</button>';
	print '</div>';
	print '<div class="lcrm-pill-row" style="margin-top:6px">';
	print '<span class="lcrm-pill-label">Issue</span>';
	$outcomes = lemoncrm_get_call_outcomes();
	foreach ($outcomes as $code => $label) {
		print '<button type="button" class="lcrm-pill lcrm-outcome" data-outcome="'.$code.'">'.$label.'</button>';
	}
	print '</div>';
	print '<div class="lcrm-field-inline">';
	print '<span class="lcrm-pill-label">Durée</span>';
	print '<input type="number" name="duration_minutes" value="'.(int)$curDuration.'" min="0" step="1" placeholder="min" class="lcrm-input-mini">';
	print ' min';
	print '</div>';
	print '</div>';

	// Email-specific: direction only
	print '<div class="lcrm-adaptive-email" style="display:none">';
	print '<div class="lcrm-pill-row">';
	print '<button type="button" class="lcrm-pill lcrm-dir'.($curDir == 'OUT' ? ' active' : '').'" data-dir="OUT"><span class="fa fa-arrow-up"></span> Envoyé</button>';
	print '<button type="button" class="lcrm-pill lcrm-dir'.($curDir == 'IN' ? ' active' : '').'" data-dir="IN"><span class="fa fa-arrow-down"></span> Reçu</button>';
	print '</div>';
	print '</div>';

	// Meeting-specific: duration
	print '<div class="lcrm-adaptive-meeting" style="display:none">';
	print '<div class="lcrm-field-inline">';
	print '<span class="lcrm-pill-label">Durée</span>';
	print '<input type="number" name="duration_minutes_meeting" value="'.(int)$curDuration.'" min="0" step="1" placeholder="min" class="lcrm-input-mini">';
	print ' min';
	print '</div>';
	print '</div>';

	// LinkedIn/Teams/Other: direction
	print '<div class="lcrm-adaptive-generic" style="display:none">';
	print '<div class="lcrm-pill-row">';
	print '<button type="button" class="lcrm-pill lcrm-dir'.($curDir == 'OUT' ? ' active' : '').'" data-dir="OUT"><span class="fa fa-arrow-up"></span> Sortant</button>';
	print '<button type="button" class="lcrm-pill lcrm-dir'.($curDir == 'IN' ? ' active' : '').'" data-dir="IN"><span class="fa fa-arrow-down"></span> Entrant</button>';
	print '</div>';
	print '</div>';

	print '</div>'; // adaptive

	// Summary - Dolibarr standard WYSIWYG editor
	print '<div class="lcrm-summary">';
	require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	$doleditor = new DolEditor('summary', $curSummary, '', 120, 'dolibarr_notes', 'In', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_SOCIETE'), ROWS_4, '100%');
	print $doleditor->Create(1);
	print '</div>';

	// ===== TIER 2 : Details (expandable) =====
	print '<div class="lcrm-details-toggle" id="lcrm-toggle-details">';
	print '<span class="fa fa-chevron-down"></span> Plus de détails';
	print '</div>';

	print '<div class="lcrm-details" id="lcrm-details" style="display:none">';

	// Sentiment pills (from dictionary)
	print '<div class="lcrm-detail-group">';
	print '<span class="lcrm-detail-label">Sentiment</span>';
	print '<div class="lcrm-pill-row" id="sentiment-pills">';
	$sentimentIcons = array('positive' => 'fa-smile-o', 'neutral' => 'fa-meh-o', 'negative' => 'fa-frown-o');
	foreach ($sentimentList as $s) {
		$active = ($curSentiment == $s['code']) ? ' active' : '';
		$icon = $sentimentIcons[$s['code']] ?? 'fa-circle';
		print '<button type="button" class="lcrm-pill lcrm-sentiment'.$active.'" data-code="'.$s['code'].'" style="--pill-color:'.$s['color'].'">';
		print '<span class="'.$icon.'"></span> '.$s['label'];
		print '</button>';
	}
	print '<button type="button" class="lcrm-pill lcrm-add-pill" data-dict="sentiment" title="Ajouter"><span class="fa fa-plus"></span></button>';
	print '</div>';
	print '</div>';

	// Prospect status pills (from dictionary)
	print '<div class="lcrm-detail-group">';
	print '<span class="lcrm-detail-label">Statut prospect</span>';
	print '<div class="lcrm-pill-row" id="prospect-pills">';
	foreach ($prospectStatusList as $s) {
		$active = ($curProspect == $s['code']) ? ' active' : '';
		print '<button type="button" class="lcrm-pill lcrm-prospect'.$active.'" data-code="'.$s['code'].'" style="--pill-color:'.$s['color'].'">';
		print '<span class="fa fa-circle" style="color:'.$s['color'].'"></span> '.$s['label'];
		print '</button>';
	}
	print '<button type="button" class="lcrm-pill lcrm-add-pill" data-dict="prospect_status" title="Ajouter"><span class="fa fa-plus"></span></button>';
	print '</div>';
	print '</div>';

	print '</div>'; // details

	// ===== TIER 3 : Followup (expandable) =====
	print '<div class="lcrm-details-toggle" id="lcrm-toggle-followup">';
	print '<span class="fa fa-bell-o"></span> Planifier un suivi';
	print '</div>';

	print '<div class="lcrm-followup-section" id="lcrm-followup" style="'.(!empty($curFollowAction) || !empty($curFollowDate) ? '' : 'display:none').'">';
	print '<div class="lcrm-row">';
	print '<div class="lcrm-field">';
	print '<textarea name="followup_action" rows="2" placeholder="Que faire ensuite ?">'.dol_escape_htmltag($curFollowAction).'</textarea>';
	print '</div>';
	print '</div>';
	print '<div class="lcrm-row">';
	print '<div class="lcrm-field">';
	$curFollowTime = $isEdit ? $object->followup_time : GETPOST('followup_time', 'alpha');
	print '<div style="display:flex;gap:8px;align-items:center;">';
	print '<input type="date" name="followup_date" value="'.dol_escape_htmltag($curFollowDate).'" class="lcrm-input" style="flex:1;">';
	print '<input type="time" name="followup_time" value="'.dol_escape_htmltag($curFollowTime ?: '09:00').'" step="900" class="lcrm-input" style="width:110px;">';
	print '</div>';
	print '</div>';
	print '<div class="lcrm-field">';
	print '<div class="lcrm-pill-row">';
	$followupIcons = array('phone' => 'fa-phone', 'email' => 'fa-envelope', 'linkedin' => 'fa-linkedin');
	foreach ($followup_modes as $code => $label) {
		if (empty($code)) continue;
		$icon = $followupIcons[$code] ?? 'fa-comment';
		$active = ($curFollowMode == $code) ? ' active' : '';
		print '<button type="button" class="lcrm-pill lcrm-fmode'.$active.'" data-mode="'.$code.'"><span class="'.$icon.'"></span> '.$label.'</button>';
	}
	print '</div>';
	print '</div>';
	print '</div>';
	// Quick date shortcuts
	print '<div class="lcrm-quick-dates">';
	print '<button type="button" class="lcrm-quick-date" data-afternoon="1">Cet après-midi</button>';
	print '<button type="button" class="lcrm-quick-date" data-hours="3">Dans 3h</button>';
	print '<button type="button" class="lcrm-quick-date" data-days="1">Demain</button>';
	print '<button type="button" class="lcrm-quick-date" data-days="3">Dans 3j</button>';
	print '<button type="button" class="lcrm-quick-date" data-days="7">Dans 1 sem</button>';
	print '<button type="button" class="lcrm-quick-date" data-days="14">Dans 2 sem</button>';
	print '</div>';
	print '</div>'; // followup

	// ===== Submit =====
	print '<div class="lcrm-submit">';
	print '<button type="button" class="lcrm-btn-cancel" onclick="if(window.opener){window.close();}else{history.back();}">Annuler</button>';
	print '<button type="submit" class="lcrm-btn-save">Enregistrer</button>';
	print '</div>';

	print '</form>';
	print '</div>'; // lemoncrm-form

	// ===== Add-to-dictionary modal =====
	print '<div class="lcrm-modal-overlay" id="lcrm-dict-modal" style="display:none">';
	print '<div class="lcrm-modal">';
	print '<div class="lcrm-modal-title">Ajouter une valeur</div>';
	print '<input type="hidden" id="dict-type" value="">';
	print '<div class="lcrm-field"><label class="lcrm-label">Libellé</label><input type="text" id="dict-label" class="lcrm-input" placeholder="Ex: Très chaud"></div>';
	print '<div class="lcrm-field"><label class="lcrm-label">Couleur</label><input type="color" id="dict-color" value="#6b7280" class="lcrm-input-color"></div>';
	print '<div class="lcrm-modal-actions">';
	print '<button type="button" class="lcrm-btn-cancel" id="dict-cancel">Annuler</button>';
	print '<button type="button" class="lcrm-btn-save" id="dict-save">Ajouter</button>';
	print '</div>';
	print '</div>';
	print '</div>';

	// ===== JavaScript =====
	$ajaxUrl = dol_buildpath('/lemoncrm/ajax/dictionary.php', 1);
	print '<script>
$(function() {
	var typeMap = {
		LCRM_TEL: "call", LCRM_EMAIL: "email",
		LCRM_LINKEDIN: "generic", LCRM_TEAMS: "meeting",
		LCRM_RDV: "meeting", LCRM_MEETING: "meeting", LCRM_NOTE: "generic"
	};

	function showAdaptive(type) {
		$("[class^=lcrm-adaptive-]").hide();
		var zone = typeMap[type];
		if (zone) $(".lcrm-adaptive-" + zone).slideDown(150);
	}

	// Init
	var curType = $("input[name=interaction_type]:checked").val() || "";
	if (curType) showAdaptive(curType);

	// Type selection (native radio, just handle adaptive zone + focus)
	$("input[name=interaction_type]").change(function() {
		showAdaptive($(this).val());
		setTimeout(function() { $("#lcrm-summary").focus(); }, 200);
	});

	// Direction
	$(document).on("click", ".lcrm-dir", function() {
		// Only toggle within same parent
		$(this).closest(".lcrm-pill-row").find(".lcrm-dir").removeClass("active");
		$(this).addClass("active");
		$("#h_direction").val($(this).data("dir"));
	});

	// Call outcome
	$(".lcrm-outcome").click(function() {
		$(".lcrm-outcome").removeClass("active");
		if ($("#h_call_outcome").val() === $(this).data("outcome")) {
			$("#h_call_outcome").val("");
		} else {
			$(this).addClass("active");
			$("#h_call_outcome").val($(this).data("outcome"));
		}
	});

	// Sentiment toggle
	$(document).on("click", ".lcrm-sentiment", function() {
		var code = $(this).data("code");
		if ($("#h_sentiment").val() === code) {
			$(this).removeClass("active");
			$("#h_sentiment").val("");
		} else {
			$(".lcrm-sentiment").removeClass("active");
			$(this).addClass("active");
			$("#h_sentiment").val(code);
		}
	});

	// Prospect status toggle
	$(document).on("click", ".lcrm-prospect", function() {
		var code = $(this).data("code");
		if ($("#h_prospect").val() === code) {
			$(this).removeClass("active");
			$("#h_prospect").val("");
		} else {
			$(".lcrm-prospect").removeClass("active");
			$(this).addClass("active");
			$("#h_prospect").val(code);
		}
	});

	// Followup mode
	$(".lcrm-fmode").click(function() {
		var code = $(this).data("mode");
		if ($("#h_followup_mode").val() === code) {
			$(this).removeClass("active");
			$("#h_followup_mode").val("");
		} else {
			$(".lcrm-fmode").removeClass("active");
			$(this).addClass("active");
			$("#h_followup_mode").val(code);
		}
	});

	// Quick dates
	$(".lcrm-quick-date").click(function() {
		var d = new Date();
		$(".lcrm-quick-date").removeClass("active");
		$(this).addClass("active");
		if ($(this).data("afternoon")) {
			$("input[name=followup_date]").val(d.toISOString().substring(0, 10));
			$("input[name=followup_time]").val("14:00");
			return;
		}
		if ($(this).data("hours")) {
			d.setHours(d.getHours() + parseInt($(this).data("hours")));
			var mins = Math.round(d.getMinutes() / 15) * 15;
			if (mins === 60) { d.setHours(d.getHours() + 1); mins = 0; }
			$("input[name=followup_date]").val(d.toISOString().substring(0, 10));
			$("input[name=followup_time]").val(String(d.getHours()).padStart(2,"0") + ":" + String(mins).padStart(2,"0"));
			return;
		}
		if ($(this).data("days")) {
			d.setDate(d.getDate() + parseInt($(this).data("days")));
			$("input[name=followup_date]").val(d.toISOString().substring(0, 10));
			if (!$("input[name=followup_time]").val()) {
				$("input[name=followup_time]").val("09:00");
			}
		}
	});

	// Toggle sections
	$("#lcrm-toggle-details").click(function() {
		$("#lcrm-details").slideToggle(200);
		$(this).toggleClass("open");
		$(this).find(".fa").toggleClass("fa-chevron-down fa-chevron-up");
	});
	$("#lcrm-toggle-followup").click(function() {
		$("#lcrm-followup").slideToggle(200);
		$(this).toggleClass("open");
	});

	// Contact reload handled by Dolibarr select_company events
	/*function reloadContacts_disabled() {
		var socid = $("#fk_soc").val() || $("[name=fk_soc]").val();
		var sel = $("#fk_socpeople");
		sel.empty().append(\'<option value="0">Contact...</option>\');
		$("#lcrm-contact-info").remove();
		if (socid > 0) {
			$.get("'.DOL_URL_ROOT.'/contact/ajax/contacts.php", {socid: socid}, function(data) {
				if (data && data.length) {
					$.each(data, function(i, c) {
						sel.append(\'<option value="\' + c.id + \'" data-phone="\' + (c.phone_mobile || c.phone_perso || c.phone_pro || "") + \'" data-email="\' + (c.email || "") + \'">\' + c.firstname + \' \' + c.lastname + \'</option>\');
					});
					if (data.length === 1) {
						sel.val(data[0].id).trigger("change");
					}
				}
			}, "json");
		}
	}
	// Bind to all possible events for thirdparty change
	$(document).on("change", "#fk_soc", reloadContacts);
	$(document).on("change", "[name=fk_soc]", reloadContacts);
	*/

	// Show contact info - disabled until contact reload works
	/* (phone/email) when selecting a contact
	$(document).on("change", "#fk_socpeople", function() {
		$("#lcrm-contact-info").remove();
		var opt = $(this).find("option:selected");
		var phone = opt.data("phone") || "";
		var email = opt.data("email") || "";
		var curType = $("input[name=interaction_type]:checked").val() || "";
		var info = "";
		if (curType === "LCRM_TEL" && phone) {
			info = \'<a href="tel:\' + phone + \'">\' + phone + \'</a>\';
		} else if (curType === "LCRM_EMAIL" && email) {
			info = \'<a href="mailto:\' + email + \'">\' + email + \'</a>\';
		} else if (phone || email) {
			if (phone) info += phone;
			if (phone && email) info += " · ";
			if (email) info += email;
		}
		if (info) {
			$(this).after(\'<div id="lcrm-contact-info" style="margin-top:4px;font-size:0.85em;color:#6b7280">\' + info + \'</div>\');
		}
	*/

	// Show contact info (phone/email) based on interaction type
	$(document).on("change", "#fk_socpeople", function() {
		$("#lcrm-contact-info").remove();
		var contactId = $(this).val();
		if (!contactId || contactId == "0") return;
		$.get("'.dol_buildpath('/lemoncrm/ajax/contact_info.php', 1).'", {id: contactId}, function(data) {
			if (!data) return;
			var curType = $("input[name=interaction_type]:checked").val() || "";
			var info = "";
			var phone = data.phone_mobile || data.phone || data.phone_perso || "";
			var email = data.email || "";
			if ((curType === "LCRM_TEL" || curType === "LCRM_NOTE") && phone) {
				info = \'<a href="tel:\' + phone + \'" style="color:#374151">\' + phone + \'</a>\';
			} else if (curType === "LCRM_EMAIL" && email) {
				info = \'<a href="mailto:\' + email + \'" style="color:#374151">\' + email + \'</a>\';
			} else {
				var parts = [];
				if (phone) parts.push(\'<a href="tel:\' + phone + \'" style="color:#374151">\' + phone + \'</a>\');
				if (email) parts.push(\'<a href="mailto:\' + email + \'" style="color:#374151">\' + email + \'</a>\');
				info = parts.join(" · ");
			}
			if (info) {
				$("#fk_socpeople").after(\'<div id="lcrm-contact-info" style="margin-top:4px;font-size:0.88em;color:#6b7280">\' + info + \'</div>\');
			}
		}, "json");
	});

	// Auto-select first contact when list is refreshed + show info
	var contactObserver = new MutationObserver(function() {
		var sel = document.getElementById("fk_socpeople");
		if (sel && sel.options.length === 2) {
			sel.selectedIndex = 1;
			$(sel).trigger("change");
		}
	});
	var contactSel = document.getElementById("fk_socpeople");
	if (contactSel) contactObserver.observe(contactSel, {childList: true});

	// Sync duration fields
	$("input[name=duration_minutes_meeting]").on("input", function() {
		$("input[name=duration_minutes]").val($(this).val());
	});
	$("input[name=duration_minutes]").on("input", function() {
		$("input[name=duration_minutes_meeting]").val($(this).val());
	});

	// Dictionary modal
	$(".lcrm-add-pill").click(function() {
		var dictType = $(this).data("dict");
		$("#dict-type").val(dictType);
		$("#dict-label").val("");
		$("#dict-color").val("#6b7280");
		$("#lcrm-dict-modal").fadeIn(150);
		setTimeout(function() { $("#dict-label").focus(); }, 100);
	});
	$("#dict-cancel").click(function() { $("#lcrm-dict-modal").fadeOut(150); });
	$("#dict-save").click(function() {
		var dictType = $("#dict-type").val();
		var label = $.trim($("#dict-label").val());
		var color = $("#dict-color").val();
		if (!label) { $("#dict-label").focus(); return; }
		$.post("'.$ajaxUrl.'", {
			action: "add",
			type: dictType,
			label: label,
			color: color,
			token: "'.newToken().'"
		}, function(data) {
			if (data.success) {
				var container = dictType === "sentiment" ? "#sentiment-pills" : "#prospect-pills";
				var cls = dictType === "sentiment" ? "lcrm-sentiment" : "lcrm-prospect";
				var icon = dictType === "sentiment" ? "fa-circle" : "fa-circle";
				var btn = \'<button type="button" class="lcrm-pill \' + cls + \'" data-code="\' + data.code + \'" style="--pill-color:\' + data.color + \'"><span class="fa \' + icon + \'" style="color:\' + data.color + \'"></span> \' + data.label + \'</button>\';
				$(container).find(".lcrm-add-pill").before(btn);
				$("#lcrm-dict-modal").fadeOut(150);
			}
		}, "json");
	});

	// Auto-set time to now for new interactions
	if (!$("input[name=id]").length) {
		var now = new Date();
		$("select[name=date_interactionhour]").val(now.getHours());
		$("select[name=date_interactionmin]").val(Math.floor(now.getMinutes() / 5) * 5);
	}
});
</script>';
}

// ==================== VIEW ====================
elseif ($id > 0) {

	// Confirm delete
	if ($action == 'delete') {
		print $form->formconfirm(
			$_SERVER["PHP_SELF"].'?id='.$object->id,
			$langs->trans('Delete'),
			$langs->trans('ConfirmDeleteInteraction'),
			'confirm_delete',
			'',
			0,
			1
		);
	}

	$typeLabel = $typeLabelsAll[$object->interaction_type] ?? $object->interaction_type;
	$typeIcon = $typeIcons[$object->interaction_type] ?? 'fa-comment-o';
	$dirLabel = $directions[$object->direction] ?? $object->direction;

	print '<div class="lemoncrm-form">';

	// Header with type icon
	print '<div class="lcrm-view-header">';
	print '<span class="lcrm-view-type-icon fa '.$typeIcon.'"></span>';
	print '<div class="lcrm-view-header-info">';
	print '<div class="lcrm-view-ref">'.$object->ref.'</div>';
	print '<div class="lcrm-view-meta">'.$typeLabel;
	$dirBadge = ($object->direction == 'IN')
		? ' <span class="lcrm-badge lcrm-badge-in"><span class="fa fa-arrow-down"></span> Entrant</span>'
		: ' <span class="lcrm-badge lcrm-badge-out"><span class="fa fa-arrow-up"></span> Sortant</span>';
	print $dirBadge;
	print ' &middot; '.dol_print_date($object->date_interaction, 'dayhour');
	if ($object->duration_minutes > 0) print ' &middot; '.$object->duration_minutes.' min';
	print '</div>';
	print '</div>';
	print '</div>';

	// Who
	print '<div class="lcrm-view-who">';
	if ($object->fk_soc > 0) {
		$soc = new Societe($db);
		$soc->fetch($object->fk_soc);
		print '<span class="fa fa-building-o"></span> '.$soc->getNomUrl(1);
	}
	if ($object->fk_socpeople > 0) {
		$contact = new Contact($db);
		$contact->fetch($object->fk_socpeople);
		print ' &middot; <span class="fa fa-user"></span> '.$contact->getNomUrl(1);
	}
	print '</div>';

	// Summary
	if (!empty($object->summary)) {
		print '<div class="lcrm-view-summary">';
		print dol_nl2br(dol_escape_htmltag($object->summary));
		print '</div>';
	}

	// Tags row (sentiment + prospect status)
	$hasTags = !empty($object->sentiment) || !empty($object->prospect_status);
	if ($hasTags) {
		print '<div class="lcrm-view-tags">';
		if (!empty($object->sentiment)) {
			$sentColor = '#6b7280';
			$sentLabel = $object->sentiment;
			foreach ($sentimentList as $s) {
				if ($s['code'] == $object->sentiment) { $sentColor = $s['color']; $sentLabel = $s['label']; break; }
			}
			print '<span class="lcrm-tag" style="--tag-color:'.$sentColor.'">'.$sentLabel.'</span>';
		}
		if (!empty($object->prospect_status)) {
			$prosColor = '#6b7280';
			$prosLabel = $object->prospect_status;
			foreach ($prospectStatusList as $s) {
				if ($s['code'] == $object->prospect_status) { $prosColor = $s['color']; $prosLabel = $s['label']; break; }
			}
			print '<span class="lcrm-tag" style="--tag-color:'.$prosColor.'">'.$prosLabel.'</span>';
		}
		print '</div>';
	}

	// Followup card
	if (!empty($object->followup_action) || !empty($object->followup_date)) {
		print '<div class="lcrm-view-followup">';
		print '<div class="lcrm-view-followup-header"><span class="fa fa-bell-o"></span> Suivi '.$object->getFollowupBadge().'</div>';
		if (!empty($object->followup_action)) {
			print '<div class="lcrm-view-followup-body">'.dol_nl2br(dol_escape_htmltag($object->followup_action)).'</div>';
		}
		if (!empty($object->followup_date)) {
			print '<div class="lcrm-view-followup-date">';
			print '<span class="fa fa-calendar"></span> '.$object->followup_date;
			if (!empty($object->followup_time)) {
				print ' à '.dol_escape_htmltag($object->followup_time);
			}
			if (!empty($object->followup_mode)) {
				$modeIcon = array('phone' => 'fa-phone', 'email' => 'fa-envelope', 'linkedin' => 'fa-linkedin');
				$mLabel = $followup_modes[$object->followup_mode] ?? $object->followup_mode;
				print ' &middot; <span class="fa '.($modeIcon[$object->followup_mode] ?? 'fa-comment').'"></span> '.$mLabel;
			}
			print '</div>';
		}
		print '</div>';
	}

	// Created info
	print '<div class="lcrm-view-created">Créé le '.dol_print_date($object->datec, 'dayhour').'</div>';

	print '</div>'; // lemoncrm-form

	// Action buttons
	print '<div class="tabsAction">';
	if ($user->hasRight('lemoncrm', 'interaction', 'write')) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit">'.$langs->trans('Modify').'</a>';
		if (!$object->followup_done && !empty($object->followup_date)) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=followup_done">'.$langs->trans('MarkFollowupDone').'</a>';
		}
	}
	if ($user->hasRight('lemoncrm', 'interaction', 'delete')) {
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete">'.$langs->trans('Delete').'</a>';
	}
	print '</div>';
}

if ($drawerMode) {
	print '</body></html>';
} else {
	llxFooter();
}
$db->close();
