# Section CATALOGUE — Produits & services

Référentiel des produits et services facturables du tenant.  
Chaque article du catalogue est une ligne pré-configurée réutilisable dans les factures.  
La gestion des taux de TVA est centrale : elle détermine la conformité fiscale des factures émises.

---

## Sommaire

| Page | URL | Rôles |
|---|---|---|
| Liste des produits | `/products` | Tous |
| Création | `/products/new` | ACCOUNTANT+ |
| Fiche détail / édition | `/products/{id}` | Tous (édition ACCOUNTANT+) |

---

## 1. `/products` — Liste des produits & services

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Boutons de création/suppression masqués pour `VIEWER`.

### Objectif
Lister, filtrer et gérer l'ensemble des articles du catalogue pour une
saisie rapide dans les formulaires de facturation.

### Features / fonctionnalités

#### Barre d'outils supérieure
- CTA "Nouveau produit / service" → `/products/new` (ACCOUNTANT+)
- Bouton "Importer" → modale import CSV/Excel
  - Template téléchargeable (référence, libellé, prix HT, TVA, unité, description)
  - Validation + prévisualisation avant import
  - Rapport d'erreurs post-import
- Bouton "Exporter" → CSV ou Excel
- Compteur : "X produits · Y services · Z archivés"

#### Filtres & recherche
- **Recherche full-text** : référence, libellé, description (debounced 300ms)
- **Filtre type** : Produit / Service / Tous (tabs)
- **Filtre taux TVA** : 0% / 5,5% / 10% / 20% / Taux spécifique
- **Filtre statut** : Actif / Archivé
- **Filtre prix** : min / max HT
- Persistance en query string

#### Tableau principal

| Colonne | Détail | Triable |
|---|---|---|
| Référence | Code article interne | ✓ |
| Libellé | Nom du produit/service | ✓ |
| Type | Badge Produit / Service | ✓ |
| Description | Tronquée à 60 chars + tooltip | ✗ |
| Prix unitaire HT | Formaté avec devise | ✓ |
| Taux TVA | Badge coloré par taux | ✓ |
| Prix TTC | Calculé automatiquement | ✓ |
| Unité | U / H / KG / M²… | ✗ |
| Statut | Actif / Archivé | ✓ |
| Actions | Voir \| Éditer \| Archiver | ✗ |

**Badges TVA par taux**

| Taux | Couleur badge | Label |
|---|---|---|
| 0% | Gris | 0% — Exonéré |
| 5,5% | Bleu clair | 5,5% — Réduit |
| 10% | Teal | 10% — Intermédiaire |
| 20% | Bleu | 20% — Normal |
| Taux spécifique | Amber | {taux}% — Spécifique |

