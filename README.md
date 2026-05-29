# Facturation Électronique SaaS

Application **SaaS multi-tenant** de facturation électronique conforme à la réforme française obligatoire (ordonnance n°2021-1190).

---

## Conformité réglementaire

| Échéance | Obligation |
|---|---|
| **1er septembre 2026** | Réception obligatoire des factures électroniques |
| **1er septembre 2027** | Émission obligatoire des factures électroniques |

- Formats supportés : **Factur-X**, **UBL 2.1**, **CII D16B**
- Connexion **PDP** (Plateforme de Dématérialisation Partenaire) et **PPF** (Portail Public de Facturation / Chorus Pro)
- **E-invoicing** B2B + **E-reporting** B2C / international (DGFiP)
- Archivage légal **10 ans** — piste d'audit fiable (art. L.102 B LPF)
- Acquittement technique des factures reçues (délai 5 jours ouvrés)

---

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.4 / Symfony 7 |
| Base de données | MySQL 8.0+ / MariaDB 10.6+ |
| Cache / Queue | Redis 7 |
| Frontend | Twig + Hotwire Turbo + Stimulus + Vue 3 |
| CSS | Bootstrap 5 |
| ORM | Doctrine ORM 3 + TenantFilter |
| Queue | Symfony Messenger (6 files Redis) |
| Stockage fichiers | S3 / MinIO (PDF, XML, exports, templates) |
| Auth | Symfony Security + LexikJWT + scheb/2fa-bundle |
| Build | Webpack Encore / npm |
| Conteneurisation | Docker (PHP-FPM 8.4, Nginx, MySQL 8, Redis 7, MinIO, Mailpit) |

---

## Démarrage rapide

### Prérequis

- Docker & Docker Compose v2
- Make
- PHP 8.4 (pour les commandes locales optionnelles)

### Installation

```bash
# 1. Cloner le dépôt
git clone https://github.com/khmerdao/facturation_electronique.git
cd facturation_electronique

# 2. Configurer l'environnement local
cp .env.local.example .env.local
# Éditez .env.local si nécessaire (les valeurs par défaut fonctionnent avec Docker)

# 3. Lancer l'infrastructure + installer les dépendances
make install
# → docker compose up -d --build
# → composer install
# → génération des clés JWT
# → création BDD + migration

# 4. Charger les fixtures de développement
make db-fixtures

# 5. Compiler les assets
make assets-install && make assets-watch
```

### Commandes Makefile

| Commande | Description |
|---|---|
| `make start` | Démarrer les containers Docker |
| `make stop` | Arrêter les containers |
| `make db-migrate` | Appliquer les migrations Doctrine |
| `make db-reset` | Recréer la BDD + charger les fixtures |
| `make cache-clear` | Vider le cache Symfony |
| `make workers` | Lancer tous les workers Messenger |
| `make assets-watch` | Watcher les assets en développement |
| `make assets-build` | Compiler les assets pour la production |
| `make jwt-keys` | Régénérer les clés JWT (openssl) |
| `make lint` | Vérifier la syntaxe PHP, Twig, YAML |
| `make test` | Lancer la suite PHPUnit |
| `make sh` | Shell dans le container PHP |
| `make psql` | Client MySQL interactif |

### Services disponibles

| Service | URL |
|---|---|
| Application web | http://localhost:8000 |
| Mailpit (emails de test) | http://localhost:8025 |
| MinIO console (stockage S3) | http://localhost:9001 |
| MySQL | localhost:3306 |
| Redis | localhost:6379 |

---

## Architecture

### Multi-tenant

Chaque organisation dispose d'un espace **complètement isolé**. L'isolation repose sur :

- **`TenantFilter`** Doctrine — filtre SQL automatique `WHERE tenant_id = ?` sur toutes les requêtes ORM sans modification des repositories
- **`TenantContext`** — stocke le tenant actif pour toute la durée de la requête HTTP
- **`TenantFilterSubscriber`** — active le filtre après authentification (priorité `kernel.request`)
- Toutes les entités métier portent **`TenantAwareTrait`** (ManyToOne vers `Tenant`)

### Rôles intra-tenant

| Permission | OWNER | ADMIN | ACCOUNTANT | VIEWER |
|---|:---:|:---:|:---:|:---:|
| Gérer l'organisation & PDP | ✓ | — | — | — |
| Gérer utilisateurs & rôles | ✓ | ✓ | — | — |
| Créer / émettre des factures | ✓ | ✓ | ✓ | — |
| Valider des factures reçues | ✓ | ✓ | ✓ | — |
| Enregistrer des paiements | ✓ | ✓ | ✓ | — |
| Exporter FEC / comptabilité | ✓ | ✓ | ✓ | — |
| Gérer clés API & webhooks | ✓ | ✓ | — | — |
| Consulter en lecture seule | ✓ | ✓ | ✓ | ✓ |

