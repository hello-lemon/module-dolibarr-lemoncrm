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

// Save settings
if (GETPOST('action', 'alpha') == 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if (GETPOST('token', 'alpha') != newToken()) {
		accessforbidden('Bad value for CSRF token');
	}
	$persist = GETPOSTINT('LEMONCRM_QUICKLOG_PERSIST');
	dolibarr_set_const($db, 'LEMONCRM_QUICKLOG_PERSIST', $persist, 'chaine', 0, '', $conf->entity);

	$menuLabel = GETPOST('LEMONCRM_MENU_LABEL', 'alpha');
	if (!empty($menuLabel)) {
		dolibarr_set_const($db, 'LEMONCRM_MENU_LABEL', $menuLabel, 'chaine', 0, '', $conf->entity);
	}
	$menuIcon = GETPOST('LEMONCRM_MENU_ICON', 'alpha');
	if (!empty($menuIcon)) {
		dolibarr_set_const($db, 'LEMONCRM_MENU_ICON', $menuIcon, 'chaine', 0, '', $conf->entity);
	}

	// Update menu in database directly (avoid deactivate/reactivate)
	if (!empty($menuLabel)) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."menu SET titre = '".$db->escape($menuLabel)."'";
		$sql .= " WHERE module = 'lemoncrm' AND type = 'top' AND mainmenu = 'lemon'";
		$sql .= " AND entity = ".$conf->entity;
		$db->query($sql);
	}
	if (!empty($menuIcon)) {
		$prefix = img_picto('', $menuIcon, 'class="fas paddingright pictofixedwidth"');
		$sql = "UPDATE ".MAIN_DB_PREFIX."menu SET prefix = '".$db->escape($prefix)."'";
		$sql .= " WHERE module = 'lemoncrm' AND type = 'top' AND mainmenu = 'lemon'";
		$sql .= " AND entity = ".$conf->entity;
		$db->query($sql);
	}
	// Save type icons mapping
	$iconTypes = GETPOST('icon_type', 'array');
	if (is_array($iconTypes) && !empty($iconTypes)) {
		$iconMap = array();
		foreach ($iconTypes as $code => $icon) {
			$icon = trim($icon);
			if (!empty($icon)) {
				$iconMap[$code] = $icon;
			}
		}
		dolibarr_set_const($db, 'LEMONCRM_TYPE_ICONS', json_encode($iconMap), 'chaine', 0, '', $conf->entity);
	}

	setEventMessages('Configuration sauvegardée', null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

// Display
llxHeader('', $langs->trans("LemonCRMSetup"));

$head = lemoncrm_admin_prepare_head();
print dol_get_fiche_head($head, ($mode == 'about' ? 'about' : 'settings'), $langs->trans("LemonCRMSetup"), -1, 'object_lemoncrm@lemoncrm');

if ($mode == 'about') {
	print '<div class="fichecenter">';
	print '<p><strong>LemonCRM</strong> v1.0.1</p>';
	print '<p>Module de suivi des interactions clients et prospects pour Dolibarr.</p>';
	print '<p>Developpeur : SASU LEMON - <a href="https://hellolemon.fr" target="_blank">hellolemon.fr</a></p>';
	print '<p>Contributeur : protectora</p>';

	print '<br><h3>Dictionnaires</h3>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>Dictionnaire</td><td>Ou le trouver</td><td>Usage</td></tr>';
	print '<tr class="oddeven"><td><strong>Types d\'interaction</strong></td>';
	print '<td><a href="'.DOL_URL_ROOT.'/admin/dict.php?mainmenu=home&id=25">Admin > Dictionnaires > Types d\'evenements de l\'agenda</a></td>';
	print '<td>Les types LemonCRM utilisent le prefixe <strong>LCRM_</strong> (LCRM_TEL, LCRM_EMAIL, etc.). Seuls ces types apparaissent dans le Quicklog. Pour ajouter un type, creez-le avec un code commencant par LCRM_.</td></tr>';
	print '<tr class="oddeven"><td><strong>Sentiments</strong></td>';
	print '<td><a href="'.DOL_URL_ROOT.'/admin/dict.php?mainmenu=home">Admin > Dictionnaires > Sentiments CRM</a></td>';
	print '<td>Sentiments associes aux interactions (positif, neutre, negatif, ou personnalises).</td></tr>';
	print '<tr class="oddeven"><td><strong>Statuts prospect</strong></td>';
	print '<td><a href="'.DOL_URL_ROOT.'/admin/dict.php?mainmenu=home">Admin > Dictionnaires > Statuts prospect CRM</a></td>';
	print '<td>Etapes du cycle de vente (cold, warm, hot, negotiation, won, lost, ou personnalises).</td></tr>';
	print '</table>';
	print '</div>';
} else {
	$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
	print load_fiche_titre($langs->trans("LemonCRMSetup"), $linkback, 'title_setup');

	$persistValue = getDolGlobalInt('LEMONCRM_QUICKLOG_PERSIST', 0);
	$menuLabelValue = getDolGlobalString('LEMONCRM_MENU_LABEL', 'Lemon');
	$menuIconValue = getDolGlobalString('LEMONCRM_MENU_ICON', 'fa-lemon');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="save">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">Apparence</td></tr>';

	// Menu label
	print '<tr class="oddeven">';
	print '<td>Nom du menu principal</td>';
	print '<td><input type="text" name="LEMONCRM_MENU_LABEL" class="flat minwidth200" value="'.dol_escape_htmltag($menuLabelValue).'">';
	print ' <span class="opacitymedium">(ex: Lemon, CRM, Gestion, Mon Entreprise)</span>';
	print '</td>';
	print '</tr>';

	// Menu icon
	print '<tr class="oddeven">';
	print '<td>Icône du menu principal</td>';
	print '<td><input type="text" name="LEMONCRM_MENU_ICON" class="flat minwidth200" value="'.dol_escape_htmltag($menuIconValue).'">';
	print ' <span class="opacitymedium">(ex: fa-lemon, fa-handshake, fa-briefcase, fa-building)</span>';
	print ' <span class="fas '.dol_escape_htmltag($menuIconValue).'" style="margin-left:8px;font-size:1.2em"></span>';
	print '</td>';
	print '</tr>';

	print '<tr class="liste_titre"><td colspan="2">Icônes des types d\'interaction</td></tr>';
	print '<tr class="oddeven"><td colspan="2" class="opacitymedium">';
	print 'Les types d\'interaction se configurent dans <a href="'.DOL_URL_ROOT.'/admin/dict.php?mainmenu=home&id=25">Admin > Dictionnaires > Types d\'evenements de l\'agenda</a> (codes commencant par LCRM_).';
	print '<br>Icones disponibles : <a href="https://fontawesome.com/v5/search?m=free" target="_blank" rel="noopener">Font Awesome 5 (free)</a> - copier la classe CSS (ex: fas fa-phone-alt, far fa-envelope, fas fa-handshake)';
	print '</td></tr>';

	// Load current icons from config or defaults
	$defaultIcons = array(
		'LCRM_TEL' => 'fas fa-phone-alt',
		'LCRM_EMAIL' => 'fas fa-envelope',
		'LCRM_LINKEDIN' => 'fab fa-linkedin',
		'LCRM_TEAMS' => 'fas fa-video',
		'LCRM_RDV' => 'far fa-calendar-check',
		'LCRM_WHATSAPP' => 'fab fa-whatsapp',
		'LCRM_NOTE' => 'far fa-comment',
		'LCRM_RELANCE' => 'fas fa-bell',
	);
	$savedIcons = json_decode(getDolGlobalString('LEMONCRM_TYPE_ICONS', '{}'), true);
	if (!is_array($savedIcons)) $savedIcons = array();

	$allTypes = lemoncrm_get_interaction_types(false);
	foreach ($allTypes as $code => $label) {
		$currentIcon = $savedIcons[$code] ?? $defaultIcons[$code] ?? 'far fa-comment';
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($label).' <span class="opacitymedium">('.$code.')</span></td>';
		print '<td>';
		print '<input type="text" name="icon_type['.$code.']" class="flat minwidth200" value="'.dol_escape_htmltag($currentIcon).'">';
		print ' <span class="'.dol_escape_htmltag($currentIcon).'" style="margin-left:8px;font-size:1.2em"></span>';
		print '</td>';
		print '</tr>';
	}

	print '<tr class="liste_titre"><td colspan="2">Quicklog</td></tr>';

	// Quicklog persist thirdparty
	print '<tr class="oddeven">';
	print '<td>Comportement du tiers dans le Quicklog</td>';
	print '<td>';
	print '<select name="LEMONCRM_QUICKLOG_PERSIST" class="flat">';
	print '<option value="0"'.($persistValue == 0 ? ' selected' : '').'>La page prime : le tiers de la page en cours remplace toujours la sélection manuelle</option>';
	print '<option value="1"'.($persistValue == 1 ? ' selected' : '').'>Persistant : le tiers sélectionné manuellement reste actif tant que le navigateur est ouvert</option>';
	print '</select>';
	print '</td>';
	print '</tr>';

	print '</table>';
	print '<br><div class="center"><input type="submit" class="button" value="'.$langs->trans("Save").'"></div>';
	print '</form>';

	print '<div class="opacitymedium" style="margin-top:16px">';
	print '<span class="fas fa-info-circle"></span> Le changement de nom et d\'icône du menu prend effet immédiatement (rechargez la page).';
	print '</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
