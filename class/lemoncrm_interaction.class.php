<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LemonCRM Interaction business class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class LemonCRMInteraction extends CommonObject
{
	public $element = 'lemoncrm_interaction';
	public $table_element = 'lemoncrm_interaction';
	public $picto = 'object_lemoncrm@lemoncrm';

	public $ref;
	public $fk_actioncomm;
	public $fk_actioncomm_followup;
	public $interaction_type;
	public $fk_soc;
	public $fk_socpeople;
	public $fk_user_author;
	public $summary;
	public $followup_action;
	public $followup_date;
	public $followup_time;
	public $followup_done = 0;
	public $followup_mode;
	public $date_interaction;
	public $duration_minutes = 0;
	public $direction = 'OUT';
	public $sentiment;
	public $prospect_status;
	public $fk_parent;
	public $fk_project;
	public $status = 1;
	public $datec;
	public $tms;
	public $entity;

	// Related objects cache
	public $thirdparty_name;
	public $contact_name;

	public $error = '';
	public $errors = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * SQL fragment for nullable string: quoted+escaped value or NULL.
	 */
	private function sqlNullableString($value)
	{
		return !empty($value) ? "'".$this->db->escape($value)."'" : "NULL";
	}

	/**
	 * SQL fragment for nullable foreign key: positive int or NULL.
	 */
	private function sqlNullableFk($value)
	{
		return ($value > 0) ? ((int) $value) : "NULL";
	}

	/**
	 * Create interaction in database + actioncomm (double ecriture)
	 *
	 * @param User $user User creating
	 * @param int $notrigger 0=triggers, 1=no triggers
	 * @return int >0 if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->db->begin();

		$error = 0;

		dol_include_once('/lemoncrm/core/lib/lemoncrm.lib.php');
		$this->fk_user_author = $user->id;
		$this->datec = dol_now();
		$this->entity = $conf->entity;

		// 1. Create actioncomm in Dolibarr agenda
		$actioncomm_id = $this->createActionComm($user);
		if ($actioncomm_id < 0) {
			$error++;
		} else {
			$this->fk_actioncomm = $actioncomm_id;
		}

		if (!$error) {
			// 2. Create lemoncrm_interaction record.
			// La ref MAX()+1 peut entrer en collision entre deux créations simultanées
			// (index UNIQUE ref+entity) : on régénère et on retente.
			$maxAttempts = 3;
			for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
				$this->ref = lemoncrm_get_next_ref($this->db);

				$sql = "INSERT INTO ".MAIN_DB_PREFIX."lemoncrm_interaction (";
				$sql .= "ref, fk_actioncomm, interaction_type, fk_soc, fk_socpeople,";
				$sql .= " fk_user_author, summary, followup_action, followup_date, followup_time, followup_done,";
				$sql .= " followup_mode, date_interaction, duration_minutes, direction,";
				$sql .= " sentiment, prospect_status, fk_parent, fk_project, status, datec, entity";
				$sql .= ") VALUES (";
				$sql .= "'".$this->db->escape($this->ref)."',";
				$sql .= " ".((int) $this->fk_actioncomm).",";
				$sql .= " '".$this->db->escape($this->interaction_type)."',";
				$sql .= " ".$this->sqlNullableFk($this->fk_soc).",";
				$sql .= " ".$this->sqlNullableFk($this->fk_socpeople).",";
				$sql .= " ".((int) $this->fk_user_author).",";
				$sql .= " ".$this->sqlNullableString($this->summary).",";
				$sql .= " ".$this->sqlNullableString($this->followup_action).",";
				$sql .= " ".$this->sqlNullableString($this->followup_date).",";
				$sql .= " ".$this->sqlNullableString($this->followup_time).",";
				$sql .= " ".((int) $this->followup_done).",";
				$sql .= " ".$this->sqlNullableString($this->followup_mode).",";
				$sql .= " '".$this->db->idate($this->date_interaction)."',";
				$sql .= " ".((int) $this->duration_minutes).",";
				$sql .= " '".$this->db->escape($this->direction)."',";
				$sql .= " ".$this->sqlNullableString($this->sentiment).",";
				$sql .= " ".$this->sqlNullableString($this->prospect_status).",";
				$sql .= " ".$this->sqlNullableFk($this->fk_parent).",";
				$sql .= " ".$this->sqlNullableFk($this->fk_project).",";
				$sql .= " ".((int) $this->status).",";
				$sql .= " '".$this->db->idate($this->datec)."',";
				$sql .= " ".((int) $this->entity);
				$sql .= ")";

				$resql = $this->db->query($sql);
				if ($resql) {
					$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."lemoncrm_interaction");
					break;
				}
				if ($this->db->lasterrno() != 'DB_ERROR_RECORD_ALREADY_EXISTS' || $attempt == $maxAttempts) {
					$error++;
					$this->error = $this->db->lasterror();
					$this->errors[] = $this->error;
					break;
				}
			}
		}

		// 3. Followup as future agenda event + prospect status on thirdparty
		if (!$error) {
			$this->syncFollowupActionComm($user);
			$this->syncProspectStatusToThirdparty();
		}

		if (!$error) {
			$this->db->commit();
			return $this->id;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Create ActionComm in Dolibarr agenda
	 *
	 * @param User $user User
	 * @return int ID of actioncomm created, or <0 if error
	 */
	private function createActionComm($user)
	{
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

		$actioncomm = new ActionComm($this->db);
		$actioncomm->type_code = $this->interaction_type;
		$actioncomm->label = $this->getActionCommLabel();
		$actioncomm->datep = $this->date_interaction;
		$actioncomm->datef = $this->date_interaction;
		$actioncomm->durationp = $this->duration_minutes * 60; // seconds
		$actioncomm->fk_user_author = $user->id;
		$actioncomm->fk_user_action = $user->id;
		$actioncomm->socid = $this->fk_soc;
		$actioncomm->contact_id = $this->fk_socpeople > 0 ? $this->fk_socpeople : 0;
		$actioncomm->note_private = $this->summary;
		$actioncomm->percentage = 100; // Done
		$actioncomm->userownerid = $user->id;
		if ($this->fk_project > 0) {
			$actioncomm->fk_project = $this->fk_project; // visible sur le projet dans l'agenda
		}

		$result = $actioncomm->create($user);
		if ($result < 0) {
			$this->error = $actioncomm->error;
			$this->errors = $actioncomm->errors;
			return -1;
		}

		return $result;
	}

	/**
	 * Generate label for actioncomm.
	 * Format: "Type (sortant) - resume tronque"
	 *
	 * @return string
	 */
	private function getActionCommLabel()
	{
		global $langs;
		$langs->load('lemoncrm@lemoncrm');

		$typeLabel = $langs->trans($this->interaction_type);
		$dirLabel = ($this->direction == 'IN') ? '(entrant)' : '(sortant)';
		$label = $typeLabel.' '.$dirLabel;

		if (!empty($this->summary)) {
			$shortSummary = dol_trunc(str_replace(array("\r\n", "\n", "\r"), ' ', $this->summary), 60);
			$label .= ' - '.$shortSummary;
		}

		return $label;
	}

	/**
	 * Update interaction
	 *
	 * @param User $user User modifying
	 * @param int $notrigger 0=triggers, 1=no triggers
	 * @return int >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$error = 0;
		$this->db->begin();

		$sql = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET";
		$sql .= " interaction_type = '".$this->db->escape($this->interaction_type)."',";
		$sql .= " fk_soc = ".$this->sqlNullableFk($this->fk_soc).",";
		$sql .= " fk_socpeople = ".$this->sqlNullableFk($this->fk_socpeople).",";
		$sql .= " summary = ".$this->sqlNullableString($this->summary).",";
		$sql .= " followup_action = ".$this->sqlNullableString($this->followup_action).",";
		$sql .= " followup_date = ".$this->sqlNullableString($this->followup_date).",";
		$sql .= " followup_time = ".$this->sqlNullableString($this->followup_time).",";
		$sql .= " followup_done = ".((int) $this->followup_done).",";
		$sql .= " followup_mode = ".$this->sqlNullableString($this->followup_mode).",";
		$sql .= " date_interaction = '".$this->db->idate($this->date_interaction)."',";
		$sql .= " duration_minutes = ".((int) $this->duration_minutes).",";
		$sql .= " direction = '".$this->db->escape($this->direction)."',";
		$sql .= " sentiment = ".$this->sqlNullableString($this->sentiment).",";
		$sql .= " prospect_status = ".$this->sqlNullableString($this->prospect_status).",";
		$sql .= " fk_project = ".$this->sqlNullableFk($this->fk_project).",";
		$sql .= " status = ".((int) $this->status);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
		}

		// Update actioncomm too
		if (!$error && $this->fk_actioncomm > 0) {
			$this->updateActionComm($user);
		}

		// Followup agenda event + prospect status on thirdparty
		if (!$error) {
			$this->syncFollowupActionComm($user);
			$this->syncProspectStatusToThirdparty();
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Update the linked ActionComm
	 *
	 * @param User $user User
	 * @return int
	 */
	private function updateActionComm($user)
	{
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

		$actioncomm = new ActionComm($this->db);
		if ($actioncomm->fetch($this->fk_actioncomm) > 0) {
			$actioncomm->type_code = $this->interaction_type;
			$actioncomm->label = $this->getActionCommLabel();
			$actioncomm->datep = $this->date_interaction;
			$actioncomm->datef = $this->date_interaction;
			$actioncomm->durationp = $this->duration_minutes * 60;
			$actioncomm->socid = $this->fk_soc;
			$actioncomm->contact_id = $this->fk_socpeople > 0 ? $this->fk_socpeople : 0;
			$actioncomm->note_private = $this->summary;
			$actioncomm->fk_project = ($this->fk_project > 0) ? $this->fk_project : 0;
			return $actioncomm->update($user);
		}
		return 0;
	}

	/**
	 * Fetch interaction by ID
	 *
	 * @param int $id Row ID
	 * @param string $ref Ref
	 * @return int >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = '')
	{
		global $conf;

		$sql = "SELECT i.rowid, i.ref, i.fk_actioncomm, i.fk_actioncomm_followup, i.interaction_type,";
		$sql .= " i.fk_soc, i.fk_socpeople, i.fk_user_author,";
		$sql .= " i.summary, i.followup_action, i.followup_date, i.followup_time, i.followup_done,";
		$sql .= " i.followup_mode, i.date_interaction, i.duration_minutes,";
		$sql .= " i.direction, i.sentiment, i.prospect_status, i.fk_parent, i.fk_project, i.status,";
		$sql .= " i.datec, i.tms, i.entity,";
		$sql .= " s.nom as thirdparty_name,";
		$sql .= " CONCAT(sp.firstname, ' ', sp.lastname) as contact_name";
		$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as sp ON i.fk_socpeople = sp.rowid";
		$sql .= " WHERE i.entity = ".((int) $conf->entity)." AND";

		if ($id > 0) {
			$sql .= " i.rowid = ".((int)$id);
		} elseif (!empty($ref)) {
			$sql .= " i.ref = '".$this->db->escape($ref)."'";
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id = $obj->rowid;
				$this->ref = $obj->ref;
				$this->fk_actioncomm = $obj->fk_actioncomm;
				$this->fk_actioncomm_followup = $obj->fk_actioncomm_followup;
				$this->interaction_type = $obj->interaction_type;
				$this->fk_soc = $obj->fk_soc;
				$this->fk_socpeople = $obj->fk_socpeople;
				$this->fk_user_author = $obj->fk_user_author;
				$this->summary = $obj->summary;
				$this->followup_action = $obj->followup_action;
				$this->followup_date = $obj->followup_date;
				$this->followup_time = $obj->followup_time;
				$this->followup_done = $obj->followup_done;
				$this->followup_mode = $obj->followup_mode;
				$this->date_interaction = $this->db->jdate($obj->date_interaction);
				$this->duration_minutes = $obj->duration_minutes;
				$this->direction = $obj->direction;
				$this->sentiment = $obj->sentiment;
				$this->prospect_status = $obj->prospect_status;
				$this->fk_parent = $obj->fk_parent;
				$this->fk_project = $obj->fk_project;
				$this->status = $obj->status;
				$this->datec = $this->db->jdate($obj->datec);
				$this->tms = $obj->tms;
				$this->entity = $obj->entity;

				$this->thirdparty_name = $obj->thirdparty_name;
				$this->contact_name = $obj->contact_name;

				return 1;
			}
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Delete interaction
	 *
	 * @param User $user User deleting
	 * @param int $notrigger 0=triggers, 1=no triggers
	 * @return int >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		$error = 0;
		$this->db->begin();

		// Delete actioncomm
		if ($this->fk_actioncomm > 0) {
			require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
			$actioncomm = new ActionComm($this->db);
			if ($actioncomm->fetch($this->fk_actioncomm) > 0) {
				$actioncomm->delete($user);
			}
		}

		// Delete the followup agenda event too
		if ($this->fk_actioncomm_followup > 0) {
			require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
			$followupAC = new ActionComm($this->db);
			if ($followupAC->fetch($this->fk_actioncomm_followup) > 0) {
				$followupAC->delete($user);
			}
		}

		// Re-parent thread children: promote the oldest child as new parent,
		// otherwise they would keep a dangling fk_parent
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."lemoncrm_interaction";
		$sql .= " WHERE fk_parent = ".((int) $this->id)." ORDER BY rowid ASC";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$children = array();
			while ($obj = $this->db->fetch_object($resql)) {
				$children[] = (int) $obj->rowid;
			}
			$newParent = array_shift($children);
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET fk_parent = NULL WHERE rowid = ".((int) $newParent));
			if (!empty($children)) {
				$this->db->query("UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET fk_parent = ".((int) $newParent)." WHERE rowid IN (".implode(',', $children).")");
			}
		}

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."lemoncrm_interaction WHERE rowid = ".((int)$this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->error = $this->db->lasterror();
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Mark followup as done
	 *
	 * @param User $user User
	 * @return int
	 */
	public function markFollowupDone($user)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET followup_done = 1";
		$sql .= " WHERE rowid = ".((int)$this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->followup_done = 1;

			// Close the linked agenda event
			if ($this->fk_actioncomm_followup > 0) {
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$followupAC = new ActionComm($this->db);
				if ($followupAC->fetch($this->fk_actioncomm_followup) > 0) {
					$followupAC->percentage = 100;
					$followupAC->update($user);
				}
			}
			return 1;
		}
		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Timestamp of the planned followup (date + time, fallback 09:00)
	 *
	 * @return int|false
	 */
	private function getFollowupTimestamp()
	{
		if (empty($this->followup_date)) {
			return false;
		}
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $this->followup_date, $d)) {
			return false;
		}
		$h = 9;
		$m = 0;
		if (!empty($this->followup_time) && preg_match('/^(\d{1,2}):(\d{2})/', $this->followup_time, $t)) {
			$h = (int) $t[1];
			$m = (int) $t[2];
		}
		return dol_mktime($h, $m, 0, (int) $d[2], (int) $d[3], (int) $d[1]);
	}

	/**
	 * Keep a future agenda event (todo) in sync with the followup fields.
	 * Created/updated when a followup is planned, removed when it disappears.
	 * Gated by LEMONCRM_FOLLOWUP_AGENDA (default on).
	 *
	 * @param User $user User
	 * @return int
	 */
	private function syncFollowupActionComm($user)
	{
		global $langs;

		if (!getDolGlobalInt('LEMONCRM_FOLLOWUP_AGENDA', 1)) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		dol_include_once('/lemoncrm/core/lib/lemoncrm.lib.php');

		$ts = $this->getFollowupTimestamp();
		$wantEvent = ($ts !== false && empty($this->followup_done));

		// Label : « Relance : action » (le tiers est porté par socid)
		$langs->load('lemoncrm@lemoncrm');
		$label = 'Relance';
		if (!empty($this->followup_mode)) {
			$modes = lemoncrm_get_followup_modes();
			// trans() encode les accents en entités HTML : décoder pour un label agenda en texte brut
			$modeLabel = html_entity_decode($modes[$this->followup_mode] ?? $this->followup_mode, ENT_QUOTES, 'UTF-8');
			$label .= ' '.lcfirst($modeLabel);
		}
		if (!empty($this->followup_action)) {
			$label .= ' : '.dol_trunc(str_replace(array("\r\n", "\n", "\r"), ' ', strip_tags($this->followup_action)), 80);
		}

		if ($wantEvent) {
			if ($this->fk_actioncomm_followup > 0) {
				// Update existing event
				$ac = new ActionComm($this->db);
				if ($ac->fetch($this->fk_actioncomm_followup) > 0) {
					$ac->label = $label;
					$ac->datep = $ts;
					$ac->datef = $ts;
					$ac->socid = $this->fk_soc;
					$ac->contact_id = $this->fk_socpeople > 0 ? $this->fk_socpeople : 0;
					$ac->fk_project = ($this->fk_project > 0) ? $this->fk_project : 0;
					return $ac->update($user);
				}
				// Event deleted manually from agenda: recreate below
				$this->fk_actioncomm_followup = 0;
			}

			$ac = new ActionComm($this->db);
			$ac->type_code = 'LCRM_RELANCE';
			$ac->label = $label;
			$ac->datep = $ts;
			$ac->datef = $ts;
			$ac->fk_user_author = $user->id;
			$ac->fk_user_action = $this->fk_user_author > 0 ? $this->fk_user_author : $user->id;
			$ac->userownerid = $this->fk_user_author > 0 ? $this->fk_user_author : $user->id;
			$ac->socid = $this->fk_soc;
			$ac->contact_id = $this->fk_socpeople > 0 ? $this->fk_socpeople : 0;
			$ac->note_private = $this->followup_action;
			$ac->percentage = 0; // à faire
			if ($this->fk_project > 0) {
				$ac->fk_project = $this->fk_project;
			}
			$result = $ac->create($user);
			if ($result > 0) {
				$this->fk_actioncomm_followup = $result;
				$this->db->query("UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET fk_actioncomm_followup = ".((int) $result)." WHERE rowid = ".((int) $this->id));
			}
			return $result;
		}

		// No followup planned (anymore): remove the orphan event
		if ($this->fk_actioncomm_followup > 0) {
			$ac = new ActionComm($this->db);
			if ($ac->fetch($this->fk_actioncomm_followup) > 0 && $ac->percentage < 100) {
				$ac->delete($user);
			}
			$this->fk_actioncomm_followup = 0;
			$this->db->query("UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET fk_actioncomm_followup = NULL WHERE rowid = ".((int) $this->id));
		}
		return 0;
	}

	/**
	 * Report the prospect status of the interaction on the thirdparty
	 * (native prospection status llx_societe.fk_stcomm).
	 * Mapping overridable via LEMONCRM_STCOMM_MAP (JSON code => stcomm id).
	 * Gated by LEMONCRM_SYNC_STCOMM (default on).
	 *
	 * @return void
	 */
	private function syncProspectStatusToThirdparty()
	{
		if (!getDolGlobalInt('LEMONCRM_SYNC_STCOMM', 1)) {
			return;
		}
		if (empty($this->prospect_status) || $this->fk_soc <= 0) {
			return;
		}

		// c_stcomm natif : -1 ne pas contacter, 0 jamais contacté, 1 à contacter, 2 en cours, 3 fait
		$map = json_decode(getDolGlobalString('LEMONCRM_STCOMM_MAP', ''), true);
		if (!is_array($map) || empty($map)) {
			$map = array('cold' => 1, 'warm' => 2, 'hot' => 2, 'negotiation' => 2, 'won' => 3, 'lost' => -1);
		}
		if (!isset($map[$this->prospect_status])) {
			return; // code custom sans correspondance : on ne touche pas au tiers
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."societe SET fk_stcomm = ".((int) $map[$this->prospect_status]);
		$sql .= " WHERE rowid = ".((int) $this->fk_soc);
		$this->db->query($sql);
	}

	/**
	 * Return clickable link to card
	 *
	 * @param int $withpicto 0=no picto, 1=with picto
	 * @param string $option Variant link
	 * @param int $maxlen Max length
	 * @return string HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $maxlen = 0)
	{
		$result = '';
		$url = dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$this->id;

		$linkstart = '<a href="'.$url.'" title="'.dol_escape_htmltag($this->ref).'">';
		$linkend = '</a>';

		$result .= $linkstart;
		if ($withpicto) {
			$result .= img_object($this->ref, 'object_lemoncrm@lemoncrm', 'class="paddingright"');
		}
		$ref = $this->ref;
		if ($maxlen > 0) {
			$ref = dol_trunc($ref, $maxlen);
		}
		$result .= $ref;
		$result .= $linkend;

		return $result;
	}

	/**
	 * Return HTML badge for followup status
	 *
	 * @return string HTML
	 */
	public function getFollowupBadge()
	{
		global $langs;

		if ($this->followup_done) {
			return '<span class="badge badge-status4">'.$langs->trans('FollowupDone').'</span>';
		}
		if (!empty($this->followup_date)) {
			$now = dol_now();
			$followup_ts = dol_stringtotime($this->followup_date);
			$today = dol_mktime(0, 0, 0, dol_print_date($now, '%m'), dol_print_date($now, '%d'), dol_print_date($now, '%Y'));

			if ($followup_ts < $today) {
				return '<span class="badge badge-status8">'.$langs->trans('FollowupOverdue').'</span>';
			} elseif ($followup_ts == $today) {
				return '<span class="badge badge-status1">'.$langs->trans('FollowupToday').'</span>';
			} else {
				return '<span class="badge badge-status0">'.$langs->trans('FollowupPending').'</span>';
			}
		}
		return '';
	}

	/**
	 * Return sentiment badge with color
	 *
	 * @return string HTML
	 */
	public function getSentimentBadge()
	{
		global $langs;

		if (empty($this->sentiment)) {
			return '';
		}

		$colors = array(
			'positive' => 'badge-status4',
			'neutral' => 'badge-status0',
			'negative' => 'badge-status8',
		);

		$class = $colors[$this->sentiment] ?? 'badge-status0';
		$label = $langs->trans('Sentiment'.ucfirst($this->sentiment));

		return '<span class="badge '.$class.'">'.$label.'</span>';
	}
}