Hiérarchie : `OWNER → ADMIN → ACCOUNTANT → VIEWER`

### Cycle de vie d'une facture (statuts DGFiP)

```
DRAFT ──▶ VALIDATED ──▶ SENT ──▶ ACKNOWLEDGED ──▶ PARTIALLY_PAID ──▶ PAID
                           │            │
                           ▼            ▼
                        REJECTED    CONTESTED
              ▼
           CANCELLED
```

---

## Structure du projet

```
facturation_electronique/
├── assets/                        # Sources frontend
│   ├── controllers/               # Stimulus controllers
│   ├── styles/app.scss            # Bootstrap 5 + variables tenant
│   └── app.js                     # Turbo + Stimulus + Flatpickr + TomSelect
├── config/
│   ├── bundles.php                # 20 bundles déclarés
│   ├── services.yaml              # DI, paramètres S3/JWT/Redis, AWS S3Client
│   ├── routes.yaml                # Chargement routes par attribut #[Route]
│   └── packages/
│       ├── doctrine.yaml          # ORM MySQL + TenantFilter
│       ├── framework.yaml         # Cache Redis, sessions, HTTP clients, rate-limiter, lock
│       ├── messenger.yaml         # 6 files Redis + routage 22 messages
│       ├── security.yaml          # 3 firewalls + ACL + role_hierarchy
│       ├── monolog.yaml           # Canaux dédiés audit / pdp / ereporting
│       ├── lexik_jwt_authentication.yaml
│       ├── scheb_two_factor.yaml  # TOTP + backup codes + trusted devices
│       ├── snc_redis.yaml
│       ├── nelmio_cors.yaml
│       ├── webpack_encore.yaml
│       ├── vich_uploader.yaml
│       ├── dev/                   # Surcharges développement
│       ├── prod/                  # Surcharges production (cache ORM, logs JSON)
│       └── test/                  # Surcharges test
├── docker/
│   ├── php/Dockerfile             # PHP 8.4-fpm Alpine + pdo_mysql + sodium + redis
│   ├── php/php.ini
│   └── nginx/default.conf
├── docs/                          # Documentation fonctionnelle (37 pages)
│   ├── foundations/
│   ├── auth/ onboarding/ dashboard/ invoices/ received-invoices/
│   ├── contacts/ products/ payments/ tax/ e-reporting/
│   ├── settings/ notifications/ admin/
├── migrations/
│   └── Version20260901000000.php  # Migration initiale MySQL — 34 tables
├── src/
│   ├── Controller/                # 14 sous-dossiers (Auth, Invoice, Dashboard…)
│   ├── Doctrine/Filter/
│   │   └── TenantFilter.php       # Filtre SQL multi-tenant automatique
│   ├── Entity/
│   │   ├── Enum/                  # 26 enums PHP natifs
│   │   ├── Embeddable/            # Address, Money, PdpConfig
│   │   ├── Trait/                 # TenantAwareTrait, TimestampableTrait
│   │   └── *.php                  # 34 entités
│   ├── EventListener/
│   │   ├── AuditLogListener.php   # Journal d'audit automatique (onFlush)
│   │   └── DoctrineTimestampListener.php
│   ├── EventSubscriber/
│   │   ├── TenantFilterSubscriber.php  # Active TenantFilter à chaque requête
│   │   └── OnboardingSubscriber.php    # Redirige si onboarding incomplet
│   ├── Messenger/
│   │   ├── Message/               # 22 types de messages asynchrones
│   │   ├── Handler/               # Handlers correspondants
│   │   └── Middleware/            # AuditLogMiddleware, TenantContextMiddleware
│   ├── Repository/                # 34 repositories avec méthodes métier
│   ├── Security/
│   │   ├── TenantContext.php      # Contexte tenant de la requête courante
│   │   ├── Authenticator/AppAuthenticator.php
│   │   ├── Authenticator/ApiKeyAuthenticator.php  # SHA-256, header X-Api-Key
│   │   └── Voter/                 # Voters par domaine métier
│   ├── Service/                   # Services métier (Invoice, PDP, EReporting…)
│   └── Twig/Extension/            # Extensions Twig personnalisées
├── templates/
│   ├── base.html.twig             # Layout principal (sidebar + header + Turbo)
│   ├── partials/
│   │   ├── _sidebar.html.twig
│   │   └── _header.html.twig
│   ├── auth/login.html.twig
│   └── …/                         # Templates par section
├── .env                           # Variables d'environnement (MySQL, Redis, S3…)
├── .env.local.example             # Modèle de configuration locale
├── composer.json                  # Dépendances PHP
├── docker-compose.yml             # Stack Docker de développement
├── Makefile                       # 20 commandes de développement
├── package.json                   # Dépendances npm
└── webpack.config.js              # 3 entry points (app / admin / invoice_editor)
```

