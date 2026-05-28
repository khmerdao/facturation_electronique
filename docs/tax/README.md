# Section TVA & COMPTABILITÉ — Tableau de bord TVA et exports

Pilotage de la TVA collectée/déductible et génération des exports réglementaires
(FEC, CSV, XML) à destination du comptable ou de l'administration fiscale.

---

## Sommaire

| Page | URL | Rôles |
|---|---|---|
| Tableau de bord TVA | `/tax` | Tous (export ACCOUNTANT+) |
| Exports comptables | `/exports` | ACCOUNTANT+ |

---

## 1. `/tax` — Tableau de bord TVA

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`
Actions d'export et de simulation masquées pour `VIEWER`.

### Objectif
Donner une vision claire et périodique de la situation TVA du tenant :
TVA collectée sur ventes, TVA déductible sur achats, solde à reverser ou
crédit de TVA, avec préparation des données pour la déclaration CA3/CA12.

### Features / fonctionnalités

#### Sélecteur de période & régime TVA

**Sélecteur de période** (haut de page, persisté en query string)
- Mois courant (défaut)
- Mois précédent
- Trimestre courant / précédent
- Année courante / précédente
- Période personnalisée (date picker du…au)

**Régime de TVA** (configuré dans `/settings/organisation`, affiché en lecture seule)
- **Régime réel normal** : déclaration mensuelle (CA3)
- **Régime réel simplifié** : déclaration trimestrielle / annuelle (CA12)
- **Franchise en base** : aucune TVA collectée (affichage simplifié)
- Le régime détermine la fréquence d'affichage et les alertes d'échéance

#### Bloc — Synthèse TVA de la période

**3 métriques principales** (cards avec détail)

| Métrique | Calcul | Couleur |
|---|---|---|
| TVA collectée | Σ TVA sur factures émises `ACKNOWLEDGED`/`PAID` de la période | Bleu |
| TVA déductible | Σ TVA sur factures reçues `APPROVED`/`PAID` de la période | Teal |
| **Solde TVA** | TVA collectée − TVA déductible | Vert si crédit / Rouge si à reverser |

- Si solde > 0 : "TVA à reverser à la DGFiP : {montant}€"
- Si solde < 0 : "Crédit de TVA : {montant}€ — report ou remboursement possible"
- Si franchise en base : "Régime franchise en base — TVA non applicable"

**Avertissement échéance déclaration**
- Calcul automatique de la prochaine date limite de dépôt CA3/CA12
- Bandeau si J ≤ 15 avant échéance : "⚠ Votre déclaration TVA est à déposer avant le {date}"
- Lien vers le portail impots.gouv.fr

#### Bloc — Détail TVA collectée (factures émises)

Tableau récapitulatif par taux de TVA :

| Taux | Base HT imposable | Montant TVA | Nb factures |
|---|---|---|---|
| 20% | 10 000,00 € | 2 000,00 € | 12 |
| 10% | 2 000,00 € | 200,00 € | 3 |
| 5,5% | 500,00 € | 27,50 € | 1 |
| 0% (exonéré) | 1 500,00 € | 0,00 € | 2 |
| **Total** | **14 000,00 €** | **2 227,50 €** | **18** |

- Clic sur une ligne → filtre `/invoices` sur la période + le taux correspondant
- Détail par motif d'exonération si taux 0% (autoliquidation, export, franchise…)

#### Bloc — Détail TVA déductible (factures reçues)

Même structure tableau pour les factures reçues `APPROVED`/`PAID` :

| Taux | Base HT | TVA déductible | Nb factures |
|---|---|---|---|
| 20% | 5 000,00 € | 1 000,00 € | 8 |
| 10% | 200,00 € | 20,00 € | 1 |
| **Total** | **5 200,00 €** | **1 020,00 €** | **9** |

- Clic sur une ligne → filtre `/received-invoices` sur la période + taux

#### Bloc — Régularisations & ajustements (ACCOUNTANT+)

> Section avancée pour les ajustements manuels nécessaires avant déclaration

- Liste des ajustements manuels saisis (corrections d'assiette, régularisations…)
- Bouton "Ajouter un ajustement" :
  - Type : TVA collectée / TVA déductible
  - Taux de TVA
  - Montant (positif ou négatif)
  - Motif (texte libre, obligatoire)
  - Référence document (optionnel)
- Ces ajustements sont inclus dans les exports FEC avec nature `OD` (opération diverse)
- `AuditLog` : `tax_adjustment.created` avec payload complet

#### Bloc — Transactions hors périmètre e-invoicing (e-reporting)

Récapitulatif des opérations soumises uniquement à l'e-reporting (pas à la
facturation électronique obligatoire) :

| Type | CA HT | TVA | Nb opérations |
|---|---|---|---|
| Ventes B2C France | 8 500,00 € | 1 700,00 € | 142 |
| Ventes intracommunautaires | 3 200,00 € | 0,00 € | 7 |
| Exportations hors UE | 1 800,00 € | 0,00 € | 3 |

- Lien "Voir le détail e-reporting →" → `/e-reporting`
- Note : "Ces données sont transmises à la DGFiP via l'e-reporting.
  Elles sont incluses dans votre récapitulatif TVA mais font l'objet
  d'une transmission séparée du flux e-invoicing."

#### Bloc — Graphiques TVA (Vue 3 + Chart.js)

**Graphique 1 — Évolution TVA collectée vs déductible (12 mois glissants)**
- Deux courbes : collectée (bleu) / déductible (teal)
- Axe X : mois | Axe Y : montant €
- Tooltip au survol : montant + nb factures
- Zone colorée entre les deux courbes (solde visuel)

**Graphique 2 — Répartition par taux de TVA (période sélectionnée)**
- Graphique en donut : part de chaque taux dans la TVA collectée totale
- Légende colorée par taux

#### Bloc — Aide à la déclaration CA3

> Correspondance entre les données de l'application et les cases de la CA3

| Case CA3 | Libellé | Montant calculé |
|---|---|---|
| 01 | Ventes et prestations à taux normal (20%) | {base HT 20%} |
| 05 | Autres opérations imposables (10%) | {base HT 10%} |
| 09 | Acquisitions intracommunautaires | {achats intracom} |
| 20 | TVA brute (total TVA collectée) | {TVA collectée} |
| 23 | TVA déductible sur immobilisations | {TVA déductible immo} |
| 24 | TVA déductible sur autres biens et services | {TVA déductible autres} |
| 28 | Total TVA déductible | {TVA déductible total} |
| **29** | **TVA nette à payer (ou crédit)** | **{solde TVA}** |

- Bouton "Exporter la synthèse CA3" (PDF ou CSV) — données pré-remplies
- Disclaimer : "Ces données sont indicatives. Vérifiez avec votre expert-comptable
  avant de déposer votre déclaration."

#### Edge cases UX
- Franchise en base : page simplifiée, pas de tableaux TVA, message
  "Vous êtes en franchise en base de TVA — aucune TVA n'est collectée ni déduite"
- Aucune facture sur la période : tableaux vides avec message explicatif
- Factures avec taux TVA manquant ou 0% sans motif :
  - Alerte "X facture(s) ont des anomalies TVA — [Voir →]"
  - Lien filtrant `/invoices` sur les factures concernées
- Ajustement avec montant > TVA collectée :
  - Warning "Cet ajustement génère un crédit de TVA important.
    Vérifiez les données avec votre comptable."
- Données e-reporting non synchronisées :
  - Warning "Les données e-reporting de ce mois ne sont pas encore transmises"
  - Lien → `/e-reporting`

### Composants UI
- Sélecteur de période (Stimulus `PeriodSelectorController` réutilisé)
- Cards synthèse TVA (Twig)
- Tableaux récapitulatifs Twig + Turbo Frames par bloc
- Graphiques Vue 3 + Chart.js (`<TvaChart>`, lazy-loaded)
- Tableau aide CA3 avec correspondances cases (Twig)
- Modale ajustement manuel (Bootstrap modal + formulaire Symfony)
- Bandeau alerte échéance déclaration

### Appels API / services Symfony

| Service | Action |
|---|---|
| `TaxController::index()` | Agrégation TVA par période |
| `TaxAggregationService` | Calcul TVA collectée/déductible par taux et période |
| `TaxAdjustmentService` | Gestion ajustements manuels |
| `Ca3ReportService` | Génération aide déclaration CA3 |
| `EReportingAggregator` | Données e-reporting pour récapitulatif |
| `InvoiceRepository` | Factures émises filtrées par période + statut |
| `ReceivedInvoiceRepository` | Factures reçues filtrées |
| `AuditLogger` | `tax_adjustment.created` |

**Turbo Frame endpoints**
```
GET /tax/frames/collected?period={p}    → tableau TVA collectée
GET /tax/frames/deductible?period={p}   → tableau TVA déductible
GET /tax/frames/adjustments?period={p}  → liste ajustements
GET /tax/frames/ereporting?period={p}   → récapitulatif e-reporting
```

### Entités Doctrine
`Invoice`, `InvoiceLine`, `ReceivedInvoice`, `ReceivedInvoiceLine`,
`TaxAdjustment`, `EReportingBatch`, `Payment`

---

## 2. `/exports` — Exports comptables

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`

