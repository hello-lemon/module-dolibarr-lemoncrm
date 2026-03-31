<?php
/*
 * Copyright (C) 2026 SASU LEMON <https://hellolemon.fr>
 *
 * AI message generation service for LemonCRM
 * Uses Claude API (Anthropic) to generate contextual commercial messages
 */

class LemonCRMAI
{
	/** @var DoliDB */
	private $db;

	public $error = '';

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
	 * Generate an AI message
	 *
	 * @param int    $socId           Thirdparty ID
	 * @param int    $contactId       Contact ID (0 if none)
	 * @param string $channel         Interaction type code (LCRM_EMAIL, LCRM_LINKEDIN, etc.)
	 * @param string $objectiveCode   Objective code (rdv, proposal, starter, etc.)
	 * @param string $incomingMessage Incoming message to reply to (empty for starter)
	 * @return array{success: bool, message?: string, error?: string}
	 */
	public function generate($socId, $contactId, $channel, $objectiveCode, $incomingMessage = '')
	{
		global $conf;

		$apiKey = getDolGlobalString('LEMONCRM_AI_API_KEY');
		if (empty($apiKey)) {
			return array('success' => false, 'error' => 'Cle API Anthropic non configuree');
		}

		$context = $this->buildContext($socId, $contactId);
		$systemPrompt = $this->buildSystemPrompt($channel, $objectiveCode);
		$userMessage = $this->buildUserMessage($context, $incomingMessage, $channel);

		$result = $this->callClaudeAPI($systemPrompt, $userMessage);
		if ($result === false) {
			return array('success' => false, 'error' => $this->error);
		}

		return array('success' => true, 'message' => $result);
	}

	/**
	 * Build context from database (contact, company, interaction history)
	 *
	 * @param int $socId     Thirdparty ID
	 * @param int $contactId Contact ID
	 * @return array Context data
	 */
	private function buildContext($socId, $contactId)
	{
		$context = array(
			'company' => array(),
			'contact' => array(),
			'history' => array(),
			'prospect_status' => '',
			'last_sentiment' => '',
		);

		// Company info
		if ($socId > 0) {
			$sql = "SELECT s.nom, s.town, s.phone, s.email, s.url, s.code_naf,";
			$sql .= " t.libelle as type_label";
			$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_typent as t ON s.fk_typent = t.id";
			$sql .= " WHERE s.rowid = ".((int) $socId);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				$context['company'] = array(
					'name' => $obj->nom,
					'city' => $obj->town,
					'phone' => $obj->phone,
					'email' => $obj->email,
					'website' => $obj->url,
					'naf' => $obj->code_naf,
					'type' => $obj->type_label,
				);
			}
		}

