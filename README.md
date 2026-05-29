# Facturation Électronique SaaS — Documentation fonctionnelle

Application SaaS multi-tenant de facturation électronique conforme à la réforme française 2026-2027.

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.3 / Symfony 7 |
| Frontend | Twig + Hotwire Turbo + Stimulus + Vue 3 |
| CSS | Bootstrap 5 |
| ORM | Doctrine + TenantFilter |
| Queue | Symfony Messenger + Redis |
| Stockage | S3 / MinIO |
| Auth | Symfony Security + LexikJWT |
| Build | Webpack Encore / npm |

## Conformité réglementaire

- **1er septembre 2026** : réception obligatoire des factures électroniques
- **1er septembre 2027** : émission obligatoire des factures électroniques
- Formats supportés : **Factur-X**, **UBL 2.1**, **CII D16B**
- Connexion **PDP** (Plateforme de Dématérialisation Partenaire) et **PPF**
- **E-invoicing** B2B + **E-reporting** B2C / international
- Archivage légal **10 ans** — piste d'audit fiable (art. L.102 B LPF)

## Structure de la documentation

```
docs/
├── foundations/          # Fondations transversales (rôles, cycle de vie, entités)
├── auth/                 # Pages d'authentification
├── onboarding/           # Parcours d'onboarding
├── dashboard/            # Tableau de bord
├── invoices/             # Factures émises
├── received-invoices/    # Factures reçues
├── contacts/             # Clients & fournisseurs
├── products/             # Catalogue produits/services
├── payments/             # Paiements
├── tax/                  # TVA & comptabilité
├── e-reporting/          # E-reporting DGFiP
├── settings/             # Paramètres
├── notifications/        # Centre de notifications
└── admin/                # Admin SaaS (super-admin)
```

## Rôles intra-tenant

| Permission | OWNER | ADMIN | ACCOUNTANT | VIEWER |
|---|:---:|:---:|:---:|:---:|
| Gérer l'organisation & facturation | ✓ | – | – | – |
| Gérer utilisateurs & paramètres | ✓ | ✓ | ✗ | ✗ |
| Créer / émettre des factures | ✓ | ✓ | ✓ | ✗ |
| Valider factures reçues | ✓ | ✓ | ✓ | ✗ |
| Exporter FEC / comptabilité | ✓ | ✓ | ✓ | ✗ |
| Consulter en lecture seule | ✓ | ✓ | ✓ | ✓ |
| Gérer clés API & webhooks | ✓ | ✓ | ✗ | ✗ |

## Cycle de vie d'une facture

```
DRAFT → VALIDATED → SENT → ACKNOWLEDGED → PAID
                       ↘ REJECTED
        ↘ CANCELLED
```

## Progression de la documentation

- [x] Fondations transversales
- [x] AUTH
- [x] ONBOARDING
- [x] DASHBOARD
- [x] FACTURES ÉMISES
- [x] FACTURES REÇUES
- [x] CLIENTS & FOURNISSEURS
- [x] CATALOGUE
- [x] PAIEMENTS
- [x] TVA & COMPTABILITÉ
- [x] E-REPORTING
- [x] PARAMÈTRES
- [x] NOTIFICATIONS
- [x] ADMIN SAAS

## Modélisation Doctrine

Modèle de données en PHP 8.4 / Doctrine ORM, sous `src/Entity/`.

### Principes de modélisation
- **Aucun ManyToMany** : toute relation N-N est matérialisée par une entité de
  liaison explicite (ManyToOne + OneToMany des deux côtés), pour garder le
  contrôle sur la table de jointure (ex : `TenantMembership` pour User↔Tenant).
- **Identifiants UUID** (`Symfony\Component\Uid\Uuid`) sur toutes les entités.
- **TenantAwareTrait** : mutualise la relation `tenant_id` (ManyToOne vers Tenant)
  sur toutes les entités métier. Le `TenantFilter` Doctrine applique
  automatiquement l'isolation multi-tenant.
