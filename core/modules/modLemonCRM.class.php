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
		$this->numero = 210002;
		$this->rights_class = 'lemoncrm';
		$this->family = "crm";
		$this->module_position = '50';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Suivi des interactions clients et prospects";
		$this->descriptionlong = "Module CRM pour logger les échanges (tel, email, LinkedIn, Teams, RDV), gérer les relances et suivre les prospects.";
		$this->version = '3.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-comments';
		$this->editor_name = 'Lemon';
		$this->editor_url = 'https://hellolemon.fr';

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
			'api' => 1,
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

		$this->const = array();

		// Dictionaries
		$this->dictionaries = array(
			'langs' => 'lemoncrm@lemoncrm',
			'tabname' => array(
				MAIN_DB_PREFIX.'c_lemoncrm_sentiment',
				MAIN_DB_PREFIX.'c_lemoncrm_prospect_status',
			),
			'tablib' => array(
				'Sentiments CRM',
				'Statuts prospect CRM',
			),
			'tabsql' => array(
				'SELECT rowid, code, label, color, position, active FROM '.MAIN_DB_PREFIX.'c_lemoncrm_sentiment WHERE entity IN (0, __ENTITY__)',
				'SELECT rowid, code, label, color, position, active FROM '.MAIN_DB_PREFIX.'c_lemoncrm_prospect_status WHERE entity IN (0, __ENTITY__)',
			),
			'tabsqlsort' => array(
				'position ASC',
				'position ASC',
			),
			'tabfield' => array(
				'code,label,color,position',
				'code,label,color,position',
			),
			'tabfieldvalue' => array(
				'code,label,color,position',
				'code,label,color,position',
			),
			'tabfieldinsert' => array(
				'code,label,color,position',
				'code,label,color,position',
			),
			'tabrowid' => array(
				'rowid',
				'rowid',
			),
			'tabcond' => array(
				'$conf->lemoncrm->enabled',
				'$conf->lemoncrm->enabled',
			),
		);

		if (!isset($conf->lemoncrm) || !isset($conf->lemoncrm->enabled)) {
			$conf->lemoncrm = new stdClass();
			$conf->lemoncrm->enabled = 0;
		}

		// Permissions — IDs alignés sur la plage officielle Lemon : numero (210002) * 100 + index
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = 21000201;
		$this->rights[$r][1] = 'Consulter les interactions';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'interaction';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = 21000202;
		$this->rights[$r][1] = 'Créer/modifier les interactions';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'interaction';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = 21000203;
		$this->rights[$r][1] = 'Supprimer les interactions';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'interaction';
		$this->rights[$r][5] = 'delete';
		$r++;

		// Menus
		$this->menu = array();
		$r = 0;

		// === Menu principal en haut (nom et icône configurables) ===
		$menuLabel = getDolGlobalString('LEMONCRM_MENU_LABEL', 'Lemon');
		$menuIcon = getDolGlobalString('LEMONCRM_MENU_ICON', 'fa-lemon');
		$this->menu[$r] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => $menuLabel,
			'prefix' => img_picto('', $menuIcon, 'class="fas paddingright pictofixedwidth"'),
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

		// Box page d'accueil : relances à venir / en retard
		$this->boxes = array(
			0 => array(
				'file' => 'box_lemoncrm_followups.php@lemoncrm',
				'note' => '',
				'enabledbydefaulton' => 'Home',
			),
		);

		// Profil d'export des interactions
		$r = 0;
		$this->export_code[$r] = $this->rights_class.'_'.$r;
		$this->export_label[$r] = 'Interactions LemonCRM';
		$this->export_icon[$r] = 'fa-comments';
		$this->export_permission[$r] = array(array("lemoncrm", "interaction", "read"));
		$this->export_fields_array[$r] = array(
			'i.ref' => 'Réf.',
			'i.date_interaction' => 'Date',
			'i.interaction_type' => 'Type',
			'i.direction' => 'Direction',
			's.nom' => 'Tiers',
			'sp.lastname' => 'Contact nom',
			'sp.firstname' => 'Contact prénom',
			'i.summary' => 'Résumé',
			'i.duration_minutes' => 'Durée (min)',
			'i.sentiment' => 'Sentiment',
			'i.prospect_status' => 'Statut prospect',
			'i.followup_action' => 'Action de relance',
			'i.followup_date' => 'Date de relance',
			'i.followup_done' => 'Relance faite',
			'u.login' => 'Auteur',
			'i.datec' => 'Date de création',
		);
		$this->export_TypeFields_array[$r] = array(
			'i.ref' => 'Text',
			'i.date_interaction' => 'Date',
			'i.interaction_type' => 'Text',
			'i.direction' => 'Text',
			's.nom' => 'Text',
			'sp.lastname' => 'Text',
			'sp.firstname' => 'Text',
			'i.summary' => 'Text',
			'i.duration_minutes' => 'Numeric',
			'i.sentiment' => 'Text',
			'i.prospect_status' => 'Text',
			'i.followup_action' => 'Text',
			'i.followup_date' => 'Date',
			'i.followup_done' => 'Boolean',
			'u.login' => 'Text',
			'i.datec' => 'Date',
		);
		$this->export_entities_array[$r] = array();
		$this->export_sql_start[$r] = 'SELECT DISTINCT ';
		$this->export_sql_end[$r] = ' FROM '.MAIN_DB_PREFIX.'lemoncrm_interaction as i';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON i.fk_soc = s.rowid';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'socpeople as sp ON i.fk_socpeople = sp.rowid';
		$this->export_sql_end[$r] .= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON i.fk_user_author = u.rowid';
		$this->export_sql_end[$r] .= ' WHERE i.entity IN ('.getEntity('lemoncrm_interaction').')';
	}

	public function init($options = '')
	{
		$this->_load_tables('/lemoncrm/sql/');

		// Insert LCRM_ types in Dolibarr agenda dictionary.
		// IDs choisis dynamiquement : un INSERT IGNORE avec id fixe entrerait
		// silencieusement en collision avec un autre module occupant le même id.
		$types = array(
			'LCRM_TEL' => array('Appel', 10),
			'LCRM_EMAIL' => array('Email', 20),
			'LCRM_LINKEDIN' => array('LinkedIn', 30),
			'LCRM_TEAMS' => array('Teams', 40),
			'LCRM_RDV' => array('Rendez-vous', 50),
			'LCRM_WHATSAPP' => array('WhatsApp', 55),
			'LCRM_RELANCE' => array('Relance', 70),
			'LCRM_NOTE' => array('Note', 100),
		);
		foreach ($types as $code => $def) {
			$resql = $this->db->query("SELECT id FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = '".$this->db->escape($code)."'");
			if ($resql && $this->db->num_rows($resql) > 0) {
				continue; // déjà présent
			}
			$nextId = 500;
			$resmax = $this->db->query("SELECT MAX(id) as maxid FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE id >= 500 AND id < 1000");
			if ($resmax && ($objmax = $this->db->fetch_object($resmax)) && $objmax->maxid !== null) {
				$nextId = ((int) $objmax->maxid) + 1;
			}
			$this->db->query("INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position)"
				." VALUES (".((int) $nextId).", '".$this->db->escape($code)."', 'module', '".$this->db->escape($def[0])."', 'lemoncrm', 1, ".((int) $def[1]).")");
		}

		$sql = array();

		// Colonnes ajoutées après la v1 (upgrade sans drop)
		$sql[] = "ALTER TABLE ".MAIN_DB_PREFIX."lemoncrm_interaction ADD COLUMN IF NOT EXISTS fk_parent INTEGER DEFAULT NULL AFTER prospect_status";
		$sql[] = "ALTER TABLE ".MAIN_DB_PREFIX."lemoncrm_interaction ADD COLUMN IF NOT EXISTS fk_project INTEGER DEFAULT NULL AFTER fk_parent";
		$sql[] = "ALTER TABLE ".MAIN_DB_PREFIX."lemoncrm_interaction ADD COLUMN IF NOT EXISTS fk_actioncomm_followup INTEGER DEFAULT NULL AFTER fk_actioncomm";

		// Seed des dictionnaires LemonCRM (install neuve : tables créées vides sinon)
		$sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."c_lemoncrm_sentiment (code, label, color, position, active, entity) VALUES"
			." ('positive', 'Positif', '#38A169', 10, 1, 1), ('neutral', 'Neutre', '#6b7280', 20, 1, 1), ('negative', 'Négatif', '#E53E3E', 30, 1, 1)";
		$sql[] = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."c_lemoncrm_prospect_status (code, label, color, position, active, entity) VALUES"
			." ('cold', 'Froid', '#3182CE', 10, 1, 1), ('warm', 'Tiède', '#DD6B20', 20, 1, 1), ('hot', 'Chaud', '#E53E3E', 30, 1, 1),"
			." ('negotiation', 'Négociation', '#805AD5', 40, 1, 1), ('won', 'Gagné', '#38A169', 50, 1, 1), ('lost', 'Perdu', '#6b7280', 60, 1, 1)";

		// Migrate existing interactions from AC_ to LCRM_ codes
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_TEL' WHERE interaction_type = 'AC_TEL'";
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_EMAIL' WHERE interaction_type = 'AC_EMAIL'";
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_LINKEDIN' WHERE interaction_type = 'AC_LINKEDIN'";
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_TEAMS' WHERE interaction_type = 'AC_TEAMS'";
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_RDV' WHERE interaction_type = 'AC_RDV'";
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_RDV' WHERE interaction_type = 'AC_MEETING_INPERSON'";
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_NOTE' WHERE interaction_type = 'AC_OTH'";
		$sql[] = "UPDATE ".MAIN_DB_PREFIX."lemoncrm_interaction SET interaction_type = 'LCRM_RELANCE' WHERE interaction_type = 'AC_RELANCE'";

		return $this->_init($sql, $options);
	}

	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
