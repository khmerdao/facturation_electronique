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
- [ ] TVA & exports (TaxAdjustment, ExportJob)
- [ ] E-reporting (EReportingBatch, EReportingTransaction, EReportingPaymentLine, EReportingCorrection)
- [ ] Notifications (Notification, NotificationPreference)
- [ ] Intégrations (ApiKey, WebhookEndpoint, WebhookDelivery)
- [ ] Traçabilité (AuditLog, SuperAdminLog)
