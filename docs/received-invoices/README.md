# Section FACTURES REÇUES — Réception & validation

Entrant en vigueur au **1er septembre 2026** (obligation de réception des factures électroniques).  
Les factures reçues arrivent via la PDP/PPF du tenant sous forme de fichiers structurés
(Factur-X, UBL, CII). L'application les parse, les stocke, et expose un workflow de validation.

---

## Sommaire

| Page | URL | Rôles |
|---|---|---|
| Liste des factures reçues | `/received-invoices` | Tous |
| Détail + validation | `/received-invoices/{id}` | Tous (action ACCOUNTANT+) |

---

## 1. `/received-invoices` — Liste des factures reçues

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Actions de validation masquées pour `VIEWER`.

### Objectif
Lister, filtrer et prioriser toutes les factures électroniques reçues via PDP/PPF,
avec mise en évidence des actions urgentes (validation, contestation).

### Features / fonctionnalités

#### Barre d'outils supérieure
- Badge compteur "X en attente de validation" (rouge si > 0)
- Bouton "Synchroniser avec la PDP" : déclenche manuellement un polling PDP
  (normalement automatique via webhook, ce bouton est un fallback)
  - Spinner + message "Synchronisation en cours…"
  - Toast résultat : "X nouvelle(s) facture(s) récupérée(s)" ou "Aucune nouvelle facture"
- Bouton "Exporter" → modale export (CSV, Excel, FEC)

#### Filtres & recherche
- **Recherche full-text** : numéro, fournisseur, montant (debounced 300ms)
- **Filtre statut** :
  - `PENDING_VALIDATION` — reçue, en attente d'action
  - `APPROVED` — validée, comptabilisée
  - `CONTESTED` — contestée auprès du fournisseur
  - `REJECTED` — rejetée (refus de payer)
  - `PAID` — paiement enregistré
- **Filtre fournisseur** : autocomplétion depuis `Contact` (type SUPPLIER ou BOTH)
- **Filtre période** : date de réception (du…au) ou raccourcis
- **Filtre format** : Factur-X / UBL / CII
- **Filtre montant** : min / max
- Persistance filtres en query string

#### Tableau principal

| Colonne | Détail | Triable |
|---|---|---|
| N° facture fournisseur | Numéro tel que reçu dans le XML | ✓ |
| Fournisseur | Nom + SIRET | ✓ |
| Date de réception | Horodatage réception PDP | ✓ |
| Date d'émission | Date figurant sur la facture | ✓ |
| Date d'échéance | Colorée si dépassée | ✓ |
| Montant HT | | ✓ |
| Montant TTC | | ✓ |
| TVA déductible | Montant TVA récupérable | ✗ |
| Format | Badge Factur-X / UBL / CII | ✗ |
| Statut | Badge coloré | ✓ |
| Actions | Boutons contextuels | ✗ |

**Badges de statut**

| Statut | Couleur | Label |
|---|---|---|
| `PENDING_VALIDATION` | Orange | À valider |
| `APPROVED` | Teal | Validée |
| `CONTESTED` | Amber | Contestée |
| `REJECTED` | Rouge | Rejetée |
| `PAID` | Vert | Payée |

**Actions contextuelles par ligne**

| Statut | Actions disponibles (ACCOUNTANT+) |
|---|---|
| `PENDING_VALIDATION` | Valider \| Contester \| Rejeter \| Voir |
| `APPROVED` | Enregistrer paiement \| Voir \| Contester |
| `CONTESTED` | Voir \| Résoudre contestation |
| `REJECTED` | Voir |
| `PAID` | Voir |

**Actions en masse**
- Valider les factures sélectionnées (confirmation modale)
- Exporter la sélection

#### Indicateur de délai légal
- Pour chaque facture `PENDING_VALIDATION` : affichage du délai de paiement restant
- Coloration progressive : vert → orange → rouge selon l'urgence
- Tooltip : "Échéance dans X jours — délai légal 30/45/60j"

#### Flux d'entrée des factures reçues (background)

> Ce flux est documenté ici car il conditionne ce qu'on voit dans la liste.

