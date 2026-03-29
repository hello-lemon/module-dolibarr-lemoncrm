<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Module descriptor for LemonCRM
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modLemonCRM extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 500210;
		$this->rights_class = 'lemoncrm';
		$this->family = "crm";
		$this->module_position = '50';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Suivi des interactions clients et prospects";
		$this->descriptionlong = "Module CRM pour logger les echanges (tel, email, LinkedIn, Teams, RDV), gerer les relances et suivre les prospects.";
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-comments';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'theme' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'css' => array('/lemoncrm/css/lemoncrm.css'),
			'js' => array('/lemoncrm/js/lemoncrm.js'),
			'hooks' => array(
				'thirdpartycard',
				'contactcard',
				'propalcard',
				'invoicecard',
				'ordercard',
				'projectcard',
				'all',
			),
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@lemoncrm');

		$this->depends = array('modSociete');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("lemoncrm@lemoncrm");

		$this->const = array(
			array('LEMONCRM_AI_API_KEY', 'chaine', '', 'Cle API Anthropic Claude', 1, 'current', 0),
			array('LEMONCRM_AI_MODEL', 'chaine', 'claude-sonnet-4-6', 'Modele IA par defaut', 1, 'current', 0),
		);

		if (!isset($conf->lemoncrm) || !isset($conf->lemoncrm->enabled)) {
			$conf->lemoncrm = new stdClass();
			$conf->lemoncrm->enabled = 0;
		}

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = 5002101;
		$this->rights[$r][1] = 'Consulter les interactions';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'interaction';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = 5002102;
		$this->rights[$r][1] = 'Creer/modifier les interactions';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'interaction';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = 5002103;
		$this->rights[$r][1] = 'Supprimer les interactions';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'interaction';
		$this->rights[$r][5] = 'delete';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		// === Menu principal "Lemon" en haut ===
		$this->menu[$r] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'Lemon',
			'prefix' => img_picto('', 'fa-lemon', 'class="fas paddingright pictofixedwidth"'),
			'mainmenu' => 'lemon',
			'leftmenu' => '',
			'url' => '/lemoncrm/index.php?mainmenu=lemon',
			'langs' => '',
			'position' => 100,
			'enabled' => '1',
			'perms' => '1',
			'target' => '',
			'user' => 0,
		);
		$r++;

		// --- CRM ---
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=lemon',
			'type' => 'left',
			'titre' => 'CRM',
			'prefix' => img_picto('', 'fa-comments', 'class="fas paddingright pictofixedwidth"'),
			'mainmenu' => 'lemon',
			'leftmenu' => 'lemoncrm',
			'url' => '/lemoncrm/index.php?mainmenu=lemon',
			'langs' => '',
			'position' => 100,
			'enabled' => '$conf->lemoncrm->enabled',
			'perms' => '$user->hasRight("lemoncrm", "interaction", "read")',
			'target' => '',
			'user' => 0,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=lemon,fk_leftmenu=lemoncrm',
			'type' => 'left',
			'titre' => 'Dashboard',
			'mainmenu' => 'lemon',
			'leftmenu' => 'lemoncrm_dashboard',
			'url' => '/lemoncrm/index.php?mainmenu=lemon',
			'langs' => '',
			'position' => 101,
			'enabled' => '$conf->lemoncrm->enabled',
			'perms' => '$user->hasRight("lemoncrm", "interaction", "read")',
			'target' => '',
			'user' => 0,
		);
		$r++;

		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=lemon,fk_leftmenu=lemoncrm',
			'type' => 'left',
			'titre' => 'Nouvelle interaction',
			'mainmenu' => 'lemon',
			'leftmenu' => 'lemoncrm_new',
			'url' => '/lemoncrm/interaction_card.php?action=create&mainmenu=lemon&leftmenu=lemoncrm',
			'langs' => '',
			'position' => 102,
			'enabled' => '$conf->lemoncrm->enabled',
			'perms' => '$user->hasRight("lemoncrm", "interaction", "write")',
			'target' => '',
			'user' => 0,
		);
		$r++;

		// Tabs on thirdparty and contact cards
		$this->tabs = array(
			'thirdparty:+lemoncrm:InteractionsCRM:lemoncrm@lemoncrm:$conf->lemoncrm->enabled && $user->hasRight("lemoncrm", "interaction", "read"):/lemoncrm/dashboard.php?socid=__ID__',
			'contact:+lemoncrm:InteractionsCRM:lemoncrm@lemoncrm:$conf->lemoncrm->enabled && $user->hasRight("lemoncrm", "interaction", "read"):/lemoncrm/interaction_list.php?contactid=__ID__',
		);
	}

	public function init($options = '')
	{
		$this->_load_tables('/lemoncrm/sql/');

		$sql = array();

		// Insert custom action types if not existing
		$sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) VALUES (100, 'AC_LINKEDIN', 'system', 'Message LinkedIn', 'lemoncrm', 1, 60)";
		$sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) VALUES (101, 'AC_TEAMS', 'system', 'Reunion Teams', 'lemoncrm', 1, 61)";
		$sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position) VALUES (102, 'AC_MEETING_INPERSON', 'system', 'Rendez-vous physique', 'lemoncrm', 1, 62)";

		return $this->_init($sql, $options);
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
