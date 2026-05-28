# Section FACTURES ÉMISES — Pages 1 & 2

## `/invoices` — Liste des factures émises
## `/invoices/new` — Création d'une facture

---

## 1. `/invoices` — Liste des factures émises

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
CTAs de création masqués pour `VIEWER`.

### Objectif
Lister, filtrer, rechercher et agir en masse sur toutes les factures émises du tenant.

### Features / fonctionnalités

#### Barre d'outils supérieure
- CTA principal "Nouvelle facture" → `/invoices/new` (ACCOUNTANT+)
- Bouton "Importer" (upload XML Factur-X/UBL pour import depuis un autre outil)
- Bouton "Exporter" → modale d'export (CSV, Excel, FEC, XML)
- Compteur total affiché : "X factures"

#### Filtres & recherche
- **Recherche full-text** : numéro, nom client, montant (debounced 300ms, Stimulus)
- **Filtre statut** : cases à cocher multiples (DRAFT / VALIDATED / SENT / ACKNOWLEDGED / REJECTED / PAID / CANCELLED)
- **Filtre période** : sélecteur date (du…au) ou raccourcis (mois courant, trimestre, année)
- **Filtre client** : select2 / autocomplétion depuis `Contact`
- **Filtre montant** : min / max (champs numériques)
- **Filtre format** : Factur-X / UBL / CII
- **Filtre type** : Facture / Avoir / Proforma
- Bouton "Réinitialiser les filtres"
- Persistance des filtres en query string (URL partageable, ex: `/invoices?status=REJECTED&period=2026-Q1`)

#### Tableau principal
Colonnes configurables (ordre drag & drop, visibilité toggle) :

| Colonne | Détail | Triable |
|---|---|---|
| N° facture | Lien vers `/invoices/{id}` | ✓ |
| Client | Nom + SIRET tronqué | ✓ |
| Date d'émission | Format JJ/MM/AAAA | ✓ |
| Date d'échéance | Colorée si dépassée (rouge) | ✓ |
| Montant HT | Formaté avec devise | ✓ |
| TVA | Montant TVA | ✗ |
| Montant TTC | Formaté avec devise | ✓ |
| Format | Badge Factur-X / UBL / CII | ✗ |
| Statut | Badge coloré (cycle DGFiP) | ✓ |
| Actions | Icônes contextuelles | ✗ |

**Actions contextuelles par ligne** (selon statut) :
- `DRAFT` : Éditer | Valider | Dupliquer | Supprimer
- `VALIDATED` : Voir | Transmettre manuellement | Dupliquer | Annuler
- `SENT` : Voir | Suivre transmission | Dupliquer
- `ACKNOWLEDGED` : Voir | Enregistrer paiement | Émettre avoir | Dupliquer
- `REJECTED` : Voir | Corriger (→ edit) | Dupliquer
- `PAID` : Voir | Dupliquer | Télécharger PDF
- `CANCELLED` : Voir | Dupliquer

**Actions en masse** (checkbox multi-sélection) :
- Valider les brouillons sélectionnés
- Transmettre à la PDP
- Exporter (PDF ZIP, CSV, XML)
- Supprimer les brouillons (avec confirmation modale)

#### Pagination & affichage
- Pagination : 25 / 50 / 100 par page (select)
- Turbo Frame sur le tableau (filtres et pagination sans rechargement page)
- Tri côté serveur (paramètre query string `sort` + `dir`)
- Affichage alternatif : vue "Kanban" par statut (optionnel, toggle)

#### Totaux en pied de tableau
- Somme des montants HT, TVA, TTC sur la sélection filtrée courante
- Nombre de factures par statut dans la sélection

#### Edge cases UX
- Aucune facture (tenant nouveau) : illustration + CTA "Créer votre première facture"
- Aucun résultat pour les filtres actifs : message + bouton "Réinitialiser les filtres"
- Facture en statut `REJECTED` : ligne surlignée en rouge pâle + icône alerte
- Échéance dépassée (statut `ACKNOWLEDGED`) : date en rouge + tooltip "Retard de X jours"
- Export volumineux (> 500 factures) : traitement async Messenger + notification email "Votre export est prêt"
- Transmission en cours (statut `SENT`) : spinner sur le badge + tooltip "Transmission en cours…"

