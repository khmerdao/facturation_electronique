# Section FACTURES ÉMISES — Pages 3 à 6

## `/invoices/{id}` — Détail / visualisation
## `/invoices/{id}/edit` — Édition brouillon
## `/invoices/{id}/duplicate` — Duplication
## `/invoices/{id}/credit-note` — Émission d'un avoir

---

## 3. `/invoices/{id}` — Détail & visualisation

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Actions de modification masquées pour `VIEWER`.

### Objectif
Visualiser tous les détails d'une facture, suivre son cycle de vie DGFiP et agir selon son statut courant.

### Features / fonctionnalités

#### En-tête de page
- Numéro de facture (titre H1) + badge statut (mis à jour en temps réel via Turbo Stream)
- Breadcrumb : Factures → N° facture
- Boutons d'action contextuels selon statut (voir matrice ci-dessous)
- Date d'émission | Date d'échéance | Devise

**Matrice d'actions par statut**

| Statut | Actions disponibles (ACCOUNTANT+) |
|---|---|
| `DRAFT` | Éditer \| Valider & émettre \| Dupliquer \| Supprimer |
| `VALIDATED` | Transmettre manuellement \| Dupliquer \| Annuler |
| `SENT` | Suivre transmission \| Dupliquer |
| `ACKNOWLEDGED` | Enregistrer paiement \| Émettre avoir \| Dupliquer \| Télécharger |
| `REJECTED` | Corriger (→ edit) \| Dupliquer \| Voir motif rejet |
| `PAID` | Émettre avoir \| Dupliquer \| Télécharger |
| `CANCELLED` | Dupliquer \| Télécharger |

#### Bloc — Visualisation du document

**Onglet "Document"** (défaut)
- Rendu PDF intégré (iframe, PDF stocké sur S3)
- Bouton télécharger PDF
- Bouton télécharger XML (Factur-X / UBL / CII selon format)
- Badge format : `Factur-X` / `UBL 2.1` / `CII D16B`
- Hash SHA-256 affiché (preuve d'intégrité, piste d'audit fiable)

**Onglet "Données structurées"**
- Affichage de l'XML embarqué (Factur-X) ou du fichier UBL/CII
- Syntax highlighting (lecture seule)
- Utile pour le débogage ou la vérification par un comptable

**Onglet "Historique & cycle de vie"**
- Timeline verticale de tous les changements de statut :
  - Icône + statut + date + heure + acteur (utilisateur ou "Système")
  - `DRAFT créé par Marie D. — 12/03/2026 à 14:32`
  - `VALIDATED par Jean M. — 12/03/2026 à 15:10`
  - `SENT → PDP Chorus Pro — 12/03/2026 à 15:10:34`
  - `ACKNOWLEDGED par PDP — 12/03/2026 à 15:12:01`
- Détail des transmissions PDP (ID externe, latence, réponse brute si erreur)
- Entrées `AuditLog` liées à cette facture
- Mise à jour temps réel via Turbo Stream (Mercure)

**Onglet "Paiements"**
- Liste des paiements enregistrés sur cette facture
- Colonnes : Date | Montant | Mode | Référence | Enregistré par
- Solde restant dû (total TTC − paiements)
- CTA "Enregistrer un paiement" → `/invoices/{id}/payment` (ACCOUNTANT+)

#### Bloc — Récapitulatif financier (sidebar ou section)
- Client : nom, SIRET, adresse (lien vers `/contacts/{id}`)
- Montant HT | TVA | TTC
- Net à payer (après avoirs/acomptes)
- Solde restant dû (coloré rouge si > 0 et échéance dépassée)
- Tableau TVA par taux

#### Bloc — Transmission PDP
- Nom de la PDP utilisée
- ID de transmission externe (ex : ID Chorus Pro)
- Statut de la transmission avec horodatage
- Si `REJECTED` : motif de rejet DGFiP affiché en encadré rouge
  - Code erreur + message DGFiP
  - Lien "Corriger et retransmettre"
- Bouton "Retransmettre manuellement" (si `REJECTED` ou `ERROR`)

#### Bloc — Avoir associé (si applicable)
- Si un avoir a été émis sur cette facture : lien vers la facture avoir
- Si c'est un avoir : lien vers la facture d'origine

#### Edge cases UX
- Facture `DRAFT` : bandeau "Cette facture est un brouillon — elle n'a pas encore été transmise"
- Statut `SENT` depuis > 24h sans `ACKNOWLEDGED` : avertissement "La PDP n'a pas encore accusé réception"
- PDF non disponible (S3 indisponible) : message d'erreur + bouton "Régénérer le PDF"
- XML corrompu ou non généré : alerte + CTA support
- Facture liée à un avoir : bandeau informatif
- Mercure indisponible : polling 30s pour mise à jour statut

