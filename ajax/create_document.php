<?php
/**
 * LemonCRM - Créer un devis/facture/projet depuis une interaction
 * Redirige vers le document créé
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	die('Method not allowed');
}

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/lemoncrm/class/lemoncrm_interaction.class.php');
dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');

// CSRF check
if (GETPOST('token', 'alpha') != newToken()) {
	accessforbidden('Bad CSRF token');
}

$type = GETPOST('type', 'alpha'); // propal, facture, projet
$interactionId = GETPOSTINT('interaction_id');

if (empty($type) || empty($interactionId)) {
	setEventMessages('Paramètres manquants', null, 'errors');
	header('Location: '.dol_buildpath('/lemoncrm/dashboard.php', 1));
	exit;
}

// Fetch interaction
$interaction = new LemonCRMInteraction($db);
if ($interaction->fetch($interactionId) <= 0 || $interaction->fk_soc <= 0) {
	setEventMessages('Interaction non trouvée ou sans tiers', null, 'errors');
	header('Location: '.dol_buildpath('/lemoncrm/dashboard.php', 1));
	exit;
}

// Build description from interaction
$desc = strip_tags(str_replace(array('<br>', '<br/>', '<br />'), "\n", $interaction->summary));
$desc = str_replace(array("\\r\\n", "\\n", "\\r"), "\n", $desc);
$desc = trim($desc);
$dateStr = lemoncrm_format_date_fr($interaction->date_interaction, 'long');
$types = lemoncrm_get_interaction_types();
$typeLabel = $types[$interaction->interaction_type] ?? $interaction->interaction_type;
$notePrivate = $typeLabel.' - '.$dateStr;
if ($interaction->duration_minutes > 0) $notePrivate .= ' ('.$interaction->duration_minutes.' min)';
$notePrivate .= "\n".$desc;

if ($type === 'propal' && $user->hasRight('propal', 'creer')) {
	require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';

	$soc = new Societe($db);
	$soc->fetch($interaction->fk_soc);

	$propal = new Propal($db);
	$propal->socid = $interaction->fk_soc;
	$propal->date = dol_now();
	$propal->duree_validite = 30;
	$propal->cond_reglement_id = $soc->cond_reglement_id ?: 1;
	$propal->mode_reglement_id = $soc->mode_reglement_id ?: 0;
	$propal->note_private = $notePrivate;
	$propal->entity = $conf->entity;

	$result = $propal->create($user);
	if ($result > 0) {
		// Ajouter une ligne avec la description et la durée
		$lineDesc = $desc;
		$qty = $interaction->duration_minutes > 0 ? round($interaction->duration_minutes / 60, 2) : 1;
		$propal->addline($lineDesc, 0, $qty, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);
		// Lier l'interaction au devis
		$propal->add_object_linked('lemoncrm_interaction', $interaction->id);
		header('Location: '.DOL_URL_ROOT.'/comm/propal/card.php?id='.$result);
		exit;
	}
	setEventMessages($propal->error, $propal->errors, 'errors');

} elseif ($type === 'facture' && $user->hasRight('facture', 'creer')) {
	require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

	if (!isset($soc) || $soc->id != $interaction->fk_soc) {
		$soc = new Societe($db);
		$soc->fetch($interaction->fk_soc);
	}

	$facture = new Facture($db);
	$facture->socid = $interaction->fk_soc;
	$facture->date = dol_now();
	$facture->type = Facture::TYPE_STANDARD;
	$facture->cond_reglement_id = $soc->cond_reglement_id ?: 1;
	$facture->mode_reglement_id = $soc->mode_reglement_id ?: 0;
	$facture->note_private = $notePrivate;
	$facture->entity = $conf->entity;

	$result = $facture->create($user);
	if ($result > 0) {
		// Ajouter une ligne avec la description et la durée
		$lineDesc = $desc;
		$qty = $interaction->duration_minutes > 0 ? round($interaction->duration_minutes / 60, 2) : 1;
		$facture->addline($lineDesc, 0, $qty, 0, 0, 0, 0, 0, '', '', 0, 0, 0, 'HT', 0, 1);
		// Lier l'interaction à la facture
		$facture->add_object_linked('lemoncrm_interaction', $interaction->id);
		header('Location: '.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$result);
		exit;
	}
	setEventMessages($facture->error, $facture->errors, 'errors');

} elseif ($type === 'projet' && $user->hasRight('projet', 'creer')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

	// Chercher un projet ouvert existant pour ce tiers
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet";
	$sql .= " WHERE fk_soc = ".(int)$interaction->fk_soc;
	$sql .= " AND fk_statut = 1"; // ouvert
	$sql .= " AND entity IN (".getEntity('projet').")";
	$sql .= " ORDER BY rowid DESC LIMIT 1";
	$resql = $db->query($sql);
	$existingProjectId = 0;
	if ($resql && $db->num_rows($resql)) {
		$obj = $db->fetch_object($resql);
		$existingProjectId = $obj->rowid;
	}

	if ($existingProjectId > 0) {
		// Redirige vers la création de tâche dans ce projet
		header('Location: '.DOL_URL_ROOT.'/projet/tasks.php?id='.$existingProjectId.'&action=create');
		exit;
	} else {
		// Redirige vers la création de projet pour ce tiers
		header('Location: '.DOL_URL_ROOT.'/projet/card.php?action=create&socid='.$interaction->fk_soc);
		exit;
	}
}

// Fallback
header('Location: '.dol_buildpath('/lemoncrm/dashboard.php', 1));
exit;
