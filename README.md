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
- [ ] NOTIFICATIONS
- [ ] ADMIN SAAS
