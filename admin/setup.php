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

$mode = GETPOST('mode', 'alpha');

// Display
llxHeader('', $langs->trans("LemonCRMSetup"));

$head = lemoncrm_admin_prepare_head();
print dol_get_fiche_head($head, ($mode == 'about' ? 'about' : 'settings'), $langs->trans("LemonCRMSetup"), -1, 'object_lemoncrm@lemoncrm');

if ($mode == 'about') {
	print '<div class="fichecenter">';
	print '<p><strong>LemonCRM</strong> v1.0.0</p>';
	print '<p>Module de suivi des interactions clients et prospects pour Dolibarr.</p>';
	print '<p>Développeur : SASU LEMON - <a href="https://hellolemon.fr" target="_blank">hellolemon.fr</a></p>';
	print '</div>';
} else {
	$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
	print load_fiche_titre($langs->trans("LemonCRMSetup"), $linkback, 'title_setup');

	print '<div class="opacitymedium">';
	print 'Aucun paramètre à configurer pour le moment. Le module fonctionne directement après activation.';
	print '</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