---

## Modèle de données

**34 entités** + **26 enums** + **3 embeddables** + **2 traits** — 0 ManyToMany.

### Entités par domaine

| Domaine | Entités |
|---|---|
| Infrastructure tenant | `Tenant`, `User`, `TenantMembership`, `TenantInvitation`, `EmailVerificationToken` |
| Contacts | `Contact`, `ContactPerson`, `ContactDocument` |
| Catalogue | `Product`, `ProductPriceHistory` |
| Factures émises | `Invoice`, `InvoiceLine`, `InvoiceStatusHistory`, `InvoiceSequence`, `InvoiceTemplate` |
| Factures reçues | `ReceivedInvoice`, `ReceivedInvoiceLine` |
| Transmissions PDP | `PdpTransmission`, `PdpWebhookLog` |
| Paiements | `Payment`, `RelanceEmail` |
| E-reporting | `EReportingBatch`, `EReportingTransaction`, `EReportingPaymentLine`, `EReportingCorrection` |
| TVA & exports | `TaxAdjustment`, `ExportJob` |
| Notifications | `Notification`, `NotificationPreference` |
| Intégrations | `ApiKey`, `WebhookEndpoint`, `WebhookDelivery` |
| Traçabilité | `AuditLog`, `SuperAdminLog` |

### Principes de modélisation

- **Aucun ManyToMany** — toute relation N-N passe par une entité de liaison explicite (`TenantMembership` pour User↔Tenant, etc.)
- **UUID** (`Symfony\Component\Uid\Uuid`) sur toutes les entités, stockés en `CHAR(36)` MySQL
- **`TenantAwareTrait`** — mutualise `tenant_id` + TenantFilter sur toutes les entités métier
- **`TimestampableTrait`** — `createdAt`/`updatedAt` auto-remplis par `DoctrineTimestampListener`
- **Embeddables** : `Address` (adresse postale), `Money` (montant + devise, `DECIMAL(14,4)`), `PdpConfig` (clé API chiffrée AES-256)
- **Enums PHP 8.1+** (backed enums) pour tous les statuts, types et codes
- **Soft delete** (`deletedAt`) pour les entités à conserver légalement
- **`AuditLog`** immuable : INSERT only, jamais UPDATE/DELETE, rétention 10 ans
- **`#[ORM\Version]`** sur `Invoice` — verrouillage optimiste contre les modifications concurrentes

### Points de conformité intégrés au modèle

- Numérotation séquentielle sans trou : `InvoiceSequence` + lock pessimiste Redis
- Immuabilité des factures validées : snapshot client copié, hash SHA-256 du fichier
- Piste d'audit fiable : `AuditLog` INSERT-only dans le même `flush()` que l'entité
- Idempotence des webhooks PDP : `PdpWebhookLog.eventId` unique par tenant
- E-reporting TVA sur encaissement : `EReportingPaymentLine` + `Payment.ereportingRequired`
- Acquittement technique obligatoire : `ReceivedInvoice.technicalAckSentAt`

---

## Queue asynchrone (Symfony Messenger)

6 files Redis avec routage dédié :

| File | Usage | Priorité |
|---|---|---|
| `pdp_urgent` | Transmissions PDP, acquittements techniques | Haute |
| `emails` | Notifications, relances, digests | Normale |
| `webhooks` | Livraison des webhooks sortants (retry x10) | Normale |
| `exports` | Génération PDF, FEC, ZIP (longue durée) | Basse |
| `async` | E-reporting, Sirene, tâches planifiées | Basse |
| `failed` | Dead-letter queue (messages épuisés) | — |

Backoff exponentiel sur tous les transports. Middleware `AuditLogMiddleware` + `TenantContextMiddleware` injectés sur le bus par défaut.

---

## Sécurité

### Firewalls

