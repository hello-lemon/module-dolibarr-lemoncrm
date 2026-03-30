<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * Hook actions for LemonCRM
 */

class ActionsLemonCRM
{
	public $db;
	public $error = '';
	public $errors = array();
	public $resPrint = '';
	public $results = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add buttons on thirdparty/contact cards
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		if (!$conf->lemoncrm->enabled || !$user->hasRight('lemoncrm', 'interaction', 'write')) {
			return 0;
		}

		$langs->load('lemoncrm@lemoncrm');
		$context = explode(':', $parameters['context']);

		if (in_array('thirdpartycard', $context) && !empty($object->id)) {
			print '<a class="butAction" href="#" onclick="lcrm_open_drawer(\'\', '.$object->id.'); return false;">'.$langs->trans('NewInteraction').'</a>';
		}

		if (in_array('contactcard', $context) && !empty($object->id)) {
			$socid = $object->socid > 0 ? $object->socid : 0;
			print '<a class="butAction" href="#" onclick="lcrm_open_drawer(\'\', '.$socid.', '.$object->id.'); return false;">'.$langs->trans('NewInteraction').'</a>';
		}

		return 0;
	}

	/**
	 * Detect socid from current page URL + DB lookup
	 */
	private function detectSocidFromPage()
	{
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		$id = GETPOSTINT('id');
		$socid = GETPOSTINT('socid');
		$facid = GETPOSTINT('facid');

		if ($socid > 0) return $socid;

		if ($id > 0 && preg_match('#/societe/card\.php#', $uri)) return $id;

		$invoiceId = $facid > 0 ? $facid : $id;
		if ($invoiceId > 0 && preg_match('#/compta/facture/(card|fiche)\.php#', $uri)) {
			return $this->fetchSocFromTable('facture', $invoiceId);
		}
		if ($invoiceId > 0 && preg_match('#/fourn.*/facture/(card|fiche)\.php#', $uri)) {
			return $this->fetchSocFromTable('facture_fourn', $invoiceId);
		}
		if ($id > 0 && preg_match('#/comm/propal/card\.php#', $uri)) {
			return $this->fetchSocFromTable('propal', $id);
		}
		if ($id > 0 && preg_match('#/commande/card\.php#', $uri)) {
			return $this->fetchSocFromTable('commande', $id);
		}
		if ($id > 0 && preg_match('#/projet/card\.php#', $uri)) {
			return $this->fetchSocFromTable('projet', $id);
		}
		if ($id > 0 && preg_match('#/contact/card\.php#', $uri)) {
			return $this->fetchSocFromTable('socpeople', $id);
		}
		// Supplier order
		if ($id > 0 && preg_match('#/fourn.*/commande/card\.php#', $uri)) {
			return $this->fetchSocFromTable('commande_fournisseur', $id);
		}

		return 0;
	}

	private function fetchSocFromTable($table, $id)
	{
		$sql = "SELECT fk_soc FROM ".MAIN_DB_PREFIX.$table." WHERE rowid = ".(int)$id;
		$res = $this->db->query($sql);
		if ($res && ($obj = $this->db->fetch_object($res))) return (int)$obj->fk_soc;
		return 0;
	}

	/**
	 * Inject context variables on every page (printCommonFooter)
	 * The actual HTML is injected by js/lemoncrm.js which loads on all pages via module_parts
	 */
	public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		if (empty($user->id) || !$conf->lemoncrm->enabled || !$user->hasRight('lemoncrm', 'interaction', 'write')) {
			return 0;
		}

		dol_include_once('/lemoncrm/lib/lemoncrm.lib.php');
		$langs->load('lemoncrm@lemoncrm');

		$socid = $this->detectSocidFromPage();
		$socName = '';
		if ($socid > 0) {
			$sql = "SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".(int)$socid;
			$res = $this->db->query($sql);
			if ($res && ($obj = $this->db->fetch_object($res))) $socName = $obj->nom;
		}

		// Build types array for JS quicklog
		$types = lemoncrm_get_interaction_types();
		$icons = lemoncrm_get_type_icons();
		$jsTypes = array();
		foreach ($types as $code => $label) {
			$jsTypes[] = array(
				'code' => $code,
				'icon' => $icons[$code] ?? 'far fa-comment',
				'label' => $label,
			);
		}

		// Pass context to JS (the JS file handles the rest)
		print '<script>';
		print 'var lcrm_base = '.json_encode(dol_buildpath('/lemoncrm/interaction_card.php', 1)).';';
		print 'var lcrm_page_socid = '.(int)$socid.';';
		print 'var lcrm_page_socname = '.json_encode($socName).';';
		print 'var lcrm_dol_root = '.json_encode(DOL_URL_ROOT).';';
		print 'var lcrm_types = '.json_encode($jsTypes).';';
		print 'var lcrm_persist = '.getDolGlobalInt('LEMONCRM_QUICKLOG_PERSIST', 0).';'."\n";
		print '</script>';

		return 0;
	}

	/**
	 * Hook to declare lemoncrm_interaction element properties for linked objects
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		if (isset($parameters['elementType']) && $parameters['elementType'] === 'lemoncrm_interaction') {
			$this->results = array(
				'classpath' => '/lemoncrm/class',
				'classfile' => 'lemoncrm_interaction',
				'classname' => 'LemonCRMInteraction',
				'module' => 'lemoncrm',
				'subelement' => 'lemoncrm_interaction',
				'table_element' => 'lemoncrm_interaction',
			);
			return 1;
		}
		return 0;
	}
}