**Actions contextuelles par ligne**
- Voir → `/products/{id}`
- Éditer → `/products/{id}` (mode édition, ACCOUNTANT+)
- Dupliquer (POST action, crée un brouillon copie, ACCOUNTANT+)
- Archiver (ACCOUNTANT+, si pas de facture en cours de brouillon l'utilisant)
- Créer une facture avec ce produit → `/invoices/new?product_id={id}` (ACCOUNTANT+)

**Actions en masse** (ACCOUNTANT+)
- Archiver les produits sélectionnés
- Modifier le taux de TVA en masse (avec avertissement sur l'impact factures futures)
- Exporter la sélection

#### Récapitulatif catalogue (pied de tableau)
- Nombre de produits par taux de TVA
- Prix moyen HT de l'ensemble du catalogue
- Dernier produit ajouté (date)

#### Edge cases UX
- Aucun produit : illustration + CTA "Créer votre premier article"
- Référence dupliquée dans le tenant : badge warning inline "Référence déjà utilisée"
- Produit archivé :
  - Ligne grisée, badge "Archivé"
  - Toujours accessible depuis les factures existantes qui l'utilisent
  - N'apparaît plus dans l'autocomplétion des nouvelles factures
- Modification taux TVA en masse :
  - Modale avertissement "Cette modification n'affecte pas les factures déjà émises.
    Uniquement les nouvelles factures utiliseront le nouveau taux."
  - Confirmation requise
- Import CSV avec taux TVA non reconnu :
  - Ligne en erreur avec message "Taux TVA '15%' non valide —
    valeurs acceptées : 0, 5.5, 10, 20"

### Composants UI
- Tabs "Produits / Services / Tous" (Turbo Frame)
- Tableau avec Turbo Frame `<turbo-frame id="products-list">`
- Composant Stimulus `FilterController` (réutilisé)
- Modale import CSV (Vue 3 `<CsvImporter>` réutilisé depuis `/contacts`)
- Badge TVA (composant Twig `_tva_rate_badge.html.twig`)
- Modale confirmation modification taux TVA en masse

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ProductController::index()` | Listing paginé avec filtres |
| `ProductRepository::findByTenantWithFilters()` | Requête filtrée |
| `ProductImportService` | Import CSV/Excel |
| `ProductExportService` | Export CSV/Excel |
| `BulkTvaUpdateService` | Modification taux TVA en masse |

### Entités Doctrine
`Product`, `InvoiceLine` (lecture — pour vérifier usage en brouillon)

---

## 2. `/products/new` — Création d'un produit ou service

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`

### Objectif
Créer un article du catalogue avec tous ses paramètres fiscaux et commerciaux
pour une réutilisation rapide dans les factures.

### Features / fonctionnalités

#### Bloc — Identification de l'article

- **Type** : Produit / Service (boutons radio, requis)
  - Produit : bien physique livré
  - Service : prestation intellectuelle ou opérationnelle
  - Ce champ est informatif et influe sur le libellé de l'unité suggérée
- **Référence** (requis, unique dans le tenant)
  - Vérification unicité en async (debounce, Stimulus)
  - Format libre (alphanumérique, tirets, points)
  - Auto-génération optionnelle : bouton "Générer une référence"
    (ex : `PROD-2026-001`, `SVC-042`)
- **Libellé** (requis)
  - Nom court affiché sur la facture
  - Max 150 caractères
- **Description longue** (optionnel)
  - Texte plus détaillé (multi-ligne)
  - Affiché sous le libellé dans les lignes de facture si renseigné
  - Max 500 caractères

#### Bloc — Tarification

- **Prix unitaire HT** (requis, ≥ 0)
  - Champ numérique avec séparateur décimal configurable
  - 4 décimales autorisées (pour les prix très bas, ex : énergie au kWh)
- **Unité de mesure** (requis)
  - Select + saisie libre :

  | Valeur | Libellé |
  |---|---|
  | `U` | Unité |
  | `H` | Heure |
  | `J` | Jour |
  | `KG` | Kilogramme |
  | `M` | Mètre |
  | `M2` | Mètre carré |
  | `M3` | Mètre cube |
  | `L` | Litre |
  | `FORFAIT` | Forfait |
  | `LOT` | Lot |
  | `AUTRE` | Autre (saisie libre) |

- **Devise** : héritée du tenant, affichée en lecture seule
  (modification possible au niveau de la facture, pas du catalogue)

#### Bloc — Fiscalité TVA

> Section critique pour la conformité réglementaire

- **Taux de TVA** (requis, select)
  - 0% — Exonéré
  - 5,5% — Taux réduit (alimentation, livres, transports…)
  - 10% — Taux intermédiaire (restauration, travaux…)
  - 20% — Taux normal (défaut)
  - Taux spécifique (champ numérique libre, pour cas particuliers DOM-TOM, etc.)

- **Motif d'exonération TVA** (conditionnel — visible si taux = 0%)
  - Select obligatoire si taux 0% :

  | Code | Libellé |
  |---|---|
  | `EXEMPT_ART293B` | Franchise en base (art. 293 B CGI) |
  | `EXEMPT_EXPORT` | Exportation hors UE |
  | `EXEMPT_INTRACOM` | Livraison intracommunautaire exonérée |
  | `EXEMPT_AUTOLIQ` | Autoliquidation (BTP, sous-traitance) |
  | `EXEMPT_DOM` | Régime DOM (Guadeloupe, Martinique, La Réunion) |
  | `EXEMPT_OTHER` | Autre exonération (texte libre obligatoire) |

  - Ce motif sera automatiquement reporté sur les lignes de facture utilisant ce produit
  - Tooltip réglementaire pour chaque motif avec base légale

- **Code comptable** (optionnel)
  - Code de compte de produit (ex : 706000 — Prestations de services)
  - Utilisé pour l'export FEC et l'intégration comptable
  - Autocomplétion depuis le plan comptable général (PCG) français

- **Code article fournisseur** (optionnel)
  - Pour les revendeurs : référence du fournisseur de ce produit

#### Bloc — Options avancées
- **Produit actif** : toggle (actif par défaut)
- **Prix minimum** (optionnel) : plancher en dessous duquel une alerte est affichée
  lors de la saisie sur une facture (non bloquant)
- **Notes internes** : mémo non imprimé sur les factures

#### Prévisualisation ligne de facture
- Aperçu en temps réel de ce que donnera ce produit dans une ligne de facture :
  ```
  [Référence] Libellé du produit                    1 U × 100,00 € HT     TVA 20%    100,00 € HT
  Description longue si renseignée                                                    120,00 € TTC
  ```
- Se met à jour en temps réel (Stimulus, computed)

#### Actions
- "Enregistrer" → POST création + redirect `/products/{id}`
- "Enregistrer et créer un autre" → POST + reset formulaire (id conservé dans flash)
- "Annuler" → retour `/products`

#### Edge cases UX
- Référence déjà utilisée : erreur inline "Cette référence est déjà utilisée —
  [Voir le produit →]"
- Prix négatif : erreur bloquante (sauf cas d'usage explicite d'une remise produit —
  déconseillé, message informatif)
- Taux TVA 0% sans motif : erreur bloquante à la soumission
- Code comptable invalide (hors PCG) : avertissement non bloquant
- Prix à 0 : avertissement non bloquant "Prix à 0 — ce produit sera facturé
  gratuitement. Confirmez-vous ?"

### Composants UI
- Formulaire Symfony `ProductType` rendu Twig
- Composant Stimulus `ReferenceUniquenessController` (vérif async unicité)
- Composant Stimulus `TvaMotifController` (affichage conditionnel motif exonération)
- Composant Stimulus `LinePreviewController` (prévisualisation ligne facture)
- Select avec recherche (autocomplétion PCG pour le code comptable)
- Tooltip réglementaire sur les motifs TVA (Bootstrap popover)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ProductController::new()` | Affichage formulaire |
| `ProductController::create()` | POST création |
| `ProductRepository::existsByReference()` | Vérification unicité référence |
| `PcgCodeService` | Autocomplétion codes PCG |
| `AuditLogger` | `product.created` |

**Endpoints internes (Stimulus AJAX)**
```
GET /api/products/check-reference?ref={ref}&exclude_id={id}
    → { available: bool }

GET /api/pcg/search?q={query}
    → [{ code: "706000", label: "Prestations de services" }, …]
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `Product` | `id`, `tenant_id`, `reference`, `label`, `description`, `type` (PRODUCT\|SERVICE), `unit_price`, `unit`, `tva_rate`, `tva_exemption_reason`, `accounting_code`, `supplier_reference`, `min_price`, `active`, `notes`, `created_at`, `updated_at` |

---

## 3. `/products/{id}` — Fiche détail & édition

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Édition masquée pour `VIEWER`.

### Objectif
Consulter et modifier un article du catalogue, visualiser son historique
d'utilisation dans les factures et suivre l'évolution de son prix.

### Features / fonctionnalités

#### En-tête de page
- Référence + Libellé (titre H1)
- Badges : type (PRODUIT / SERVICE) + taux TVA + statut (ACTIF / ARCHIVÉ)
- Prix unitaire HT en grand + prix TTC calculé
- Boutons d'action :
  - "Éditer" (ACCOUNTANT+) → mode édition inline
  - "Dupliquer" (ACCOUNTANT+) → POST duplication + redirect `/products/{new_id}/edit`
  - "Archiver" / "Désarchiver" (ACCOUNTANT+)
  - "Créer une facture avec ce produit" → `/invoices/new?product_id={id}` (ACCOUNTANT+)

#### Onglet "Détails"

Affichage de tous les champs de la fiche (lecture seule ou édition inline) :
- Identification : référence, libellé, description, type
- Tarification : prix HT, unité, devise
- Fiscalité : taux TVA, motif exonération (si applicable), code comptable
- Options : prix minimum, notes internes, statut actif

**Mode édition inline**
- Clic "Éditer" → bascule les blocs en mode édition
- Mêmes validations que `/products/new`
- Avertissement si le produit est utilisé dans des **brouillons en cours** :
  "Ce produit est utilisé dans X brouillon(s). La modification du prix ou du taux
  TVA sera appliquée immédiatement à ces brouillons. Confirmez-vous ?"
- Modification taux TVA :
  - Avertissement "Ce changement n'affecte PAS les factures déjà validées ou émises.
    Uniquement les nouvelles lignes créées avec ce produit utiliseront le nouveau taux."
- `AuditLogger` : `product.updated` avec diff complet avant/après

#### Onglet "Utilisation dans les factures"

Historique de toutes les `InvoiceLine` utilisant ce produit :

Colonnes : N° Facture | Client | Date | Qté | Prix unitaire appliqué | Taux TVA appliqué | Montant HT

- **Filtres** : période + statut facture
- **Récapitulatif** :
  - Nombre total d'utilisations
  - Quantité totale vendue (sur toutes les factures `ACKNOWLEDGED`/`PAID`)
  - Chiffre d'affaires généré par ce produit (total HT sur factures émises)
  - Prix moyen facturé (peut différer du prix catalogue si remises appliquées)

- Turbo Frame lazy pour ce tableau

#### Onglet "Historique des prix"

Timeline des modifications de prix avec :
- Date de modification
- Ancien prix → Nouveau prix
- Utilisateur ayant modifié
- Variation en % et en valeur absolue

- Graphique d'évolution du prix (Vue 3 + Chart.js, courbe simple)
- Note : "Les factures émises conservent le prix en vigueur au moment de leur création"

#### Onglet "Historique des modifications"
- Timeline des changements de tous les champs (AuditLog)
- Diff visuel avant/après pour chaque modification

#### Edge cases UX
- Produit archivé :
  - Tous les champs en lecture seule
  - Bandeau "Ce produit est archivé — il n'apparaît plus dans les sélections"
  - Onglet "Utilisation" toujours accessible (factures passées restent visibles)
  - Bouton "Désarchiver" visible
- Produit utilisé dans des brouillons actifs :
  - Badge warning "Utilisé dans X brouillon(s) en cours"
  - Modification de prix → modale d'avertissement avec liste des brouillons impactés
- Produit avec prix à 0 : badge warning "Prix gratuit"
- Aucune utilisation (produit jamais facturé) :
  - Onglet "Utilisation" affiche état vide avec CTA "Créer une facture avec ce produit"
- Tentative d'archivage d'un produit présent dans des brouillons :
  - Erreur "Ce produit est utilisé dans X brouillon(s) non finalisé(s).
    Finalisez ou supprimez ces brouillons avant d'archiver."
- Modification code comptable sur produit déjà exporté en FEC :
  - Warning "Ce produit a déjà été inclus dans des exports FEC.
    La modification du code comptable n'affecte pas les exports passés."

### Composants UI
- En-tête avec badges et boutons d'action
- Onglets Bootstrap, chaque onglet = Turbo Frame lazy
- Édition inline (Stimulus `InlineEditController` réutilisé)
- Tableau utilisation avec Turbo Frame + filtres Stimulus
- Graphique évolution prix (Vue 3 `<PriceHistoryChart>`, lazy-loaded)
- Timeline modifications (composant Twig réutilisé)
- Modales d'avertissement Bootstrap (modification prix, taux TVA)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ProductController::show()` | Chargement fiche produit |
| `ProductController::update()` | PUT mise à jour |
| `ProductController::archive()` | POST archivage |
| `ProductController::duplicate()` | POST duplication |
| `InvoiceLineRepository::findByProduct()` | Historique utilisation |
| `ProductStatsService` | CA généré, quantité vendue, prix moyen |
| `ProductPriceHistoryRepository` | Timeline évolution prix |
| `AuditLogRepository` | Timeline modifications |
| `AuditLogger` | `product.updated`, `product.archived`, `product.duplicated` |

**Turbo Frame endpoints**
```
GET /products/{id}/frames/usage        → tableau utilisation dans factures
GET /products/{id}/frames/price-history → timeline + graphique prix
GET /products/{id}/frames/history      → timeline AuditLog
```

**Endpoints API**
```
PUT    /api/products/{id}              → mise à jour
POST   /api/products/{id}/archive      → archivage
POST   /api/products/{id}/unarchive    → désarchivage
POST   /api/products/{id}/duplicate    → duplication
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `Product` | Tous les champs (lecture + édition) |
| `ProductPriceHistory` | `id`, `product_id`, `tenant_id`, `old_price`, `new_price`, `changed_by`, `changed_at` |
| `InvoiceLine` | Historique utilisation (lecture) |
| `Invoice` | Données factures liées (lecture) |
| `AuditLog` | Timeline (lecture) |

---

## Notes transversales — Section CATALOGUE

### Relation produit ↔ ligne de facture
Quand un produit est sélectionné dans une ligne de facture (`InvoiceLine`),
les valeurs sont **copiées** dans la ligne au moment de la sélection :
- `InvoiceLine.unit_price` ← `Product.unit_price` (modifiable sur la ligne)
- `InvoiceLine.tva_rate` ← `Product.tva_rate` (modifiable sur la ligne)
- `InvoiceLine.tva_exemption_reason` ← `Product.tva_exemption_reason`
- `InvoiceLine.product_id` ← `Product.id` (référence conservée)

Ce comportement garantit que la modification ultérieure d'un produit du catalogue
**n'affecte jamais** les factures déjà créées (immuabilité des factures émises).

### `ProductPriceHistory`
Chaque modification du champ `unit_price` via `ProductController::update()`
déclenche un `INSERT` dans `ProductPriceHistory` (Doctrine EventListener
`ProductPriceHistoryListener` sur `preUpdate`).

### Codes TVA et export FEC
Les `accounting_code` des produits sont utilisés lors de la génération du
FEC (Fichier des Écritures Comptables). L'absence de code comptable sur un
produit déclenche un avertissement lors de l'export FEC (non bloquant —
un code par défaut est utilisé) et une recommandation de compléter le catalogue.

### Archivage et factures existantes
Un produit archivé :
- N'apparaît plus dans l'autocomplétion de `/invoices/new`
- Reste accessible dans les factures existantes qui le référencent
- Reste visible dans les statistiques d'utilisation
- Peut être désarchivé à tout moment (aucune donnée perdue)