### Composants UI
- Onglets Bootstrap (`nav-tabs`)
- Turbo Frame `<turbo-frame id="invoice-status">` sur le badge statut
- Iframe PDF (avec fallback lien téléchargement si iframe bloquée)
- Timeline verticale (CSS + Twig)
- Syntax highlighter (Prism.js ou équivalent léger, lazy-loaded)
- Modale confirmation pour actions destructives (Annuler, Supprimer)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `InvoiceController::show()` | Chargement facture + données associées |
| `ArchiveService::getSignedUrl()` | URL S3 temporaire pour le PDF |
| `PdpTransmissionRepository` | Historique transmissions |
| `InvoiceStatusHistoryRepository` | Timeline cycle de vie |
| `PaymentRepository` | Paiements liés |
| `PdpDispatcher::retransmit()` | Retransmission manuelle |
| `AuditLogRepository` | Entrées audit liées |
| `MercurePublisher` | Topic `/tenants/{id}/invoices/{invoice_id}` |

### Entités Doctrine
`Invoice`, `InvoiceLine`, `InvoiceStatusHistory`, `Contact`,
`PdpTransmission`, `Payment`, `AuditLog`

### Dépendances
- Lié depuis `/invoices` (liste), `/dashboard`, notifications
- Actions vers `/invoices/{id}/edit`, `/invoices/{id}/payment`,
  `/invoices/{id}/credit-note`, `/contacts/{id}`

---

## 4. `/invoices/{id}/edit` — Édition d'un brouillon

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`  
**Accessible uniquement si statut = `DRAFT`.**  
Toute tentative sur un statut ≥ `VALIDATED` → redirect `/invoices/{id}` avec flash warning "Cette facture ne peut plus être modifiée."

### Objectif
Modifier tous les champs d'une facture encore en brouillon avant validation et transmission.

### Features / fonctionnalités
- **Formulaire identique à `/invoices/new`** (même composant Vue 3 `<InvoiceEditor>`)
- Données pré-remplies depuis la facture existante (`existingInvoice` prop)
- Indicateur "Modification d'un brouillon — numéro non encore alloué"
- Autosave toutes les 60s (même comportement que `/invoices/new`)
- Bouton "Enregistrer les modifications" (PUT `/api/invoices/{id}`)
- Bouton "Valider & émettre" (même flux que `/invoices/new`)
- Bouton "Annuler les modifications" → retour `/invoices/{id}` sans sauvegarder

#### Contrôle d'accès renforcé
- Vérification statut au chargement de la page ET à la soumission
- Si la facture a changé de statut entre le chargement et la soumission (race condition) : erreur "Cette facture a été modifiée par un autre utilisateur ou a changé de statut. Rechargez la page."
- Champ `version` (optimistic lock Doctrine) pour détecter les conflits

#### Edge cases UX
- Facture passée `VALIDATED` pendant l'édition (ex: autre onglet) : alerte JavaScript "Cette facture vient d'être validée — vos modifications ne peuvent plus être enregistrées"
- Autosave échoué (réseau) : badge rouge "Sauvegarde échouée — vérifiez votre connexion"
- Tentative d'accès à l'édition d'une facture non-DRAFT : redirect avec flash warning

### Composants UI
Identiques à `/invoices/new` — même composant Vue 3 `<InvoiceEditor>` avec prop `mode="edit"`.

### Appels API / services Symfony

| Service | Action |
|---|---|
| `InvoiceController::edit()` | Chargement facture pour édition |
| `InvoiceController::update()` | PUT mise à jour brouillon |
| `InvoiceController::validate()` | Même flux que `/invoices/new` |
| `AuditLogger` | `invoice.updated` |

**Endpoint**
```
PUT /api/invoices/{id}    → mise à jour brouillon (body : même structure que POST)
```

### Entités Doctrine
`Invoice` (avec champ `version` pour optimistic lock), `InvoiceLine`

### Dépendances
- Accessible uniquement depuis `/invoices/{id}` (statut `DRAFT`)
- Post-sauvegarde → `/invoices/{id}`
- Post-validation → `/invoices/{id}` (statut `VALIDATED` puis `SENT`)

---

## 5. `/invoices/{id}/duplicate` — Duplication

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`

### Objectif
Créer un nouveau brouillon pré-rempli à partir d'une facture existante (tout statut), pour accélérer la saisie de factures récurrentes.

### Features / fonctionnalités

**Ce n'est pas une page à proprement parler — c'est une action POST avec redirect.**

