# Historique des versions - LemonCRM

Toutes les modifications notables du module sont listees ici.

## [3.0.0] - 2026-06-11

### Cassant
- **IDs de permissions renumérotés** : 5002101/02/03 → 21000201/02/03 (alignement sur la plage officielle Lemon 210002 × 100 + index). Sur une instance existante, migrer en SQL AVANT de déployer (sinon la réactivation du module efface les droits accordés) :
  `UPDATE llx_rights_def SET id = 21000201 WHERE id = 5002101 AND module = 'lemoncrm';` (idem 02/03) puis les mêmes UPDATE sur `llx_user_rights.fk_id` et `llx_usergroup_rights.fk_id`.
- **`lib/lemoncrm.lib.php` déplacé vers `core/lib/lemoncrm.lib.php`** (convention Dolibarr). Supprimer l'ancien dossier `lib/` au déploiement.

### Ajouté
- **Relance = événement agenda** : chaque relance planifiée crée un événement « à faire » (type LCRM_RELANCE) dans l'agenda Dolibarr à la date/heure de relance — rappels natifs, visibilité agenda et fiche projet. Clôturé automatiquement quand la relance est marquée faite, supprimé si la relance disparaît. Nouvelle colonne `fk_actioncomm_followup`. Désactivable (`LEMONCRM_FOLLOWUP_AGENDA`).
- **Statut prospect reporté sur la fiche tiers** : le statut prospect de l'interaction met à jour le statut de prospection natif du tiers (`fk_stcomm`). Mapping par défaut froid→à contacter, tiède/chaud/négociation→en cours, gagné→contact fait, perdu→ne pas contacter ; personnalisable via `LEMONCRM_STCOMM_MAP` (JSON). Désactivable (`LEMONCRM_SYNC_STCOMM`).
- **API REST** (`/api/index.php/lemoncrm/interactions`) : liste (filtre socid, pagination), détail, création, relance faite, suppression. Auth DOLAPIKEY + permissions du module.
- **Profil d'export** natif (Outils > Export) : interactions avec tiers, contact, auteur, relance, sentiment, statut.
- **Box page d'accueil** « Relances CRM à faire » (en retard / aujourd'hui / à venir).
- **Filtre et colonne Auteur** sur le dashboard et la liste des interactions.
- **Seed des dictionnaires** sentiment et statut prospect à l'installation (tables créées vides auparavant).

### Corrigé
- **Fiche interaction cassée** : `require_once` vers `projet/class/html.formprojet.class.php` (inexistant en Dolibarr 22, la classe est dans `core/class/`) → fatal en plein rendu, boutons Enregistrer/Annuler absents et toggles morts.
- **Bouton « Créer une tâche » du dashboard** : lien GET vers un endpoint qui exige POST → 405 systématique. Passé en mini-formulaire POST.
- **Fiche en vue : résumé HTML affiché brut** (balises visibles). Rendu via `dol_htmlwithnojs` comme sur le dashboard.
- **Filtre « en retard » de la liste** : comparait une DATE à un datetime → les relances du jour sortaient en retard.
- **Label agenda des relances** : entités HTML décodées (`t&eacute;l&eacute;phone` → `téléphone`).
- Update check GitHub : les échecs sont aussi mis en cache 24 h (fini l'appel bloquant 5 s à chaque ouverture de la page admin quand GitHub est indisponible).
- Référence dupliquée possible en création simultanée : régénération + retry sur collision d'index unique.
- Suppression d'un parent de thread : les enfants sont re-parentés (l'aîné devient parent) au lieu de garder un `fk_parent` orphelin.
- `Module210002Name` : clé de traduction alignée sur le numéro de module (restée sur 500210 depuis la migration d'ID).

### Sécurité
- `action=followup_done` : passage en POST + token CSRF (état modifiable via simple GET auparavant).
- Suppression de masse du dashboard : vérification explicite du token CSRF.
- Scoping entité (`entity`) sur `fetch()`, `ajax/contact_info.php` et le rattachement de thread.
- `ajax/dictionary.php` action=list : vérification de la permission de lecture.
- Message d'erreur de formulaire échappé (`dol_escape_htmltag`).
- IDs `c_actioncomm` choisis dynamiquement à l'installation (un INSERT IGNORE à id fixe entrait silencieusement en collision avec un autre module).

## [2.1.0] - 2026-06-10

### Ajoute
- **Lien projet exploite de bout en bout** (la colonne `fk_project` existait depuis la v2 mais n'etait jamais ecrite ni lue) :
  - `createActionComm()` / `updateActionComm()` propagent `fk_project` -> l'evenement agenda apparait sur la fiche projet Dolibarr
  - `update()` persiste `fk_project`
  - Fiche interaction : selecteur « Projet lie » (projets ouverts) dans « Plus de details », projet affiche dans la vue
  - Liste des interactions : colonne Projet (jointure `llx_projet`, triable)
- Compatible avec le pont temps du module LemonDeck 1.2.0 (`POST /lemondeck/interaction` avec `fk_project`)

## [1.0.1] - 2026-03-30

### Securite
- **CSRF** : toutes les actions d'ecriture (suppression, cloture de tache, relance, creation devis/facture/tache, rattachement thread) passent en POST + token CSRF au lieu de liens GET
- `ajax/create_document.php` et `ajax/link_interaction.php` : refus des requetes GET sur les actions d'ecriture

### Ajoute
- **Prefixe LCRM_** : les types d'interaction utilisent le prefixe `LCRM_` (LCRM_TEL, LCRM_EMAIL, LCRM_LINKEDIN, etc.) dans le dictionnaire Dolibarr `c_actioncomm`. Migration automatique des interactions existantes depuis les anciens codes AC_
- **Page de configuration** (Admin > LemonCRM) :
  - Nom et icone du menu principal personnalisables (prise d'effet immediate)
  - Option de persistance du tiers dans le Quicklog (page prime vs persistant)
- **Page A propos** : documentation des dictionnaires avec liens directs
- **Quicklog** : autocomplete jQuery UI pour la recherche de tiers
- **Quicklog** : lien cliquable vers la fiche du tiers a cote du nom
- **Quicklog** : persistance du tiers selectionne en sessionStorage (comportement configurable)
- **Quicklog** : types d'interaction injectes dynamiquement depuis PHP via le hook `printCommonFooter`
- **Dictionnaires** dans Admin > Dictionnaires :
  - Types d'interaction : geres via le dictionnaire agenda Dolibarr (prefixe LCRM_), activation/desactivation par type
  - Sentiments CRM : dictionnaire dedie LemonCRM
  - Statuts prospect CRM : dictionnaire dedie LemonCRM

### Remerciements
Merci a **protectora** pour sa contribution et ses idees sur la configuration des types, le lien vers la fiche tiers, la persistance du tiers et la securisation CSRF. Plusieurs de ses propositions ont ete integrees et adaptees dans cette version.

## [1.0.0] - 2026-03

### Version initiale
- Popup Quicklog global (bouton jaune sur toutes les pages)
- Types d'interaction : Appel, Email, LinkedIn, Teams, RDV, Note
- Dashboard unifie (global + filtre par tiers)
- Liste interactions avec accordeon
- Double ecriture actioncomm + table custom
- Dictionnaires sentiments et statuts prospect
- Hooks sur fiches tiers, contact, propal, facture, commande, projet
- WYSIWYG contenteditable
- Progressive disclosure (details, suivi)
- Systeme de threads (rattachement d'interactions)
- Boutons creation devis/facture/tache projet depuis une interaction