- **Embeddables** : `Address`, `Money`, `PdpConfig` (objets valeur).
- **Enums PHP natifs** (backed enums) pour tous les statuts et types.
- **Soft delete** (champ `deletedAt`) pour les entités à conserver légalement.
- **AuditLog immuable** : INSERT only, jamais UPDATE/DELETE.

### Organisation des fichiers
```
src/Entity/
├── Enum/            # 26 enums (Role, InvoiceStatus, PaymentMode…)
├── Embeddable/      # Address, Money, PdpConfig
├── Trait/           # TenantAwareTrait, TimestampableTrait
└── *.php            # Entités
```

### Avancement de la modélisation
- [x] Enums (26)
- [x] Embeddables (Address, Money, PdpConfig)
- [x] Traits (TenantAware, Timestampable)
- [x] Infrastructure tenant (Tenant, User, TenantMembership, TenantInvitation, EmailVerificationToken)
- [x] Contacts (Contact, ContactPerson, ContactDocument)
- [x] Catalogue (Product, ProductPriceHistory)
- [x] Factures émises (Invoice, InvoiceLine, InvoiceStatusHistory, InvoiceSequence, InvoiceTemplate)
- [x] Factures reçues (ReceivedInvoice, ReceivedInvoiceLine)
- [x] Transmissions (PdpTransmission, PdpWebhookLog)
- [x] Paiements (Payment, RelanceEmail)
- [x] TVA & exports (TaxAdjustment, ExportJob)
- [x] E-reporting (EReportingBatch, EReportingTransaction, EReportingPaymentLine, EReportingCorrection)
- [x] Notifications (Notification, NotificationPreference)
- [x] Intégrations (ApiKey, WebhookEndpoint, WebhookDelivery)
- [x] Traçabilité (AuditLog, SuperAdminLog)

### Récapitulatif du modèle

**34 entités** + **26 enums** + **3 embeddables** + **2 traits**.

Relations : **42 ManyToOne** / **17 OneToMany**, **aucun ManyToMany**
(toutes les liaisons N-N passent par une entité de liaison explicite).

Entités de liaison explicites (remplacent les ManyToMany) :
- `TenantMembership` : User ↔ Tenant (porte le rôle)

Tables de jointure contrôlées via ManyToOne/OneToMany pour les relations
multiples : lignes de facture, historiques, transmissions, lots e-reporting,
livraisons webhook, etc.

Points de conformité réglementaire intégrés au modèle :
- Numérotation séquentielle sans trou (`InvoiceSequence`, lock + verrouillage)
- Immuabilité des factures (snapshot client, `#[ORM\Version]`, archivage WORM)
- Piste d'audit fiable (`AuditLog` INSERT only, hash SHA-256 des fichiers)
- Idempotence des webhooks PDP (`PdpWebhookLog.eventId` unique)
- E-reporting transaction + paiement (TVA sur encaissement)
- Conservation légale (soft delete + horodatage)

## Migrations Doctrine

Fichier unique : `migrations/Version20260901000000.php`

Crée les **34 tables** dans l'ordre correct (contraintes FK respectées).
Résout la dépendance circulaire `payments ↔ ereporting_payment_lines`
en ajoutant la FK `payment_id` via `ALTER TABLE` après la création des deux tables.

Pour appliquer : `php bin/console doctrine:migrations:migrate`

## Repositories

**34 repositories** dans `src/Repository/`, un par entité.
Chaque méthode est commentée avec son rôle, son contexte d'utilisation
et les pages/services qui l'appellent.

### Méthodes notables par domaine

