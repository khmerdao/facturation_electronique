# Section PARAMÈTRES — Configuration du tenant

Pages de configuration accessibles depuis le menu "Paramètres".  
Navigation commune : sidebar gauche avec les 6 rubriques.  
Toutes les pages paramètres sont protégées — accès minimum `ADMIN`
sauf indication contraire.

---

## Sommaire

| Page | URL | Rôles minimum |
|---|---|---|
| Organisation | `/settings/organisation` | OWNER |
| Utilisateurs & rôles | `/settings/users` | ADMIN |
| Modèles de factures | `/settings/templates` | ADMIN |
| Séquences de numérotation | `/settings/sequences` | ADMIN |
| PDP / PPF | `/settings/pdp` | ADMIN |
| Intégrations (API & webhooks) | `/settings/integrations` | ADMIN |

---

## 1. `/settings/organisation` — Paramètres de l'organisation

### Rôles autorisés
`OWNER` uniquement — seul l'owner peut modifier les données légales et le plan.

### Objectif
Modifier les informations légales, fiscales et commerciales du tenant,
gérer le plan d'abonnement SaaS et les options de conformité réglementaire.

### Features / fonctionnalités

#### Bloc — Identité légale

Identique au formulaire `/onboarding/organisation` (bloc Identité légale),
avec les mêmes validations. Champs pré-remplis depuis `Tenant`.

- Raison sociale (requis)
- SIRET (lecture seule — non modifiable après la première facture émise)
  - Badge "X facture(s) émises — SIRET verrouillé" si applicable
  - Si aucune facture : bouton "Modifier le SIRET" avec confirmation modale