### Composants UI
- Toolbar Bootstrap avec groupes de boutons
- Composant Stimulus `FilterController` (gestion filtres + query string)
- Composant Stimulus `BulkSelectController` (sélection multiple + actions en masse)
- Tableau Twig avec Turbo Frame `<turbo-frame id="invoices-list">`
- Badges statut `_invoice_status_badge.html.twig` (réutilisé)
- Modale d'export (Bootstrap modal, choix format)
- Dropdown actions contextuelles par ligne (Bootstrap dropdown)
- Toast de confirmation pour actions en masse

### Appels API / services Symfony

| Service | Action |
|---|---|
| `InvoiceController::index()` | Listing paginé avec filtres |
| `InvoiceRepository::findByTenantWithFilters()` | Requête filtrée + triée + paginée |
| `InvoiceExportService` | Export CSV/Excel/FEC/XML (sync ou async) |
| `BulkInvoiceActionService` | Validation / transmission en masse |
| `PdpDispatcher` | Transmission manuelle depuis la liste |

**Turbo Frame endpoint**
```
GET /invoices?{filtres}&_turbo_frame=invoices-list
→ retourne uniquement le fragment tableau
```

### Entités Doctrine
`Invoice`, `Contact`, `PdpTransmission`

---

## 2. `/invoices/new` — Création d'une facture

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`

### Objectif
Créer une facture électronique conforme (Factur-X / UBL / CII), la valider et la transmettre à la PDP/PPF.

> C'est la page la plus complexe de l'application. Le formulaire est un **composant Vue 3** complet monté dans la page Twig.

### Features / fonctionnalités

#### En-tête du formulaire
- Titre "Nouvelle facture" + badge type (FACTURE / AVOIR / PROFORMA)
- Bouton "Enregistrer brouillon" (autosave toutes les 60s en arrière-plan)
- Bouton "Aperçu PDF" (panel latéral ou modale, rendu temps réel)
- Bouton "Valider & émettre" (principal, déclenche la validation puis transmission)
- Indicateur statut : `DRAFT` (gris) → se met à jour après validation

#### Bloc — Informations générales
- **Type de document** : Facture | Avoir | Proforma (select, défaut Facture)
- **Numéro** : généré automatiquement selon la séquence (`FAC-2026-XXXX`), affiché en lecture seule (alloué à la validation)
  - Mention "Le numéro sera alloué à la validation"
- **Date d'émission** : date picker (défaut : aujourd'hui)
- **Date d'échéance** : calculée automatiquement depuis les conditions de paiement du client, modifiable
  - Avertissement si > 60 jours (dépassement légal L.441-10)
- **Référence client** (optionnel) : numéro de bon de commande ou référence interne client
- **Objet / description générale** (optionnel)
- **Devise** : héritée des préférences tenant, modifiable par facture

#### Bloc — Client
- **Sélection client** : autocomplétion depuis `Contact` (type CLIENT ou BOTH)
  - Recherche par nom, SIRET, email
  - Lien "Créer un nouveau client" (ouvre modale rapide de création `Contact`)
- Après sélection, affichage automatique des informations :
  - Nom, adresse complète, SIRET, TVA intracommunautaire
  - Identifiant PDP du client (si renseigné)
  - Conditions de paiement habituelles
  - Bouton "Modifier la fiche client" → `/contacts/{id}` (nouvel onglet)
- **Champ identifiant PDP destinataire** : pré-rempli depuis Contact, éditable
  - Requis si le client est assujetti à la réforme (B2B français)
  - Tooltip : "Identifiant du client auprès de sa PDP/PPF, requis pour la transmission électronique"

#### Bloc — Lignes de facture

> Cœur du composant Vue 3 — entièrement réactif

**Tableau de lignes** (colonnes) :
- **#** : numéro de ligne (drag & drop pour réordonner)
- **Description** : champ texte libre ou sélection depuis catalogue `Product`
  - Autocomplétion produit (recherche par référence ou libellé)
  - Sélection produit → pré-remplit description, prix unitaire, TVA, unité
  - Création inline produit (icône "+", ouvre modale)
- **Référence** : référence produit (optionnel, auto-rempli si produit sélectionné)
- **Quantité** : champ numérique (décimales autorisées)
- **Unité** : select (U, H, KG, M², L, Forfait, Autre)
- **Prix unitaire HT** : champ numérique
- **Taux TVA** : select (0%, 5.5%, 10%, 20%, taux spécifique)
  - Mention auto si taux 0% : motif d'exonération obligatoire (select : autoliquidation, export, franchise en base…)
- **Remise %** : champ optionnel (0% par défaut)
- **Montant HT ligne** : calculé automatiquement (qty × pu × (1 - remise))
- **Icône supprimer ligne**

**Actions sur les lignes** :
- "Ajouter une ligne" (bouton bas de tableau)
- "Ajouter un commentaire / section" (ligne de type texte pur, sans montant)
- "Ajouter une remise globale" (ligne remise en % ou montant fixe)
- Drag & drop pour réordonner

**Calculs automatiques en temps réel** (Vue 3 computed) :

```
Pour chaque ligne :
  montant_ht = quantite × prix_unitaire × (1 - remise / 100)