**Via webhook PDP (mode push)**
1. La PDP du tenant notifie l'endpoint webhook : `POST /api/webhooks/pdp/{tenant_slug}`
2. `PdpWebhookHandler` vérifie la signature (HMAC ou certificat selon la PDP)
3. Téléchargement du fichier depuis la PDP (URL fournie dans le webhook)
4. `ReceivedInvoiceParser` parse le XML (Factur-X / UBL / CII) :
   - Extraction des champs obligatoires (numéro, émetteur, SIRET, montants, TVA, dates)
   - Validation XSD du format
   - Calcul hash SHA-256 du fichier reçu
5. Création `ReceivedInvoice` en statut `PENDING_VALIDATION`
6. Upload S3/MinIO du fichier original
7. Matching automatique avec `Contact` existant (par SIRET fournisseur)
8. `NotificationService` : notification "Nouvelle facture reçue de {fournisseur}"
9. `MercurePublisher` : mise à jour compteur dashboard temps réel

**Via polling PDP (mode pull, fallback)**
- Job Symfony Messenger schedulé toutes les 15 minutes : `PollPdpMessage`
- Même pipeline de traitement qu'en mode webhook

**Via dépôt manuel (import)**
- Bouton "Importer une facture" sur le dashboard ou cette liste
- Upload drag & drop (Factur-X PDF, XML UBL/CII)
- Même pipeline de parsing (sans webhook)
- Cas d'usage : facture reçue par email hors PDP (transitoire avant généralisation)

#### Edge cases UX
- Aucune facture reçue : illustration + message "Aucune facture reçue — votre PDP transmettra automatiquement les factures de vos fournisseurs"
- PDP non configurée : bandeau d'avertissement "Configurez votre PDP pour recevoir des factures électroniques" + lien `/settings/pdp`
- Facture reçue avec SIRET fournisseur inconnu : badge "Fournisseur inconnu" + CTA "Créer la fiche fournisseur"
- Facture reçue avec XML invalide (erreur XSD) : statut `PARSE_ERROR`, ligne en rouge, CTA "Voir l'erreur technique"
- Doublon détecté (même numéro + même émetteur SIRET) : warning "Possible doublon de la facture {ref}" avec lien vers l'existante
- Synchronisation PDP manuelle : désactivée si une sync est déjà en cours (bouton grisé + tooltip)

