# Section CLIENTS & FOURNISSEURS — Gestion des contacts

Répertoire unifié des clients et fournisseurs du tenant.  
Un contact peut être de type `CLIENT`, `SUPPLIER` ou `BOTH`.  
Le SIRET est le pivot d'identification pour la réforme électronique (matching PDP, vérification Sirene).

---

## Sommaire

| Page | URL | Rôles |
|---|---|---|
| Liste unifiée | `/contacts` | Tous |
| Création | `/contacts/new` | ACCOUNTANT+ |
| Fiche détail | `/contacts/{id}` | Tous (édition ACCOUNTANT+) |

---

## 1. `/contacts` — Liste unifiée clients & fournisseurs

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Boutons de création/suppression masqués pour `VIEWER`.

### Objectif
Centraliser la gestion de tous les tiers (clients, fournisseurs, les deux) avec
accès rapide aux informations fiscales et à l'historique transactionnel.

### Features / fonctionnalités

#### Barre d'outils supérieure
- CTA "Nouveau contact" → `/contacts/new` (ACCOUNTANT+)
- Bouton "Importer" → modale import CSV/Excel
  - Template CSV téléchargeable
  - Mapping colonnes (nom, SIRET, email, adresse…)
  - Prévisualisation avant import + rapport d'erreurs
  - Import async (> 100 contacts → Messenger + notification)
- Bouton "Exporter" → CSV ou Excel (filtres actifs appliqués)
- Compteur : "X clients · Y fournisseurs · Z les deux"

#### Filtres & recherche
- **Recherche full-text** : nom, SIRET, email, ville (debounced 300ms)
- **Filtre type** : Client / Fournisseur / Les deux (tabs ou checkboxes)
- **Filtre statut** : Actif / Archivé
- **Filtre pays** : select ISO 3166
- **Filtre TVA** : Assujetti / Non assujetti / Exonéré
- Persistance en query string

#### Tableau principal

| Colonne | Détail | Triable |
|---|---|---|
| Nom | Raison sociale ou nom complet | ✓ |
| Type | Badge CLIENT / FOURNISSEUR / LES DEUX | ✓ |
| SIRET | Formaté XX XXX XXX XXXXX | ✓ |
| Email | Adresse email principale | ✗ |
| Ville | Ville du siège | ✓ |
| Délai de paiement | En jours (défaut du contact) | ✓ |
| Encours client | Montant TTC facturé non payé | ✓ |
| Statut | Actif / Archivé | ✓ |
| Actions | Voir \| Éditer \| Archiver | ✗ |

**Actions contextuelles par ligne**
- Voir → `/contacts/{id}`
- Éditer → `/contacts/{id}` (mode édition, ACCOUNTANT+)
- Archiver (contact sans facture en cours, ACCOUNTANT+)
- Créer une facture pour ce client → `/invoices/new?contact_id={id}` (ACCOUNTANT+)

**Actions en masse** (ACCOUNTANT+)
- Archiver les contacts sélectionnés
- Exporter la sélection

#### Vue alternative — Cards
- Toggle liste / cards (préférence stockée localement)
- Cards avec avatar initiales, nom, type, email, encours

#### Edge cases UX
- Aucun contact : illustration + CTA "Ajouter votre premier client"
- Contact avec SIRET dupliqué dans le tenant : badge warning "SIRET déjà utilisé"
- Contact archivé : ligne grisée, badge "Archivé", actions limitées (désarchiver, voir)
- Tentative d'archivage d'un contact avec factures ouvertes : erreur
  "Ce contact a X facture(s) en cours — clôturez-les avant d'archiver"
- Import CSV avec erreurs : rapport ligne par ligne, lignes valides importées,
  lignes en erreur téléchargeables en CSV corrigé

### Composants UI
- Tabs "Clients / Fournisseurs / Tous" (Turbo Frame, filtre type)
- Tableau avec Turbo Frame `<turbo-frame id="contacts-list">`
- Composant Stimulus `FilterController` (réutilisé)
- Toggle liste/cards (Stimulus `ViewToggleController`)
- Modale import CSV avec prévisualisation (Vue 3 `<CsvImporter>`)
- Avatar initiales (Twig helper, couleur déterministe depuis le nom)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ContactController::index()` | Listing paginé avec filtres |
| `ContactRepository::findByTenantWithFilters()` | Requête filtrée |
| `ContactImportService` | Import CSV/Excel (sync ou async Messenger) |
| `ContactExportService` | Export CSV/Excel |
| `EncoursMontantService` | Calcul encours client par contact |

### Entités Doctrine
`Contact`, `Invoice` (encours), `ReceivedInvoice`

---

## 2. `/contacts/new` — Création d'un contact

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`

### Objectif
Créer une fiche contact complète (client, fournisseur ou les deux) avec
vérification automatique des données légales via Sirene.