#### Flux de duplication
1. `POST /invoices/{id}/duplicate`
2. Serveur crée un nouveau `Invoice` en statut `DRAFT` avec :
   - Copie de toutes les lignes (`InvoiceLine`)
   - Même client, même devise, même format, même template
   - **Date d'émission** : aujourd'hui
   - **Date d'échéance** : recalculée depuis les conditions de paiement du client
   - **Numéro** : non alloué (sera alloué à la validation du nouveau brouillon)
   - **Statut** : `DRAFT`
   - **Champs réinitialisés** : `s3_key`, `pdp_transmission_id`, `credit_note_for`
   - **Notes internes copiées**, notes client copiées
3. Redirect vers `/invoices/{new_id}/edit` avec flash info "Facture dupliquée — vérifiez les informations avant validation"

#### Modale de confirmation (avant l'action POST)
Déclenchée par le bouton "Dupliquer" sur `/invoices/{id}` ou la liste :
- "Vous allez créer un nouveau brouillon basé sur la facture {numéro}."
- Option : "Mettre à jour les prix depuis le catalogue" (oui/non — recalcule les prix unitaires depuis `Product.unit_price` courant)
- Option : "Copier les pièces jointes" (oui/non)
- Bouton "Dupliquer" | "Annuler"

#### Edge cases UX
- Client archivé (inactif) : warning "Le client de cette facture est archivé. Souhaitez-vous continuer ou choisir un autre client ?"
- Produits avec prix modifiés depuis la facture d'origine : info "X produit(s) ont des prix différents dans votre catalogue. Les anciens prix ont été conservés." (si option "mettre à jour" non cochée)
- Séquence verrouillée ou indisponible : gérée à la validation, pas à la duplication

### Composants UI
- Bouton "Dupliquer" sur `/invoices/{id}` et sur chaque ligne de `/invoices`
- Modale Bootstrap de confirmation avec options
- Spinner pendant la création
- Toast "Brouillon créé" + lien vers le nouveau brouillon

### Appels API / services Symfony

| Service | Action |
|---|---|
| `InvoiceController::duplicate()` | POST création copie brouillon |
| `InvoiceDuplicationService` | Copie profonde Invoice + InvoiceLine |
| `AuditLogger` | `invoice.duplicated` (source_id, new_id) |

**Endpoint**
```
POST /invoices/{id}/duplicate
Body : { update_prices: bool, copy_attachments: bool }
→ 302 redirect /invoices/{new_id}/edit
```

### Entités Doctrine
`Invoice`, `InvoiceLine`

---

