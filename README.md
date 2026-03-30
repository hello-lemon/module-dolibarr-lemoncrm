# LemonCRM - Module Dolibarr

Module CRM pour Dolibarr 22+ : suivi des interactions commerciales (appels, emails, rendez-vous, LinkedIn, Teams) avec dashboard unifié, threads de conversation, et intégration complète avec l'écosystème Dolibarr.

Développé par [Lemon - Agence de communication](https://hellolemon.fr), Clermont-Ferrand.

## Fonctionnalités

### Quicklog (bouton flottant)
- Bouton jaune accessible sur toutes les pages Dolibarr
- Détecte automatiquement le tiers de la page courante
- Recherche autocomplete pour changer de tiers
- Ouvre un popup de saisie rapide d'interaction

### Formulaire d'interaction
- 7 types : Appel, Email, LinkedIn, Teams, RDV, Réunion, Note
- Direction (entrant/sortant), durée, issue d'appel
- Éditeur WYSIWYG Dolibarr (CKEditor) pour le résumé
- Sentiment et statut prospect (dictionnaires personnalisables)
- Planification de suivi avec raccourcis (cet après-midi, dans 3h, demain, etc.)
- Rattachement à un thread existant (fk_parent)

### Dashboard
- Statistiques : interactions de la semaine, relances en retard, jours sans contact, factures impayées
- Tableau des relances à faire avec bouton "fait"
- Liste des interactions avec filtres (tiers, date, type, direction, texte libre, suivi)
- Sélection multiple + suppression en masse (pattern Dolibarr standard)

### Threads de conversation
- Regroupement des interactions liées (modèle plat, 1 niveau)
- Dernière interaction visible, chevron pour déplier les anciennes
- Bouton "Enchaîner" pour ajouter une suite
- Bouton "Rattacher" pour lier une interaction après coup
- Fond visuel sur le thread déplié (variable CSS Dolibarr)

### Modale de détail
- Clic sur le message pour ouvrir une modale avec le détail complet
- Retours à la ligne, contact, durée, tags
- Actions directes :
  - **Devis** : crée un devis brouillon pré-rempli (tiers + description + durée en heures)
  - **Facture** : crée une facture brouillon pré-remplie
  - **Tâche projet** : crée une tâche dans un projet du tiers
  - **Temps consommé** : saisir du temps sur une tâche existante
  - **Modifier** : éditer l'interaction dans un popup
  - **Enchaîner** : créer une interaction liée
  - **Agenda** : lien vers l'événement dans l'agenda Dolibarr
  - **Rattacher** : lier à une interaction existante du tiers
  - **Supprimer** : supprime l'interaction et l'événement agenda associé

### Intégration Dolibarr
- Double écriture : chaque interaction crée un événement dans l'agenda Dolibarr (ActionComm)
- Objets liés : les devis/factures créés sont rattachés à l'interaction source
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

## Structure du module

```
lemoncrm/
├── ajax/
│   ├── create_document.php    # Création devis/facture/projet depuis une interaction
│   ├── dictionary.php         # CRUD dictionnaires (sentiment, statut prospect)
│   ├── link_interaction.php   # Rattachement thread + listing tâches
│   └── search_company.php     # Recherche tiers (autocomplete quicklog)
├── class/
│   ├── actions_lemoncrm.class.php      # Hooks Dolibarr (footer, element properties)
│   └── lemoncrm_interaction.class.php  # Classe métier interaction
├── core/modules/
│   └── modLemonCRM.class.php  # Descripteur du module
├── css/
│   └── lemoncrm.css           # Styles (quicklog, formulaire, dashboard, threads)
├── js/
│   └── lemoncrm.js            # Quicklog panel + recherche tiers
├── langs/fr_FR/
│   └── lemoncrm.lang          # Traductions françaises
├── lib/
│   └── lemoncrm.lib.php       # Helpers (types, icônes, dates, onglets)
├── sql/
│   ├── data.sql                              # Données initiales (dictionnaires)
│   ├── llx_lemoncrm_interaction.sql          # Table principale
│   ├── llx_lemoncrm_interaction.key.sql      # Index
│   ├── llx_lemoncrm_interaction_v2.sql       # Migration v2 (threads + projets)
│   ├── llx_c_lemoncrm_sentiment.sql          # Dictionnaire sentiments
│   └── llx_c_lemoncrm_prospect_status.sql    # Dictionnaire statuts prospect
├── dashboard.php              # Dashboard CRM unifié
├── interaction_card.php       # Fiche interaction (création/édition/vue)
├── interaction_list.php       # Liste des interactions
└── index.php                  # Redirection vers dashboard
```

## Licence

GPLv3 - Voir [LICENSE](LICENSE)

## Auteur

**Axel Piquet-Gauthier** - [Lemon](https://hellolemon.fr)
