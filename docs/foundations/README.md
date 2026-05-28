# Fondations transversales

Ce document est la référence unique pour les concepts qui s'appliquent à **toutes** les pages de l'application. Chaque spec de page y renvoie sans répéter ces définitions.

---

## 1. Modèle multi-tenant

### Stratégie d'isolation

- **Colonne `tenant_id`** sur chaque entité métier (UUID, FK vers `Tenant`)
- **Doctrine Filter global** `TenantFilter` activé automatiquement sur toutes les requêtes
- Le filtre est injecté via `TenantContext` (service `RequestStack`-aware) résolu depuis le JWT
- Aucune requête cross-tenant n'est possible depuis le code applicatif (le filtre est `FORCE_PARTIAL`)
- Les entités sans `tenant_id` sont : `Tenant`, `User` (partiel), `SuperAdminLog`

### TenantContext (service Symfony)

```php
// Injecté partout via autowiring
interface TenantContextInterface
{
    public function getTenant(): Tenant;
    public function getTenantId(): Uuid;
}
```

---

## 2. Rôles & permissions

### Rôles Symfony Security

```
ROLE_SUPER_ADMIN      → accès /admin/* uniquement, hors tenant
ROLE_OWNER            → toutes permissions intra-tenant
ROLE_ADMIN            → toutes permissions sauf transfert de propriété
ROLE_ACCOUNTANT       → factures + exports, pas de gestion utilisateurs
ROLE_VIEWER           → lecture seule sur toutes les ressources du tenant
```

### Hiérarchie (security.yaml)

```yaml
role_hierarchy:
    ROLE_OWNER:      [ROLE_ADMIN]
    ROLE_ADMIN:      [ROLE_ACCOUNTANT]
    ROLE_ACCOUNTANT: [ROLE_VIEWER]
```

### Matrice détaillée

| Permission | OWNER | ADMIN | ACCOUNTANT | VIEWER |
|---|:---:|:---:|:---:|:---:|
| Transférer la propriété de l'organisation | ✓ | ✗ | ✗ | ✗ |
| Gérer le plan & la facturation SaaS | ✓ | ✗ | ✗ | ✗ |
| Gérer utilisateurs & invitations | ✓ | ✓ | ✗ | ✗ |
| Configurer PDP / PPF | ✓ | ✓ | ✗ | ✗ |
| Gérer clés API & webhooks | ✓ | ✓ | ✗ | ✗ |
| Créer / éditer / supprimer brouillons | ✓ | ✓ | ✓ | ✗ |
| Valider & émettre des factures | ✓ | ✓ | ✓ | ✗ |
| Valider factures reçues | ✓ | ✓ | ✓ | ✗ |
| Enregistrer des paiements | ✓ | ✓ | ✓ | ✗ |
| Exporter FEC / CSV / XML | ✓ | ✓ | ✓ | ✗ |
| Gérer contacts & catalogue | ✓ | ✓ | ✓ | ✗ |
| Consulter en lecture seule | ✓ | ✓ | ✓ | ✓ |

> **Règle d'or** : un `ADMIN` ne peut pas rétrograder un `OWNER`. Seul l'`OWNER` peut transférer sa propriété.

---

## 3. Cycle de vie des factures

### Statuts (conformes au cycle DGFiP)

```
DRAFT
  │
  ├─[validate]──► VALIDATED
  │                   │
  │               [submit to PDP/PPF]
  │                   │
  ├─[cancel]──►  CANCELLED
  │               SENT
  │                   │
  │           ┌───────┴────────┐
  │           │                │
  │     [ack received]   [rejected by PDP]
  │           │                │
  │      ACKNOWLEDGED      REJECTED
  │           │
  │     [payment recorded]
  │           │
  │          PAID
```

### Détail des transitions

| De | Vers | Déclencheur | Acteur |
|---|---|---|---|
| `DRAFT` | `VALIDATED` | Clic "Valider" + contrôles conformité | ACCOUNTANT+ |
| `DRAFT` | `CANCELLED` | Clic "Annuler brouillon" | ACCOUNTANT+ |
| `VALIDATED` | `SENT` | Transmission PDP/PPF (async Messenger) | Système |
| `SENT` | `ACKNOWLEDGED` | Webhook PDP / polling accusé | Système |
| `SENT` | `REJECTED` | Webhook PDP / motif de rejet DGFiP | Système |
| `REJECTED` | `VALIDATED` | Correction + renvoi | ACCOUNTANT+ |
| `ACKNOWLEDGED` | `PAID` | Enregistrement paiement | ACCOUNTANT+ |
| `VALIDATED`/`SENT` | `CANCELLED` | Émission avoir + annulation | ACCOUNTANT+ |

### Règles d'immutabilité

- Une facture `SENT` ou au-delà **ne peut plus être éditée** — seul un avoir (`CreditNote`) est possible
- Le numéro de séquence est **définitivement alloué** à la transition `DRAFT → VALIDATED`
- Tout changement de statut produit une entrée `InvoiceStatusHistory` + `AuditLog`

---

## 4. Entités Doctrine transversales

### Entités d'infrastructure tenant

```php
Tenant
├── id: Uuid
├── slug: string              // subdomain ou identifiant URL
├── name: string
├── siret: string
├── tva_intra: string|null
├── logo_s3_key: string|null
├── plan: enum(FREE|PRO|ENTERPRISE)
├── pdp_config: json          // voir PdpConfig VO
├── invoice_sequence_prefix: string
├── invoice_sequence_next: int
├── created_at: DateTimeImmutable
└── settings: json            // préférences générales

User
├── id: Uuid
├── email: string
├── password: string          // Argon2id
├── totp_secret: string|null  // 2FA
├── locale: string            // fr|en
├── created_at: DateTimeImmutable
└── memberships: Collection<TenantMembership>

TenantMembership
├── id: Uuid
├── user_id: Uuid (FK User)
├── tenant_id: Uuid (FK Tenant)
├── role: enum(OWNER|ADMIN|ACCOUNTANT|VIEWER)
├── invited_at: DateTimeImmutable
└── joined_at: DateTimeImmutable|null
```

