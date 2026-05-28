# Section PAIEMENTS — Suivi & enregistrement

Gestion des encaissements (paiements reçus sur factures émises) et des
décaissements (paiements effectués sur factures reçues).  
Cette section est également centrale pour le **e-reporting paiement** :
depuis le 1er septembre 2026, les données de paiement sur les transactions
B2C et internationales doivent être transmises à la DGFiP.

---

## Sommaire

| Page | URL | Rôles |
|---|---|---|
| Liste des paiements | `/payments` | Tous |
| Enregistrer un paiement | `/invoices/{id}/payment` | ACCOUNTANT+ |

---

## 1. `/payments` — Liste des paiements enregistrés

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Boutons d'enregistrement masqués pour `VIEWER`.

### Objectif
Centraliser tous les mouvements de trésorerie liés aux factures (encaissements
clients et décaissements fournisseurs) avec réconciliation et suivi des retards.

### Features / fonctionnalités

#### Barre d'outils supérieure
- CTA "Enregistrer un paiement" → modale de sélection de facture puis
  redirect `/invoices/{id}/payment` (ACCOUNTANT+)
- Bouton "Exporter" → CSV, Excel, ou FEC (filtres actifs appliqués)
- Compteur : "X encaissements · Y décaissements · Z en attente"

#### Tableau de bord paiements (en haut de page)

**4 KPI cards** (période sélectionnable : mois / trimestre / année)

| KPI | Calcul |
|---|---|
| Encaissements du mois | Somme paiements reçus (factures émises) sur la période |
| Décaissements du mois | Somme paiements effectués (factures reçues) sur la période |
| En attente d'encaissement | Factures émises `ACKNOWLEDGED` non payées (toutes périodes) |
| Retards de paiement | Factures émises `ACKNOWLEDGED` dont l'échéance est dépassée |

Chaque card avec variation vs période précédente (% + flèche).

#### Filtres & recherche
- **Recherche** : numéro de facture, nom du tiers, référence paiement
- **Filtre sens** : Encaissement (client) / Décaissement (fournisseur) / Tous
- **Filtre mode** : Virement / Chèque / Carte / Prélèvement / Espèces / Autre
- **Filtre période** : date de paiement (du…au) ou raccourcis
- **Filtre statut facture** : `ACKNOWLEDGED` / `PAID`
- **Filtre tiers** : autocomplétion Contact
- Persistance en query string

#### Tableau principal

| Colonne | Détail | Triable |
|---|---|---|
| Date paiement | JJ/MM/AAAA | ✓ |
| Tiers | Nom client ou fournisseur | ✓ |
| N° facture | Lien vers la facture | ✓ |
| Sens | Badge Encaissement (vert) / Décaissement (rouge) | ✓ |
| Montant payé | Formaté avec devise | ✓ |
| Mode de paiement | Icône + libellé | ✓ |
| Référence | N° virement, chèque… | ✗ |
| Paiement partiel | Badge "Partiel" si montant < TTC facture | ✗ |
| Enregistré par | Prénom Nom utilisateur | ✗ |
| Actions | Voir facture \| Modifier \| Supprimer | ✗ |

**Notes sur les actions**
- Modifier un paiement : uniquement si la facture est encore `ACKNOWLEDGED`
  (pas encore entièrement soldée). ACCOUNTANT+.
- Supprimer un paiement : uniquement si la facture repasse en `ACKNOWLEDGED`
  après suppression. Confirmation modale obligatoire. ACCOUNTANT+.
  `AuditLog` : `payment.deleted` avec payload complet.

#### Section — Factures en attente de paiement (onglet dédié)

Liste des factures émises `ACKNOWLEDGED` non entièrement soldées,
triées par ancienneté d'échéance (les plus urgentes en premier) :

| Colonne | Détail |
|---|---|
| N° facture | Lien |
| Client | Nom |
| Date échéance | Colorée selon retard |
| Montant TTC | Total facture |
| Déjà encaissé | Somme paiements partiels |
| Reste à encaisser | Différence |
| Retard | "X jours" en rouge si échéance dépassée |
| Action | "Enregistrer paiement" |

- Badge retard sur les lignes en souffrance
- Relance email possible depuis cette liste (ACCOUNTANT+) :
  - Bouton "Relancer" par ligne
  - Modale prévisualisation email de relance (personnalisable)
  - Historique des relances envoyées (date + objet)

#### Section — Rapprochement bancaire (fonctionnalité avancée)

> Optionnel — affiché uniquement si activé dans les paramètres