### Composants UI
- Toolbar avec bouton sync + compteur badge (Turbo Frame pour le compteur)
- Composant Stimulus `FilterController` (réutilisé)
- Tableau avec Turbo Frame `<turbo-frame id="received-invoices-list">`
- Badge urgence délai paiement (composant Twig `_payment_deadline_badge.html.twig`)
- Modale confirmation validation en masse
- Toast résultat synchronisation PDP

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ReceivedInvoiceController::index()` | Listing paginé avec filtres |
| `ReceivedInvoiceRepository::findByTenantWithFilters()` | Requête filtrée |
| `PdpSyncService::syncManual()` | Déclenchement sync manuelle |
| `PdpWebhookHandler` | Réception webhook PDP (endpoint séparé) |
| `ReceivedInvoiceParser` | Parse XML + validation XSD |
| `DuplicateDetectionService` | Détection doublons par SIRET + numéro |
| `ContactMatchingService` | Matching automatique fournisseur par SIRET |
| `ArchiveService::store()` | Upload S3 fichier reçu |
| `NotificationService` | Notification nouvelle facture reçue |
| `MercurePublisher` | Mise à jour compteur temps réel |

**Endpoint webhook PDP**
```
POST /api/webhooks/pdp/{tenant_slug}
Headers : X-PDP-Signature: {hmac}
Body : { invoice_url, invoice_id, format, emitter_siret, … }
→ 200 OK (traitement async Messenger)
```

**Endpoint sync manuelle**
```
POST /api/received-invoices/sync
→ { synced: int, errors: int }
```

### Entités Doctrine
`ReceivedInvoice`, `Contact` (fournisseur), `PdpTransmission`

---

## 2. `/received-invoices/{id}` — Détail + validation

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Actions de validation/contestation masquées pour `VIEWER`.

### Objectif
Visualiser le contenu complet d'une facture reçue, vérifier sa conformité, l'approuver
ou la contester, et déclencher le processus de paiement.

### Features / fonctionnalités

#### En-tête de page
- Numéro de facture fournisseur (titre H1) + badge statut
- Nom du fournisseur + lien vers `/contacts/{id}`
- Date de réception PDP | Date d'émission | Date d'échéance
- Boutons d'action contextuels (voir matrice ci-dessous)
- Breadcrumb : Factures reçues → N° facture

**Matrice d'actions par statut**

| Statut | Actions ACCOUNTANT+ |
|---|---|
| `PENDING_VALIDATION` | Valider \| Contester \| Rejeter |
| `APPROVED` | Enregistrer paiement \| Contester \| Télécharger |
| `CONTESTED` | Résoudre (→ valider ou rejeter) \| Télécharger |
| `REJECTED` | Télécharger |
| `PAID` | Télécharger |

#### Panneau gauche — Visualisation du document

**Onglet "Document"**
- Rendu du fichier reçu :
  - Si Factur-X : PDF affiché dans iframe (depuis S3)
  - Si UBL/CII (XML pur) : rendu HTML généré depuis le XML (XSLT ou template Twig)
    - Vue lisible et structurée du document (en-tête, lignes, totaux)
    - Badge "Rendu non-officiel — le fichier XML fait foi"
- Bouton télécharger fichier original (PDF ou XML)
- Hash SHA-256 du fichier reçu (intégrité, piste d'audit fiable)
- Horodatage de réception PDP

**Onglet "Données extraites"**
Tableau des champs parsés depuis le XML, avec indicateur de conformité :

| Champ | Valeur extraite | Statut |
|---|---|---|
| N° facture | FAC-2026-0042 | ✓ Présent |
| SIRET émetteur | 12345678901234 | ✓ Vérifié |
| N° TVA émetteur | FR12345678901 | ✓ Valide |
| Date d'émission | 15/03/2026 | ✓ |
| Date d'échéance | 14/04/2026 | ✓ |
| Montant HT | 1 000,00 € | ✓ |
| Taux TVA | 20% | ✓ |
| Montant TVA | 200,00 € | ✓ |
| Montant TTC | 1 200,00 € | ✓ |
| Mode de règlement | Virement | ✓ |
| IBAN fournisseur | FR76… | ✓ |

- Champs manquants ou invalides affichés en rouge avec explication
- Score de conformité global : "X/Y champs conformes"

**Onglet "XML source"**
- Affichage du XML brut avec syntax highlighting (lecture seule)
- Bouton "Copier le XML"
- Utile pour le débogage ou transmission au comptable

**Onglet "Historique"**
- Timeline des actions sur cette facture reçue :
  - Réception PDP (horodatage exact)
  - Parsing + validation XSD
  - Actions utilisateurs (validation, contestation, paiement)
- Entrées `AuditLog` liées

#### Panneau droit — Actions & validation

**Bloc — Vérifications avant validation**
Liste de contrôles affichés avec statut (✓ / ⚠ / ✗) :
- SIRET fournisseur connu dans vos contacts
- SIRET fournisseur vérifié dans Sirene (API)
- Numéro TVA fournisseur valide
- Format XML conforme (validation XSD)
- Montants cohérents (HT + TVA = TTC)
- Pas de doublon détecté
- Facture dans le délai légal de réception (non antédatée > 12 mois)

Chaque vérification échouée affiche le détail de l'erreur et une action suggérée.

**Bloc — Récapitulatif financier**
- Montant HT | TVA déductible | Montant TTC
- Détail TVA par taux (pour la comptabilité)
- Date d'échéance + délai restant (badge coloré)
- Mode de règlement préconisé + IBAN fournisseur

**Action — Valider la facture**
- Bouton "Valider la facture" (vert, ACCOUNTANT+)
- Si des avertissements ⚠ existent : modale de confirmation
  "Des anomalies ont été détectées. Confirmez-vous la validation ?"
  (liste des avertissements + cases à cocher "J'ai vérifié ce point")
- Si des erreurs ✗ existent : validation bloquée avec message explicatif
- Post-validation :
  - Statut → `APPROVED`
  - `AuditLog` : `received_invoice.approved` avec payload complet
  - Notification à l'équipe (si configuré)
  - Envoi accusé de réception à la PDP du fournisseur (obligatoire réforme 2026)
    - `PdpAcknowledgementService::sendAck()` → message Messenger
    - L'accusé de réception est un message standardisé transmis via PPF/PDP

**Action — Contester la facture**
- Bouton "Contester" (orange, ACCOUNTANT+)
- Modale de contestation :
  - Motif de contestation (select) :
    - Montant incorrect
    - Prestation non reçue / non conforme
    - Doublon
    - Erreur de TVA
    - Référence commande inconnue
    - Autre (texte libre obligatoire)
  - Champ description détaillée (obligatoire)
  - Option "Notifier le fournisseur par email" (avec prévisualisation du message)
- Post-contestation :
  - Statut → `CONTESTED`
  - Envoi message de contestation à la PDP (si supporté par la PDP)
  - Email au fournisseur (si option cochée)
  - `AuditLog` : `received_invoice.contested`

**Action — Rejeter la facture**
- Bouton "Rejeter" (rouge, ACCOUNTANT+)
- Modale de rejet avec motif obligatoire
- Différence avec contestation : le rejet est définitif, la contestation est un dialogue
- Envoi notification de rejet à la PDP du fournisseur
- Statut → `REJECTED`

**Action — Enregistrer le paiement** (si statut `APPROVED`)
- Bouton "Enregistrer le paiement" → `/invoices/{id}/payment`
  (même composant que pour les factures émises, inversé)
- Ou formulaire inline rapide :
  - Date de paiement
  - Montant payé (défaut : montant TTC restant dû)
  - Mode de paiement
  - Référence virement (optionnel)
  - Bouton "Enregistrer"
- Post-paiement : statut → `PAID`

#### Bloc — Fournisseur (sidebar)
- Nom, SIRET, adresse, TVA intra
- Lien "Voir la fiche fournisseur" → `/contacts/{id}`
- Historique des factures reçues de ce fournisseur (3 dernières, lien "Voir tout")
- Bouton "Créer la fiche fournisseur" si fournisseur non encore dans les contacts

#### Edge cases UX
- Fichier XML invalide (erreur XSD au parsing) :
  - Statut `PARSE_ERROR`
  - Onglet "Document" : message d'erreur technique + XML brut
  - Onglet "Données extraites" : champs partiellement extraits avec erreurs
  - CTA "Signaler à votre PDP" + bouton "Importer manuellement les données"
  - Formulaire de saisie manuelle des champs clés (montant, dates, fournisseur)
- PDF non disponible (Factur-X sans PDF, ou S3 indisponible) :
  - Rendu HTML du XML affiché à la place avec badge "Rendu de secours"
- Fournisseur inconnu :
  - Bloc fournisseur affiche "SIRET : {siret} — Non enregistré dans vos contacts"
  - CTA "Créer la fiche fournisseur" (ouvre modale rapide, pré-remplie depuis XML)
- Doublon confirmé :
  - Bandeau orange "Cette facture semble être un doublon de {ref}"
  - Lien vers la facture similaire
  - Bouton "Confirmer quand même" + "Rejeter comme doublon"
- Accusé de réception PDP échoué (après validation) :
  - Warning "L'accusé de réception n'a pas pu être transmis à la PDP"
  - Bouton "Retransmettre l'accusé" (ACCOUNTANT+)
  - La validation locale reste `APPROVED` — seule la transmission PDP est en échec
- Facture en devise étrangère :
  - Taux de change affiché (récupéré depuis ECB ou configurable)
  - Montant converti en EUR (indicatif)
  - Badge "Devise étrangère — {code}"

### Composants UI
- Layout deux colonnes (document à gauche, actions à droite) — une colonne sur mobile
- Onglets Bootstrap (`nav-tabs`) sur le panneau document
- Checklist de validation avec icônes statut (Tabler icons)
- Modales Bootstrap pour contestation, rejet, confirmation validation avec anomalies
- Formulaire paiement inline (Stimulus `InlinePaymentController`)
- Timeline historique (composant Twig réutilisé)
- Bouton sync accusé de réception (Turbo Stream feedback)
- Skeleton loader pendant le chargement du PDF/XML

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ReceivedInvoiceController::show()` | Chargement + données associées |
| `ReceivedInvoiceValidationService` | Exécution des contrôles de conformité |
| `ReceivedInvoiceController::approve()` | POST validation → statut APPROVED |
| `ReceivedInvoiceController::contest()` | POST contestation → statut CONTESTED |
| `ReceivedInvoiceController::reject()` | POST rejet → statut REJECTED |
| `PdpAcknowledgementService::sendAck()` | Envoi accusé réception (async Messenger) |
| `PdpAcknowledgementService::sendContest()` | Envoi message contestation PDP |
| `SireneApiClient` | Vérification SIRET fournisseur (contrôle conformité) |
| `DuplicateDetectionService` | Vérification doublon |
| `ContactMatchingService` | Matching fournisseur + création rapide |
| `PaymentService::recordForReceived()` | Enregistrement paiement fournisseur |
| `CurrencyService` | Conversion devise étrangère (taux ECB) |
| `ArchiveService::getSignedUrl()` | URL S3 fichier reçu |
| `AuditLogger` | Toutes les actions sur la facture reçue |
| `NotificationService` | Notifications équipe |
| `Symfony Mailer` | Email contestation au fournisseur |