### Objectif
Générer et télécharger les fichiers d'export réglementaires et comptables :
FEC (Fichier des Écritures Comptables), CSV analytique, XML DGFiP,
à destination du comptable ou en cas de contrôle fiscal.

### Features / fonctionnalités

#### Bloc — Export FEC (Fichier des Écritures Comptables)

> Format obligatoire en cas de contrôle fiscal (art. L.47 A LPF).
> Structure normalisée par l'arrêté du 29 juillet 2013.

**Paramètres de l'export FEC**

- **Exercice comptable** :
  - Sélection de l'année (select, exercices disponibles depuis la 1ère facture)
  - Ou période personnalisée (dates libres)
- **Périmètre** :
  - Toutes les écritures (factures émises + reçues + paiements + ajustements)
  - Factures émises uniquement
  - Factures reçues uniquement
  - Paiements uniquement
- **Options avancées** :
  - Inclure les brouillons (non par défaut — non recommandé pour FEC officiel)
  - Inclure les ajustements manuels (`TaxAdjustment`)
  - Format des montants : virgule ou point comme séparateur décimal
  - Encodage fichier : UTF-8 (défaut) ou ISO-8859-1 (compatibilité anciens logiciels)

**Structure du FEC généré** (colonnes normalisées)

```
JournalCode | JournalLib | EcritureNum | EcritureDate | CompteNum |
CompteLib | CompAuxNum | CompAuxLib | PieceRef | PieceDate |
EcritureLib | Debit | Credit | EcritureLet | DateLet |
ValidDate | Montantdevise | Idevise
```