- Import d'un relevé bancaire CSV/OFX/QIF
- Algorithme de matching automatique :
  - Correspondance montant exact + proximité de date (±5 jours)
  - Correspondance référence (si numéro de facture dans le libellé virement)
- Résultat :
  - Lignes "Rapprochées automatiquement" (suggestion à confirmer)
  - Lignes "Non rapprochées" (à associer manuellement)
- Validation en masse des rapprochements suggérés
- Ce flux est purement interne — n'interagit pas avec la PDP

#### Edge cases UX
- Paiement partiel :
  - Facture reste `ACKNOWLEDGED` tant que le solde restant > 0
  - Facture passe `PAID` quand solde restant ≤ 0 (avec tolérance 0,01€ pour les arrondis)
  - Badge "Partiellement payée" sur la facture
- Paiement en devise étrangère :
  - Champ taux de change (EUR si devise = EUR tenant)
  - Montant converti affiché (indicatif)
- Suppression d'un paiement qui soldait la facture :
  - Facture repasse automatiquement en `ACKNOWLEDGED`
  - Avertissement "La facture {ref} repassera en statut 'Reçue & acceptée'"
- Double saisie accidentelle (même montant, même date, même facture) :
  - Détection automatique + warning "Un paiement similaire a déjà été enregistré
    le {date}. Confirmez-vous l'ajout d'un second paiement ?"
- Aucun paiement enregistré : illustration + CTA "Enregistrer votre premier paiement"
- Export FEC volumineux : traitement async + notification email

### Composants UI
- KPI cards (composant Twig `_kpi_card.html.twig` réutilisé)
- Tabs "Tous les paiements / En attente" (Turbo Frame)
- Tableau avec Turbo Frame `<turbo-frame id="payments-list">`
- Composant Stimulus `FilterController` (réutilisé)
- Modale email de relance (prévisualisation + envoi)
- Section rapprochement bancaire (Vue 3 `<BankReconciliation>`, lazy-loaded)
- Badge "Partiel" (composant Twig)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `PaymentController::index()` | Listing paginé avec filtres + KPIs |
| `PaymentRepository::findByTenantWithFilters()` | Requête filtrée |
| `PaymentStatsService` | Calcul KPIs (encaissements, décaissements, retards) |
| `PendingPaymentService` | Liste factures en attente + calcul retards |
| `RelanceEmailService` | Génération + envoi email de relance |
| `BankReconciliationService` | Import relevé + algorithme matching |
| `PaymentExportService` | Export CSV/Excel/FEC |
| `EReportingPaymentService` | Marquage paiements B2C pour e-reporting |

### Entités Doctrine
`Payment`, `Invoice`, `ReceivedInvoice`, `Contact`, `RelanceEmail`

---

