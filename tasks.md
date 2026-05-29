# Tasks — Facturation Électronique SaaS

Suivi de l'avancement du développement. Mis à jour à chaque tâche démarrée ou terminée.

**Légende des statuts :** `nouveau` · `en cours` · `terminé`

---

## Résumé de l'état actuel

| Couche | État |
|---|---|
| Documentation fonctionnelle (37 pages) | ✅ terminé |
| Modélisation Doctrine (34 entités, 26 enums, migration MySQL) | ✅ terminé |
| 34 Repositories avec méthodes métier | ✅ terminé |
| Configuration Symfony 7 (15 packages, 3 firewalls, Messenger 6 files) | ✅ terminé |
| Isolation multi-tenant (TenantFilter, TenantContext, Subscribers) | ✅ terminé |
| Authentification (Login, 2FA TOTP, JWT, clé API SHA-256) | ✅ terminé |
| Infrastructure Docker (MySQL 8, Redis 7, MinIO, Mailpit, Nginx) | ✅ terminé |
| Landing page publique (/) | ✅ terminé |
| Bootstrap applicatif (app démarrable, routes niveau 1+2) | ✅ terminé |
| Services métier | ✅ 9/11 (numérotation, calcul, statuts, duplication, avoir, paiement, notification + S3/Sirene/PPF) |
| Controllers | ❌ 14 routes réelles / 37 documentées |
| Form Types | ❌ 0/~15 |
| Voters Symfony | ❌ 0/4 |
| Validators personnalisés | ❌ 0/5 |
| Messenger Handlers | ❌ 0/23 |
| Templates (pages applicatives) | ❌ 11 stubs vides + 20 manquants |
| Tests PHPUnit | ❌ 0 |
| Fixtures de développement | ❌ 0 |
| Commandes Symfony | ❌ 0/4 |
| Système de paiement / abonnement | ❌ non démarré |

---

## TASK-001 — Services métier fondamentaux
**Statut :** `terminé`
**Priorité :** critique — aucune facture ne peut être créée sans ces services

### Ce qu'il faut faire

#### `InvoiceNumberingService` (`src/Service/Invoice/InvoiceNumberingService.php`)
- Générer le prochain numéro de facture depuis `InvoiceSequence`
- Verrou Redis distribué (`lock.factory` — clé `invoice_numbering_{tenantId}`) pour garantir la séquence sans trou
- Appliquer le format configuré : `{prefix}{séparateur}{année}{séparateur}{numéro padded}`
- Réinitialisation annuelle si `resetYearly = true` et changement d'année
- Marquer la séquence `locked = true` dès la première utilisation (empêche modification)
- Méthodes : `next(InvoiceSequence $seq): string`, `preview(InvoiceSequence $seq): string`

#### `InvoiceBuilderService` (`src/Service/Invoice/InvoiceBuilderService.php`)
- Calculer `amountHt` et `amountTva` pour chaque `InvoiceLine` avec `bcmath` (précision 4 décimales)
- Formule : `amountHt = quantity × unitPrice × (1 - discount/100)` arrondi à 2 décimales
- Formule : `amountTva = amountHt × tvaRate/100` arrondi à 2 décimales
- Agréger les totaux `totalHt`, `totalTva`, `totalTtc` sur la facture
- Copier le snapshot client (nom, SIRET, identifiant PDP) au moment de la validation
- Méthodes : `recalculate(Invoice $invoice): void`, `validateAndFreeze(Invoice $invoice, User $actor): void`