		// Contact info
		if ($contactId > 0) {
			$sql = "SELECT sp.firstname, sp.lastname, sp.poste, sp.email, sp.phone_mobile, sp.phone_perso";
			$sql .= " FROM ".MAIN_DB_PREFIX."socpeople as sp";
			$sql .= " WHERE sp.rowid = ".((int) $contactId);
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				$context['contact'] = array(
					'firstname' => $obj->firstname,
					'lastname' => $obj->lastname,
					'position' => $obj->poste,
					'email' => $obj->email,
					'phone' => $obj->phone_mobile ?: $obj->phone_perso,
				);
			}
		}

		// Interaction history (last 10)
		if ($socId > 0) {
			$sql = "SELECT i.interaction_type, i.summary, i.direction, i.sentiment,";
			$sql .= " i.prospect_status, i.date_interaction, i.followup_action";
			$sql .= " FROM ".MAIN_DB_PREFIX."lemoncrm_interaction as i";
			$sql .= " WHERE i.fk_soc = ".((int) $socId);
			if ($contactId > 0) {
				$sql .= " AND (i.fk_socpeople = ".((int) $contactId)." OR i.fk_socpeople IS NULL)";
			}
			$sql .= " ORDER BY i.date_interaction DESC LIMIT 10";
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$context['history'][] = array(
						'type' => $obj->interaction_type,
						'summary' => strip_tags($obj->summary),
						'direction' => $obj->direction,
						'sentiment' => $obj->sentiment,
						'prospect_status' => $obj->prospect_status,
						'date' => $obj->date_interaction,
						'followup' => $obj->followup_action,
					);
				}
			}

			// Last prospect status and sentiment from most recent interaction
			if (!empty($context['history'])) {
				foreach ($context['history'] as $h) {
					if (!empty($h['prospect_status']) && empty($context['prospect_status'])) {
						$context['prospect_status'] = $h['prospect_status'];
					}
					if (!empty($h['sentiment']) && empty($context['last_sentiment'])) {
						$context['last_sentiment'] = $h['sentiment'];
					}
					if (!empty($context['prospect_status']) && !empty($context['last_sentiment'])) {
						break;
					}
				}
			}
		}

		return $context;
	}

	/**
	 * Build the system prompt (global + channel rules + objective prompt)
	 *
	 * @param string $channel       Channel code (LCRM_EMAIL, etc.)
	 * @param string $objectiveCode Objective code
	 * @return string System prompt
	 */
	private function buildSystemPrompt($channel, $objectiveCode)
	{
		$parts = array();

		// Global system prompt
		$globalPrompt = getDolGlobalString('LEMONCRM_AI_SYSTEM_PROMPT');
		if (!empty($globalPrompt)) {
			$parts[] = $globalPrompt;
		} else {
			$parts[] = "Tu es un assistant commercial. Tu generes des messages professionnels, personnalises et efficaces pour aider a developper les relations commerciales.";
		}

		// Channel-specific rules
		$channelRules = self::getChannelRules($channel);
		if (!empty($channelRules)) {
			$parts[] = "--- REGLES DU CANAL ---\n".$channelRules;
		}

		// Objective-specific prompt
		$objectives = self::getObjectives();
		foreach ($objectives as $obj) {
			if ($obj['code'] === $objectiveCode && !empty($obj['prompt'])) {
				$parts[] = "--- OBJECTIF ---\n".$obj['prompt'];
				break;
			}
		}

		// General rules
		$parts[] = "--- REGLES GENERALES ---";
		$parts[] = "- Reponds directement avec le message a envoyer, sans commentaire ni explication.";
		$parts[] = "- Ne mets pas de placeholder comme [Nom] ou [Date], utilise les informations fournies.";
		$parts[] = "- Adapte le ton et la longueur au canal de communication.";
		$parts[] = "- Sois naturel et humain, evite le ton robotique.";

		return implode("\n\n", $parts);
	}

	/**
	 * Build the user message with context
	 *
	 * @param array  $context         Context data
	 * @param string $incomingMessage Incoming message to reply to
	 * @param string $channel         Channel code
	 * @return string User message
	 */
	private function buildUserMessage($context, $incomingMessage, $channel)
	{
		$parts = array();

		// Company context
		if (!empty($context['company'])) {
			$c = $context['company'];
			$companyInfo = "Entreprise : ".$c['name'];
			if (!empty($c['city'])) $companyInfo .= " (".$c['city'].")";
			if (!empty($c['type'])) $companyInfo .= " - Type : ".$c['type'];
			if (!empty($c['naf'])) $companyInfo .= " - NAF : ".$c['naf'];
			$parts[] = $companyInfo;
		}

		// Contact context
		if (!empty($context['contact'])) {
			$ct = $context['contact'];
			$contactInfo = "Contact : ".$ct['firstname']." ".$ct['lastname'];
			if (!empty($ct['position'])) $contactInfo .= " - Poste : ".$ct['position'];
			$parts[] = $contactInfo;
		}

		// Prospect status
		if (!empty($context['prospect_status'])) {
			$statusLabels = array('cold' => 'Froid', 'warm' => 'Tiede', 'hot' => 'Chaud', 'negotiation' => 'Negociation', 'won' => 'Gagne', 'lost' => 'Perdu');
			$label = $statusLabels[$context['prospect_status']] ?? $context['prospect_status'];
			$parts[] = "Statut prospect : ".$label;
		}
		if (!empty($context['last_sentiment'])) {
			$sentimentLabels = array('positive' => 'Positif', 'neutral' => 'Neutre', 'negative' => 'Negatif');
			$label = $sentimentLabels[$context['last_sentiment']] ?? $context['last_sentiment'];
			$parts[] = "Dernier sentiment : ".$label;
		}

		// Interaction history
		if (!empty($context['history'])) {
			$parts[] = "--- HISTORIQUE DES ECHANGES (du plus recent au plus ancien) ---";
			foreach ($context['history'] as $h) {
				$dir = ($h['direction'] === 'IN') ? 'Recu' : 'Envoye';
				$line = "[".$h['date']."] ".$h['type']." (".$dir.")";
				if (!empty($h['summary'])) {
					$summary = dol_trunc(strip_tags($h['summary']), 200);
					$line .= " : ".$summary;
				}
				$parts[] = $line;
			}
		}

		// Incoming message
		if (!empty($incomingMessage)) {
			$parts[] = "--- MESSAGE RECU A TRAITER ---";
			$parts[] = $incomingMessage;
			$parts[] = "---";
			$parts[] = "Genere une reponse appropriee a ce message.";
		} else {
			$parts[] = "---";
			$parts[] = "Genere un premier message de prise de contact.";
		}

		return implode("\n", $parts);
	}

	/**
	 * Call Claude API via cURL
	 *
	 * @param string $systemPrompt System prompt
	 * @param string $userMessage  User message
	 * @return string|false Generated message or false on error
	 */
	private function callClaudeAPI($systemPrompt, $userMessage)
	{
		$apiKey = getDolGlobalString('LEMONCRM_AI_API_KEY');
		$model = getDolGlobalString('LEMONCRM_AI_MODEL', 'claude-sonnet-4-20250514');

		$data = array(
			'model' => $model,
			'max_tokens' => 1024,
			'system' => $systemPrompt,
			'messages' => array(
				array('role' => 'user', 'content' => $userMessage),
			),
		);

		$ch = curl_init('https://api.anthropic.com/v1/messages');
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json',
				'x-api-key: '.$apiKey,
				'anthropic-version: 2023-06-01',
			),
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 10,
		));

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		if (!empty($curlError)) {
			$this->error = 'Erreur de connexion : '.$curlError;
			return false;
		}

		$decoded = json_decode($response, true);

		if ($httpCode !== 200) {
			$errorMsg = $decoded['error']['message'] ?? 'Erreur API HTTP '.$httpCode;
			$this->error = $errorMsg;
			return false;
		}

		if (empty($decoded['content'][0]['text'])) {
			$this->error = 'Reponse API vide ou invalide';
			return false;
		}

		return $decoded['content'][0]['text'];
	}

	/**
	 * Get channel-specific formatting rules
	 *
	 * @param string $channel Channel code
	 * @return string Rules text
	 */
	public static function getChannelRules($channel)
	{
		$rules = array(
			'LCRM_EMAIL' => "Canal : Email\n- Format structure avec objet et corps de mail\n- Ton professionnel\n- Commence par une formule de politesse adaptee\n- Termine par une signature professionnelle\n- Longueur : 100 a 250 mots",
			'LCRM_LINKEDIN' => "Canal : Message LinkedIn\n- Ton conversationnel et professionnel\n- Court et percutant\n- Pas de formule de politesse trop formelle\n- Maximum 300 caracteres (ideal pour LinkedIn)\n- Personnalise en fonction du profil",
			'LCRM_WHATSAPP' => "Canal : WhatsApp\n- Message tres court et direct\n- Ton amical mais professionnel\n- Emojis autorises avec moderation\n- Maximum 160 caracteres\n- Une seule idee par message",
			'LCRM_TEL' => "Canal : Script telephonique\n- Format script structure :\n  1. Accroche (presentation + raison de l'appel)\n  2. Proposition de valeur (2-3 phrases)\n  3. Question d'engagement\n  4. Reponses aux objections courantes\n- Ton dynamique et naturel\n- Phrases courtes pour etre lu a voix haute",
			'LCRM_TEAMS' => "Canal : Message Teams\n- Semi-formel\n- Clair et structure\n- Longueur moderee (50 a 150 mots)\n- Peut inclure des points ou une liste",
			'LCRM_NOTE' => "Canal : Note interne\n- Format libre\n- Resume des points cles\n- Actions a retenir",
			'LCRM_RELANCE' => "Canal : Message de relance\n- Rappelle le contexte de l'echange precedent\n- Court et direct\n- Propose une action concrete\n- Ton courtois mais assertif",
			'LCRM_RDV' => "Canal : Invitation rendez-vous\n- Propose un creneau concret\n- Precise le format (visio/presentiel)\n- Indique la duree estimee\n- Resume l'ordre du jour",
		);

		return $rules[$channel] ?? '';
	}

	/**
	 * Get configured objectives (from DB or defaults)
	 *
	 * @return array Array of objectives [{code, label, prompt}, ...]
	 */
	public static function getObjectives()
	{
		$json = getDolGlobalString('LEMONCRM_AI_OBJECTIVES');
		if (!empty($json)) {
			$objectives = json_decode($json, true);
			if (is_array($objectives) && !empty($objectives)) {
				return $objectives;
			}
		}

		return self::getDefaultObjectives();
	}

	/**
	 * Get default objectives
	 *
	 * @return array
	 */
	public static function getDefaultObjectives()
	{
		return array(
			array(
				'code' => 'rdv',
				'label' => 'Obtenir un RDV / Teams',
				'prompt' => "L'objectif est d'obtenir un rendez-vous visio ou physique avec le prospect. Propose une date et un creneau. Sois direct mais courtois.",
			),
			array(
				'code' => 'proposal',
				'label' => 'Proposition commerciale',
				'prompt' => "L'objectif est d'amener le prospect a accepter l'envoi d'une proposition commerciale. Mets en avant la valeur ajoutee et propose d'envoyer un devis personnalise.",
			),
			array(
				'code' => 'starter',
				'label' => 'Premier contact',
				'prompt' => "C'est un premier contact a froid. L'objectif est de susciter l'interet du prospect pour nos services. Sois accrocheur et personnalise en fonction du profil du prospect.",
			),
		);
	}
}
