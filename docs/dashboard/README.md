# Section DASHBOARD — Tableau de bord

Page d'accueil post-login. Premier écran vu après l'onboarding et à chaque connexion.  
Agrège les données clés du tenant en temps réel via Turbo Streams + Mercure.

---

## `/dashboard` — Tableau de bord principal

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Le contenu affiché est identique pour tous les rôles — les CTAs d'action sont masqués pour `VIEWER`.

### Objectif
Donner une vision instantanée de la santé financière, des actions urgentes et de la conformité réforme électronique du tenant.

### Features / fonctionnalités

---

#### Bloc — Alertes & actions urgentes (priorité haute, top of page)

Affiché uniquement si des conditions critiques sont détectées. Disparaît quand les actions sont réalisées.

**Alertes réglementaires**
- `PDP_NOT_CONFIGURED` : bandeau rouge "Votre connexion PDP n'est pas configurée — la transmission de factures électroniques est impossible. [Configurer maintenant →]"
- `PDP_CONNECTION_ERROR` : bandeau orange "Votre PDP ne répond plus depuis X heures. [Vérifier →]"
- `DEADLINE_APPROACHING` : bandeau bleu informatif (affiché jusqu'au 1er sept 2026/2027) "La réception obligatoire des factures électroniques entre en vigueur dans X jours"

**Alertes métier**
- Factures reçues en attente de validation (> 0) : "X facture(s) reçue(s) à valider"
- Factures émises rejetées par la PDP (statut `REJECTED`) : "X facture(s) rejetée(s) — action requise"
- Factures en retard de paiement (échéance dépassée, statut `ACKNOWLEDGED`) : "X facture(s) en retard de paiement"
- Brouillons anciens (> 30 jours sans modification) : "X brouillon(s) inactif(s)"

---

#### Bloc — KPIs financiers (période courante)

Sélecteur de période en haut du bloc : **Mois courant** (défaut) / Trimestre / Année / Période personnalisée.  
Mise à jour Turbo Frame au changement de période (pas de rechargement page).

**4 métriques principales (cards)**

| Métrique | Détail |
|---|---|
| Chiffre d'affaires HT | Total factures émises `ACKNOWLEDGED` + `PAID` sur la période |
| Encaissements | Total paiements enregistrés sur la période |
| En attente de paiement | Total factures `ACKNOWLEDGED` non payées (toutes périodes) |
| TVA collectée | Total TVA sur factures `ACKNOWLEDGED` + `PAID` (période) |

Chaque card affiche :
- Valeur principale (montant formaté avec devise)
- Variation vs période précédente (% + flèche haut/bas colorée)
- Clic → page détail concernée (`/invoices`, `/payments`, `/tax`)

**Graphique — Évolution CA mensuel (12 derniers mois)**
- Graphique en barres (Vue 3 + Chart.js)
- Deux séries : CA facturé (barres) + encaissements réels (ligne)
- Tooltip au survol avec détail du mois
- Clic sur une barre → filtre `/invoices` sur le mois correspondant

---

#### Bloc — Factures émises récentes

Liste des 5 dernières factures émises (toutes périodes).

Colonnes : Numéro | Client | Date d'émission | Montant TTC | Statut

**Badges de statut (conformes au cycle DGFiP)**

| Statut | Couleur | Label affiché |
|---|---|---|
| `DRAFT` | Gris | Brouillon |
| `VALIDATED` | Bleu | Validée |
| `SENT` | Bleu clair | Transmise PDP |
| `ACKNOWLEDGED` | Teal | Reçue & acceptée |
| `REJECTED` | Rouge | Rejetée |
| `PAID` | Vert | Payée |
| `CANCELLED` | Orange | Annulée |

- Lien "Voir toutes les factures →" → `/invoices`
- CTA "Nouvelle facture" (masqué pour `VIEWER`) → `/invoices/new`
- Mise à jour temps réel des statuts via **Turbo Streams + Mercure** (le badge change sans rechargement quand la PDP répond)

---

#### Bloc — Factures reçues en attente

Liste des factures reçues avec statut `PENDING_VALIDATION`.

Colonnes : Fournisseur | Date de réception | Montant TTC | Format | Action

- Bouton "Valider" par ligne → `/received-invoices/{id}` (masqué pour `VIEWER`)
- Compteur badge sur l'entrée de menu latéral
- Lien "Voir toutes →" → `/received-invoices`
- État vide : illustration + message "Aucune facture reçue en attente — tout est à jour ✓"

---

#### Bloc — Statut E-reporting & transmissions PDP

> Spécifique à la conformité réforme 2026-2027

**Transmissions PDP récentes**
- Indicateur global : `X transmissions réussies / Y en erreur / Z en attente` (période courante)
- Mini-tableau des 5 dernières transmissions avec statut
- Lien "Voir le détail →" → `/e-reporting`

**Prochain batch e-reporting**
- Date limite de dépôt du prochain batch B2C/international (J-X jours)
- Statut du batch en cours : `DRAFT` / `READY` / `SUBMITTED` / `ACCEPTED`
- CTA "Préparer le batch →" → `/e-reporting` (ACCOUNTANT+)

---

#### Bloc — Paiements récents

Liste des 5 derniers paiements enregistrés.

Colonnes : Facture | Client | Date | Montant | Mode

- Lien "Voir tous les paiements →" → `/payments`
- CTA "Enregistrer un paiement" → `/payments` (masqué pour `VIEWER`)

---

#### Bloc — Activité récente (fil d'audit)

5 dernières entrées `AuditLog` du tenant, affichées en timeline :
- Icône selon le type d'action (facture créée, paiement enregistré, facture rejetée…)
- Texte lisible : "Marie D. a validé la facture FAC-2026-0042 (1 250,00 €)"
- Timestamp relatif ("il y a 3 minutes")
- Lien vers l'entité concernée si applicable

---

#### Bloc — Raccourcis rapides (Quick actions)

Boutons d'action CTA visibles pour ACCOUNTANT+ :
- "Nouvelle facture" → `/invoices/new`
- "Nouveau client" → `/contacts/new`
- "Enregistrer un paiement" → `/payments`
- "Importer une facture" → (upload drag & drop inline, décrit dans `/received-invoices`)

---

#### Widget — Solde TVA estimé

Calcul indicatif (non officiel) :
- TVA collectée (période) − TVA déductible estimée
- Badge "Estimation indicative — consultez votre comptable"
- Lien → `/tax`

---

### États vides & edge cases UX

| Situation | Comportement |
|---|---|
| Tenant tout nouveau (aucune donnée) | Page d'accueil avec checklist onboarding (PDP configurée ?, première facture créée ?, premier client ajouté ?) + illustrations |
| Aucune facture sur la période sélectionnée | KPI cards à zéro, graphique vide avec message "Aucune facture sur cette période" |
| PDP déconnectée | Bannière rouge persistante + badge d'erreur sur le bloc transmissions |
| Mercure SSE indisponible | Fallback polling toutes les 30s (Stimulus `PollingController`), badge "Mise à jour auto indisponible" |
| Données en cours de calcul (agrégations lourdes) | Skeleton loaders sur chaque card (Bootstrap placeholder) |
| Erreur chargement graphique | Placeholder grisé + bouton "Réessayer" |
| Facture rejetée PDP | Notification push + badge rouge sur l'entrée de menu + alerte dashboard |

---

### Composants UI

- **Layout** : grille Bootstrap 12 colonnes, responsive (mobile : cards empilées)
- **Cards KPI** : composant Twig réutilisable `_kpi_card.html.twig` (valeur, variation, icône, lien)
- **Graphique CA** : composant Vue 3 `<CaChart>` (Chart.js, lazy-loaded)
- **Tableaux mini-listes** : Turbo Frames individuels (chaque bloc = 1 frame, rechargeable indépendamment)
- **Badges statut** : composant Twig `_invoice_status_badge.html.twig` (réutilisé sur toutes les pages)
- **Skeleton loaders** : Bootstrap `placeholder` sur les cards pendant le chargement
- **Sélecteur de période** : composant Stimulus `PeriodSelectorController` + Turbo Frame
- **Bannières d'alerte** : composant Twig `_alert_banner.html.twig` avec niveaux (danger/warning/info)
- **Timeline activité** : liste Twig avec icônes Tabler

---

### Appels API / services Symfony

| Service | Action |
|---|---|
| `DashboardController::index()` | Orchestration, résolution période, dispatch queries |
| `DashboardStatsService` | Calcul KPIs (CA HT, encaissements, en attente, TVA) |
| `InvoiceRepository::findRecentByTenant()` | 5 dernières factures émises |
| `ReceivedInvoiceRepository::findPendingValidation()` | Factures reçues en attente |
| `PdpTransmissionRepository::findRecentByTenant()` | 5 dernières transmissions |
| `PaymentRepository::findRecentByTenant()` | 5 derniers paiements |
| `AuditLogRepository::findRecentByTenant()` | 5 dernières entrées audit |
| `EReportingBatchRepository::findCurrentBatch()` | Batch e-reporting en cours |
| `AlertDetectionService` | Détection des conditions d'alerte (PDP, rejets, retards) |
| `MercurePublisher` | Push Turbo Streams sur changements de statut facture |
| `TaxEstimationService` | Calcul indicatif solde TVA |

**Turbo Frame endpoints (rechargement partiel)**

```
GET /dashboard/frames/kpis?period={month|quarter|year|custom}&from={date}&to={date}
GET /dashboard/frames/recent-invoices
GET /dashboard/frames/pending-received
GET /dashboard/frames/pdp-status
GET /dashboard/frames/recent-payments
GET /dashboard/frames/audit-feed
```

**Mercure topics (SSE)**

```
Topic : /tenants/{tenant_id}/invoices          → mise à jour statut facture
Topic : /tenants/{tenant_id}/pdp-transmissions → statut transmission PDP
Topic : /tenants/{tenant_id}/notifications     → nouvelles notifications
```

---

### Entités Doctrine

| Entité | Usage |
|---|---|
| `Invoice` | KPIs, liste récente, badges statut |
| `ReceivedInvoice` | Bloc factures reçues en attente |
| `Payment` | KPI encaissements, liste récente |
| `PdpTransmission` | Bloc statut transmissions |
| `EReportingBatch` | Widget prochain batch |
| `AuditLog` | Fil d'activité récente |
| `Tenant` | Statut PDP, alertes réglementaires |
| `TaxEntry` | Widget solde TVA estimé |

---

### Performance & cache

- **Cache Redis** sur les agrégats KPI : TTL 5 minutes (tag `dashboard_{tenant_id}`)
- Invalidation du cache à chaque changement de statut `Invoice` (EventListener Doctrine)
- Les Turbo Frames se chargent en **parallèle** (lazy frames avec `loading="lazy"`)
- Le graphique Vue 3 est **lazy-loaded** (Webpack chunk séparé, chargé après le fold)
- Requêtes DB optimisées : index sur `(tenant_id, status, issue_date)` pour les agrégats

---

### Dépendances inter-pages

| Élément du dashboard | Page cible |
|---|---|
| Clic badge statut facture | `/invoices/{id}` |
| CTA "Nouvelle facture" | `/invoices/new` |
| Clic ligne facture reçue | `/received-invoices/{id}` |
| Bloc transmissions PDP | `/e-reporting` |
| Widget TVA | `/tax` |
| Bannière PDP non configurée | `/settings/pdp` |
| Clic entrée audit | Entité concernée |