| Repository | Méthodes clés |
|---|---|
| `InvoiceRepository` | `findByFilters()`, `getKpis()`, `getTvaStats()`, `findForFec()`, `findForEreporting()`, `getMonthlyRevenue()` |
| `ReceivedInvoiceRepository` | `existsByExternalPdpId()` (idempotence), `findPendingTechnicalAck()` (réforme 2026) |
| `PdpTransmissionRepository` | `getSuccessStats()`, `findPending()`, `findAllTenants()` |
| `EReportingBatchRepository` | `findByPeriod()`, `findLate()`, `findDueSoon()` |
| `PaymentRepository` | `findPendingEreporting()`, `findByIdempotencyKey()`, `sumIncoming()` |
| `InvoiceSequenceRepository` | `lockForUpdate()` (lock pessimiste, numérotation sans trou) |
| `NotificationRepository` | `countUnread()`, `markAllAsRead()`, `findForDigest()` |
| `AuditLogRepository` | `findByEntity()` (piste d'audit fiable), `findAllTenants()` |
| `WebhookEndpointRepository` | `findActiveForEvent()` (requête native JSON), `findToDeactivate()` |
| `TenantRepository` | `findAllWithStats()`, `findStuckOnboarding()`, `countByPlan()` |

## Structure Symfony 7

### Installation rapide

```bash
# 1. Cloner et démarrer l'infrastructure Docker
make install

# 2. Copier et adapter la configuration locale
cp .env.local.example .env.local
# Éditez .env.local avec vos valeurs

# 3. Appliquer la migration initiale
make db-migrate

# 4. Compiler les assets
make assets-install && make assets-dev
```

### Commandes utiles

| Commande | Description |
|---|---|
| `make start` | Démarrer les containers |
| `make db-migrate` | Appliquer les migrations |
| `make cache-clear` | Vider le cache Symfony |
| `make workers` | Lancer les workers Messenger |
| `make assets-watch` | Watcher les assets (dev) |
| `make test` | Lancer PHPUnit |
| `make lint` | Vérifier la syntaxe |

### Services Docker

| Service | URL |
|---|---|
| Application | http://localhost:8000 |
| Mailpit (emails) | http://localhost:8025 |
| MinIO console | http://localhost:9001 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

### Architecture des fichiers de configuration

```
config/
├── bundles.php              # Déclaration des 20 bundles
├── services.yaml            # DI, paramètres, bindings
├── routes.yaml              # Chargement des routes
├── packages/
│   ├── doctrine.yaml        # ORM + TenantFilter
│   ├── doctrine_migrations.yaml
│   ├── framework.yaml       # Cache, session, HTTP client, rate-limiter
│   ├── messenger.yaml       # 6 files Redis + routage de 20 messages
│   ├── security.yaml        # 3 firewalls + ACL + 2FA
│   ├── twig.yaml
│   ├── monolog.yaml         # Canaux audit/pdp/ereporting dédiés
│   ├── lexik_jwt_authentication.yaml
│   ├── scheb_two_factor.yaml
│   ├── snc_redis.yaml
│   ├── nelmio_cors.yaml
│   ├── webpack_encore.yaml
│   ├── vich_uploader.yaml
│   ├── dev/                 # Surcharges développement
│   ├── prod/                # Surcharges production (cache, logs)
│   └── test/                # Surcharges test
src/
├── Kernel.php
├── Doctrine/Filter/TenantFilter.php   # Isolation multi-tenant automatique
├── Security/
│   ├── TenantContext.php              # Contexte tenant de la requête
│   ├── Authenticator/AppAuthenticator.php
│   └── Authenticator/ApiKeyAuthenticator.php
├── EventSubscriber/
│   ├── TenantFilterSubscriber.php     # Active TenantFilter à chaque requête
│   └── OnboardingSubscriber.php      # Redirige si onboarding incomplet
└── EventListener/
    ├── DoctrineTimestampListener.php  # Auto-fill createdAt/updatedAt
    └── AuditLogListener.php          # Journal d'audit automatique
```

### Stack technique complète

- **PHP 8.4** / **Symfony 7** (FrameworkBundle, Security, Messenger, Mailer…)
- **Doctrine ORM 3** avec TenantFilter multi-tenant
- **PostgreSQL 16** (UUID natif, JSON, transactions)
- **Redis 7** (sessions, cache, Messenger streams)
- **S3 / MinIO** (stockage fichiers : PDF, XML, exports)
- **Bootstrap 5** + **Hotwire Turbo** + **Stimulus** + **Vue 3**
- **Webpack Encore** (assets compilés)
- **LexikJWT** (API stateless)
- **scheb/2fa-bundle** (TOTP Google Authenticator)
- **Docker** (dev) + **Nginx** + **PHP-FPM**
