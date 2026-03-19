<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * Configuration page for LemonCRM module
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');

if (!$user->admin) {
	accessforbidden();
}

$langs->loadLangs(array("admin", "lemoncrm@lemoncrm"));

$action = GETPOST('action', 'aZ09');
$mode = GETPOST('mode', 'alpha');

// Save settings
if ($action == 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (GETPOST('token', 'alpha') != newToken()) {
		accessforbidden('Bad value for CSRF token');
	}
	$error = 0;

	$aikey = GETPOST('LEMONCRM_AI_API_KEY', 'alpha');
	$aimodel = GETPOST('LEMONCRM_AI_MODEL', 'alpha');

	if (dolibarr_set_const($db, 'LEMONCRM_AI_API_KEY', $aikey, 'chaine', 0, '', $conf->entity) < 0) {
		$error++;
	}
	if (dolibarr_set_const($db, 'LEMONCRM_AI_MODEL', trim($aimodel), 'chaine', 0, '', $conf->entity) < 0) {
		$error++;
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

// Display
llxHeader('', $langs->trans("LemonCRMSetup"));

$head = lemoncrm_admin_prepare_head();
print dol_get_fiche_head($head, ($mode == 'about' ? 'about' : 'settings'), $langs->trans("LemonCRMSetup"), -1, 'object_lemoncrm@lemoncrm');

if ($mode == 'about') {
	print '<div class="fichecenter">';
	print '<p><strong>LemonCRM</strong> v1.0.0</p>';
	print '<p>Module de suivi des interactions clients et prospects pour Dolibarr.</p>';
	print '<p>Developpeur : SASU LEMON - <a href="https://hellolemon.fr" target="_blank">hellolemon.fr</a></p>';
	print '</div>';
} else {
	$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
	print load_fiche_titre($langs->trans("LemonCRMSetup"), $linkback, 'title_setup');

	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Parameter").'</td>';
	print '<td>'.$langs->trans("Value").'</td>';
	print '</tr>';

	// AI API Key (Phase 2)
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("LemonCRMAIApiKey").'</td>';
	print '<td>';
	print '<input type="password" name="LEMONCRM_AI_API_KEY" class="flat minwidth400" value="'.dol_escape_htmltag(getDolGlobalString('LEMONCRM_AI_API_KEY')).'">';
	print '</td>';
	print '</tr>';

	// AI Model
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("LemonCRMAIModel").'</td>';
	print '<td>';
	$currentModel = getDolGlobalString('LEMONCRM_AI_MODEL', 'claude-sonnet-4-6');
	print '<select name="LEMONCRM_AI_MODEL" class="flat">';
	print '<option value="claude-sonnet-4-6"'.($currentModel == 'claude-sonnet-4-6' ? ' selected' : '').'>Claude Sonnet 4.6</option>';
	print '<option value="claude-haiku-4-5-20251001"'.($currentModel == 'claude-haiku-4-5-20251001' ? ' selected' : '').'>Claude Haiku 4.5</option>';
	print '<option value="claude-opus-4-6"'.($currentModel == 'claude-opus-4-6' ? ' selected' : '').'>Claude Opus 4.6</option>';
	print '</select>';
	print '</td>';
	print '</tr>';

	print '</table>';
	print '<br>';
	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
	print '</div>';
	print '</form>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