## 6. `/invoices/{id}/credit-note` — Émission d'un avoir

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`  
**Accessible uniquement si statut = `ACKNOWLEDGED` ou `PAID`.**

### Objectif
Émettre un avoir (note de crédit) total ou partiel sur une facture existante, conformément aux obligations légales et au cycle de vie DGFiP.

### Features / fonctionnalités

> Un avoir est lui-même une facture de type `CREDIT_NOTE`, avec un numéro de séquence propre (ex : `AV-2026-0001`), transmise à la PDP/PPF comme toute facture.

#### Formulaire d'avoir

**Bloc — Référence à la facture d'origine**
- Numéro de la facture originale (lecture seule, lien)
- Date d'émission originale (lecture seule)
- Montant TTC original (lecture seule)
- Motif de l'avoir (select obligatoire) :
  - Annulation totale de la facture
  - Erreur de tarification
  - Retour marchandise
  - Remise commerciale accordée a posteriori
  - Erreur de TVA
  - Autre (champ texte libre obligatoire)

**Bloc — Type d'avoir**
- **Avoir total** : avoir pour le montant intégral de la facture (toutes les lignes copiées en négatif, non modifiables)
- **Avoir partiel** : sélection des lignes à avoir et/ou modification des quantités
  - Tableau des lignes de la facture d'origine
  - Colonne "Quantité à avoir" (0 par défaut, éditable jusqu'à la quantité originale)
  - Colonne "Montant avoir HT" (calculé)
  - Impossible d'avoir plus que le montant original

**Calculs automatiques** (Vue 3)
- Total HT avoir | Total TVA avoir | Total TTC avoir
- Récapitulatif TVA par taux (négatifs)
- Solde restant sur la facture originale après cet avoir

**Bloc — Informations complémentaires**
- Date de l'avoir (défaut : aujourd'hui)
- Numéro de l'avoir : généré depuis la séquence avoir (`AV-XXXX`), alloué à la validation
- Référence interne (optionnel)
- Notes client (optionnel)

**Bloc — Format & transmission**
- Même configuration que pour une facture standard
- L'avoir est transmis à la PDP/PPF avec référence obligatoire à la facture d'origine (champ `credit_note_for` dans le XML)
- Le XML Factur-X / UBL contient le `BillingReference` pointant vers la facture originale

**Actions**
- "Prévisualiser l'avoir" (PDF avec watermark)
- "Valider & émettre l'avoir" → même flux que `/invoices/new` (validation → numérotation → génération → transmission)
- "Enregistrer en brouillon"
- "Annuler" → retour `/invoices/{id}`

**Post-émission**
- L'avoir est créé comme une nouvelle `Invoice` (type `CREDIT_NOTE`, `credit_note_for = {id}`)
- La facture originale reste dans son statut courant (`ACKNOWLEDGED` ou `PAID`)
- Un lien croisé est affiché sur les deux factures
- Si avoir total : statut de la facture originale mis à jour → `CANCELLED` (après accusé de réception de l'avoir)

#### Edge cases UX
- Tentative d'avoir sur facture `DRAFT`/`VALIDATED`/`SENT` : action indisponible (bouton grisé + tooltip "Impossible d'émettre un avoir sur une facture non acceptée")
- Avoir partiel avec toutes les quantités à 0 : erreur "Sélectionnez au moins une ligne à avoir"
- Avoir déjà émis totalement sur cette facture : warning "Un avoir total a déjà été émis sur cette facture"
- PDP non disponible : brouillon créé, transmission différée
- Montant avoir > montant facture (après avoirs précédents) : erreur bloquante

### Composants UI
- Formulaire Vue 3 `<CreditNoteEditor>` (sous-ensemble de `<InvoiceEditor>`)
- Tableau lignes avec colonnes "quantité à avoir" éditables
- Récapitulatif financier temps réel (computed Vue 3)
- Preview PDF (même composant que `/invoices/new`)
- Modale confirmation avant émission

### Appels API / services Symfony

| Service | Action |
|---|---|
| `InvoiceController::creditNote()` | Chargement formulaire avec données facture source |
| `CreditNoteService::create()` | Création `Invoice` type `CREDIT_NOTE` |
| `InvoiceController::validate()` | Même flux validation/transmission |
| `InvoiceNumberingService::allocateCreditNote()` | Séquence avoir |
| `FormatConverter::generateCreditNote()` | XML avec BillingReference |
| `AuditLogger` | `credit_note.created`, `credit_note.sent` |

**Endpoints**
```
GET  /invoices/{id}/credit-note           → formulaire pré-rempli
POST /api/invoices/{id}/credit-note       → création brouillon avoir
POST /api/invoices/{id}/credit-note/validate → validation + transmission
```

### Entités Doctrine
`Invoice` (type `CREDIT_NOTE`, `credit_note_for`), `InvoiceLine`,
`InvoiceSequence` (séquence avoir), `PdpTransmission`

### Dépendances
- Accessible depuis `/invoices/{id}` (statut `ACKNOWLEDGED` ou `PAID`)
- Crée une nouvelle `Invoice` liée via `credit_note_for`
- Post-émission → `/invoices/{new_credit_note_id}`

---

## Notes transversales — Section FACTURES ÉMISES

### Immutabilité et piste d'audit fiable
- Toute facture `VALIDATED` ou au-delà est **immuable** — le PDF et le XML sont archivés sur S3 avec politique WORM
- Le hash SHA-256 est recalculé et comparé à chaque téléchargement (intégrité garantie)
- `InvoiceStatusHistory` : chaque transition est enregistrée avec timestamp, acteur, et payload JSON avant/après
- Aucun `DELETE` SQL sur `Invoice` (soft delete uniquement pour les brouillons, via champ `deleted_at`)

### Séquence de numérotation
- Allocation par lock pessimiste (`SELECT FOR UPDATE`) sur `InvoiceSequence`
- En cas d'échec de transmission PDP après allocation : le numéro est conservé, la facture reste `VALIDATED` — il n'y a **jamais** de "trou" dans la numérotation (obligation légale)
- La séquence avoir est indépendante de la séquence facture

### Formats électroniques
- **Factur-X** : PDF/A-3 généré avec `setasign/fpdi` ou `teknoo/east-paas`, XML CII D16B embarqué en pièce jointe PDF
- **UBL 2.1** : XML généré via template Twig (`invoice.ubl.xml.twig`) + validation XSD
- **CII D16B** : XML généré via template Twig (`invoice.cii.xml.twig`) + validation XSD
- Validation XSD systématique avant archivage S3

### Messenger & workers
```
TransmitInvoiceMessage
  → TransmitInvoiceHandler
      → PdpDispatcher::transmit()
      → Mise à jour statut Invoice
      → MercurePublisher::publishStatusUpdate()
      → NotificationService (si rejected)

RetryFailedTransmissionMessage (scheduled, toutes les heures)
  → Retry des transmissions en statut ERROR depuis < 24h
```
