# WC Multi-Step Booking Checkout

Plugin WordPress pour créer un workflow multi-étapes avec WooCommerce Bookings.

## 🎯 Objectif

Transformer le processus de commande WooCommerce standard en un parcours guidé en 4 étapes distinctes, intégrées dans le site via Elementor, avec stockage sécurisé des données dans la commande et disponibilité dans les webhooks WooCommerce.

## 📋 Fonctionnalités

### Workflow Multi-Étapes

- **Étape 1** : Sélection de la prestation et des dates (WooCommerce Bookings)
- **Étape 2** : Formulaire complémentaire (Gravity Forms)
- **Étape 3** : Signature électronique
- **Étape 4** : Validation finale et redirection checkout

### Sécurité et Persistance

- Gestion de session WooCommerce sécurisée
- Protection d'accès séquentiel aux étapes
- Validation côté serveur à chaque transition
- TTL configurable pour les sessions (5-60 minutes)

### Intégration Elementor

- Shortcodes pour intégration complète
- Barre de progression responsive
- Boutons de navigation avec validation
- Protection de contenu par étape

### Administration

- Interface de configuration intuitive
- Validation en temps réel
- Statut de configuration
- Mode debug avec logs détaillés

## 🚀 Installation

1. **Télécharger** le plugin dans `/wp-content/plugins/wc_multi_step_booking_checkout/`
2. **Installer les dépendances** : `composer install`
3. **Activer** le plugin dans l'admin WordPress
4. **Configurer** les pages dans WooCommerce > Multi-Step Checkout

## ⚙️ Configuration

### Prérequis

- WordPress 5.0+
- WooCommerce 6.0+
- WooCommerce Bookings (recommandé)
- PHP 7.4+

### Pages du Workflow

Configurez 4 pages WordPress (une par étape) dans l'interface d'administration :

- **Étape 1** : Page avec produit bookable
- **Étape 2** : Page avec formulaire
- **Étape 3** : Page avec signature
- **Étape 4** : Page de validation

## 📝 Shortcodes

### `[wcmsbc_progress]`

Affiche la barre de progression du workflow.

**Attributs :**

- `show_labels="true"` : Afficher les libellés d'étapes
- `show_percentage="false"` : Afficher le pourcentage
- `class=""` : Classes CSS supplémentaires

**Exemple :**

```
[wcmsbc_progress show_labels="true" class="my-custom-class"]
```

### `[wcmsbc_next]`

Bouton pour passer à l'étape suivante.

**Attributs :**

- `text="Suivant"` : Texte du bouton
- `class="btn btn-primary"` : Classes CSS
- `validate="true"` : Activer la validation

**Exemple :**

```
[wcmsbc_next text="Continuer" class="btn btn-custom"]
```

### `[wcmsbc_prev]`

Bouton pour revenir à l'étape précédente.

**Attributs :**

- `text="Précédent"` : Texte du bouton
- `class="btn btn-secondary"` : Classes CSS
- `show_on_step_1="false"` : Masquer sur l'étape 1

**Exemple :**

```
[wcmsbc_prev text="Retour" show_on_step_1="true"]
```

### `[wcmsbc_step_guard]`

Protection de contenu par étape.

**Attributs :**

- `step="2"` : Étape requise pour voir le contenu
- `message="..."` : Message si accès non autorisé

**Exemple :**

```
[wcmsbc_step_guard step="2" message="Complétez l'étape 1 d'abord"]
  Contenu visible uniquement après l'étape 1
[/wcmsbc_step_guard]
```

## 🏗️ Architecture

### Structure Modulaire

```
src/
├── Core/
│   ├── Plugin.php          # Orchestration principale
│   └── Logger.php          # Gestion des logs
├── Modules/
│   ├── Session/            # Gestion des sessions
│   ├── Workflow/           # Logique du workflow
│   └── Shortcodes/         # Shortcodes Elementor
└── Admin/                  # Interface d'administration
```

### Principes de Développement

- **Architecture modulaire** : Séparation claire des responsabilités
- **SOLID** : Respect des principes SOLID
- **PSR-4** : Autoload standardisé
- **Sécurité** : Validation, sanitisation, nonces
- **Performance** : Chargement conditionnel des assets

## 📊 Données Stockées

### Session WooCommerce

- `wizard_started` : Timestamp de démarrage
- `current_step` : Étape courante
- `steps_completed` : Étapes validées
- `form_data` : Données du formulaire
- `signature_data` : Données de signature

### Métadonnées de Commande

- `_tb_form_data` : Données formulaire (JSON)
- `_tb_signature` : Données signature (JSON)
- `_tb_wizard_version` : Version du workflow

### Webhooks

Enrichissement automatique des payloads avec :

```json
{
  "tb_wizard": {
    "form": {...},
    "signature": {...},
    "wizard_version": "1.0",
    "booking_id": "..."
  }
}
```

## 🚨 Sécurité

### Validation

- Nonces sur tous les appels AJAX
- Sanitisation des données utilisateur
- Validation côté serveur obligatoire
- Vérification des capabilities

### Protection d'Accès

- Accès séquentiel aux étapes
- Redirection automatique si contournement
- Expiration des sessions
- Validation des prérequis

## 🎨 Personnalisation CSS

### Classes Principales

- `.wc-msbc-progress` : Barre de progression
- `.wc-msbc-next-btn` : Bouton suivant
- `.wc-msbc-prev-btn` : Bouton précédent
- `.wc-msbc-step` : Étape individuelle

### Responsive

Design entièrement responsive avec breakpoints :

- Desktop : > 1200px
- Tablet : 768px - 1200px
- Mobile : < 768px

## 📈 Version

**Version actuelle :** 1.0.0

### Changelog

- **1.0.0** : Version initiale MVP
  - Workflow 4 étapes
  - Shortcodes Elementor
  - Interface d'administration
  - Session WooCommerce

## 🤝 Support

Pour le support et les questions :

1. Vérifiez la configuration dans l'admin
2. Consultez les logs de debug
3. Vérifiez les prérequis (WooCommerce, Bookings)

## 📄 Licence

GPL-2.0-or-later

---

**Développé par TB Formation** - Plugin WordPress professionnel pour workflow e-commerce avancé.