**Endpoints**
```
POST /api/received-invoices/{id}/approve
POST /api/received-invoices/{id}/contest   Body: { reason, description, notify_supplier }
POST /api/received-invoices/{id}/reject    Body: { reason, description }
POST /api/received-invoices/{id}/payment   Body: { date, amount, mode, reference }
POST /api/received-invoices/{id}/retry-ack → retransmission accusé réception
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `ReceivedInvoice` | `id`, `tenant_id`, `supplier_contact_id`, `raw_file_s3_key`, `file_hash`, `parsed_data` (json), `status`, `received_at`, `invoice_number`, `invoice_date`, `due_date`, `amount_ht`, `amount_tva`, `amount_ttc`, `currency`, `parse_errors` (json), `ack_sent_at`, `contest_reason`, `contest_description` |
| `ReceivedInvoiceLine` | `received_invoice_id`, `description`, `quantity`, `unit_price`, `tva_rate`, `amount_ht` |
| `Contact` | Fournisseur (lecture + création rapide) |
| `Payment` | Paiement fournisseur enregistré |
| `AuditLog` | Toutes les actions |

---

## Notes transversales — Section FACTURES REÇUES

### Accusé de réception obligatoire (réforme 2026)
À partir du 1er septembre 2026, la réception d'une facture électronique via PDP/PPF
doit faire l'objet d'un **accusé de réception technique** automatique (distinct de la
validation métier). Ce flux est géré en background :

```
ReceivedInvoice créée (PENDING_VALIDATION)
  → AcknowledgeTechnicalReceiptMessage (Messenger, immédiat)
      → PdpAcknowledgementService::sendTechnicalAck()
      → Mise à jour ReceivedInvoice.technical_ack_sent_at
```

La validation métier (bouton "Valider") est une action humaine séparée.

### Parsing multi-format
```
ReceivedInvoiceParser::parse(string $filePath, string $format)
  → Factur-X : extraction PDF/A-3 → XML CII embarqué → parse CII
  → UBL      : parse XML UBL 2.1 → validation XSD UBL
  → CII      : parse XML CII D16B → validation XSD CII
  → Résultat : ReceivedInvoiceDTO (champs normalisés)
```

### Idempotence du webhook
Le `PdpWebhookHandler` vérifie `ReceivedInvoice.external_pdp_id` avant création
pour éviter les doublons en cas de retry webhook. Réponse `200 OK` immédiate,
traitement 100% async via Messenger.

### Archivage
- Le fichier reçu (PDF ou XML) est archivé sur S3/MinIO tel quel (non modifié)
- Hash SHA-256 calculé sur le fichier original reçu
- Conservation 10 ans (WORM bucket policy)
- Le fichier reçu fait foi — le rendu HTML est uniquement indicatif
