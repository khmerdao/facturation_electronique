# Section ONBOARDING — Parcours d'initialisation

Parcours déclenché automatiquement après la vérification email lors de l'inscription.  
Accessible uniquement aux utilisateurs authentifiés dont le tenant n'a pas encore complété l'onboarding (`Tenant.onboarding_completed = false`).

Un middleware `OnboardingMiddleware` redirige vers `/onboarding/organisation` toute tentative d'accès à une route protégée si l'onboarding n'est pas terminé.

---

## Sommaire

| Page | URL | Rôles |
|---|---|---|
| Setup organisation | `/onboarding/organisation` | OWNER |
| Préférences | `/onboarding/preferences` | OWNER |

---

## 1. `/onboarding/organisation` — Setup initial de l'organisation

### Rôles autorisés
`OWNER` uniquement — c'est le fondateur du tenant qui configure l'organisation.

### Objectif
Compléter le profil légal et fiscal de l'organisation, configurer la connexion PDP/PPF, et uploader le logo pour les documents émis.

### Features / fonctionnalités

**Stepper de progression**
- Indicateur visuel 2 étapes : "Organisation" (active) → "Préférences"
- Sauvegarde automatique à chaque champ (autosave debounced 2s, Stimulus)
- Possibilité de quitter et reprendre (état persisté en `Tenant`)