- Forme juridique
- Numéro de TVA intracommunautaire (avec validation checksum)
- Toggle "Non assujetti à la TVA" (franchise en base)
- Capital social, RCS/RM, code NAF
- **Régime TVA** (nouveau par rapport à l'onboarding) :
  - Régime réel normal (CA3 mensuelle)
  - Régime réel simplifié (CA12 annuelle)
  - Franchise en base (art. 293 B CGI)
  - Micro-entreprise (régime micro-BIC/BNC)
  - Ce champ influe sur `/tax` et `/e-reporting` (périodicité)

#### Bloc — Adresse & coordonnées
Identique à l'onboarding — adresse siège, email facturation, téléphone, IBAN/BIC.

#### Bloc — Logo & identité visuelle
- Upload logo (même composant que l'onboarding)
- Couleur principale de la marque
- Aperçu de la mise en page sur un modèle de facture (iframe preview)
- Bouton "Régénérer les aperçus des templates" (si logo ou couleur modifiés)

#### Bloc — Plan & abonnement (OWNER uniquement)

- Plan actuel : FREE / PRO / ENTERPRISE (badge + date de renouvellement)
- Limites du plan courant :
  - Nb factures/mois utilisé vs limite
  - Nb utilisateurs actifs vs limite
  - Espace de stockage S3 utilisé vs limite
  - Barre de progression pour chaque limite
- Bouton "Changer de plan" → page de facturation SaaS (externe ou modale)
- Bouton "Annuler l'abonnement" (avec confirmation + conséquences affichées)
- Historique des factures SaaS (lien vers portail client Stripe ou équivalent)

#### Bloc — Danger zone (OWNER uniquement)

Section en bas de page, fond rouge pâle, accordéon fermé par défaut.

- **Transférer la propriété** :
  - Select utilisateur du tenant (ADMIN uniquement)
  - Confirmation : saisie du mot de passe + "Je comprends que je perds
    les droits OWNER" + bouton "Transférer"
  - `AuditLog` : `tenant.ownership_transferred`
- **Supprimer l'organisation** :
  - Conditions préalables affichées :
    - Aucune facture en statut `SENT`/`ACKNOWLEDGED`
    - Tous les batches e-reporting soumis
    - Aucun abonnement actif
  - Confirmation : saisie du nom de l'organisation + bouton "Supprimer définitivement"
  - Suppression soft (flag `deleted_at`) + anonymisation RGPD planifiée à 30 jours
  - Email de confirmation envoyé
  - `AuditLog` : `tenant.deletion_requested`

#### Edge cases UX
- SIRET verrouillé tentatif de modification : erreur claire + explication légale
- Modification TVA intracommunautaire avec factures existantes :
  warning "Ce changement n'affecte pas les factures déjà émises"
- Changement régime TVA : modale confirmation "Ce changement modifie la
  périodicité de vos déclarations. Les batches e-reporting en cours
  conservent leur périodicité d'origine."
- Upload logo > 2Mo : erreur "Fichier trop volumineux (max 2 Mo)"
- Plan limits atteintes : bannière "Vous avez atteint la limite de X factures/mois.
  Passez à PRO pour continuer." (non bloquant — grace period 7 jours)

### Composants UI
- Sections accordéon ou cards distinctes
- Composant Stimulus `SiretLockController` (verrouillage conditionnel)
- Barres de progression plan (Bootstrap)
- Danger zone accordéon (Bootstrap collapse, style rouge)
- Modale transfert propriété avec confirmation mot de passe
- Modale suppression avec saisie nom organisation

### Appels API / services Symfony

| Service | Action |
|---|---|
| `OrganisationSettingsController` | GET/POST paramètres organisation |
| `TenantProfileService::update()` | Mise à jour données légales |
| `TenantPlanService` | Calcul utilisation + limites plan |
| `LogoUploadService` | Upload S3 logo |
| `OwnershipTransferService` | Transfert propriété avec validation |
| `TenantDeletionService` | Soft delete + planification anonymisation |
| `AuditLogger` | Toutes les actions sensibles |

### Entités Doctrine
`Tenant`, `TenantMembership`, `User`, `AuditLog`

---

## 2. `/settings/users` — Gestion des membres et rôles

### Rôles autorisés
`ADMIN`, `OWNER`

### Objectif
Inviter des collaborateurs, gérer leurs rôles dans le tenant et révoquer les accès.

### Features / fonctionnalités

#### Liste des membres actifs

Tableau des utilisateurs membres du tenant :

| Avatar | Nom | Email | Rôle | Statut | Depuis | Actions |
|---|---|---|---|---|---|---|
| MD | Marie Dupont | marie@… | OWNER | Actif | 01/01/2026 | — |
| JM | Jean Martin | jean@… | ADMIN | Actif | 15/01/2026 | Modifier rôle \| Révoquer |
| PL | Pierre Leclerc | pierre@… | ACCOUNTANT | Actif | 20/02/2026 | Modifier rôle \| Révoquer |

**Règles d'affichage des actions**
- `OWNER` : aucune action possible sur soi-même ni sur l'autre OWNER
- `ADMIN` : peut modifier rôle/révoquer `ACCOUNTANT` et `VIEWER`
  mais pas `OWNER` ni un autre `ADMIN`
- Soi-même : pas d'action (ne peut pas se révoquer)

**Modifier le rôle** (OWNER sur tout, ADMIN sur ACCOUNTANT/VIEWER)
- Dropdown inline : ADMIN / ACCOUNTANT / VIEWER
- Pas de rétrogradation OWNER possible (sauf via transfert dans `/settings/organisation`)
- Confirmation modale si rétrogradation d'un ADMIN
- `AuditLog` : `membership.role_changed`

**Révoquer un accès**
- Modale confirmation "Retirer {Nom} de cette organisation ?"
- Post-révocation : invalidation JWT + sessions de l'utilisateur révoqué
- Email notification à l'utilisateur révoqué
- `AuditLog` : `membership.revoked`

#### Invitations en attente

Tableau des invitations envoyées non encore acceptées :

| Email | Rôle invité | Envoyée le | Expire le | Actions |
|---|---|---|---|---|
| nouveau@… | ACCOUNTANT | 10/03/2026 | 17/03/2026 | Renvoyer \| Annuler |

- Badge "Expirée" si > 7 jours sans acceptation
- Bouton "Renvoyer l'invitation" (renvoie email, reset expiry à 7j)
- Bouton "Annuler l'invitation"

#### Formulaire d'invitation

- **Email** (requis, validation format)
- **Rôle** (select ADMIN / ACCOUNTANT / VIEWER — pas OWNER)
- **Message personnalisé** (optionnel, inclus dans l'email d'invitation)
- Bouton "Envoyer l'invitation"

Flux d'invitation :
1. Création `TenantInvitation` (token signé, expiry 7 jours)
2. Email envoyé avec lien d'acceptation `/invitations/{token}/accept`
3. Si l'email correspond à un `User` existant : clic → ajout direct au tenant
4. Si l'email est nouveau : clic → redirect `/register?invitation={token}`
   (formulaire pré-rempli avec l'email, champs organisation masqués)
5. Post-acceptation : création `TenantMembership` avec le rôle invité
6. `AuditLog` : `membership.invitation_accepted`

#### Paramètres de sécurité du tenant

- **Forcer le 2FA** : toggle "Exiger l'authentification à deux facteurs pour tous les membres"
  - Si activé : membres sans 2FA configuré voient un écran de setup au prochain login
  - Délai de grâce : 7 jours (configurable)
- **Durée de session** : select (1h / 8h / 24h / 7 jours / 30 jours)
- **IP autorisées** (optionnel, feature ENTERPRISE) :
  - Liste blanche CIDR (ex : `192.168.1.0/24`, `82.x.x.x/32`)
  - Accès refusé depuis IPs hors liste avec message explicatif

#### Edge cases UX
- Invitation à un email déjà membre du tenant :
  erreur "Cet utilisateur est déjà membre de cette organisation"
- Invitation à un email déjà invité (en attente) :
  erreur "Une invitation est déjà en attente pour cet email" + option "Renvoyer"
- Limite d'utilisateurs plan atteinte :
  erreur "Votre plan {FREE} est limité à {X} utilisateurs.
  [Passer à PRO pour inviter plus de membres →]"
- Révocation du dernier ADMIN/OWNER : erreur
  "Impossible — l'organisation doit toujours avoir au moins un OWNER"
- Forcer 2FA avec membres sans 2FA : liste des membres concernés affichée
  avec délai de grâce avant activation

### Composants UI
- Tableau membres avec actions inline (Turbo Frame)
- Formulaire invitation (Bootstrap card)
- Dropdown rôle inline (Stimulus `RoleChangeController`)
- Modale confirmation révocation
- Section sécurité tenant (Bootstrap cards)
- Badge invitation expirée

### Appels API / services Symfony

| Service | Action |
|---|---|
| `UserSettingsController` | GET membres, POST invitation |
| `InvitationService` | Création token + envoi email |
| `MembershipService::changeRole()` | Modification rôle avec validation |
| `MembershipService::revoke()` | Révocation + invalidation sessions/JWT |
| `TenantSecurityService` | Configuration 2FA forcé, session duration, IP whitelist |
| `AuditLogger` | Toutes les actions membres |

### Entités Doctrine
`TenantMembership`, `TenantInvitation`, `User`, `Tenant`, `AuditLog`

---

## 3. `/settings/templates` — Modèles de factures

### Rôles autorisés
`ADMIN`, `OWNER`

### Objectif
Personnaliser les modèles visuels de factures (PDF) utilisés lors de l'émission,
avec prévisualisation en temps réel.

### Features / fonctionnalités

#### Galerie des templates disponibles

Grille de cards, chaque card = un template :
- Miniature aperçu PDF
- Nom du template
- Badge "Par défaut" (le template actif)
- Badge "Personnalisé" si modifié par rapport au template de base
- Actions : Sélectionner comme défaut | Personnaliser | Dupliquer | Supprimer
  (suppression uniquement si pas template par défaut et pas le dernier)

Templates de base fournis (non supprimables, duplicables) :
- **Classique** : en-tête sobre, tableau lignes standard
- **Moderne** : couleur de marque en en-tête, mise en page aérée
- **Compact** : optimisé une page, typographie dense
- **Détaillé** : colonnes supplémentaires (référence, code comptable)

#### Éditeur de template (panneau latéral ou page dédiée)

**Onglet "Contenu"**

Zones configurables (chaque zone = toggle afficher/masquer + ordre) :

| Zone | Configurable | Défaut |
|---|---|---|
| Logo | Position (gauche/droite/centré), taille | Affiché |
| Informations émetteur | Champs visibles (SIRET, TVA, RCS, capital…) | Tous affichés |
| Informations destinataire | Champs visibles | Tous affichés |
| Référence & dates | N° facture, date émission, échéance, référence client | Tous affichés |
| Tableau des lignes | Colonnes visibles, libellés colonnes personnalisables | Standard |
| Récapitulatif TVA | Afficher/masquer le détail par taux | Affiché |
| Conditions de paiement | IBAN, délai, pénalités, indemnité 40€ | Affiché |
| Mentions légales | Texte configurable | Depuis tenant settings |
| Notes client | Affiché/masqué | Affiché si renseigné |
| QR code | Lien vers la facture en ligne (option) | Masqué |
| Code-barres 2D | Pour la lecture automatique (Factur-X) | Masqué |

**Onglet "Style"**

- Police de caractères (select : Helvetica, Arial, Times, Roboto…)
- Taille de police corps (10pt / 11pt / 12pt)
- Couleur principale (hérite de la couleur de marque du tenant, surchargeab le)
- Couleur secondaire (sous-titres, séparateurs)
- Couleur texte (noir par défaut)
- Style d'en-tête : plein couleur / bande colorée / minimal / aucun
- Style de tableau : lignes alternées / bordures / minimal
- Marges (normal / étroit / large)
- Format papier : A4 (défaut) / Letter

**Onglet "Mentions obligatoires"**

Champs texte pré-remplis avec les mentions légales obligatoires
(récupérées depuis `Tenant`), éditables ici :
- Mentions légales bas de page
- Conditions générales (texte ou lien PDF)
- Message de pied de page libre

**Prévisualisation temps réel**
- Panneau droit : aperçu PDF rendu serveur
- Se met à jour automatiquement après 1,5s d'inactivité (debounce)
- Données de prévisualisation : facture fictive avec données réalistes
  (client "ACME SAS", lignes types, montants variés)
- Bouton "Prévisualiser avec une vraie facture" :
  select d'une facture existante → rendu avec les vraies données
- Bouton "Télécharger l'aperçu"

**Actions**
- "Enregistrer le template" (PUT)
- "Réinitialiser aux valeurs par défaut" (avec confirmation)
- "Dupliquer ce template"

#### Edge cases UX
- Template par défaut supprimé (impossible — bouton grisé) :
  tooltip "Définissez un autre template par défaut avant de supprimer celui-ci"
- Preview indisponible (rendu PDF serveur en erreur) :
  message "Aperçu indisponible — les modifications sont sauvegardées"
- Logo non uploadé : placeholder initiales dans l'aperçu
- Mentions légales vides : warning "Les mentions légales sont obligatoires
  sur les factures. [Compléter →]"

### Composants UI
- Grille templates (Bootstrap cards, CSS grid)
- Éditeur template : onglets Bootstrap + sections accordéon
- Color pickers (Stimulus `ColorPickerController`)
- Toggle zones avec drag & drop pour l'ordre (Stimulus `SortableController`)
- Preview PDF (iframe, debounce Stimulus `PreviewDebounceController`)
- Select facture réelle pour aperçu (autocomplétion)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `TemplateSettingsController` | CRUD templates |
| `InvoiceTemplateService` | Persistance configuration template |
| `PdfRenderService` | Rendu aperçu (facture fictive ou réelle) |
| `AuditLogger` | `template.updated`, `template.set_default` |

**Endpoints**
```
GET    /settings/templates
GET    /settings/templates/{id}/edit
PUT    /api/templates/{id}
POST   /api/templates/{id}/duplicate
DELETE /api/templates/{id}
POST   /api/templates/{id}/set-default
POST   /api/templates/preview     Body: { template_config, invoice_id? }
                                  → PDF bytes (inline)
```

### Entités Doctrine
`InvoiceTemplate` (tous les champs de configuration JSON), `Invoice` (aperçu)

---

## 4. `/settings/sequences` — Numérotation des factures

### Rôles autorisés
`ADMIN`, `OWNER`

### Objectif
Configurer et superviser les séquences de numérotation des factures et avoirs,
dans le respect de l'obligation légale de numérotation continue et chronologique.

### Features / fonctionnalités

#### Bloc — Séquence principale (factures)

**Paramètres de la séquence**
- Préfixe (ex : `FAC`, `FACT`, `INV`, libre)
- Format année : AAAA (2026) / AA (26) / aucun
- Format mois : MM inclus / non inclus
- Séparateur : `-` / `/` / `.` / aucun
- Numéro de départ (modifiable uniquement si `locked = false`)
- Nombre de chiffres minimum (zéro-padding) : 3 / 4 / 5 / 6

**Prévisualisation en temps réel**
- Exemples générés : prochaine facture, 2ème, 3ème, facture de l'année suivante
  ```
  Prochaine facture    : FAC-2026-0043
  Suivante             : FAC-2026-0044
  Janvier 2027         : FAC-2027-0001 (remise à zéro annuelle)
  ```
- Option "Remise à zéro annuelle" : toggle (si activé, le compteur repart à
  `numéro_de_départ` chaque 1er janvier)

**Statut de verrouillage**
- `locked = false` : séquence modifiable (aucune facture validée)
  - Badge vert "Séquence modifiable"
  - Tous les champs éditables
- `locked = true` : séquence verrouillée (au moins une facture validée)
  - Badge orange "Séquence verrouillée — {X} factures émises"
  - Champs verrouillés : préfixe, format, séparateur, numéro de départ
  - **Seul le prochain numéro est visible** (en lecture seule)
  - Encadré réglementaire : "La modification de la séquence après émission
    de factures est interdite (art. 242 nonies A annexe II CGI). Pour changer
    de format, créez une nouvelle séquence après clôture de l'exercice."

**Numéro courant**
- Prochain numéro qui sera alloué (lecture seule, rafraîchi en temps réel)
- Historique des 5 derniers numéros alloués (facture + date)

#### Bloc — Séquence avoirs

Même paramètres que la séquence principale, avec :
- Préfixe distinct recommandé (ex : `AV`, `AVOIR`, `CN`)
- Prévisualisation : `AV-2026-0012`
- Verrouillage indépendant de la séquence principale

#### Bloc — Séquences supplémentaires (optionnel)

Pour les entreprises avec plusieurs entités ou flux de facturation distincts :
- Bouton "Ajouter une séquence" (ex : séquence pour proformas, acomptes)
- Chaque séquence supplémentaire a un nom, ses propres paramètres
- Sélectionnable au niveau de la facture (champ "Séquence" dans `/invoices/new`)

#### Bloc — Audit de la numérotation

Vérification de l'intégrité de la séquence (lancée à la demande ou hebdomadaire) :
- Contrôle : aucun trou dans la numérotation
- Contrôle : numéros en ordre chronologique
- Contrôle : aucun doublon
- Résultat :
  - "Séquence intègre ✓ — aucun trou détecté sur {X} factures"
  - "⚠ Anomalie détectée : {description}" + liste des numéros concernés

#### Edge cases UX
- Tentative de modification séquence verrouillée :
  champs désactivés + tooltip réglementaire
- Numéro de départ < numéro courant (si déverrouillé) :
  erreur "Le numéro de départ ne peut pas être inférieur au numéro
  actuellement alloué ({N})"
- Préfixe avec caractères spéciaux interdits (/, \\, espaces) :
  erreur de validation inline
- Audit avec trou détecté : lien vers les factures manquantes dans `/invoices`

### Composants UI
- Formulaire Symfony `SequenceType` rendu Twig
- Composant Stimulus `SequencePreviewController` (réutilisé depuis onboarding)
- Sections verrouillées (champs `disabled` + overlay Stimulus)
- Résultats audit (Twig, icônes Tabler)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `SequenceSettingsController` | GET/PUT séquences |
| `SequenceService::update()` | Mise à jour avec validation verrouillage |
| `SequenceAuditService` | Vérification intégrité numérotation |
| `AuditLogger` | `sequence.updated` |

### Entités Doctrine
`InvoiceSequence`, `Invoice` (vérification verrouillage + audit)

---

## 5. `/settings/pdp` — Connexion PDP / PPF

### Rôles autorisés
`ADMIN`, `OWNER`

### Objectif
Configurer et tester la connexion à la Plateforme de Dématérialisation Partenaire
ou au Portail Public de Facturation, pour l'e-invoicing et l'e-reporting.

### Features / fonctionnalités

#### Bloc — Mode de transmission actuel

Badge statut global :
- `CONNECTED` (vert) : "PDP {nom} connectée — dernière transmission réussie il y a {X}"
- `DEGRADED` (orange) : "PDP {nom} — dernières transmissions ralenties"
- `ERROR` (rouge) : "PDP {nom} — connexion interrompue depuis {date}"
- `NOT_CONFIGURED` (gris) : "Aucune PDP configurée"

#### Bloc — Configuration PDP / PPF

**Sélection du mode**
- Radio : PPF (Portail Public de Facturation) / PDP (opérateur privé)

**Si PPF sélectionné**
- Identifiant Chorus Pro (SIRET de l'entité Chorus)
- Environnement : Production / Bac à sable (test)
- Bouton "Tester la connexion"
- Documentation lien : "Comment configurer Chorus Pro ?"

**Si PDP sélectionné**
- Select PDP parmi liste des PDP immatriculées DGFiP :
  - Chaque entrée : nom PDP + logo + lien documentation
  - Option "Autre PDP" (saisie manuelle)
- URL endpoint API de la PDP
- Clé API / token d'authentification (champ masqué, input type password)
  - Stocké chiffré AES-256 en base (`PdpConfig.api_key_encrypted`)
  - Bouton "Afficher" (toggle show/hide)
  - Bouton "Régénérer" (si supporté par la PDP)
- Identifiant émetteur sur la PDP (SIRET ou UUID selon la PDP)
- **Configuration séparée e-reporting** (toggle) :
  - Si la PDP e-reporting est différente de la PDP e-invoicing
  - Champs identiques pour la seconde PDP
- Bouton "Tester la connexion"

**Résultat du test de connexion**
- Success : badge vert + latence + version API PDP
- Erreur : badge rouge + message d'erreur + code HTTP + suggestion de correction

#### Bloc — Certificats & sécurité (feature ENTERPRISE)

- Upload certificat client (mTLS si supporté par la PDP)
- Empreinte certificat actuel + date d'expiration
- Alerte si certificat expire dans < 30 jours

#### Bloc — Historique des connexions

Mini-tableau des 10 derniers tests de connexion :
| Date | Mode | Résultat | Latence |
|---|---|---|---|
| 15/03/2026 15:32 | PDP Chorus | ✓ Succès | 234ms |
| 15/03/2026 09:10 | PDP Chorus | ✗ Timeout | — |

#### Bloc — Configuration webhook entrant

Pour la réception des notifications PDP (accusés, rejets) :
- URL webhook généré automatiquement :
  `https://app.domain.com/api/webhooks/pdp/{tenant_slug}`
- Secret HMAC (affiché une fois, stocké haché)
- Bouton "Régénérer le secret" (invalide les anciens webhooks)
- Instructions de configuration côté PDP (texte + lien documentation)
- Historique des derniers webhooks reçus (10 derniers, avec statut de traitement)

#### Edge cases UX
- PDP non dans la liste officielle DGFiP :
  warning "Cette PDP n'est pas dans la liste des PDP immatriculées DGFiP.
  Vérifiez qu'elle est bien agréée avant de l'utiliser."
- Test de connexion timeout (> 10s) :
  message "La PDP ne répond pas — vérifiez l'URL et vos credentials"
- Credentials modifiés alors que des transmissions sont en cours :
  warning "Des transmissions sont en cours. La modification des credentials
  peut les faire échouer. Continuez ?"
- Certificat expiré : alerte rouge + blocage transmission jusqu'au renouvellement
- Webhook secret régénéré : modale "Attention — l'ancien secret sera immédiatement
  invalidé. Mettez à jour la configuration côté PDP avant de confirmer."

### Composants UI
- Badge statut connexion (composant Twig `_pdp_status_badge.html.twig`)
- Composant Stimulus `PdpTestController` (réutilisé depuis onboarding)
- Champ password avec toggle show/hide (Stimulus)
- Historique connexions (Turbo Frame, lazy)
- Modale confirmation régénération secret

### Appels API / services Symfony

| Service | Action |
|---|---|
| `PdpSettingsController` | GET/PUT configuration PDP |
| `PdpConfigEncryptor` | Chiffrement/déchiffrement credentials |
| `PdpConnectionTester` | Test connexion async |
| `PdpWebhookSecretService` | Génération/rotation secret HMAC |
| `PdpImmatriculationList` | Liste des PDP agréées DGFiP (mise à jour manuelle) |
| `AuditLogger` | `pdp.configured`, `pdp.tested`, `pdp.webhook_secret_rotated` |

**Endpoints**
```
PUT  /api/settings/pdp
POST /api/settings/pdp/test-connection
POST /api/settings/pdp/rotate-webhook-secret
GET  /api/settings/pdp/webhook-history
```

### Entités Doctrine
`Tenant` (champ `pdp_config` JSON), `PdpWebhookLog`

---

## 6. `/settings/integrations` — Clés API & webhooks

### Rôles autorisés
`ADMIN`, `OWNER`

### Objectif
Gérer les accès API externes (clés API pour intégrations tierces) et
configurer les webhooks sortants pour notifier des systèmes externes
des événements de l'application.

### Features / fonctionnalités

#### Bloc — Clés API

**Liste des clés API actives**

| Nom | Préfixe | Créée le | Dernière utilisation | Permissions | Actions |
|---|---|---|---|---|---|
| ERP Sage | `sk_live_abc…` | 01/02/2026 | Il y a 2h | Lecture seule | Voir \| Révoquer |
| Compta expert | `sk_live_xyz…` | 15/03/2026 | Jamais | Lecture + Écriture | Voir \| Révoquer |

- La clé complète n'est **jamais réaffichée** après création (affichée une seule fois)
- Bouton "Créer une clé API"

**Création d'une clé API**
- Nom (description de l'usage, ex : "Intégration ERP Sage")
- Permissions granulaires (checkboxes) :

  | Permission | Description |
  |---|---|
  | `invoices:read` | Lire les factures émises |
  | `invoices:write` | Créer / modifier des factures |
  | `received_invoices:read` | Lire les factures reçues |
  | `contacts:read` | Lire les contacts |
  | `contacts:write` | Créer / modifier des contacts |
  | `products:read` | Lire le catalogue |
  | `payments:read` | Lire les paiements |
  | `payments:write` | Enregistrer des paiements |
  | `exports:read` | Déclencher des exports |

- Date d'expiration (optionnel : 30j / 90j / 1an / jamais)
- Environnement : Production / Test (les clés test ne transmettent pas à la PDP réelle)
- Post-création : **affichage unique de la clé complète** (`sk_live_...`)
  - Modale avec clé + bouton copier + message "Cette clé ne sera plus affichée —
    copiez-la maintenant"

**Révocation d'une clé**
- Confirmation modale "Révoquer la clé '{nom}' ? Elle sera immédiatement invalidée."
- Invalidation immédiate (Redis blacklist)
- `AuditLog` : `api_key.revoked`

#### Bloc — Webhooks sortants

**Liste des webhooks configurés**

| URL | Événements | Statut | Dernière livraison | Actions |
|---|---|---|---|---|
| https://erp.client.fr/hooks | invoice.paid, invoice.sent | Actif ✓ | Il y a 5min (200) | Éditer \| Tester \| Désactiver \| Supprimer |
| https://compta.firm.fr/api | invoice.* | Erreur ✗ | Il y a 2h (500) | Éditer \| Tester \| Désactiver \| Supprimer |

**Création / édition d'un webhook**
- URL de destination (HTTPS uniquement)
- Sélection des événements (checkboxes groupées par catégorie) :

  **Factures émises**
  - `invoice.created` — brouillon créé
  - `invoice.validated` — facture validée
  - `invoice.sent` — transmise à la PDP
  - `invoice.acknowledged` — accusée de réception PDP
  - `invoice.rejected` — rejetée par la PDP
  - `invoice.paid` — facture soldée

  **Factures reçues**
  - `received_invoice.received` — nouvelle facture reçue
  - `received_invoice.approved` — facture validée
  - `received_invoice.contested` — facture contestée

  **Paiements**
  - `payment.recorded` — paiement enregistré

  **E-reporting**
  - `ereporting_batch.submitted` — batch soumis DGFiP
  - `ereporting_batch.accepted` — batch accepté
  - `ereporting_batch.rejected` — batch rejeté

- Secret de signature (généré automatiquement, HMAC-SHA256)
  - Affiché à la création uniquement
  - Documentation inline : "Vérifiez la signature X-Webhook-Signature sur chaque appel"
- Retry policy : 3 tentatives (immédiat, +5min, +1h)
- Timeout : 30 secondes par appel

**Bouton "Tester le webhook"**
- Envoi d'un événement fictif `webhook.test` à l'URL configurée
- Affichage de la réponse (statut HTTP + body) dans une modale

**Historique des livraisons (par webhook)**

Modale ou page dédiée :

| Date | Événement | Statut | HTTP | Durée | Actions |
|---|---|---|---|---|---|
| 15/03 14:32 | invoice.paid | ✓ Livré | 200 | 145ms | Voir payload |
| 15/03 14:32 | invoice.paid | ✗ Échoué | 500 | 28s | Rejouer |

- Affichage du payload JSON envoyé (lecture seule, syntax highlighting)
- Bouton "Rejouer" : renvoi immédiat de l'événement
- Conservation 30 jours

#### Bloc — Documentation API

- Lien vers la documentation OpenAPI (Swagger UI, route `/api/docs`)
- Collection Postman téléchargeable
- Exemples de code (PHP, Python, JavaScript, cURL)
- Lien vers les guides d'intégration

#### Edge cases UX
- URL webhook HTTP (non HTTPS) : erreur bloquante
  "Les webhooks doivent utiliser HTTPS"
- URL webhook pointant vers une IP privée (127.x, 192.168.x) :
  erreur bloquante (protection SSRF)
- Webhook avec taux d'erreur > 50% sur 24h :
  badge "Dégradé" + suggestion de désactivation temporaire
- Clé API expirée : badge "Expirée" + bouton "Renouveler"
  (crée une nouvelle clé, l'ancienne est invalidée)
- Plus de 10 clés API actives :
  warning "Vous avez beaucoup de clés actives. Révoquez les clés inutilisées."

### Composants UI
- Tableaux clés API et webhooks (Turbo Frames)
- Modale création clé avec affichage unique (Bootstrap modal)
- Checkboxes groupées événements webhook (Stimulus `EventSelectorController`)
- Modale historique livraisons (syntax highlighting payload JSON)
- Badge statut webhook (`_webhook_status_badge.html.twig`)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `IntegrationSettingsController` | CRUD clés API et webhooks |
| `ApiKeyService::create()` | Génération clé, hachage, stockage |
| `ApiKeyService::revoke()` | Révocation + blacklist Redis |
| `WebhookService::create()` | Création endpoint + secret HMAC |
| `WebhookDeliveryService` | Envoi événements + retry policy |
| `WebhookTestService` | Envoi événement de test |
| `AuditLogger` | Toutes les actions clés/webhooks |

**Endpoints**
```
POST   /api/settings/api-keys
DELETE /api/settings/api-keys/{id}

POST   /api/settings/webhooks
PUT    /api/settings/webhooks/{id}
DELETE /api/settings/webhooks/{id}
POST   /api/settings/webhooks/{id}/test
GET    /api/settings/webhooks/{id}/deliveries
POST   /api/settings/webhooks/{id}/deliveries/{delivery_id}/replay
```

### Entités Doctrine

| Entité | Champs |
|---|---|
| `ApiKey` | `id`, `tenant_id`, `name`, `key_hash`, `key_prefix`, `permissions` (json), `environment`, `expires_at`, `last_used_at`, `created_by`, `revoked_at` |
| `WebhookEndpoint` | `id`, `tenant_id`, `url`, `events` (json), `secret_hash`, `active`, `failure_count`, `last_delivery_at`, `last_delivery_status` |
| `WebhookDelivery` | `id`, `endpoint_id`, `event_type`, `payload` (json), `status`, `http_status`, `response_body`, `duration_ms`, `delivered_at`, `attempts` |
| `AuditLog` | Traçabilité |

---

## Notes transversales — Section PARAMÈTRES

### Navigation commune
Un layout commun `settings_layout.html.twig` inclut :
- Sidebar gauche avec les 6 rubriques (icônes Tabler + libellés)
- Badge de statut PDP sur l'entrée "PDP / PPF" (vert/orange/rouge)
- Badge "!" sur "Séquences" si séquence non verrouillée et factures émises
- Breadcrumb "Paramètres > {rubrique}"
- Le layout est un Turbo Frame racine (`<turbo-frame id="settings-content">`)
  → navigation entre rubriques sans rechargement du sidebar

### Sécurité des paramètres
- Toutes les routes `/settings/*` nécessitent `IS_AUTHENTICATED_FULLY`
  (pas de remember_me accepté — re-authentification requise)
- Actions sensibles (suppression tenant, transfert propriété, rotation secrets) :
  confirmation du mot de passe systématique
- Toutes les modifications sont loguées dans `AuditLog`

### Cohérence inter-rubriques
| Modification | Impact sur d'autres pages |
|---|---|
| Logo changé | Regénération aperçus templates |
| Régime TVA changé | Périodicité e-reporting, affichage `/tax` |
| PDP credentials changés | Transmissions en cours peuvent échouer |
| Séquence format changé (si non verrouillée) | Prévisualisation `/invoices/new` |
| Clé API révoquée | Invalidation immédiate Redis |
| Webhook désactivé | Arrêt des livraisons sans affecter les factures |