## 2. `/invoices/{id}/payment` — Enregistrer un paiement

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`

**Accessible uniquement si :**
- Facture émise : statut = `ACKNOWLEDGED` (et solde restant > 0)
- Facture reçue : statut = `APPROVED` (et solde restant > 0)

Toute tentative sur un statut invalide → redirect vers la facture avec flash warning.

### Objectif
Enregistrer un encaissement (facture émise) ou un décaissement (facture reçue),
avec gestion des paiements partiels et des paiements en plusieurs fois.

### Features / fonctionnalités

#### En-tête du formulaire
- Titre "Enregistrer un paiement" + sens (Encaissement / Décaissement)
- Récapitulatif de la facture :
  - N° facture | Tiers | Date émission | Date échéance
  - Montant TTC total
  - Paiements déjà enregistrés (liste si partiels existants)
  - **Reste à payer** (en grand, coloré rouge si échéance dépassée)
- Breadcrumb : Factures → N° facture → Enregistrer un paiement

#### Formulaire principal

**Champ — Date de paiement** (requis)
- Date picker, défaut : aujourd'hui
- Validation : ne peut pas être antérieure à la date d'émission de la facture
- Ne peut pas être dans le futur (un paiement enregistre un fait passé ou présent)

**Champ — Montant payé** (requis)
- Numérique, décimales autorisées
- Défaut : montant restant dû (totalité si premier paiement, solde sinon)
- Indicateur temps réel :
  - Si montant < reste dû : badge "Paiement partiel — il restera {X}€ à encaisser"
  - Si montant = reste dû : badge vert "Solde la facture"
  - Si montant > reste dû : erreur "Le montant dépasse le solde restant dû ({X}€)"
- Champ devise (lecture seule, héritée de la facture)

**Champ — Mode de paiement** (requis)

| Valeur | Icône | Label |
|---|---|---|
| `VIREMENT` | ti-building-bank | Virement bancaire |
| `CHEQUE` | ti-writing | Chèque |
| `CARTE` | ti-credit-card | Carte bancaire |
| `PRELEVEMENT` | ti-refresh | Prélèvement automatique |
| `ESPECES` | ti-cash | Espèces |
| `COMPENSATION` | ti-arrows-exchange | Compensation / avoir |
| `AUTRE` | ti-dots | Autre |

**Champ — Référence du paiement** (optionnel mais recommandé)
- Numéro de virement, numéro de chèque, référence transaction CB…
- Tooltip : "La référence facilite le rapprochement bancaire"

**Champ — Notes** (optionnel)
- Commentaire interne sur ce paiement

#### Bloc — E-reporting paiement (conditionnel)

> Affiché uniquement si la facture est de type B2C ou internationale
> (hors périmètre e-invoicing obligatoire mais soumise à e-reporting paiement)

Bandeau informatif :
"Cette facture est soumise à l'**e-reporting paiement** DGFiP.
Les données de ce paiement seront incluses dans le prochain batch e-reporting."

- Champ **Date de valeur** (peut différer de la date de paiement comptable)
- Champ **Moyen de paiement normalisé** (liste DGFiP) :

| Code DGFiP | Libellé |
|---|---|
| `30` | Virement |
| `31` | Prélèvement |
| `40` | Carte bancaire |
| `50` | Chèque |
| `60` | Espèces |
| `70` | Compensation |
| `99` | Autre |

- Confirmation "Ces données seront transmises à la DGFiP dans le cadre de l'e-reporting"

#### Bloc — Paiements précédents (si paiements partiels existants)
Affiché si au moins un paiement a déjà été enregistré sur cette facture :

| Date | Montant | Mode | Référence | Enregistré par |
|---|---|---|---|---|
| 01/04/2026 | 500,00 € | Virement | VIR-2026-042 | Marie D. |

- Bouton "Annuler ce paiement" par ligne (avec confirmation modale)

#### Actions
- Bouton principal "Enregistrer le paiement" (POST)
- Bouton "Annuler" → retour `/invoices/{id}`

#### Post-enregistrement

**Si paiement solde la facture (montant = solde restant)**
1. Création `Payment` en base
2. Statut `Invoice` → `PAID`
3. `AuditLog` : `payment.recorded`, `invoice.paid`
4. `NotificationService` : notification "Facture {ref} soldée"
5. Si facture B2C/internationale : marquage pour e-reporting paiement
6. Redirect `/invoices/{id}` avec flash success "Paiement enregistré — facture soldée ✓"
7. Turbo Stream : mise à jour badge statut facture (Mercure)

**Si paiement partiel (montant < solde restant)**
1. Création `Payment` en base
2. Statut `Invoice` reste `ACKNOWLEDGED`
3. `AuditLog` : `payment.partial_recorded`
4. Redirect `/invoices/{id}` avec flash info
   "Paiement partiel enregistré — reste {X}€ à encaisser"

**E-reporting paiement (async)**
```
Si facture B2C ou internationale :
  EReportingPaymentMessage dispatché → Messenger
    → EReportingPaymentHandler
        → Ajout dans EReportingBatch courant (période = mois du paiement)
        → Statut batch mis à jour si complet