**Journaux comptables générés automatiquement**

| Journal | Code | Contenu |
|---|---|---|
| Ventes | `VT` | Factures émises (ACKNOWLEDGED/PAID/CANCELLED) |
| Achats | `AC` | Factures reçues (APPROVED/PAID) |
| Trésorerie | `TR` | Paiements enregistrés |
| Opérations diverses | `OD` | Ajustements TVA manuels, avoirs |

**Comptes comptables** (Plan Comptable Général)
- Compte client : `411{SIRET_6_premiers_chiffres}` ou `411000` (générique)
- Compte fournisseur : `401{SIRET_6_premiers_chiffres}` ou `401000`
- Compte produit : depuis `Product.accounting_code` ou `706000` par défaut
- Compte TVA collectée : `44571{taux}` (ex : `445710` pour 20%)
- Compte TVA déductible : `44566{taux}`
- Compte banque : `512000`

**Avertissements pré-export FEC**
- Produits sans code comptable → liste des produits concernés + lien `/products`
- Contacts sans SIRET → liste + impact sur les comptes auxiliaires
- Factures avec anomalies TVA → liste
- Trous dans la numérotation → si détectés, avertissement (normalement impossible
  grâce au lock pessimiste, mais vérification défensive)

**Génération**
- Export synchrone si < 500 écritures
- Export async (Messenger) si ≥ 500 écritures :
  - Notification email + in-app "Votre export FEC est prêt"
  - Lien de téléchargement sécurisé (URL S3 signée, expiry 24h)