**Bloc — Identité légale**
- Raison sociale (pré-rempli depuis l'inscription, éditable)
- SIRET (pré-rempli depuis l'inscription, non éditable après vérification — affichage seul)
- Forme juridique (select : SAS, SARL, EI, EURL, SA, SNC, Association, Autre)
- Numéro de TVA intracommunautaire
  - Validation checksum algorithmique côté client (Stimulus) + côté serveur
  - Toggle "Non assujetti à la TVA" (micro-entrepreneurs, auto-entrepreneurs sous seuil)
  - Si non assujetti : mention légale automatique sur les factures ("TVA non applicable, art. 293 B du CGI")
- Capital social (optionnel, affiché sur les factures si renseigné)
- Numéro RCS / RM (optionnel)
- Code APE / NAF (pré-rempli depuis API Sirene si disponible)

**Bloc — Adresse du siège social**
- Adresse ligne 1 (voie)
- Adresse ligne 2 (complément, optionnel)
- Code postal + Ville (avec autocomplétion API adresse.data.gouv.fr)
- Pays (select ISO 3166, France par défaut)

**Bloc — Coordonnées de contact**
- Email de facturation (peut différer de l'email du compte)
- Téléphone (optionnel)
- Site web (optionnel)
- IBAN (optionnel, affiché sur les factures pour le virement)
  - Validation format IBAN (checksum)
  - Champ masqué par défaut, toggle pour voir
- BIC/SWIFT (optionnel, lié à l'IBAN)

**Bloc — Logo & identité visuelle**
- Upload logo (JPG/PNG/SVG, max 2 Mo)
  - Preview instantanée après upload (Stimulus FilePreviewController)
  - Recadrage optionnel (ratio libre ou 1:1)
  - Stockage S3/MinIO avec clé `tenants/{tenant_id}/logo.{ext}`
- Couleur principale de la marque (color picker, utilisée dans les templates de factures)

**Bloc — Configuration PDP / PPF**

> Point critique de conformité réforme 2026-2027

- Choix du mode de transmission :
  - **PPF (Portail Public de Facturation)** — gratuit, via Chorus Pro
  - **PDP (Plateforme de Dématérialisation Partenaire)** — via un opérateur privé immatriculé DGFiP
- Si PPF sélectionné :
  - Champ identifiant Chorus Pro (SIRET de l'entité dans Chorus)
  - Test de connexion (appel ping vers l'API Chorus Pro)
  - Statut "Connecté ✓" ou erreur avec message explicatif
- Si PDP sélectionné :
  - Select parmi les PDP immatriculées (liste maintenue en base + mise à jour manuelle)
  - Champ URL endpoint PDP (ou sélection depuis liste prédéfinie)
  - Champ clé API / token d'authentification PDP (stocké chiffré AES-256 en base)
  - Champ identifiant émetteur sur la PDP (SIRET ou identifiant spécifique)
  - Test de connexion (appel ping vers l'API PDP)
  - Statut connexion avec détail de l'erreur si échec
- Note réglementaire inline : "Obligatoire pour la réception de factures électroniques dès le 1er septembre 2026"
- Option "Configurer plus tard" (autorisé mais badge d'avertissement persistant sur le dashboard)

**Actions**
- Bouton "Enregistrer et continuer" → `/onboarding/preferences`
- Lien "Passer pour l'instant" (uniquement pour le bloc PDP, pas pour l'identité légale)

**Edge cases UX**
- SIRET non reconnu par l'API Sirene : possibilité de saisir manuellement tous les champs
- TVA intracommunautaire invalide : blocage soumission + message d'erreur explicite
- Upload logo échoué (réseau, taille) : message d'erreur + possibilité de réessayer sans perdre le reste du formulaire
- Test connexion PDP timeout (> 10s) : message "La PDP ne répond pas, vérifiez vos paramètres ou réessayez plus tard"
- Test connexion PDP échoué (credentials) : message d'erreur détaillé depuis la réponse PDP
- Fermeture navigateur : état sauvegardé, reprise possible depuis le même URL
- Formulaire incomplet à la soumission : scroll vers le premier champ invalide + highlight

### Composants UI
- Stepper horizontal 2 étapes (Bootstrap nav pills)
- Sections accordéon ou cards distinctes par bloc
- Composant Stimulus `IbanValidatorController`
- Composant Stimulus `SiretDisplayController` (lecture seule avec lien INSEE)
- Composant Stimulus `PdpTestController` (appel AJAX test connexion + affichage statut)
- Composant Stimulus `FilePreviewController` (preview logo + recadrage)
- Color picker Bootstrap-compatible (vue 3 ou stimulus)
- Badge statut PDP : `CONNECTED` (vert) / `PENDING` (orange) / `ERROR` (rouge)
- Autosave indicator "Enregistré il y a X secondes"

### Appels API / services Symfony

| Service | Action |
|---|---|
| `OnboardingController::organisation()` | Affichage + traitement formulaire |
| `TenantProfileService::update()` | Persistance des données légales |
| `SireneApiClient` | Vérification / enrichissement SIRET |
| `AddressAutocompleteClient` | Autocomplétion adresse (api.gouv.fr) |
| `LogoUploadService` | Upload S3, génération URL signée |
| `PdpConnectionTester` | Test ping PDP/PPF (HTTP client async) |
| `PdpConfigEncryptor` | Chiffrement AES-256 des credentials PDP |
| `IbanValidatorService` | Validation checksum IBAN |
| `AuditLogger` | `tenant.organisation_configured` |

**Endpoint interne (Stimulus AJAX)**

```
POST /api/onboarding/test-pdp-connection
Body : { pdp_name, endpoint_url, api_key, emitter_id }
Response : { status: "ok"|"error", message: string, latency_ms: int }
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `Tenant` | `name`, `siret`, `legal_form`, `tva_intra`, `vat_exempt`, `address`, `email`, `phone`, `iban`, `bic`, `logo_s3_key`, `brand_color`, `pdp_config`, `onboarding_step` |
| `PdpConfig` (Value Object JSON dans Tenant) | `mode`, `pdp_name`, `endpoint_url`, `api_key_encrypted`, `emitter_id`, `connected_at`, `last_test_status` |

### Dépendances
- Accessible uniquement si `Tenant.onboarding_completed = false`
- Pré-rempli avec les données saisies lors de `/register`
- Post-validation → `/onboarding/preferences`
- Données utilisées ensuite sur : `/invoices/new`, `/settings/organisation`, `/settings/pdp`

---

## 2. `/onboarding/preferences` — Préférences de facturation

### Rôles autorisés
`OWNER` uniquement.

### Objectif
Configurer les paramètres par défaut de facturation : devise, modèle de document, séquence de numérotation et mentions légales obligatoires.

### Features / fonctionnalités

**Stepper de progression**
- Indicateur visuel 2 étapes : "Organisation" (complétée ✓) → "Préférences" (active)
- Même comportement autosave que l'étape précédente

**Bloc — Devise & langue**
- Devise par défaut (select ISO 4217 : EUR par défaut, USD, GBP, CHF…)
- Langue des documents émis (FR / EN — peut différer de la langue de l'interface)
- Format de date sur les factures (JJ/MM/AAAA par défaut)
- Format numérique (séparateur décimal : virgule FR ou point EN)

**Bloc — Séquence de numérotation**

> Obligation légale : la numérotation doit être chronologique, continue et sans rupture (art. 242 nonies A de l'annexe II du CGI)

- Préfixe de séquence (ex : `FAC`, `FACT`, `INV`)
- Format de l'année (AAAA ou AA)
- Format du mois (inclus ou non)
- Séparateur (tiret, slash, point, aucun)
- Numéro de départ (défaut : 1, modifiable uniquement avant la première facture)
- Prévisualisation en temps réel du format : `FAC-2026-0001`, `FACT/26/001`, etc.
- Champ séquence avoir (préfixe séparé, ex : `AV-2026-0001`)
- **Avertissement réglementaire** : "La numérotation ne pourra plus être modifiée après émission de la première facture"

**Bloc — Modèle de facture par défaut**
- Sélection parmi les templates disponibles (grille de previews visuels)
  - Template "Classique" (simple, sobre)
  - Template "Moderne" (avec couleur de marque)
  - Template "Compact" (optimisé une page)
  - Template "Détaillé" (avec colonnes supplémentaires)
- Preview du template sélectionné avec les données de l'organisation
- Le template peut être modifié ultérieurement dans `/settings/templates`

**Bloc — Format d'export électronique par défaut**

> Formats obligatoires pour la réforme

- Format structuré par défaut :
  - **Factur-X** (PDF/A-3 + XML CII embarqué) — recommandé pour TPE/PME
  - **UBL 2.1** (XML pur)
  - **CII D16B** (XML pur)
- Note explicative sur chaque format (tooltip ou accordion)
- Option "Laisser la PDP choisir" (si la PDP impose un format)

**Bloc — Conditions de paiement par défaut**
- Délai de paiement par défaut (select : 0j comptant, 15j, 30j, 45j, 60j, personnalisé)
  - Info bulle : délai légal max 60j (art. L.441-10 Code de commerce), 45j fin de mois
- Mode de paiement par défaut (virement, chèque, carte, prélèvement, autre)
- Pénalités de retard (taux légal en vigueur, personnalisable)
  - Taux légal pré-rempli automatiquement (récupéré depuis une source configurable)
  - Indemnité forfaitaire de recouvrement : 40€ (légalement obligatoire pour B2B)
- Escompte pour paiement anticipé (optionnel, en %)

**Bloc — Mentions légales obligatoires**
- Zone de texte "Mentions légales" pré-remplie avec les mentions obligatoires selon le statut juridique :
  - N° RCS si SA/SAS/SARL
  - Mention "Dispensé d'immatriculation au RCS" si EI/micro
  - Mention TVA si assujetti ou "TVA non applicable art. 293 B du CGI"
  - Capital social si SA/SAS
- Champ "Conditions générales de vente" (optionnel, texte libre ou upload PDF)
- Champ "Notes de bas de page" récurrentes (ex : coordonnées bancaires si pas d'IBAN renseigné)

**Bloc — Notifications & rappels (configuration initiale)**
- Activer les rappels automatiques de paiement (oui/non)
  - Si oui : délais configurables (J+15, J+30, J+45 après échéance)
- Activer les alertes email sur les rejets PDP (oui/non)
- Activer les alertes sur les factures reçues en attente de validation (oui/non)

**Actions**
- Bouton "Terminer la configuration" → marque `Tenant.onboarding_completed = true` → redirect `/dashboard`
- Lien "Retour" → `/onboarding/organisation`
- Ces préférences sont toutes modifiables ultérieurement dans `/settings/*`

**Edge cases UX**
- Numéro de départ invalide (< 1 ou non numérique) : erreur de validation
- Format de séquence produisant des doublons potentiels : avertissement (ex : préfixe vide + pas d'année)
- Template non chargé (erreur réseau) : placeholder grisé avec icône reload
- Taux de pénalité personnalisé inférieur au taux légal : warning (non bloquant) "Ce taux est inférieur au taux légal en vigueur"
- Soumission sans avoir choisi de template : confirmation modale "Vous n'avez pas choisi de modèle, le template Classique sera utilisé par défaut. Continuer ?"

### Composants UI
- Stepper horizontal 2 étapes (étape 1 cochée)
- Composant Stimulus `SequencePreviewController` (prévisualisation temps réel du numéro)
- Grille de cards template avec radio button visuel (sélection par clic sur la card)
- Preview PDF du template dans une iframe ou modale (rendu serveur)
- Tooltips Bootstrap sur les mentions réglementaires
- Autosave indicator

### Appels API / services Symfony

| Service | Action |
|---|---|
| `OnboardingController::preferences()` | Affichage + traitement formulaire |
| `TenantPreferencesService::save()` | Persistance des préférences |
| `InvoiceTemplateService` | Récupération liste templates + preview |
| `SequenceService::initialize()` | Init `InvoiceSequence` avec préfixe + numéro de départ |
| `LegalMentionGenerator` | Génération mentions légales depuis forme juridique |
| `TaxRateService` | Récupération taux légal pénalités de retard courant |
| `AuditLogger` | `tenant.onboarding_completed` |
| `NotificationService` | Notification de bienvenue (type `ONBOARDING_COMPLETE`) |

**Endpoint interne (Stimulus AJAX)**

```
GET /api/onboarding/sequence-preview?prefix={p}&year_format={y}&month={m}&separator={s}&start={n}
Response : { preview: "FAC-2026-0001", next_credit_note: "AV-2026-0001" }
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `Tenant` | `default_currency`, `document_locale`, `default_payment_terms`, `default_payment_mode`, `late_payment_rate`, `recovery_fee`, `legal_mentions`, `cgv_s3_key`, `default_invoice_format`, `onboarding_completed` |
| `InvoiceSequence` | `tenant_id`, `prefix`, `year_format`, `month_format`, `separator`, `next_number`, `credit_note_prefix`, `locked` (true après 1ère facture) |
| `InvoiceTemplate` | `tenant_id`, `template_key`, `is_default`, `custom_settings` |
| `NotificationPreference` | `tenant_id`, `type`, `enabled`, `delay_days` |

### Dépendances
- Étape 2 de l'onboarding — requiert l'étape 1 complétée
- Post-complétion → `/dashboard` (première visite)
- Paramètres modifiables ensuite dans :
  - `/settings/organisation` (identité légale)
  - `/settings/sequences` (numérotation — partiellement verrouillée)
  - `/settings/templates` (modèles)
  - `/settings/pdp` (connexion PDP)

---

## Notes transversales section ONBOARDING

### Middleware de protection
```php
// OnboardingMiddleware (Symfony EventListener sur kernel.request)
// Si User authentifié ET Tenant.onboarding_completed = false
// ET route courante n'est pas /onboarding/*
// → redirect /onboarding/organisation
```

### Progression et reprise
- `Tenant.onboarding_step` (enum : `ORGANISATION` | `PREFERENCES` | `COMPLETED`) permet de reprendre exactement là où l'utilisateur s'est arrêté
- Un utilisateur qui ferme le navigateur à mi-étape retrouve son état sauvegardé

### Données pré-remplies depuis `/register`
Les champs suivants sont déjà renseignés et non modifiables (ou pré-remplis éditables) :
- SIRET (non éditable)
- Raison sociale (éditable)
- Plan d'abonnement (non éditable ici, géré dans `/settings/organisation`)

### Implication réglementaire directe
La configuration PDP dans cette étape est le premier point de conformité obligatoire. Sans PDP configurée, l'application ne peut pas transmettre de factures électroniques. Le badge d'avertissement persistant sur le dashboard est le seul moyen de pression UX prévu — on ne bloque pas la création de factures (l'utilisateur peut encore créer des brouillons), mais la transmission sera impossible.
