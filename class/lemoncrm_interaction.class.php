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

		// Generate ref
		dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');
		$this->ref = lemoncrm_get_next_ref($this->db);
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
			// 2. Create lemoncrm_interaction record
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."lemoncrm_interaction (";
			$sql .= "ref, fk_actioncomm, interaction_type, fk_soc, fk_socpeople,";
			$sql .= " fk_user_author, summary, followup_action, followup_date, followup_time, followup_done,";
			$sql .= " followup_mode, date_interaction, duration_minutes, direction,";
			$sql .= " sentiment, prospect_status, fk_parent, fk_project, status, datec, entity";
			$sql .= ") VALUES (";
			$sql .= "'".$this->db->escape($this->ref)."',";
			$sql .= " ".((int)$this->fk_actioncomm).",";
			$sql .= " '".$this->db->escape($this->interaction_type)."',";
			$sql .= " ".($this->fk_soc > 0 ? ((int)$this->fk_soc) : "NULL").",";
			$sql .= " ".($this->fk_socpeople > 0 ? ((int)$this->fk_socpeople) : "NULL").",";
			$sql .= " ".((int)$this->fk_user_author).",";
			$sql .= " ".(!empty($this->summary) ? "'".$this->db->escape($this->summary)."'" : "NULL").",";
			$sql .= " ".(!empty($this->followup_action) ? "'".$this->db->escape($this->followup_action)."'" : "NULL").",";
			$sql .= " ".(!empty($this->followup_date) ? "'".$this->db->escape($this->followup_date)."'" : "NULL").",";
			$sql .= " ".(!empty($this->followup_time) ? "'".$this->db->escape($this->followup_time)."'" : "NULL").",";
			$sql .= " ".((int)$this->followup_done).",";
			$sql .= " ".(!empty($this->followup_mode) ? "'".$this->db->escape($this->followup_mode)."'" : "NULL").",";
			$sql .= " '".$this->db->idate($this->date_interaction)."',";
			$sql .= " ".((int)$this->duration_minutes).",";
			$sql .= " '".$this->db->escape($this->direction)."',";
			$sql .= " ".(!empty($this->sentiment) ? "'".$this->db->escape($this->sentiment)."'" : "NULL").",";
			$sql .= " ".(!empty($this->prospect_status) ? "'".$this->db->escape($this->prospect_status)."'" : "NULL").",";
			$sql .= " ".($this->fk_parent > 0 ? ((int)$this->fk_parent) : "NULL").",";
			$sql .= " ".($this->fk_project > 0 ? ((int)$this->fk_project) : "NULL").",";
			$sql .= " ".((int)$this->status).",";
			$sql .= " '".$this->db->idate($this->datec)."',";
			$sql .= " ".((int)$this->entity);
			$sql .= ")";

			$resql = $this->db->query($sql);
			if (!$resql) {
				$error++;
				$this->error = $this->db->lasterror();
				$this->errors[] = $this->error;
			} else {
				$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."lemoncrm_interaction");
			}
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

		$result = $actioncomm->create($user);
		if ($result < 0) {
			$this->error = $actioncomm->error;
			$this->errors = $actioncomm->errors;
			return -1;
		}

		return $result;
	}

	/**
	 * Generate label for actioncomm
	 *
	 * @return string
	 */
	private function getActionCommLabel()
	{
		global $langs;
		$langs->load('lemoncrm@lemoncrm');

		$typeLabel = $langs->trans($this->interaction_type);
		if ($typeLabel == $this->interaction_type) {
			$typeLabel = $this->interaction_type;
		}

		$dirLabel = ($this->direction == 'IN') ? '(entrant)' : '(sortant)';

		// Clean label: "Appel telephonique (sortant) - resume tronque"
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
		$sql .= " fk_soc = ".($this->fk_soc > 0 ? ((int)$this->fk_soc) : "NULL").",";
		$sql .= " fk_socpeople = ".($this->fk_socpeople > 0 ? ((int)$this->fk_socpeople) : "NULL").",";
		$sql .= " summary = ".(!empty($this->summary) ? "'".$this->db->escape($this->summary)."'" : "NULL").",";
		$sql .= " followup_action = ".(!empty($this->followup_action) ? "'".$this->db->escape($this->followup_action)."'" : "NULL").",";
		$sql .= " followup_date = ".(!empty($this->followup_date) ? "'".$this->db->escape($this->followup_date)."'" : "NULL").",";
		$sql .= " followup_time = ".(!empty($this->followup_time) ? "'".$this->db->escape($this->followup_time)."'" : "NULL").",";
		$sql .= " followup_done = ".((int)$this->followup_done).",";
		$sql .= " followup_mode = ".(!empty($this->followup_mode) ? "'".$this->db->escape($this->followup_mode)."'" : "NULL").",";
		$sql .= " date_interaction = '".$this->db->idate($this->date_interaction)."',";
		$sql .= " duration_minutes = ".((int)$this->duration_minutes).",";
		$sql .= " direction = '".$this->db->escape($this->direction)."',";
		$sql .= " sentiment = ".(!empty($this->sentiment) ? "'".$this->db->escape($this->sentiment)."'" : "NULL").",";
		$sql .= " prospect_status = ".(!empty($this->prospect_status) ? "'".$this->db->escape($this->prospect_status)."'" : "NULL").",";
		$sql .= " status = ".((int)$this->status);
		$sql .= " WHERE rowid = ".((int)$this->id);

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
		$sql = "SELECT i.rowid, i.ref, i.fk_actioncomm, i.interaction_type,";
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
		$sql .= " WHERE";

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
			return 1;
		}
		$this->error = $this->db->lasterror();
		return -1;
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
