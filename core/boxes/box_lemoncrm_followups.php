<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * Box page d'accueil : relances LemonCRM à venir / en retard
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_lemoncrm_followups extends ModeleBoxes
{
	public $boxcode = 'lemoncrmfollowups';
	public $boximg = 'fa-bell';
	public $boxlabel = 'BoxLemonCRMFollowups';
	public $depends = array('lemoncrm');

	public $info_box_head = array();
	public $info_box_contents = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 * @param string $param More parameters
	 */
	public function __construct($db, $param = '')
	{
		global $user;
		parent::__construct($db, $param);
		$this->hidden = !$user->hasRight('lemoncrm', 'interaction', 'read');
	}

	/**
	 * Load box data
	 *
	 * @param int $max Max number of records
	 * @return void
	 */
	public function loadBox($max = 8)
	{
		global $conf, $langs;

		$langs->load('lemoncrm@lemoncrm');
		$this->max = $max;

		$this->info_box_head = array(
			'text' => $langs->trans('BoxLemonCRMFollowups'),
			'sublink' => dol_buildpath('/lemoncrm/dashboard.php', 1),
			'subtext' => $langs->trans('DashboardCRM'),
			'subpicto' => 'fa-comments',
		);

		dol_include_once('/lemoncrm/core/lib/lemoncrm.lib.php');

		$sql = "SELECT i.rowid, i.ref, i.followup_action, i.followup_date, i.followup_mode,";
		$sql .= " s.rowid as socid, s.nom as thirdparty_name";
		$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON i.fk_soc = s.rowid";
		$sql .= " WHERE i.entity = ".((int) $conf->entity);
		$sql .= " AND i.followup_done = 0 AND i.followup_date IS NOT NULL";
		$sql .= " ORDER BY i.followup_date ASC";
		$sql .= $this->db->plimit($max);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->info_box_contents[0][0] = array('td' => '', 'maxlength' => 500, 'text' => $this->db->lasterror());
			return;
		}

		$today = date('Y-m-d');
		$line = 0;
		while ($obj = $this->db->fetch_object($resql)) {
			$urlCard = dol_buildpath('/lemoncrm/interaction_card.php', 1).'?id='.$obj->rowid;

			$badge = '';
			if ($obj->followup_date < $today) {
				$badge = ' <span class="badge badge-status8">'.$langs->trans('FollowupOverdue').'</span>';
			} elseif ($obj->followup_date == $today) {
				$badge = ' <span class="badge badge-status1">'.$langs->trans('FollowupToday').'</span>';
			}

			$this->info_box_contents[$line][0] = array(
				'td' => 'class="nowraponall"',
				'text' => lemoncrm_format_date_fr($obj->followup_date).$badge,
				'asis' => 1,
			);
			$this->info_box_contents[$line][1] = array(
				'td' => 'class="tdoverflowmax150"',
				'text' => $obj->thirdparty_name ? dol_escape_htmltag($obj->thirdparty_name) : '',
				'url' => $obj->socid ? DOL_URL_ROOT.'/societe/card.php?socid='.$obj->socid : '',
			);
			$this->info_box_contents[$line][2] = array(
				'td' => 'class="tdoverflowmax200"',
				'text' => dol_trunc(dol_escape_htmltag($obj->followup_action), 60),
				'url' => $urlCard,
			);
			$line++;
		}

		if ($line == 0) {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="center opacitymedium"',
				'text' => $langs->trans('BoxNoFollowups'),
			);
		}
	}

	/**
	 * Display the box
	 *
	 * @param array|null $head Head
	 * @param array|null $contents Contents
	 * @param int $nooutput No output
	 * @return string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
