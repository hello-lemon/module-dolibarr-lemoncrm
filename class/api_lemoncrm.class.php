<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * API REST LemonCRM : interactions
 */

dol_include_once('/lemoncrm/class/lemoncrm_interaction.class.php');
dol_include_once('/lemoncrm/core/lib/lemoncrm.lib.php');

use Luracast\Restler\RestException;

/**
 * API LemonCRM — interactions clients/prospects.
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Lemoncrm extends DolibarrApi
{
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * Liste des interactions
	 *
	 * @url GET interactions
	 * @param int    $socid  Filtrer par tiers (0 = tous)
	 * @param int    $limit  Nombre max de résultats (défaut 50, max 200)
	 * @param int    $page   Page (0 = première)
	 * @return array
	 * @throws RestException
	 */
	public function getInteractions($socid = 0, $limit = 50, $page = 0)
	{
		global $user, $conf;
		if (DolibarrApiAccess::$user) {
			$user = DolibarrApiAccess::$user;
		}

		if (!$user->hasRight('lemoncrm', 'interaction', 'read')) {
			throw new RestException(403, 'Accès refusé');
		}

		$limit = min(max((int) $limit, 1), 200);
		$offset = max((int) $page, 0) * $limit;

		$sql = "SELECT i.rowid FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
		$sql .= " WHERE i.entity = ".((int) $conf->entity);
		if ((int) $socid > 0) {
			$sql .= " AND i.fk_soc = ".((int) $socid);
		}
		$sql .= " ORDER BY i.date_interaction DESC";
		$sql .= $this->db->plimit($limit, $offset);

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(get_class($this)."::getInteractions error: ".$this->db->lasterror(), LOG_ERR);
			throw new RestException(500, 'Erreur lors de la lecture');
		}

		$result = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$interaction = new LemonCRMInteraction($this->db);
			if ($interaction->fetch((int) $obj->rowid) > 0) {
				$result[] = $this->_formatInteraction($interaction);
			}
		}
		return $result;
	}

	/**
	 * Détail d'une interaction
	 *
	 * @url GET interactions/{id}
	 * @param int $id Id de l'interaction
	 * @return array
	 * @throws RestException
	 */
	public function getInteraction($id)
	{
		global $user;
		if (DolibarrApiAccess::$user) {
			$user = DolibarrApiAccess::$user;
		}

		if (!$user->hasRight('lemoncrm', 'interaction', 'read')) {
			throw new RestException(403, 'Accès refusé');
		}

		$interaction = new LemonCRMInteraction($this->db);
		if ($interaction->fetch((int) $id) <= 0) {
			throw new RestException(404, 'Interaction non trouvée');
		}

		return $this->_formatInteraction($interaction);
	}

	/**
	 * Créer une interaction
	 *
	 * Champs : interaction_type (obligatoire, ex LCRM_TEL), date_interaction
	 * ('YYYY-MM-DD HH:MM', défaut maintenant), fk_soc, fk_socpeople, direction
	 * (IN/OUT), summary, duration_minutes, sentiment, prospect_status,
	 * followup_action, followup_date (YYYY-MM-DD), followup_time (HH:MM),
	 * followup_mode (phone/email/linkedin), fk_project, fk_parent.
	 *
	 * @url POST interactions
	 * @param array $request_data Données
	 * @return array
	 * @throws RestException
	 */
	public function postInteraction($request_data = null)
	{
		global $user;
		if (DolibarrApiAccess::$user) {
			$user = DolibarrApiAccess::$user;
		}

		if (!$user->hasRight('lemoncrm', 'interaction', 'write')) {
			throw new RestException(403, 'Accès refusé');
		}

		$type = isset($request_data['interaction_type']) ? substr(trim($request_data['interaction_type']), 0, 32) : '';
		if (empty($type)) {
			throw new RestException(400, 'interaction_type est obligatoire');
		}
		$knownTypes = lemoncrm_get_interaction_types(false);
		if (!isset($knownTypes[$type])) {
			throw new RestException(400, 'interaction_type inconnu (codes LCRM_* du dictionnaire agenda)');
		}

		$interaction = new LemonCRMInteraction($this->db);
		$interaction->interaction_type = $type;
		$interaction->fk_soc = isset($request_data['fk_soc']) ? (int) $request_data['fk_soc'] : 0;
		$interaction->fk_socpeople = isset($request_data['fk_socpeople']) ? (int) $request_data['fk_socpeople'] : 0;
		$interaction->fk_project = isset($request_data['fk_project']) ? (int) $request_data['fk_project'] : 0;
		$interaction->fk_parent = isset($request_data['fk_parent']) ? (int) $request_data['fk_parent'] : 0;
		$interaction->direction = (isset($request_data['direction']) && strtoupper($request_data['direction']) === 'IN') ? 'IN' : 'OUT';
		$interaction->summary = isset($request_data['summary']) ? substr($request_data['summary'], 0, 65000) : '';
		$interaction->duration_minutes = isset($request_data['duration_minutes']) ? (int) $request_data['duration_minutes'] : 0;
		$interaction->sentiment = isset($request_data['sentiment']) ? substr(trim($request_data['sentiment']), 0, 64) : '';
		$interaction->prospect_status = isset($request_data['prospect_status']) ? substr(trim($request_data['prospect_status']), 0, 64) : '';
		$interaction->followup_action = isset($request_data['followup_action']) ? substr($request_data['followup_action'], 0, 65000) : '';
		$interaction->followup_mode = isset($request_data['followup_mode']) ? substr(trim($request_data['followup_mode']), 0, 32) : '';

		$followupDate = isset($request_data['followup_date']) ? trim($request_data['followup_date']) : '';
		if (!empty($followupDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $followupDate)) {
			throw new RestException(400, 'followup_date attendu au format YYYY-MM-DD');
		}
		$interaction->followup_date = $followupDate;
		$followupTime = isset($request_data['followup_time']) ? trim($request_data['followup_time']) : '';
		if (!empty($followupTime) && !preg_match('/^\d{1,2}:\d{2}$/', $followupTime)) {
			throw new RestException(400, 'followup_time attendu au format HH:MM');
		}
		$interaction->followup_time = $followupTime;

		$dateStr = isset($request_data['date_interaction']) ? trim($request_data['date_interaction']) : '';
		if (!empty($dateStr)) {
			if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $dateStr, $m)) {
				throw new RestException(400, "date_interaction attendu au format 'YYYY-MM-DD HH:MM'");
			}
			$interaction->date_interaction = dol_mktime((int) $m[4], (int) $m[5], 0, (int) $m[2], (int) $m[3], (int) $m[1]);
		} else {
			$interaction->date_interaction = dol_now();
		}

		$result = $interaction->create($user);
		if ($result <= 0) {
			dol_syslog(get_class($this)."::postInteraction error: ".$interaction->error, LOG_ERR);
			throw new RestException(500, 'Erreur lors de la création');
		}

		return array('success' => true, 'id' => (int) $result, 'ref' => $interaction->ref);
	}

	/**
	 * Marquer la relance d'une interaction comme faite
	 *
	 * @url POST interactions/{id}/followupdone
	 * @param int $id Id de l'interaction
	 * @return array
	 * @throws RestException
	 */
	public function postFollowupDone($id)
	{
		global $user;
		if (DolibarrApiAccess::$user) {
			$user = DolibarrApiAccess::$user;
		}

		if (!$user->hasRight('lemoncrm', 'interaction', 'write')) {
			throw new RestException(403, 'Accès refusé');
		}

		$interaction = new LemonCRMInteraction($this->db);
		if ($interaction->fetch((int) $id) <= 0) {
			throw new RestException(404, 'Interaction non trouvée');
		}

		if ($interaction->markFollowupDone($user) <= 0) {
			dol_syslog(get_class($this)."::postFollowupDone error: ".$interaction->error, LOG_ERR);
			throw new RestException(500, 'Erreur lors de la mise à jour');
		}

		return array('success' => true, 'id' => (int) $id);
	}

	/**
	 * Supprimer une interaction
	 *
	 * @url DELETE interactions/{id}
	 * @param int $id Id de l'interaction
	 * @return array
	 * @throws RestException
	 */
	public function deleteInteraction($id)
	{
		global $user;
		if (DolibarrApiAccess::$user) {
			$user = DolibarrApiAccess::$user;
		}

		if (!$user->hasRight('lemoncrm', 'interaction', 'delete')) {
			throw new RestException(403, 'Accès refusé');
		}

		$interaction = new LemonCRMInteraction($this->db);
		if ($interaction->fetch((int) $id) <= 0) {
			throw new RestException(404, 'Interaction non trouvée');
		}

		if ($interaction->delete($user) <= 0) {
			dol_syslog(get_class($this)."::deleteInteraction error: ".$interaction->error, LOG_ERR);
			throw new RestException(500, 'Erreur lors de la suppression');
		}

		return array('success' => true, 'id' => (int) $id);
	}

	/**
	 * Mise en forme d'une interaction pour la sortie API
	 *
	 * @param LemonCRMInteraction $i Interaction
	 * @return array
	 */
	private function _formatInteraction(LemonCRMInteraction $i)
	{
		return array(
			'id' => (int) $i->id,
			'ref' => $i->ref,
			'interaction_type' => $i->interaction_type,
			'date_interaction' => $i->date_interaction ? dol_print_date($i->date_interaction, '%Y-%m-%d %H:%M:%S') : null,
			'direction' => $i->direction,
			'fk_soc' => (int) $i->fk_soc,
			'thirdparty_name' => $i->thirdparty_name,
			'fk_socpeople' => (int) $i->fk_socpeople,
			'contact_name' => trim((string) $i->contact_name) ?: null,
			'summary' => $i->summary,
			'duration_minutes' => (int) $i->duration_minutes,
			'sentiment' => $i->sentiment,
			'prospect_status' => $i->prospect_status,
			'followup_action' => $i->followup_action,
			'followup_date' => $i->followup_date,
			'followup_time' => $i->followup_time,
			'followup_mode' => $i->followup_mode,
			'followup_done' => (int) $i->followup_done,
			'fk_parent' => (int) $i->fk_parent,
			'fk_project' => (int) $i->fk_project,
			'fk_actioncomm' => (int) $i->fk_actioncomm,
		);
	}
}
