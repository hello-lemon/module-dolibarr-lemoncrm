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

	// Save AI settings
	$aiEnabled = GETPOSTINT('LEMONCRM_AI_ENABLED');
	dolibarr_set_const($db, 'LEMONCRM_AI_ENABLED', $aiEnabled, 'chaine', 0, '', $conf->entity);

	$aiApiKey = GETPOST('LEMONCRM_AI_API_KEY', 'alpha');
	if (!empty($aiApiKey) && $aiApiKey !== '••••••••') {
		dolibarr_set_const($db, 'LEMONCRM_AI_API_KEY', $aiApiKey, 'chaine', 0, '', $conf->entity);
	}

	$aiModel = GETPOST('LEMONCRM_AI_MODEL', 'alpha');
	if (!empty($aiModel)) {
		dolibarr_set_const($db, 'LEMONCRM_AI_MODEL', $aiModel, 'chaine', 0, '', $conf->entity);
	}

	$aiSystemPrompt = GETPOST('LEMONCRM_AI_SYSTEM_PROMPT', 'restricthtml');
	dolibarr_set_const($db, 'LEMONCRM_AI_SYSTEM_PROMPT', $aiSystemPrompt, 'chaine', 0, '', $conf->entity);

	// Save AI objectives
	$objCodes = GETPOST('ai_obj_code', 'array');
	$objLabels = GETPOST('ai_obj_label', 'array');
	$objPrompts = GETPOST('ai_obj_prompt', 'array');
	if (is_array($objCodes)) {
		$objectives = array();
		foreach ($objCodes as $i => $code) {
			$code = trim($code);
			$label = trim($objLabels[$i] ?? '');
			$prompt = trim($objPrompts[$i] ?? '');
			if (!empty($code) && !empty($label)) {
				$objectives[] = array('code' => $code, 'label' => $label, 'prompt' => $prompt);
			}
		}
		dolibarr_set_const($db, 'LEMONCRM_AI_OBJECTIVES', json_encode($objectives), 'chaine', 0, '', $conf->entity);
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

	// ===== AI CONFIGURATION =====
	dol_include_once('/lemoncrm/class/lemoncrm_ai.class.php');

	$aiEnabled = getDolGlobalInt('LEMONCRM_AI_ENABLED', 0);
	$aiApiKey = getDolGlobalString('LEMONCRM_AI_API_KEY', '');
	$aiModel = getDolGlobalString('LEMONCRM_AI_MODEL', 'claude-sonnet-4-20250514');
	$aiSystemPrompt = getDolGlobalString('LEMONCRM_AI_SYSTEM_PROMPT', '');
	$aiObjectives = LemonCRMAI::getObjectives();

	print '<tr class="liste_titre"><td colspan="2">Intelligence Artificielle</td></tr>';

	// Enable/disable
	print '<tr class="oddeven">';
	print '<td>Activer la generation IA</td>';
	print '<td>';
	print '<select name="LEMONCRM_AI_ENABLED" class="flat">';
	print '<option value="0"'.($aiEnabled == 0 ? ' selected' : '').'>Desactive</option>';
	print '<option value="1"'.($aiEnabled == 1 ? ' selected' : '').'>Active</option>';
	print '</select>';
	print '</td>';
	print '</tr>';

	// API Key
	print '<tr class="oddeven">';
	print '<td>Cle API Anthropic</td>';
	print '<td>';
	$displayKey = !empty($aiApiKey) ? '••••••••' : '';
	print '<input type="password" name="LEMONCRM_AI_API_KEY" class="flat minwidth300" value="'.dol_escape_htmltag($displayKey).'" autocomplete="off">';
	print ' <span class="opacitymedium">(Claude API - <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">obtenir une cle</a>)</span>';
	print '</td>';
	print '</tr>';

	// Model
	print '<tr class="oddeven">';
	print '<td>Modele Claude</td>';
	print '<td>';
	print '<select name="LEMONCRM_AI_MODEL" class="flat minwidth200">';
	$models = array(
		'claude-sonnet-4-20250514' => 'Claude Sonnet 4 (recommande)',
		'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (rapide, economique)',
	);
	foreach ($models as $code => $label) {
		$sel = ($aiModel == $code) ? ' selected' : '';
		print '<option value="'.$code.'"'.$sel.'>'.$label.'</option>';
	}
	print '</select>';
	print '</td>';
	print '</tr>';

	// System prompt
	print '<tr class="oddeven">';
	print '<td style="vertical-align:top">Prompt global<br><span class="opacitymedium" style="font-size:0.82em">Regles, ton, infos entreprise.<br>S\'applique a toutes les generations.</span></td>';
	print '<td>';
	print '<textarea name="LEMONCRM_AI_SYSTEM_PROMPT" class="flat" rows="5" style="width:100%;max-width:600px;box-sizing:border-box" placeholder="Ex: Tu es un commercial de [Nom Entreprise]. Tu vends [services]. Ton ton est professionnel mais chaleureux. Ne tutoie jamais. Mentionne toujours notre garantie satisfait ou rembourse.">';
	print dol_escape_htmltag($aiSystemPrompt);
	print '</textarea>';
	print '</td>';
	print '</tr>';

	// Objectives
	print '<tr class="oddeven">';
	print '<td style="vertical-align:top">Objectifs de generation<br><span class="opacitymedium" style="font-size:0.82em">Chaque objectif a un prompt<br>qui s\'ajoute au prompt global.</span></td>';
	print '<td>';
	print '<table class="noborder" id="lcrm-ai-objectives-table" style="max-width:600px">';
	print '<tr class="liste_titre"><td>Code</td><td>Nom</td><td>Prompt specifique</td><td></td></tr>';
	foreach ($aiObjectives as $i => $obj) {
		print '<tr class="oddeven lcrm-ai-obj-row">';
		print '<td><input type="text" name="ai_obj_code[]" class="flat" style="width:80px" value="'.dol_escape_htmltag($obj['code']).'"></td>';
		print '<td><input type="text" name="ai_obj_label[]" class="flat" style="width:160px" value="'.dol_escape_htmltag($obj['label']).'"></td>';
		print '<td><textarea name="ai_obj_prompt[]" class="flat" rows="2" style="width:100%;box-sizing:border-box">'.dol_escape_htmltag($obj['prompt']).'</textarea></td>';
		print '<td><button type="button" class="button" onclick="this.closest(\'tr\').remove()" title="Supprimer"><span class="fa fa-trash"></span></button></td>';
		print '</tr>';
	}
	print '</table>';
	print '<button type="button" class="button" id="lcrm-ai-add-obj" style="margin-top:6px"><span class="fa fa-plus"></span> Ajouter un objectif</button>';
	print '<script>';
	print 'document.getElementById("lcrm-ai-add-obj").addEventListener("click", function() {';
	print '  var table = document.getElementById("lcrm-ai-objectives-table");';
	print '  var row = table.insertRow(-1);';
	print '  row.className = "oddeven lcrm-ai-obj-row";';
	print '  row.innerHTML = \'<td><input type="text" name="ai_obj_code[]" class="flat" style="width:80px" placeholder="code"></td>\'';
	print '    + \'<td><input type="text" name="ai_obj_label[]" class="flat" style="width:160px" placeholder="Nom de l\\\'objectif"></td>\'';
	print '    + \'<td><textarea name="ai_obj_prompt[]" class="flat" rows="2" style="width:100%;box-sizing:border-box" placeholder="Prompt specifique pour cet objectif..."></textarea></td>\'';
	print '    + \'<td><button type="button" class="button" onclick="this.closest(\\\'tr\\\').remove()" title="Supprimer"><span class="fa fa-trash"></span></button></td>\';';
	print '});';
	print '</script>';
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