```

#### Edge cases UX
- Montant saisi > solde restant :
  - Erreur temps réel (Stimulus computed) + blocage soumission
  - Message "Le montant ({X}€) dépasse le solde restant ({Y}€)"
- Date de paiement dans le futur :
  - Erreur bloquante "La date de paiement ne peut pas être dans le futur"
- Mode paiement ESPECES + montant > 1 000€ :
  - Avertissement non bloquant "Les paiements en espèces > 1 000€ sont
    réglementés (art. L.112-6 CMF). Assurez-vous d'être en conformité."
- Réseau indisponible pendant la soumission :
  - Détection via fetch error + message "Connexion perdue —
    votre paiement n'a pas été enregistré. Réessayez."
  - Pas de double soumission (bouton désactivé après premier clic, token CSRF)
- Double soumission accidentelle (même montant, même date) :
  - Détection + modale "Un paiement identique a été enregistré il y a moins d'une minute.
    Confirmez-vous l'ajout d'un second paiement ?"
- Facture en devise étrangère :
  - Champ "Taux de change EUR/{devise}" (saisi manuellement ou récupéré BCE)
  - Montant en EUR calculé automatiquement (enregistré dans `Payment.amount_eur`)
- Paiement enregistré par erreur :
  - Bouton "Annuler ce paiement" visible sur `/invoices/{id}` onglet Paiements
  - Délai d'annulation sans restriction : ACCOUNTANT+ peut toujours annuler
    un paiement si la facture n'est pas clôturée comptablement

### Composants UI
- Formulaire Symfony `PaymentType` rendu Twig (simple, pas de Vue 3)
- Composant Stimulus `PaymentAmountController` :
  - Calcul temps réel solde restant
  - Indicateur "Paiement partiel / Solde la facture / Dépasse le solde"
  - Validation montant max
- Composant Stimulus `DateValidatorController` (date ≤ aujourd'hui)
- Section e-reporting conditionnelle (Stimulus `ConditionalSectionController`)
- Tableau paiements précédents (Twig, chargement synchrone)
- Toast confirmation post-enregistrement

### Appels API / services Symfony

| Service | Action |
|---|---|
| `PaymentController::new()` | Affichage formulaire + données facture |
| `PaymentController::create()` | POST enregistrement paiement |
| `PaymentService::record()` | Logique métier : création Payment, maj statut Invoice |
| `InvoiceRepository::findWithPayments()` | Facture + paiements existants |
| `EReportingPaymentService::markForReporting()` | Marquage e-reporting si B2C/international |
| `AuditLogger` | `payment.recorded`, `payment.partial_recorded`, `invoice.paid` |
| `NotificationService` | Notification solde facture |
| `MercurePublisher` | Mise à jour statut facture temps réel |
| `CurrencyService` | Taux de change BCE si devise étrangère |

**Endpoint**
```
POST /invoices/{id}/payment
Body : {
  date: "YYYY-MM-DD",
  amount: decimal,
  mode: enum,
  reference: string|null,
  notes: string|null,
  ereporting_date_valeur: "YYYY-MM-DD"|null,
  ereporting_mode: string|null,
  exchange_rate: decimal|null
}
→ 302 redirect /invoices/{id}
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `Payment` | `id`, `tenant_id`, `invoice_id`, `received_invoice_id`, `direction` (ENCAISSEMENT\|DECAISSEMENT), `date`, `amount`, `amount_eur`, `currency`, `exchange_rate`, `mode`, `mode_dgfip_code`, `reference`, `notes`, `ereporting_date_valeur`, `ereporting_batch_id`, `recorded_by`, `created_at` |
| `Invoice` | Mise à jour statut → `PAID` si soldée |
| `ReceivedInvoice` | Mise à jour statut → `PAID` si soldée (facture reçue) |
| `EReportingBatch` | Ajout du paiement au batch courant si B2C/international |
| `AuditLog` | Traçabilité complète |

---

## Notes transversales — Section PAIEMENTS

### Lien avec l'e-reporting paiement
Depuis le 1er septembre 2026, les données de paiement sur les transactions
**hors périmètre e-invoicing** (B2C, international) doivent être déclarées à la DGFiP :

```
Transactions concernées par l'e-reporting paiement :
  - Ventes B2C (particuliers français)
  - Ventes à des entreprises étrangères non assujetties en France
  - Ventes intracommunautaires non soumises à facturation électronique obligatoire

Données à transmettre par paiement :
  - Montant encaissé HT par taux de TVA
  - Date de paiement (ou date de valeur)
  - Moyen de paiement (code DGFiP normalisé)
  - Référence de la facture associée
```

Le `EReportingPaymentService` agrège ces données dans le `EReportingBatch`
du mois correspondant. La transmission DGFiP est gérée depuis `/e-reporting`.

### Gestion des paiements partiels
- Une facture peut avoir N paiements partiels
- `Invoice.status` reste `ACKNOWLEDGED` tant que `Σ Payment.amount < Invoice.total_ttc`
- La bascule `ACKNOWLEDGED → PAID` est atomique (transaction DB) et déclenchée
  uniquement quand `solde_restant ≤ 0.01€` (tolérance arrondi)
- Chaque paiement partiel est un `Payment` indépendant

### Sécurité double soumission
Chaque formulaire de paiement génère un **idempotency key** (UUID v4, stocké
en session, durée de vie 5 minutes). Le serveur vérifie l'unicité de cette clé
avant INSERT. En cas de retry réseau, la même clé retourne le `Payment` existant
sans créer de doublon.
