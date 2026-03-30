# LemonCRM - Module Dolibarr

Module CRM pour Dolibarr 22+ : suivi des interactions commerciales (appels, emails, rendez-vous, LinkedIn, Teams) avec dashboard unifié, threads de conversation, et intégration complète avec l'écosystème Dolibarr.

Développé par [Lemon - Agence de communication](https://hellolemon.fr), Clermont-Ferrand.

## Fonctionnalités

### Quicklog (bouton flottant)
- Bouton jaune accessible sur toutes les pages Dolibarr (sauf login)
- Détecte automatiquement le tiers de la page courante
- Recherche autocomplete pour changer de tiers
- Ouvre un popup de saisie rapide d'interaction

### Formulaire d'interaction
- 8 types (préfixe LCRM_) : Appel, Email, LinkedIn, Teams, RDV, RDV physique, Note, Relance
- Direction (entrant/sortant), durée, issue d'appel
- Éditeur WYSIWYG Dolibarr (CKEditor) pour le résumé
- Sélection du contact avec affichage contextuel (téléphone si appel, email si email)
- Auto-sélection du contact unique
- Sentiment et statut prospect (dictionnaires personnalisables)
- Planification de suivi avec raccourcis (cet après-midi, dans 3h, demain, etc.)
- Rattachement à un thread existant (fk_parent)
- Affichage de l'auteur de l'interaction

### Dashboard
- Statistiques : interactions de la semaine, relances en retard, jours sans contact, tâches en cours
- Tableau des relances à faire avec boutons : créer une tâche, marquer comme fait
- Tableau des tâches en cours avec boutons : saisir du temps, clôturer
- Liste des interactions avec filtres (tiers, date, type, direction, texte libre, suivi)
- Sélection multiple + suppression en masse

### Threads de conversation
- Regroupement des interactions liées (modèle plat, 1 niveau)
- Dernière interaction visible, clic pour déplier les anciennes
- Bouton "Enchaîner" pour ajouter une suite
- Bouton "Rattacher" pour lier une interaction après coup

### Modale de détail
- Clic sur le message pour ouvrir une modale avec le détail complet
- Retours à la ligne, contact, durée, tags, auteur
- Actions business :
  - **Devis** : crée un devis brouillon pré-rempli (tiers, conditions paiement, description, durée)
  - **Facture** : crée une facture brouillon pré-remplie
  - **Tâche projet** : crée une tâche dans un projet du tiers
  - **Temps consommé** : saisir du temps sur une tâche existante (avec filtre de recherche)
- Actions CRM :
  - **Modifier** : éditer l'interaction dans un popup
  - **Enchaîner** : créer une interaction liée
  - **Agenda** : lien vers l'événement dans l'agenda Dolibarr
  - **Rattacher** : lier à une interaction existante du tiers
  - **Supprimer** : supprime l'interaction et l'événement agenda associé

### Intégration Dolibarr
- Double écriture : chaque interaction crée un événement dans l'agenda Dolibarr (ActionComm)
- Objets liés : les devis/factures créés sont rattachés à l'interaction source
- Création devis/facture avec conditions de paiement du tiers
- Onglet CRM sur les fiches tiers
- Menu "Lemon" dans la barre principale
- Variables CSS Dolibarr pour le thème
- Mass action standard Dolibarr

## Installation

### Prérequis
- Dolibarr 22.0+
- Module FCKEditor/CKEditor activé (recommandé, pour l'éditeur WYSIWYG)

### Installation manuelle
```bash
# Copier le module dans le dossier custom
cp -r lemoncrm /path/to/dolibarr/htdocs/custom/

# Vérifier les permissions
chown -R www-data:www-data /path/to/dolibarr/htdocs/custom/lemoncrm
```

### Activation
1. Aller dans **Configuration > Modules/Applications**
2. Chercher "LemonCRM" dans la catégorie "CRM"
3. Activer le module
4. Les tables SQL sont créées automatiquement

### Migration depuis une version précédente
```bash
# Exécuter la migration v2 (ajout threads + projets)
mysql -u root dolibarr < /path/to/dolibarr/htdocs/custom/lemoncrm/sql/llx_lemoncrm_interaction_v2.sql
```

## Configuration

### Types d'interaction
Les types d'interaction du quicklog utilisent le préfixe `LCRM_` dans le dictionnaire Dolibarr :
**Admin > Dictionnaires > Types d'événements de l'agenda**

Types par défaut : LCRM_TEL, LCRM_EMAIL, LCRM_LINKEDIN, LCRM_TEAMS, LCRM_RDV, LCRM_MEETING, LCRM_NOTE, LCRM_RELANCE.

Seuls les types avec le préfixe `LCRM_` apparaissent dans le quicklog. Les types standards de l'agenda Dolibarr (AC_TEL, AC_FAX, etc.) ne sont pas affichés.

Pour masquer un type, désactivez-le dans le dictionnaire. Pour ajouter un nouveau type, créez-le avec un code commençant par `LCRM_` (ex: LCRM_WHATSAPP).

### Sentiments et statuts prospect
Ces deux dictionnaires sont propres à LemonCRM et gérés dans :
**Admin > Dictionnaires > Sentiments CRM / Statuts prospect CRM**

### Apparence
Dans **Admin > LemonCRM > Configuration** :
- Nom et icône du menu principal (par défaut : "Lemon" avec fa-lemon)
- Comportement du tiers dans le quicklog (page prime ou persistant)

## Structure du module

```
lemoncrm/
├── ajax/
│   ├── contact_info.php           # Infos contact (téléphone, email)
│   ├── create_document.php        # Création devis/facture/projet depuis une interaction
│   ├── dictionary.php             # CRUD dictionnaires (sentiment, statut prospect)
│   ├── link_interaction.php       # Rattachement thread + listing tâches
│   └── search_company.php         # Recherche tiers (autocomplete quicklog)
├── class/
│   ├── actions_lemoncrm.class.php      # Hooks Dolibarr (footer, element properties)
│   └── lemoncrm_interaction.class.php  # Classe métier interaction
├── core/modules/
│   └── modLemonCRM.class.php      # Descripteur du module
├── css/
│   └── lemoncrm.css               # Styles (quicklog, formulaire, dashboard, threads)
├── js/
│   └── lemoncrm.js                # Quicklog panel + recherche tiers
├── langs/fr_FR/
│   └── lemoncrm.lang              # Traductions françaises
├── lib/
│   └── lemoncrm.lib.php           # Helpers (types, icônes, dates, onglets)
├── sql/
│   ├── data.sql                              # Données initiales (dictionnaires)
│   ├── llx_lemoncrm_interaction.sql          # Table principale
│   ├── llx_lemoncrm_interaction.key.sql      # Index
│   ├── llx_lemoncrm_interaction_v2.sql       # Migration v2 (threads + projets)
│   ├── llx_c_lemoncrm_sentiment.sql          # Dictionnaire sentiments
│   └── llx_c_lemoncrm_prospect_status.sql    # Dictionnaire statuts prospect
├── admin/
│   └── setup.php                  # Page de configuration
├── dashboard.php                  # Dashboard CRM unifié
├── interaction_card.php           # Fiche interaction (création/édition/vue)
├── interaction_list.php           # Liste des interactions
└── index.php                      # Redirection vers dashboard
```

## Licence

GPLv3 - Voir [LICENSE](LICENSE)

## Auteur

[Lemon](https://hellolemon.fr)