Récapitulatif TVA (groupé par taux) :
  base_ht_taux_X + montant_tva_taux_X

Total général :
  total_ht     = Σ montant_ht lignes
  total_tva    = Σ montant_tva lignes
  total_ttc    = total_ht + total_tva
  net_a_payer  = total_ttc − acomptes éventuels
```

**Récapitulatif TVA** (tableau automatique sous les lignes) :
- Affiche une ligne par taux de TVA utilisé
- Base HT | Taux | Montant TVA

#### Bloc — Acomptes & avoirs imputés (optionnel)
- Sélection d'acomptes précédemment émis à déduire
- Sélection d'avoirs à imputer
- Recalcul automatique du "Net à payer"

#### Bloc — Informations de paiement
- **Mode de paiement** : virement, chèque, carte, prélèvement, autre
- **IBAN** : pré-rempli depuis les préférences tenant, modifiable
- **Conditions** : texte libre pré-rempli depuis template (pénalités, indemnité 40€)

#### Bloc — Format & transmission électronique

> Spécifique réforme 2026-2027

- **Format de sortie** : Factur-X (défaut) / UBL 2.1 / CII D16B
  - Info contextuelle : "Factur-X = PDF lisible + XML structuré embarqué"
- **Mode de transmission** :
  - Via PDP configurée (défaut, nom de la PDP affiché)
  - Via PPF directement
  - Manuel (téléchargement uniquement — pour cas hors périmètre)
- **Affichage identifiant PDP destinataire** (rappel depuis bloc client)

#### Bloc — Notes & pièces jointes
- **Notes internes** : non imprimées sur la facture (mémo interne)
- **Notes client** : imprimées en bas de facture
- **Pièces jointes** : upload fichiers (PDF, images) joints au dossier (non transmis à la PDP sauf configuration explicite)

#### Bloc — Aperçu PDF temps réel
- Panel latéral (sidebar fixe sur desktop) ou modale sur mobile
- Rendu PDF côté serveur (`/api/invoices/preview`) déclenché :
  - À l'ouverture (template vide)
  - Après 2s d'inactivité sur le formulaire (debounce)
  - Manuellement via bouton "Rafraîchir l'aperçu"
- Affiche le PDF dans une iframe (format A4)
- Bouton "Télécharger l'aperçu" (PDF non signé, watermark "APERÇU")

#### Flux de validation & transmission

**Étape 1 — Validation locale (clic "Valider & émettre")**
Contrôles de conformité côté serveur :
- Présence des champs obligatoires (client, au moins 1 ligne, dates)
- SIRET client valide (si B2B français)
- Identifiant PDP destinataire renseigné (si B2B soumis à réforme)
- Cohérence TVA (motifs d'exonération si taux 0%)
- Montant non nul
- Format de numérotation conforme
- Si erreurs : liste des erreurs avec liens vers les champs concernés, pas de transmission

**Étape 2 — Allocation du numéro de séquence**
- `InvoiceNumberingService::allocate()` : lock pessimiste DB, incrément atomique
- Numéro définitivement attribué, séquence incrémentée

**Étape 3 — Génération du document structuré**
- `FormatConverter::generate()` : création Factur-X (PDF/A-3 + XML CII) ou UBL/CII
- Hash SHA-256 calculé et stocké
- Upload S3/MinIO (clé `tenants/{tenant_id}/invoices/{year}/{invoice_id}.pdf`)

**Étape 4 — Transmission PDP (async Messenger)**
- Message `TransmitInvoiceMessage` dispatché sur la queue Redis
- Statut `Invoice` → `SENT`
- Worker Symfony Messenger consomme le message, appelle l'API PDP
- Résultat mis à jour via Mercure (dashboard + page détail)
- En cas d'erreur PDP : statut `REJECTED` + notification

**Feedback utilisateur pendant la transmission**
- Redirect immédiat vers `/invoices/{id}` (statut `VALIDATED` puis `SENT`)
- Turbo Stream met à jour le badge statut en temps réel
- Toast "Facture transmise à la PDP" quand `ACKNOWLEDGED`

#### Edge cases UX
- Perte de connexion pendant la saisie : autosave toavert la perte, récupération au reload
- Client sans identifiant PDP (B2B) : warning bloquant avant validation
- Taux TVA 0% sans motif : erreur de validation
- Ligne avec quantité 0 : warning (non bloquant)
- Date d'échéance < date d'émission : erreur bloquante
- Dépassement délai légal 60j : warning non bloquant avec mention légale
- PDP non configurée : warning "Aucune PDP configurée — la facture sera créée en brouillon uniquement. [Configurer la PDP →]"
- Séquence verrouillée (première facture déjà émise) : numérotation non modifiable
- Montant total négatif (remise > total) : erreur bloquante
- Timeout génération PDF aperçu : message "Aperçu indisponible" sans bloquer la saisie
- Modale création client rapide : si annulation, retour au formulaire sans perte de données

### Composants UI
- **Composant Vue 3 `<InvoiceEditor>`** : montage dans `<div id="invoice-editor">` depuis Twig
  - Props : `tenantConfig`, `existingInvoice` (null pour new), `contacts`, `products`
  - Émits : `saved` (brouillon), `validated` (prêt à émettre)
- `ProductAutocomplete` (Vue 3, sous-composant)
- `ClientSelector` (Vue 3, avec modale création rapide)
- `LineItemRow` (Vue 3, drag & drop avec `vue-draggable`)
- `TaxSummaryTable` (Vue 3 computed)
- `InvoiceTotals` (Vue 3 computed, sticky bas de page)
- `PdfPreviewPanel` (Stimulus `PdfPreviewController`, iframe + debounce)
- Stepper de validation (Bootstrap progress steps)
- Toast Turbo Stream pour retour statut transmission

### Appels API / services Symfony

| Service | Action |
|---|---|
| `InvoiceController::new()` | Affichage formulaire, données initiales |
| `InvoiceController::create()` | POST création brouillon |
| `InvoiceController::validate()` | POST validation + allocation numéro + génération |
| `InvoiceNumberingService::allocate()` | Lock pessimiste + incrément séquence |
| `FormatConverter::generate()` | Génération Factur-X / UBL / CII |
| `ArchiveService::store()` | Upload S3 + hash SHA-256 |
| `PdpDispatcher::transmit()` | Dispatch message Messenger |
| `AuditLogger` | `invoice.created`, `invoice.validated`, `invoice.sent` |
| `NotificationService` | Notification transmission |
| `ContactRepository::search()` | Autocomplétion client |
| `ProductRepository::search()` | Autocomplétion produit |

**Endpoints API internes (Vue 3 → Symfony)**

```
POST   /api/invoices                    → création brouillon
PUT    /api/invoices/{id}               → mise à jour brouillon
POST   /api/invoices/{id}/validate      → validation + numérotation + génération
POST   /api/invoices/preview            → rendu PDF aperçu (watermark)
GET    /api/contacts/search?q={query}   → autocomplétion client
GET    /api/products/search?q={query}   → autocomplétion produit
POST   /api/contacts                    → création rapide client (modale)
```

### Entités Doctrine
`Invoice`, `InvoiceLine`, `Contact`, `Product`, `InvoiceSequence`,
`PdpTransmission`, `AuditLog`

### Dépendances
- Pré-remplit depuis `/invoices/{id}/duplicate` (données copiées)
- Pré-remplit depuis `/invoices/{id}/credit-note` (avoir basé sur une facture)
- Produits depuis `/products`
- Clients depuis `/contacts`
- Séquence depuis `InvoiceSequence` (configurée dans `/settings/sequences`)
- Template PDF depuis `InvoiceTemplate` (configuré dans `/settings/templates`)
- Post-validation → `/invoices/{id}`