### Features / fonctionnalités

#### Bloc — Identification légale
- **Type de contact** : Client / Fournisseur / Les deux (boutons radio, requis)
- **Nom / Raison sociale** (requis)
- **SIRET** (14 chiffres)
  - Bouton "Vérifier le SIRET" → appel API Sirene
  - Auto-remplissage : raison sociale, adresse, code NAF, forme juridique
  - Badge "SIRET vérifié ✓" ou "SIRET introuvable" ou "Entreprise radiée ⚠"
  - Champ optionnel (contacts étrangers sans SIRET)
  - Checkbox "Contact étranger (sans SIRET)" → masque le champ SIRET
- **Numéro de TVA intracommunautaire**
  - Validation checksum (algorithme Luhn adapté par pays)
  - Vérification VIES (service EU) en async si numéro UE
  - Badge statut : "Valide ✓" / "Invalide ✗" / "Non vérifié"
- **Forme juridique** (optionnel) : SAS, SARL, EI, SA, EURL, Auto-entrepreneur, Autre
- **Code NAF / APE** (optionnel, auto-rempli depuis Sirene)

#### Bloc — Adresse
- Adresse ligne 1 (requis)
- Adresse ligne 2 (optionnel)
- Code postal + Ville (autocomplétion api.gouv.fr pour la France)
- Pays (select ISO 3166, France par défaut)
- **Adresse de livraison différente** : toggle → affiche un second bloc adresse