### Entités de traçabilité

```php
AuditLog                      // immuable — INSERT ONLY
├── id: Uuid
├── tenant_id: Uuid
├── user_id: Uuid|null        // null = action système
├── action: string            // ex: invoice.validated
├── entity_type: string       // ex: Invoice
├── entity_id: Uuid
├── payload_before: json|null
├── payload_after: json|null
├── ip_address: string
└── created_at: DateTimeImmutable

Notification
├── id: Uuid
├── tenant_id: Uuid
├── user_id: Uuid|null        // null = toute l'équipe
├── type: enum(INVOICE_REJECTED|PDP_ERROR|PAYMENT_DUE|...)
├── payload: json
├── read_at: DateTimeImmutable|null
└── created_at: DateTimeImmutable
```

### Entités métier principales

```php
Invoice
├── id: Uuid
├── tenant_id: Uuid
├── number: string|null       // null tant que DRAFT
├── status: enum(DRAFT|VALIDATED|SENT|ACKNOWLEDGED|REJECTED|PAID|CANCELLED)
├── type: enum(INVOICE|CREDIT_NOTE|PROFORMA)
├── format: enum(FACTURX|UBL|CII)
├── contact_id: Uuid (FK Contact)
├── lines: Collection<InvoiceLine>
├── total_ht: Money
├── total_tva: Money
├── total_ttc: Money
├── currency: string          // ISO 4217
├── issue_date: DateTimeImmutable
├── due_date: DateTimeImmutable|null
├── s3_key: string|null       // PDF/XML archivé
├── pdp_transmission_id: Uuid|null
├── credit_note_for: Uuid|null // FK Invoice (avoir)
├── notes: string|null
├── created_at: DateTimeImmutable
└── updated_at: DateTimeImmutable

Contact
├── id: Uuid
├── tenant_id: Uuid
├── type: enum(CLIENT|SUPPLIER|BOTH)
├── name: string
├── siret: string|null
├── tva_intra: string|null
├── pdp_identifier: string|null  // identifiant PDP du destinataire
├── address: Address (embeddable)
├── email: string|null
├── payment_terms: int|null   // jours
└── created_at: DateTimeImmutable

Product
├── id: Uuid
├── tenant_id: Uuid
├── reference: string
├── label: string
├── description: string|null
├── unit_price: Money
├── tva_rate: decimal         // 0.00 / 5.50 / 10.00 / 20.00
├── unit: string              // U, H, KG, M²…
└── active: bool

PdpTransmission
├── id: Uuid
├── tenant_id: Uuid
├── invoice_id: Uuid (FK Invoice)
├── pdp_name: string          // choruspro|sovos|generix|ppf
├── external_id: string|null  // ID côté PDP
├── status: enum(PENDING|SENT|ACKNOWLEDGED|REJECTED|ERROR)
├── sent_at: DateTimeImmutable|null
├── ack_at: DateTimeImmutable|null
├── reject_reason: string|null
├── raw_response: json|null
└── created_at: DateTimeImmutable

EReportingBatch
├── id: Uuid
├── tenant_id: Uuid
├── period: string            // YYYY-MM
├── type: enum(B2C|INTERNATIONAL|PAYMENT)
├── status: enum(DRAFT|SUBMITTED|ACCEPTED|REJECTED)
├── dgfip_ref: string|null
├── submitted_at: DateTimeImmutable|null
└── invoices: Collection<Invoice>
```

---

## 5. Services Symfony transversaux

| Service | Responsabilité |
|---|---|
| `TenantContext` | Résolution du tenant courant depuis JWT / session |
| `InvoiceNumberingService` | Allocation séquentielle thread-safe (pessimistic lock) |
| `PdpDispatcher` | Abstraction envoi vers PDP/PPF (strategy pattern) |
| `FormatConverter` | Génération Factur-X / UBL / CII depuis Invoice |
| `ArchiveService` | Upload signé S3/MinIO + calcul hash SHA-256 |
| `AuditLogger` | INSERT AuditLog (ne jamais appeler UPDATE/DELETE) |
| `NotificationService` | Création + dispatch notifications (Mercure SSE) |
| `EReportingAggregator` | Agrégation transactions B2C/international pour DGFiP |

---

## 6. Conventions transversales

### Authentification

- **Session** : cookie `PHPSESSID` pour les pages Twig
- **API** : Bearer JWT (LexikJWT, expiry 1h, refresh 7j)
- **2FA** : TOTP optionnel (paramètre par tenant)
- Toute route hors `/login`, `/register`, `/forgot-password`, `/reset-password` requiert `IS_AUTHENTICATED_FULLY`

### Gestion des erreurs UX

Chaque page doit gérer :
- **État vide** : illustration + CTA contextuel
- **Erreur réseau** : toast + retry automatique (Turbo)
- **Timeout PDP** : badge `PENDING` + message explicatif
- **Accès refusé** : redirect `/dashboard` + flash warning

### Archivage & piste d'audit fiable

- Tout document fiscal archivé sur S3/MinIO avec :
  - Hash SHA-256 stocké en DB
  - Signature temporelle (timestamp TSA)
  - Bucket policy `WORM` (Write Once Read Many)
  - Rétention minimum 10 ans (art. L.102 B LPF)
- `AuditLog` : INSERT ONLY, jamais de UPDATE/DELETE, index sur `(tenant_id, entity_type, entity_id)`

---

*Dernière mise à jour : voir git log*
