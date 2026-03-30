# Historique des versions - LemonCRM

Toutes les modifications notables du module sont listees ici.

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