- Fichier produit : `FEC_{SIRET}_{YYYYMMDD}_{HHMMSS}.txt` (encodage, séparateur tab)
- Hash SHA-256 du fichier affiché après génération (pour vérification d'intégrité)

#### Bloc — Export CSV analytique

Export tabulaire simple pour traitement dans Excel/LibreOffice ou import
dans un logiciel comptable tiers.

**Types d'exports CSV disponibles**

| Export | Contenu | Filtres disponibles |
|---|---|---|
| Factures émises | Toutes les colonnes Invoice | Période, statut, client, format |
| Factures reçues | Toutes les colonnes ReceivedInvoice | Période, statut, fournisseur |
| Paiements | Tous les paiements | Période, sens, mode |
| Contacts | Clients et/ou fournisseurs | Type, statut, pays |
| Catalogue produits | Tous les produits | Type, TVA, statut |
| Synthèse TVA | Récapitulatif par taux et période | Période, régime TVA |

- Sélection des colonnes à inclure (cases à cocher)
- Choix séparateur : virgule, point-virgule (défaut pour Excel FR), tabulation
- Choix encodage : UTF-8, UTF-8 BOM (Excel), ISO-8859-1
- Aperçu des 5 premières lignes avant téléchargement

#### Bloc — Export XML DGFiP (e-reporting)

> Fichiers XML de transmission à la DGFiP pour l'e-reporting.
> Gérés principalement depuis `/e-reporting` — raccourci ici.

- Lien "Gérer les transmissions e-reporting →" → `/e-reporting`
- Export XML du dernier batch soumis (téléchargement direct)

#### Bloc — Historique des exports

Tableau des exports générés :

| Date | Type | Période | Généré par | Taille | Actions |
|---|---|---|---|---|---|
| 15/04/2026 | FEC 2026-Q1 | Jan-Mar 2026 | Marie D. | 245 Ko | Télécharger \| Hash |
| 01/04/2026 | CSV Factures | Mars 2026 | Jean M. | 18 Ko | Télécharger |

- Conservation des exports 90 jours (puis suppression S3)
- Badge "Expiré" pour les exports de plus de 90 jours (régénération possible)
- Hash SHA-256 accessible par ligne (preuve d'intégrité)

#### Edge cases UX
- Export FEC avec exercice sans données :
  - Message "Aucune écriture sur cette période" + suggestion de changer la période
- Export FEC avec codes comptables manquants :
  - Modale d'avertissement listant les produits sans code
  - Option "Continuer avec les codes par défaut" ou "Aller compléter le catalogue"
- Export async en cours :
  - Bouton grisé "Export en cours…" + spinner
  - Notification email + in-app à la fin
- Lien S3 expiré (> 24h) :
  - Bouton "Régénérer le lien" (crée une nouvelle URL signée sans regénérer le fichier)
- Fichier S3 supprimé (> 90 jours) :
  - Bouton "Regénérer l'export" (recalcul complet depuis les données en base)
- Encodage : si caractères spéciaux (accents) mal rendus en aperçu → toggle UTF-8/ISO

### Composants UI
- Cards par type d'export avec formulaires de paramétrage
- Accordéon Bootstrap (options avancées FEC)
- Aperçu CSV (table Bootstrap 5 premières lignes, Turbo Frame)
- Tableau historique exports avec Turbo Frame
- Progress bar pour exports async (polling Stimulus toutes les 3s)
- Modale avertissements pré-FEC avec liste des anomalies

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ExportController::index()` | Affichage page + historique |
| `FecExportService` | Génération FEC (sync ou async) |
| `CsvExportService` | Génération CSV par type |
| `ExportHistoryRepository` | Historique des exports |
| `ArchiveService::getSignedUrl()` | URL S3 temporaire pour téléchargement |
| `ExportIntegrityService` | Calcul + vérification hash SHA-256 |
| `AuditLogger` | `export.generated` (type, période, nb lignes) |

**Endpoints**
```
POST /exports/fec
     Body: { year, period_from, period_to, scope, options }
     → sync: fichier direct | async: { job_id }

POST /exports/csv
     Body: { type, filters, columns, separator, encoding }
     → sync: fichier direct | async: { job_id }

GET  /exports/{job_id}/status
     → { status: pending|processing|done|error, download_url?, progress_pct }

GET  /exports/{export_id}/download
     → redirect URL S3 signée (ou 410 Gone si expiré)

POST /exports/{export_id}/refresh-link
     → { download_url }  (nouvelle URL S3 signée sans regénérer)
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `ExportJob` | `id`, `tenant_id`, `type` (FEC\|CSV\|XML), `status`, `params` (json), `s3_key`, `file_hash`, `file_size`, `generated_by`, `generated_at`, `expires_at` |
| `Invoice` | Source écritures VT |
| `ReceivedInvoice` | Source écritures AC |
| `Payment` | Source écritures TR |
| `TaxAdjustment` | Source écritures OD |
| `Product` | Codes comptables PCG |
| `Contact` | Comptes auxiliaires 411/401 |
| `AuditLog` | Traçabilité exports |

---

## Notes transversales — Section TVA & COMPTABILITÉ

### Périmètre des données TVA

Les données TVA de l'application sont **indicatives** — elles ne remplacent pas
une comptabilité tenue par un expert-comptable. Trois limites à toujours afficher :

1. Seules les factures avec statut `ACKNOWLEDGED` ou `PAID` (émises)
   et `APPROVED` ou `PAID` (reçues) entrent dans les calculs TVA.
   Les brouillons et les factures annulées sont exclus.

2. La TVA déductible sur immobilisations vs autres biens n'est pas distinguée
   automatiquement (dépend de la catégorie comptable). L'export FEC utilise
   le code comptable du produit pour cette distinction.

3. Le régime TVA (normal, simplifié, franchise) est saisi manuellement dans
   `/settings/organisation` — il n'est pas vérifié auprès de l'administration.

### FEC et conformité LPF

Le FEC doit respecter strictement l'arrêté du 29 juillet 2013 :
- Encodage UTF-8 sans BOM ou ISO-8859-1 (au choix)
- Séparateur tabulation (`\t`)
- Fin de ligne CRLF
- 18 colonnes exactement dans l'ordre normalisé
- Montants avec 2 décimales, virgule comme séparateur décimal
- Dates au format YYYYMMDD
- Pas de ligne vide, pas d'en-tête (certains logiciels acceptent un en-tête — option)

Le `FecExportService` valide ces contraintes avant génération et lève une
exception métier `FecFormatException` si une écriture ne peut pas être
formatée correctement (ex : compte comptable vide).

### Cache et performance
- Calculs TVA mis en cache Redis (tag `tax_{tenant_id}_{period}`, TTL 10 min)
- Invalidation sur tout changement de statut `Invoice` ou `ReceivedInvoice`
- Les exports FEC/CSV ne sont jamais mis en cache — générés à la demande
  depuis les données fraîches (garantie de cohérence pour l'administration)