#### `InvoiceDuplicateService` (`src/Service/Invoice/InvoiceDuplicateService.php`)
- Dupliquer une facture (nouvel UUID, statut DRAFT, numéro vide, date aujourd'hui)
- Copier toutes les `InvoiceLine` avec leurs valeurs
- Ne pas copier : numéro, statuts de transmission, paiements, PDF/XML
- Méthodes : `duplicate(Invoice $source, User $actor): Invoice`

#### `CreditNoteService` (`src/Service/Invoice/CreditNoteService.php`)
- Créer un avoir à partir d'une facture (inverser les montants)
- Lier via `creditNoteFor`
- Utiliser la séquence d'avoir du tenant (si configurée)
- Méthodes : `createFrom(Invoice $original, User $actor): Invoice`

---

## TASK-002 — Génération PDF et XML (conformité réglementaire)
**Statut :** `en cours`
**Priorité :** critique — obligation légale de produire les bons formats

### Ce qu'il faut faire

#### `PdfGeneratorService` (`src/Service/Invoice/PdfGeneratorService.php`)
- Générer un PDF/A-3 à partir d'un template Twig (`templates/pdf/invoice.html.twig`)
- Utiliser `dompdf/dompdf` pour la conversion HTML → PDF
- Embed les données Factur-X (XML CII/ZUGFeRD) dans le PDF via `horstoeko/zugferd`
- Uploader le PDF sur S3 (bucket `invoices`) via `S3StorageService`
- Calculer et stocker le hash SHA-256 du fichier (`Invoice::fileHash`)
- Méthodes : `generate(Invoice $invoice): string` (retourne la clé S3)
- Template PDF : `templates/pdf/invoice.html.twig` — mise en page professionnelle avec logo, en-têtes légaux, tableau des lignes, totaux TVA, mentions légales, CGV

#### `XmlGeneratorService` (`src/Service/Invoice/XmlGeneratorService.php`)
- Générer le XML selon le format demandé (`InvoiceFormat`) :
  - **Factur-X / CII** : via `horstoeko/zugferd` (profil EN16931)
  - **UBL 2.1** : via génération XML manuelle ou librairie UBL
- Valider le XML généré contre le schéma XSD correspondant
- Uploader le XML sur S3 (bucket `invoices`, clé `{tenantId}/invoices/{year}/{number}.xml`)
- Méthodes : `generateCii(Invoice $invoice): string`, `generateUbl(Invoice $invoice): string`

---

## TASK-003 — Transmission PDP / PPF
**Statut :** `nouveau`
**Priorité :** critique — cœur de la conformité réforme 2026

### Ce qu'il faut faire

#### `PdpDispatchService` (`src/Service/PDP/PdpDispatchService.php`)
- Choisir le canal de transmission selon la config du tenant : PDP partenaire ou PPF direct
- Déchiffrer la clé API PDP stockée chiffrée AES-256 (`PdpConfigEncryptorService`)
- Envoyer la facture (XML + PDF) via HTTP au PDP ou PPF
- Créer une `PdpTransmission` (statut PENDING → SENT → ACKNOWLEDGED / REJECTED)
- Gérer les réponses d'erreur : stocker `rejectCode` + `rejectReason`
- Méthodes : `dispatch(Invoice $invoice): PdpTransmission`, `checkStatus(PdpTransmission $tx): void`

#### `PdpConfigEncryptorService` (`src/Service/PDP/PdpConfigEncryptorService.php`)
- Chiffrer / déchiffrer la clé API PDP avec AES-256-GCM (sodium)
- Utiliser `ENCRYPTION_KEY` depuis `.env`
- Méthodes : `encrypt(string $plaintext): string`, `decrypt(string $ciphertext): string`

#### `SendInvoiceToPdpHandler` (`src/Messenger/Handler/SendInvoiceToPdpHandler.php`)
- Handler pour `SendInvoiceToPdpMessage`
- Charger la facture, appeler `PdpDispatchService::dispatch()`
- En cas d'échec : mettre à jour le statut de la `PdpTransmission`, relancer si `attempt < maxRetries`

#### `SendTechnicalAckHandler` (`src/Messenger/Handler/SendTechnicalAckHandler.php`)
- Handler pour `SendTechnicalAckMessage`
- Envoyer l'acquittement technique au PDP émetteur de la facture reçue
- Mettre à jour `ReceivedInvoice::technicalAckSentAt`

#### `ProcessPdpWebhookHandler` (`src/Messenger/Handler/ProcessPdpWebhookHandler.php`)
- Handler pour `ProcessPdpWebhookMessage`
- Traiter les événements entrants (changement statut facture, nouvel AR, rejet)
- Mettre à jour `PdpTransmission`, déclencher notifications

---

## TASK-004 — Module Factures émises (CRUD complet)
**Statut :** `nouveau`
**Priorité :** haute — fonctionnalité principale du produit

### Ce qu'il faut faire

#### Controller `InvoiceController` — compléter les routes manquantes
Routes à ajouter dans `src/Controller/Invoice/InvoiceController.php` :
- `GET /invoices` — liste avec filtres (statut, période, client, montant), pagination
- `GET/POST /invoices/new` — formulaire de création
- `GET /invoices/{id}` — détail : PDF viewer, timeline statuts, transmission PDP
- `GET/POST /invoices/{id}/edit` — édition (brouillon seulement)
- `POST /invoices/{id}/validate` — valider (DRAFT → VALIDATED + numérotation + PDF)
- `POST /invoices/{id}/send` — envoyer au PDP/PPF (VALIDATED → SENT)
- `GET /invoices/{id}/download` — télécharger le PDF (URL signée S3)
- `POST /invoices/{id}/duplicate` — dupliquer
- `GET/POST /invoices/{id}/credit-note` — créer un avoir

#### Form Types
- `InvoiceType` (`src/Form/Type/Invoice/InvoiceType.php`) — sélection contact, date, objet, devise
- `InvoiceLineType` (`src/Form/Type/Invoice/InvoiceLineType.php`) — ligne facture (produit, qté, prix, TVA, remise)
- `InvoiceLineCollectionType` — collection de lignes avec ajout/suppression JS (Stimulus)

#### Voter
- `InvoiceVoter` (`src/Security/Voter/InvoiceVoter.php`) :
  - `VIEW` : tous les rôles
  - `EDIT` : ACCOUNTANT+ et facture en DRAFT
  - `VALIDATE` : ACCOUNTANT+
  - `SEND` : ACCOUNTANT+
  - `DELETE` : ADMIN+ et facture en DRAFT

#### Templates
- `templates/invoices/index.html.twig` — tableau Bootstrap, filtres Stimulus, badges statuts DGFiP colorés, pagination Turbo
- `templates/invoices/new.html.twig` — formulaire en 2 colonnes (en-tête + lignes dynamiques Vue 3)
- `templates/invoices/show.html.twig` — visualiseur PDF iframe, timeline statuts, panneau actions
- `templates/invoices/edit.html.twig` — même formulaire que new, pré-rempli
- `templates/invoices/credit_note.html.twig` — formulaire avoir (simplifié, montants pré-remplis)
- `templates/pdf/invoice.html.twig` — template PDF (sera converti par dompdf)

#### Handler Messenger
- `GenerateInvoicePdfHandler` — génère PDF + XML et les upload S3

---

## TASK-005 — Module Factures reçues
**Statut :** `nouveau`
**Priorité :** haute — obligation légale dès septembre 2026

### Ce qu'il faut faire

#### Controller — routes à ajouter dans `ReceivedInvoiceController`
- `GET /received-invoices` — liste avec filtres (statut, fournisseur, période)
- `GET /received-invoices/{id}` — détail : affichage XML parsé, validation, contestation
- `POST /received-invoices/{id}/validate` — marquer APPROVED + enregistrer paiement optionnel
- `POST /received-invoices/{id}/reject` — contester avec motif
- `POST /received-invoices/{id}/ack` — envoyer acquittement technique (si pas encore fait)

#### Service
- `ReceivedInvoiceParserService` (`src/Service/Invoice/ReceivedInvoiceParserService.php`) :
  - Parser les fichiers Factur-X (extraction XML embarqué), UBL, CII reçus via PDP
  - Remplir les champs structurés de `ReceivedInvoice` (numéro, montants, TVA, IBAN fournisseur)
  - Stocker les erreurs de parsing dans `parseErrors` JSON
  - Méthodes : `parse(string $fileContent, string $format): array`

#### Endpoint Webhook PDP entrant
- Route `POST /api/pdp/webhook/{tenantId}` dans un `PdpWebhookController`
- Vérifier la signature HMAC du payload
- Créer `PdpWebhookLog` (idempotence via `eventId`)
- Dispatcher `ProcessPdpWebhookMessage` asynchrone

#### Templates
- `templates/received_invoices/index.html.twig` — tableau, badges statuts, compteur en attente d'acquittement
- `templates/received_invoices/show.html.twig` — données parsées structurées, PDF/XML viewer, formulaire validation/contestation

---

## TASK-006 — Module Contacts
**Statut :** `nouveau`
**Priorité :** haute — requis pour créer une facture

### Ce qu'il faut faire

#### Controller — routes à ajouter dans `ContactController`
- `GET /contacts` — liste unifiée clients + fournisseurs, filtres par type
- `GET/POST /contacts/new` — création avec vérification SIRET live (Sirene API)
- `GET /contacts/{id}` — fiche détail : factures liées, paiements, historique
- `POST/GET /contacts/{id}/edit` — édition
- `DELETE /contacts/{id}` — archivage (soft delete)

#### Form Type
- `ContactType` (`src/Form/Type/Contact/ContactType.php`) — tous les champs Contact + adresse + interlocuteurs

#### Voter
- `ContactVoter` (`src/Security/Voter/ContactVoter.php`) — VIEW/EDIT/DELETE selon rôle

#### Service
- Intégrer `SireneApiClient` dans le formulaire de création : vérification SIRET temps réel via Stimulus controller

#### Templates
- `templates/contacts/index.html.twig` — tableau avec onglets CLIENT/FOURNISSEUR/TOUS, badge Sirene actif/inactif
- `templates/contacts/new.html.twig` — formulaire avec lookup SIRET Stimulus
- `templates/contacts/show.html.twig` — fiche avec onglets Factures / Paiements / Documents

---

## TASK-007 — Module Catalogue (Produits/Services)
**Statut :** `nouveau`
**Priorité :** moyenne — utile mais pas bloquant pour créer des factures manuellement

### Ce qu'il faut faire

#### Controller — routes à ajouter dans `ProductController`
- `GET /products` — liste avec filtres (type, TVA, actif/archivé)
- `GET/POST /products/new` — création
- `GET /products/{id}` — fiche détail avec historique des prix
- `POST/GET /products/{id}/edit` — édition (crée une entrée `ProductPriceHistory` si prix change)
- `DELETE /products/{id}` — archivage

#### Form Type
- `ProductType` (`src/Form/Type/Product/ProductType.php`)

#### Voter
- `ProductVoter` (`src/Security/Voter/ProductVoter.php`)

#### Listener Doctrine
- `ProductPriceHistoryListener` (`src/EventListener/ProductPriceHistoryListener.php`) — déclenché sur `preUpdate`, crée `ProductPriceHistory` si `unitPrice` change

#### Templates
- `templates/products/index.html.twig` — tableau avec colonnes référence, libellé, prix HT, taux TVA, type
- `templates/products/new.html.twig` — formulaire création
- `templates/products/show.html.twig` — fiche avec graphique historique des prix

---

## TASK-008 — Module Paiements
**Statut :** `nouveau`
**Priorité :** haute — requis pour clôturer le cycle de vie d'une facture

### Ce qu'il faut faire

#### Controller — routes à ajouter dans `PaymentController`
- `GET /payments` — liste avec filtres (direction, mode, période)
- `GET/POST /invoices/{id}/payment` — enregistrer un paiement sur une facture
- `DELETE /payments/{id}` — annuler un paiement

#### Form Type
- `PaymentType` (`src/Form/Type/Payment/PaymentType.php`) — date, montant, mode (liste `PaymentMode`), référence

#### Service
- `PaymentService` (`src/Service/Payment/PaymentService.php`) :
  - Enregistrer un paiement, mettre à jour `Invoice::amountPaid`
  - Si `amountPaid >= totalTtc` → transition statut → PAID
  - Si paiement B2C ou international → marquer `ereportingRequired = true`
  - Générer `idempotencyKey` UUID avant persistance
  - Méthodes : `record(Invoice $invoice, array $data, User $actor): Payment`

#### Handler Messenger
- `SendPaymentConfirmationEmailHandler` — email de confirmation au client

#### Templates
- `templates/payments/index.html.twig` — liste chronologique avec totaux entrants/sortants
- `templates/payments/_form.html.twig` — formulaire modal réutilisable (Turbo Frame)

---

## TASK-009 — Dashboard
**Statut :** `nouveau`
**Priorité :** haute — première page vue après connexion

### Ce qu'il faut faire

#### Controller `DashboardController` — compléter
- Appeler `InvoiceRepository::getKpis()` pour CA du mois, factures en attente, TVA due
- Appeler `InvoiceRepository::getMonthlyRevenue()` pour graphique 12 mois
- Appeler `InvoiceRepository::findOverdue()` pour les factures en retard
- Appeler `EReportingBatchRepository::findDueSoon()` pour les alertes e-reporting
- Appeler `ReceivedInvoiceRepository::findPendingTechnicalAck()` pour les acquittements à envoyer

#### Template `templates/dashboard/index.html.twig`
- **KPI bar** : CA du mois TTC, nombre de factures émises, montant en attente de paiement, TVA collectée
- **Graphique CA 12 mois** : Chart.js (courbe), données JSON depuis `getMonthlyRevenue()`
- **Alertes** : factures en retard (rouge), acquittements à envoyer (orange), e-reporting à soumettre (jaune)
- **Dernières factures** : tableau des 5 dernières avec statut et montant
- **Activité récente** : 5 dernières notifications

#### Asset JS
- `assets/controllers/chart_controller.js` — Stimulus controller pour Chart.js

---

## TASK-010 — Module TVA et Exports
**Statut :** `nouveau`
**Priorité :** haute — FEC obligatoire pour l'administration fiscale

### Ce qu'il faut faire

#### `ExportService` (`src/Service/Export/ExportService.php`)
- **FEC** : générer le Fichier des Écritures Comptables au format DGFiP exact (18 colonnes, pipe-séparé, encodage ISO-8859-1, noms de champs exacts de l'art. A47 A-1 CGI)
- **CSV factures** : export tabulaire des factures filtrées
- **XML e-reporting** : format XML DGFiP pour les lots e-reporting
- **ZIP** : archive contenant PDF + XML de chaque facture sur une période
- Créer un `ExportJob` (statut PENDING → PROCESSING → DONE), dispatcher `GenerateExport*Message`
- Méthodes : `requestFec(Tenant $tenant, DateTimeImmutable $from, DateTimeImmutable $to, User $actor): ExportJob`

#### `GenerateExportFecHandler` / `GenerateExportCsvHandler` / `GenerateExportXmlHandler` / `GenerateArchiveZipHandler`
- Handlers Messenger qui exécutent la génération en arrière-plan
- Upload sur S3 (bucket `exports`), mettre à jour `ExportJob` avec la clé S3 et le hash
- Notifier l'utilisateur via `SendNotificationMessage` quand l'export est prêt

#### Controller — routes à compléter dans `TaxController`
- `GET /tax` — tableau de bord TVA : TVA collectée/déductible par taux, par période
- `GET/POST /exports` — historique des exports + bouton "Nouvel export"
- `GET /exports/{id}/download` — URL présignée S3 vers le fichier

#### Templates
- `templates/tax/index.html.twig` — tableau TVA par taux (20%, 10%, 5.5%, 0%), par mois
- `templates/tax/exports.html.twig` — liste des exports avec statut et bouton téléchargement

---

## TASK-011 — E-reporting DGFiP
**Statut :** `nouveau`
**Priorité :** haute — obligatoire pour les transactions B2C et internationales

### Ce qu'il faut faire

#### `EReportingAggregatorService` (`src/Service/EReporting/EReportingAggregatorService.php`)
- Agréger les transactions B2C et internationales d'une période dans un `EReportingBatch`
- Créer les `EReportingTransaction` par type (B2C France, export UE, export hors-UE)
- Créer les `EReportingPaymentLine` pour la TVA sur encaissement
- Calculer les montants HT par taux de TVA (`amountHtByRate` JSON)
- Détecter les lots en retard (`deadline` dépassée, `late = true`)
- Méthodes : `createBatch(Tenant $tenant, string $period): EReportingBatch`, `aggregate(EReportingBatch $batch): void`

#### `EReportingXmlBuilder` (`src/Service/EReporting/EReportingXmlBuilder.php`)
- Générer le XML de déclaration e-reporting au format DGFiP (schéma officiel)
- Inclure toutes les transactions et lignes de paiement du lot
- Valider contre le schéma XSD DGFiP

#### Handlers Messenger
- `CreateEReportingBatchHandler` — déclenché mensuellement via commande Symfony
- `AggregateEReportingTransactionsHandler` — agrège et construit le XML

#### Controller — compléter `EReportingController`
- `GET /e-reporting` — liste des lots avec statuts (en attente, soumis, accepté, rejeté)
- `GET /e-reporting/{id}` — détail lot : transactions, corrections
- `POST /e-reporting/{id}/submit` — soumettre le lot au PPF
- `POST /e-reporting/{id}/correct` — ajouter une correction

#### Templates
- `templates/e_reporting/index.html.twig` — tableau des lots, badges statuts, alertes retard
- `templates/e_reporting/show.html.twig` — détail lot avec tableau transactions

---

## TASK-012 — Paramètres (5 pages manquantes)
**Statut :** `nouveau`
**Priorité :** haute — nécessaire pour configurer PDP, séquences, utilisateurs

### Ce qu'il faut faire

#### Routes à ajouter dans `SettingsController`
- `GET/POST /settings/organisation` — nom, SIRET, TVA, adresse, IBAN, logo, mentions légales
- `GET/POST /settings/users` — liste membres, invitation, changement rôle, révocation
- `GET/POST /settings/templates` — modèles de factures (choisir base, couleurs, logo)
- `GET/POST /settings/sequences` — numérotation (préfixe, format, reset annuel)
- `GET/POST /settings/pdp` — configuration PDP (mode, endpoint, clé API chiffrée, test connexion)
- `GET/POST /settings/integrations` — clés API, webhooks endpoints

#### Form Types
- `OrganisationSettingsType`
- `InviteUserType` (email + rôle)
- `InvoiceSequenceType`
- `PdpSettingsType` (endpoint + clé — la clé est masquée en lecture)
- `ApiKeyCreateType` (nom + environnement + permissions)
- `WebhookEndpointType` (URL + événements + secret)

#### Services
- `InvitationService` (`src/Service/Invitation/InvitationService.php`) — créer `TenantInvitation`, dispatcher `SendInvitationEmailMessage`
- Dispatcher `SendInvitationEmailHandler`

#### Voter
- `TenantSettingsVoter` — OWNER uniquement pour PDP, séquences, plan ; ADMIN pour users, templates, intégrations

#### Templates
- `templates/settings/organisation.html.twig` — formulaire complet avec upload logo
- `templates/settings/users.html.twig` — tableau membres + formulaire invitation
- `templates/settings/templates.html.twig` — galerie de modèles avec prévisualisation
- `templates/settings/sequences.html.twig` — configuration numérotation avec prévisualisation
- `templates/settings/pdp.html.twig` — formulaire PDP + bouton "Tester la connexion" (Turbo)
- `templates/settings/integrations.html.twig` — liste clés API + webhooks

---

## TASK-013 — Notifications
**Statut :** `nouveau`
**Priorité :** moyenne

### Ce qu'il faut faire

#### `NotificationService` (`src/Service/Notification/NotificationService.php`)
- Créer une `Notification` en base selon le type et la sévérité
- Dispatcher `SendNotificationMessage` si l'utilisateur a activé les notifications email
- Méthodes : `notify(Tenant $tenant, string $type, string $title, string $description, ?string $actionUrl, ?User $user): Notification`

#### Handler Messenger
- `SendNotificationHandler` — crée la notification, vérifie les préférences (`NotificationPreference`), envoie email si activé

#### Controller — compléter `NotificationController`
- `GET /notifications` — liste paginée avec filtre lu/non-lu, sévérité
- `POST /notifications/{id}/read` — marquer comme lue (Turbo Stream)
- `POST /notifications/read-all` — tout marquer comme lu
- `GET /api/notifications/count` — endpoint JSON pour le badge temps réel (header)

#### Template
- `templates/notifications/index.html.twig` — liste avec groupement par date, icônes par sévérité, actions Turbo

---

## TASK-014 — Admin SaaS (Super-admin)
**Statut :** `nouveau`
**Priorité :** faible — pas nécessaire pour les utilisateurs finaux

### Ce qu'il faut faire

#### Controller — compléter `AdminController`
- `GET /admin/tenants` — tableau de tous les tenants avec stats (nb factures, plan, statut)
- `GET /admin/tenants/{id}` — fiche tenant : membres, métriques, logs, actions (changer plan, suspendre)
- `POST /admin/tenants/{id}/plan` — changer le plan d'un tenant
- `POST /admin/tenants/{id}/suspend` — suspendre un tenant
- `GET /admin/logs` — `SuperAdminLog` paginés avec filtres

#### Templates
- `templates/admin/tenants.html.twig` — tableau avec filtres plan/statut, pagination
- `templates/admin/tenant_show.html.twig` — fiche tenant complète
- `templates/admin/logs.html.twig` — journal des actions super-admin

---

## TASK-015 — Validators personnalisés
**Statut :** `nouveau`
**Priorité :** moyenne — améliore la qualité des données

### Ce qu'il faut faire

- `ValidSiret` (`src/Validator/Constraint/ValidSiret.php` + `ValidSiretValidator.php`) — algorithme de Luhn sur 14 chiffres
- `ValidTvaIntra` (`src/Validator/Constraint/ValidTvaIntra.php` + `ValidTvaIntraValidator.php`) — appel VIES SOAP (async si possible)
- `ValidIban` (`src/Validator/Constraint/ValidIban.php` + `ValidIbanValidator.php`) — validation ISO 13616
- `ValidBic` (`src/Validator/Constraint/ValidBic.php` + `ValidBicValidator.php`) — format BIC/SWIFT
- `UniqueInvoiceNumber` — vérifier l'unicité par tenant dans `InvoiceRepository`

---

## TASK-016 — Twig Extensions et assets JS
**Statut :** `nouveau`
**Priorité :** moyenne — améliore l'ergonomie des templates

### Ce qu'il faut faire

#### Extensions Twig (`src/Twig/Extension/`)
- `InvoiceStatusExtension` — filtre `invoice_status_label(status)`, fonction `invoice_status_badge(status)` → HTML badge coloré
- `MoneyExtension` — filtre `money(amount, currency)` → "1 234,56 €" (formatage `NumberFormatter`)
- `DateFrExtension` — filtre `date_fr(date)` → "15 janvier 2026"
- `SiretExtension` — filtre `siret_format(siret)` → "123 456 789 01234"

#### Stimulus Controllers (`assets/controllers/`)
- `invoice_lines_controller.js` — ajouter/supprimer des lignes dynamiquement (Vue 3 ou Stimulus)
- `siret_lookup_controller.js` — lookup SIRET via appel Fetch sur `/api/sirene/{siret}`
- `chart_controller.js` — initialiser Chart.js sur un élément canvas
- `confirm_controller.js` — modale de confirmation avant action destructive
- `flash_controller.js` — auto-dismiss des alertes flash après N secondes
- `copy_controller.js` — copier une valeur dans le presse-papier (clés API)

---

## TASK-017 — Handlers Messenger (emails)
**Statut :** `nouveau`
**Priorité :** moyenne — améliore l'expérience mais non bloquant

### Ce qu'il faut faire

Créer les handlers et les templates email (`templates/emails/`) :

- `SendWelcomeEmailHandler` + `templates/emails/welcome.html.twig`
- `SendInvitationEmailHandler` + `templates/emails/invitation.html.twig`
- `SendPasswordResetEmailHandler` (déjà dans `ForgotPasswordController`, refactorer en handler)
- `SendInvoiceEmailHandler` + `templates/emails/invoice_sent.html.twig` (email au client avec PDF joint)
- `SendRelanceEmailHandler` + `templates/emails/relance_1.html.twig`, `relance_2.html.twig`, `relance_3.html.twig`
- `SendPaymentConfirmationEmailHandler` + `templates/emails/payment_confirmation.html.twig`
- `SendDigestEmailHandler` + `templates/emails/digest.html.twig` (résumé quotidien/hebdomadaire)

---

## TASK-018 — Handlers Messenger (tâches planifiées)
**Statut :** `nouveau`
**Priorité :** moyenne

### Ce qu'il faut faire

- `CheckOverdueInvoicesHandler` — trouver les factures en retard, créer des notifications, dispatcher relances
- `RefreshSireneStatusHandler` — appeler `SireneApiClient` pour rafraîchir le statut des contacts
- `PurgeExpiredExportsHandler` — supprimer de S3 les `ExportJob` expirés, nettoyer la BDD
- `DeliverWebhookHandler` — livrer les `WebhookDelivery` en attente (POST HTTP avec HMAC)
- `RetryWebhookDeliveryHandler` — réessayer les livraisons en échec selon `failureCount`

#### Commandes Symfony (`src/Command/`)
- `EReportingGenerateBatchCommand` — `app:ereporting:generate-batch {period}` — déclenche `CreateEReportingBatchMessage`
- `InvoicesCheckOverdueCommand` — `app:invoices:check-overdue` — déclenche `CheckOverdueInvoicesMessage` pour tous les tenants
- `ExportsPurgeExpiredCommand` — `app:exports:purge-expired` — nettoie les exports expirés
- `SireneRefreshCommand` — `app:sirene:refresh` — rafraîchit les statuts Sirene des contacts actifs

---

## TASK-019 — API REST publique
**Statut :** `nouveau`
**Priorité :** faible — pour les intégrations tierces

### Ce qu'il faut faire

Créer les controllers sous `src/Controller/Api/` (firewall JWT, stateless) :

- `GET/POST /api/invoices` — liste et création
- `GET/PUT /api/invoices/{id}` — détail et mise à jour
- `POST /api/invoices/{id}/send` — envoi
- `GET /api/contacts` — liste contacts
- `GET /api/products` — catalogue
- `GET /api/auth/token` — génération token JWT (email + password)

Middleware :
- `TenantFromJwtSubscriber` — résoudre le tenant depuis le claim `tenant_id` du JWT
- Sérialisation JSON avec groupes (`#[Groups]`)
- Gestion erreurs RFC 7807 (Problem Details)

---

## TASK-020 — Tests PHPUnit
**Statut :** `nouveau`
**Priorité :** haute avant mise en production

### Ce qu'il faut faire

#### Tests unitaires (`tests/Unit/`)
- `InvoiceNumberingServiceTest` — séquence sans trou, réinitialisation annuelle
- `InvoiceBuilderServiceTest` — calculs HT/TVA/TTC, arrondis `bcmath`, remises
- `CreditNoteServiceTest` — inversion des montants, liaison `creditNoteFor`
- `SiretValidatorTest` — algorithme de Luhn, cas limites
- `IbanValidatorTest` — validation ISO 13616, IBAN français

#### Tests fonctionnels (`tests/Functional/`)
- `AuthControllerTest` — login, logout, 2FA, register, forgot/reset password
- `OnboardingControllerTest` — parcours complet, redirect si incomplet
- `InvoiceControllerTest` — CRUD, transitions de statut, vérifications Voter
- `PaymentControllerTest` — enregistrement, mise à jour `amountPaid`, transition PAID

#### Tests d'intégration (`tests/Integration/`)
- `TenantFilterTest` — isolation multi-tenant (requêtes cross-tenant impossibles)
- `InvoiceLifecycleTest` — cycle complet DRAFT → VALIDATED → SENT → PAID

---

## TASK-021 — Fixtures de développement
**Statut :** `nouveau`
**Priorité :** haute — nécessaire pour tester localement

### Ce qu'il faut faire

Créer `src/DataFixtures/AppFixtures.php` avec `zenstruck/foundry` :

- 1 Tenant demo (`Acme SAS`, SIRET valide, plan PRO, onboarding complété)
- 1 User OWNER (`admin@demo.test` / `password`)
- 1 User ACCOUNTANT (`comptable@demo.test` / `password`)
- 10 Contacts (5 clients, 5 fournisseurs) avec SIRET fictifs valides
- 20 Produits/Services à différents taux de TVA
- 30 Factures à différents statuts (DRAFT/VALIDATED/SENT/PAID/REJECTED)
- 5 Factures reçues à différents statuts
- 10 Paiements
- 2 InvoiceSequence (principale + avoirs)
- 1 InvoiceTemplate (modèle classique)
- Quelques Notifications non lues

---

## TASK-022 — Système de paiement et abonnements
**Statut :** `nouveau`
**Priorité :** haute pour le modèle SaaS

### Ce qu'il faut faire

> Le système de paiement est la condition pour facturer les clients de la plateforme (plans PRO et ENTERPRISE).

#### Choix technique à confirmer
- **Stripe** (recommandé) : Checkout, Customer Portal, Webhooks, gestion des taxes

#### Ce qu'il faudra implémenter
- `StripeService` (`src/Service/Billing/StripeService.php`) — création checkout session, gestion abonnements
- `StripeWebhookController` (`src/Controller/Api/StripeWebhookController.php`) — traiter les événements Stripe (payment_intent.succeeded, invoice.paid, customer.subscription.deleted…)
- `PlanLimitChecker` (`src/Service/Billing/PlanLimitChecker.php`) — vérifier les limites du plan avant chaque action (nb factures, utilisateurs, stockage)
- Routes `/billing` : page abonnement, upgrade/downgrade, historique factures SaaS
- Intégrer les prix réels dans `Plan` enum et la landing page

#### Entité à ajouter
- `Subscription` (ou enrichir `Tenant`) — `stripeCustomerId`, `stripeSubscriptionId`, `currentPeriodEnd`, `cancelAtPeriodEnd`

---

## Journal des modifications

| Date | Tâche | Action |
|---|---|---|
| 2026-05-29 | Toutes (TASK-001 à TASK-022) | Création initiale du fichier tasks.md |
| 2026-05-30 | TASK-001 | Terminé — InvoiceNumberingService, InvoiceCalculatorService, InvoiceStatusService, InvoiceDuplicateService, PaymentService, NotificationService |