#### Bloc — Coordonnées
- Email principal (requis si client B2B pour envoi factures)
- Email facturation (optionnel, peut différer de l'email principal)
- Téléphone
- Site web
- **Interlocuteur(s)** : liste dynamique de contacts nommés
  - Prénom, Nom, Rôle (Comptabilité, Direction, Achats…), Email, Téléphone
  - Bouton "Ajouter un interlocuteur"

#### Bloc — Paramètres de facturation (si type CLIENT ou BOTH)
- **Identifiant PDP** : identifiant du client auprès de sa PDP/PPF
  - Requis pour la transmission électronique B2B soumis à réforme
  - Tooltip : "Demandez cet identifiant à votre client ou à sa PDP"
  - Format variable selon la PDP (SIRET, UUID, code spécifique)
- **Délai de paiement** (jours) : défaut global ou valeur spécifique
  - Avertissement si > 60j (dépassement légal)
- **Mode de paiement préféré** : virement, chèque, prélèvement, carte
- **Remise commerciale habituelle** (%, optionnel — pré-remplie sur les nouvelles factures)
- **Devise préférée** (EUR par défaut)
- **Langue des documents** (FR / EN — pour les factures envoyées à ce client)
- **Notes internes** (mémo visible uniquement par l'équipe, non imprimé)

#### Bloc — Paramètres fournisseur (si type SUPPLIER ou BOTH)
- **IBAN fournisseur** (optionnel, pour prépaiement automatique)
  - Validation checksum IBAN
- **Conditions de paiement reçues** (jours négociés avec ce fournisseur)
- **Notes internes fournisseur**

#### Actions
- "Enregistrer" → POST création + redirect `/contacts/{id}`
- "Enregistrer et créer une facture" → POST création + redirect `/invoices/new?contact_id={id}` (si type CLIENT)
- "Annuler" → retour `/contacts`

#### Edge cases UX
- SIRET déjà utilisé par un autre contact du tenant : erreur
  "Un contact avec ce SIRET existe déjà — [Voir la fiche →]"
- API Sirene indisponible : avertissement non bloquant "Vérification Sirene impossible —
  vous pouvez continuer sans vérification"
- VIES indisponible : même comportement non bloquant
- Champs requis manquants : scroll vers le premier champ invalide + highlight
- Contact étranger sans SIRET : SIRET optionnel, TVA intracommunautaire requise
  si UE + assujetti

### Composants UI
- Formulaire Symfony `ContactType` rendu Twig
- Composant Stimulus `SiretLookupController` (réutilisé depuis onboarding)
- Composant Stimulus `ViesValidatorController` (vérification TVA UE)
- Composant Stimulus `AddressAutocompleteController` (api.gouv.fr)
- Liste dynamique interlocuteurs (Stimulus `DynamicListController`)
- Toggle "adresse de livraison différente" (Stimulus)
- Champs conditionnels (Stimulus `ConditionalFieldsController`)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ContactController::new()` | Affichage formulaire |
| `ContactController::create()` | POST création |
| `SireneApiClient` | Vérification SIRET |
| `ViesApiClient` | Vérification TVA intracommunautaire UE |
| `AddressAutocompleteClient` | Autocomplétion adresse France |
| `IbanValidatorService` | Validation IBAN fournisseur |
| `AuditLogger` | `contact.created` |

**Endpoints internes (Stimulus AJAX)**
```
GET /api/sirene/lookup?siret={siret}
    → { name, address, naf, legal_form, active: bool }

GET /api/vies/validate?vat_number={number}
    → { valid: bool, name, address }
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `Contact` | Tous les champs (création complète) |
| `ContactPerson` | Interlocuteurs liés (collection) |

---

## 3. `/contacts/{id}` — Fiche détail d'un contact

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Édition masquée pour `VIEWER`.

### Objectif
Centraliser toutes les informations légales, fiscales et transactionnelles d'un contact,
avec accès à l'historique complet des factures émises et reçues.

### Features / fonctionnalités

#### En-tête de page
- Avatar initiales + Nom (titre H1)
- Badges type (CLIENT / FOURNISSEUR / LES DEUX) + statut (ACTIF / ARCHIVÉ)
- SIRET + badge vérifié Sirene (avec date de dernière vérification)
- Boutons d'action :
  - "Éditer" (ACCOUNTANT+) → mode édition inline ou page dédiée
  - "Créer une facture" → `/invoices/new?contact_id={id}` (si CLIENT, ACCOUNTANT+)
  - "Archiver" (ACCOUNTANT+, si aucune facture ouverte)
  - "Désarchiver" (si archivé)
  - Menu "…" : Dupliquer la fiche, Exporter (vCard), Supprimer (si aucune facture)

#### Onglet "Informations"

**Sous-bloc Identité légale**
- Raison sociale, SIRET, TVA intracommunautaire, forme juridique, NAF
- Bouton "Re-vérifier via Sirene" (met à jour si changement d'adresse, radiation…)
- Badge "Entreprise active ✓" ou "Entreprise radiée ⚠" (depuis Sirene)
- Date de création de l'entreprise (depuis Sirene, informatif)

**Sous-bloc Adresse**
- Adresse siège + adresse de livraison si différente
- Lien "Voir sur une carte" (Google Maps / OpenStreetMap)

**Sous-bloc Coordonnées**
- Email(s), téléphone, site web
- Liste des interlocuteurs avec rôles

**Sous-bloc Paramètres de facturation**
- Identifiant PDP, délai de paiement, mode de règlement, devise, langue
- Remise habituelle, notes internes

#### Onglet "Factures émises" (si CLIENT ou BOTH)

Mini-liste des factures émises pour ce client, avec filtres intégrés :
- Filtre période + filtre statut
- Colonnes : N° | Date | Montant TTC | Statut | Actions
- Lien "Nouvelle facture pour ce client" (ACCOUNTANT+)
- **Récapitulatif financier en haut** :
  - Total facturé (toutes périodes)
  - Total encaissé
  - Encours actuel (factures `ACKNOWLEDGED` non payées)
  - Retard de paiement (factures échues non payées)

#### Onglet "Factures reçues" (si SUPPLIER ou BOTH)

Mini-liste des factures reçues de ce fournisseur :
- Colonnes : N° | Date réception | Montant TTC | Statut | Actions
- **Récapitulatif** :
  - Total reçu (toutes périodes)
  - Total payé
  - Reste à payer

#### Onglet "Paiements"
- Historique de tous les paiements liés à ce contact (émis + reçus)
- Colonnes : Date | Facture | Montant | Mode | Référence | Sens (débit/crédit)
- Solde net avec ce contact

#### Onglet "Notes & documents"
- Zone de notes internes libres (Markdown simple, éditable inline, ACCOUNTANT+)
- Liste des pièces jointes libres (CGV reçues, contrat, bon de commande…)
- Upload drag & drop (PDF, images, Word) → stockage S3
- Chaque document : nom, date upload, taille, bouton télécharger/supprimer

#### Onglet "Historique"
- Timeline des actions sur ce contact :
  - Création, modifications de champs (avant/après)
  - Factures créées/reçues liées
  - Vérifications Sirene
- Entrées `AuditLog` filtrées sur ce contact

#### Mode édition inline
- Clic "Éditer" : les blocs passent en mode édition (champs deviennent des inputs)
- Bouton "Enregistrer les modifications" + "Annuler"
- Même validations que `/contacts/new`
- Champs verrouillés si des factures émises existent (SIRET non modifiable)
- `AuditLogger` : `contact.updated` avec diff avant/après

#### Edge cases UX
- Contact archivé :
  - Tous les champs en lecture seule
  - Bandeau orange "Ce contact est archivé — il n'apparaîtra plus dans les sélections"
  - Bouton "Désarchiver"
  - Historique toujours accessible
- SIRET modifié sur un contact avec factures existantes :
  - Erreur bloquante "Le SIRET ne peut pas être modifié car des factures ont été émises avec ce contact"
- Sirene indique l'entreprise radiée :
  - Badge rouge "Entreprise radiée" + date de radiation
  - Warning "Ce contact est lié à une entreprise radiée — vérifiez sa situation avant d'émettre de nouvelles factures"
- Contact sans factures : onglets "Factures" affichent état vide + CTA
- Encours > 0 avec retard : alerte visible sur la fiche + montant en rouge
- Documents S3 : erreur de chargement → lien téléchargement direct comme fallback
- PDP identifiant manquant pour un client B2B :
  - Badge warning "Identifiant PDP manquant — la transmission électronique est impossible"
  - CTA "Renseigner l'identifiant PDP" (focus sur le champ)

### Composants UI
- En-tête avec avatar, badges, boutons d'action
- Onglets Bootstrap (`nav-tabs`), chaque onglet = Turbo Frame lazy
- Édition inline (Stimulus `InlineEditController`)
- Mini-tableaux factures avec Turbo Frames
- Zone notes Markdown (Stimulus `MarkdownEditorController`, lib Easymde ou équivalent)
- Upload documents drag & drop (Stimulus `FileUploadController`)
- Timeline historique (composant Twig réutilisé)
- Badge "Sirene" avec tooltip date dernière vérification

### Appels API / services Symfony

| Service | Action |
|---|---|
| `ContactController::show()` | Chargement fiche + données onglets |
| `ContactController::update()` | PUT mise à jour |
| `ContactController::archive()` | POST archivage |
| `InvoiceRepository::findByContact()` | Factures émises + récap financier |
| `ReceivedInvoiceRepository::findByContact()` | Factures reçues |
| `PaymentRepository::findByContact()` | Historique paiements |
| `EncoursMontantService` | Calcul encours + retards |
| `SireneApiClient` | Re-vérification SIRET à la demande |
| `DocumentUploadService` | Upload S3 pièces jointes libres |
| `AuditLogRepository` | Timeline contact |
| `AuditLogger` | `contact.updated`, `contact.archived` |

**Turbo Frame endpoints (chargement lazy par onglet)**
```
GET /contacts/{id}/frames/invoices          → mini-liste factures émises
GET /contacts/{id}/frames/received          → mini-liste factures reçues
GET /contacts/{id}/frames/payments          → historique paiements
GET /contacts/{id}/frames/documents         → liste pièces jointes
GET /contacts/{id}/frames/history           → timeline audit
```

**Endpoints API**
```
PUT  /api/contacts/{id}                     → mise à jour
POST /api/contacts/{id}/archive             → archivage
POST /api/contacts/{id}/unarchive           → désarchivage
POST /api/contacts/{id}/documents           → upload pièce jointe
DELETE /api/contacts/{id}/documents/{docId} → suppression pièce jointe
GET  /api/contacts/{id}/sirene-refresh      → re-vérification Sirene
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `Contact` | Tous les champs (lecture + édition) |
| `ContactPerson` | Interlocuteurs |
| `ContactDocument` | `id`, `contact_id`, `tenant_id`, `s3_key`, `filename`, `mime_type`, `size`, `uploaded_by`, `uploaded_at` |
| `Invoice` | Factures émises liées (lecture) |
| `ReceivedInvoice` | Factures reçues liées (lecture) |
| `Payment` | Paiements liés (lecture) |
| `AuditLog` | Timeline (lecture) |

---

## Notes transversales — Section CONTACTS

### SIRET comme identifiant pivot
Le SIRET est l'identifiant central pour :
- Le matching automatique fournisseur lors de la réception de factures (`ContactMatchingService`)
- La transmission PDP (le SIRET de l'émetteur et du destinataire sont obligatoires dans le XML)
- La vérification Sirene (statut entreprise, adresse officielle)
- La détection de doublons

Un contact sans SIRET est possible (étranger, particulier) mais déclenche des
avertissements sur les pages de facturation si la réforme s'applique.

### Identifiant PDP destinataire
Distinct du SIRET. Chaque entreprise assujettie à la réforme doit être joignable
via un identifiant PDP (qui peut être son SIRET, un UUID, ou un code spécifique
à sa PDP). Ce champ est la condition sine qua non de la transmission électronique.

### Archivage vs suppression
- **Archivage** : contact masqué des listes et sélecteurs mais conservé en base.
  Toutes les factures liées restent accessibles. Réversible.
- **Suppression** : uniquement si aucune facture (émise ou reçue) n't est liée.
  Irréversible. Déclenche un `AuditLog` de type `contact.deleted`.

### Vérification Sirene périodique
Un job hebdomadaire `RefreshSireneStatusJob` re-vérifie le statut des contacts
ayant un SIRET (entreprise active / radiée) et met à jour `Contact.sirene_status`
+ `Contact.sirene_checked_at`. Une notification est envoyée si une entreprise
passe en statut "radiée" alors qu'elle a des factures ouvertes.