| Firewall | Pattern | Mécanisme |
|---|---|---|
| `dev` | `^/(_(profiler\|wdt)\|css\|js)/` | Pas de sécurité |
| `api` | `^/api` | JWT stateless + clé API (X-Api-Key SHA-256) |
| `admin` | `^/admin` | Form-login + 2FA TOTP, session séparée |
| `main` | `^/` | Form-login + 2FA TOTP + Remember-me 30j |

### 2FA (scheb/2fa-bundle)

- TOTP compatible Google Authenticator, Authy, etc.
- Codes de secours (backup codes)
- Appareil de confiance (cookie 30 jours)
- Activation optionnelle par utilisateur

### Rate limiting

- Login : 5 tentatives / minute (protection bruteforce)
- Webhooks PDP entrants : 100 requêtes / minute

---

## Documentation fonctionnelle

37 pages dans `docs/`, couvrant les 37 URLs de l'application :

| Section | Fichiers |
|---|---|
| Fondations | Rôles, cycle de vie, entités Doctrine, conformité |
| Auth (4 pages) | Login, register, mot de passe oublié, réinitialisation |
| Onboarding (2 pages) | Organisation (SIRET, PDP, logo), préférences |
| Dashboard | KPIs, graphiques CA, alertes, e-reporting en cours |
| Factures émises (6 pages) | Liste, création, détail, édition, duplication, avoir |
| Factures reçues (2 pages) | Liste, détail + validation + acquittement |
| Contacts (3 pages) | Liste unifiée, création, fiche détail |
| Catalogue (3 pages) | Liste, création, fiche produit |
| Paiements (2 pages) | Liste, enregistrement |
| TVA & exports (2 pages) | Tableau de bord TVA, export FEC/CSV/XML |
| E-reporting (1 page) | Statuts transmissions DGFiP, lots, corrections |
| Paramètres (6 pages) | Organisation, utilisateurs, templates, séquences, PDP, intégrations |
| Notifications (1 page) | Centre de notifications, préférences |
| Admin SaaS (3 pages) | Liste tenants, fiche tenant, logs super-admin |

---

## Variables d'environnement clés

| Variable | Description | Exemple |
|---|---|---|
| `DATABASE_URL` | Connexion MySQL | `mysql://user:pass@127.0.0.1:3306/db?serverVersion=8.0&charset=utf8mb4` |
| `REDIS_URL` | Redis (cache + sessions + Messenger) | `redis://localhost:6379` |
| `JWT_PASSPHRASE` | Passphrase des clés JWT | chaîne aléatoire |
| `ENCRYPTION_KEY` | Clé AES-256 (secrets PDP, clés API) | `openssl rand -base64 32` |
| `S3_ENDPOINT` | Endpoint S3 / MinIO | `http://localhost:9000` |
| `S3_BUCKET_INVOICES` | Bucket PDF + XML factures | `invoices` |
| `SIRENE_API_KEY` | Clé API INSEE (vérification SIRET) | token INSEE |
| `PPF_API_KEY` | Clé API Chorus Pro (PPF) | token Chorus |
| `MAILER_DSN` | SMTP | `smtp://localhost:1025` |

---

## Avancement du projet

- [x] Documentation fonctionnelle (37 pages, 14 sections)
- [x] Modélisation Doctrine (34 entités, 26 enums, 3 embeddables, 2 traits)
- [x] Migration MySQL initiale (34 tables, FK circulaire résolue)
- [x] 34 repositories avec méthodes métier commentées
- [x] Structure Symfony 7 (94 dossiers, 110+ fichiers PHP)
- [x] Configuration complète (15 packages YAML, 3 environnements)
- [x] Isolation multi-tenant (TenantFilter, TenantContext, TenantFilterSubscriber)
- [x] Sécurité (3 firewalls, 2FA TOTP, JWT, clé API SHA-256, rate-limiter)
- [x] Queue Messenger (6 files Redis, 22 messages routés, dead-letter)
- [x] Infrastructure Docker (MySQL 8, Redis 7, MinIO, Mailpit, Nginx)
- [x] Assets frontend (Bootstrap 5 SCSS, Turbo, Stimulus, Vue 3 prêt)
- [ ] Services métier (InvoiceNumberingService, PdpConfigEncryptor, EReportingAggregator…)
- [ ] Messages + Handlers Messenger
- [ ] Controllers (37 pages)
- [ ] Form Types + Voters Symfony
- [ ] Templates Twig (37 pages)
- [ ] Fixtures de développement
- [ ] Tests PHPUnit
