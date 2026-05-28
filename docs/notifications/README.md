# Section NOTIFICATIONS — Centre de notifications

## Section ADMIN SAAS — Super-administration

---

# PARTIE 1 — NOTIFICATIONS

## `/notifications` — Centre de notifications

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`  
Chaque utilisateur ne voit que ses propres notifications
(filtrées sur `Notification.user_id = current_user` ou `user_id IS NULL`
pour les notifications d'équipe).

### Objectif
Centraliser toutes les alertes, événements et messages système destinés
à l'utilisateur courant, avec gestion de la lecture et des préférences
de notification.

### Features / fonctionnalités

#### En-tête de page
- Titre "Notifications" + compteur badge non lues (ex : "12 non lues")
- Bouton "Tout marquer comme lu" (mark all read)
- Bouton "Paramètres de notification" → anchor `#preferences` bas de page
- Filtre rapide : Toutes / Non lues / Par type

#### Liste des notifications

**Structure d'une notification**

Chaque notification affiche :
- **Icône** selon le type (Tabler icon, colorée par sévérité)
- **Titre** court et lisible
- **Description** détaillée (1-2 lignes)
- **Timestamp** relatif ("il y a 5 minutes") + absolu au survol (tooltip)
- **Lien d'action** contextuel (bouton ou lien texte)
- **Indicateur non lue** (point bleu à gauche, disparaît au clic)
- **Bouton "Ignorer"** (suppression soft, masque de la liste)

Clic sur une notification → marque comme lue + navigation vers l'entité concernée.

**Catalogue complet des types de notifications**

*Factures émises*

| Type | Icône | Sévérité | Titre | Action |
|---|---|---|---|---|
| `INVOICE_SENT` | ti-send | Info | "Facture {ref} transmise à la PDP" | Voir la facture |
| `INVOICE_ACKNOWLEDGED` | ti-circle-check | Succès | "Facture {ref} acceptée par {client}" | Voir la facture |
| `INVOICE_REJECTED` | ti-alert-circle | Danger | "Facture {ref} rejetée — action requise" | Corriger |
| `INVOICE_PAID` | ti-cash | Succès | "Facture {ref} soldée ({montant}€)" | Voir la facture |
| `INVOICE_OVERDUE` | ti-clock-exclamation | Warning | "Facture {ref} en retard ({X} jours)" | Relancer |
| `INVOICE_DUE_SOON` | ti-clock | Warning | "Facture {ref} échéance dans {X} jours" | Voir la facture |

*Factures reçues*

| Type | Icône | Sévérité | Titre | Action |
|---|---|---|---|---|
| `RECEIVED_INVOICE_NEW` | ti-file-invoice | Info | "Nouvelle facture reçue de {fournisseur}" | Valider |
| `RECEIVED_INVOICE_PARSE_ERROR` | ti-file-x | Danger | "Erreur de lecture — facture de {fournisseur}" | Voir l'erreur |
| `RECEIVED_INVOICE_DUPLICATE` | ti-copy | Warning | "Doublon possible — facture de {fournisseur}" | Vérifier |

*PDP & transmissions*

| Type | Icône | Sévérité | Titre | Action |
|---|---|---|---|---|
| `PDP_CONNECTION_ERROR` | ti-plug-x | Danger | "PDP hors ligne depuis {durée}" | Configurer |
| `PDP_CONNECTION_RESTORED` | ti-plug | Succès | "PDP reconnectée — transmissions reprises" | Voir |
| `PDP_TRANSMISSION_RETRY` | ti-refresh | Warning | "Retry transmission — facture {ref}" | Voir |
| `PDP_CERTIFICATE_EXPIRING` | ti-certificate | Warning | "Certificat PDP expire dans {X} jours" | Renouveler |

*E-reporting*

| Type | Icône | Sévérité | Titre | Action |
|---|---|---|---|---|
| `EREPORTING_BATCH_DUE` | ti-calendar-event | Warning | "Batch e-reporting {mois} à soumettre avant le {date}" | Soumettre |
| `EREPORTING_BATCH_ACCEPTED` | ti-circle-check | Succès | "Batch e-reporting {mois} accepté par la DGFiP" | Voir |
| `EREPORTING_BATCH_REJECTED` | ti-alert-triangle | Danger | "Batch e-reporting {mois} rejeté — corrections requises" | Corriger |
| `EREPORTING_BATCH_LATE` | ti-alarm | Danger | "Batch e-reporting {mois} hors délai" | Soumettre |

*Paiements*

| Type | Icône | Sévérité | Titre | Action |
|---|---|---|---|---|
| `PAYMENT_RECORDED` | ti-receipt | Info | "Paiement enregistré — facture {ref}" | Voir |
| `PAYMENT_EXPORT_READY` | ti-download | Info | "Export FEC prêt au téléchargement" | Télécharger |

*Équipe & organisation*

| Type | Icône | Sévérité | Titre | Action |
|---|---|---|---|---|
| `MEMBER_JOINED` | ti-user-plus | Info | "{Nom} a rejoint l'organisation" | Voir membres |
| `MEMBER_REVOKED` | ti-user-minus | Warning | "Accès de {Nom} révoqué" | Voir membres |
| `ROLE_CHANGED` | ti-user-edit | Info | "Votre rôle a été modifié : {ancien} → {nouveau}" | — |
| `PLAN_LIMIT_APPROACHING` | ti-chart-bar | Warning | "Limite du plan atteinte à {X}% — {ressource}" | Upgrader |
| `PLAN_LIMIT_REACHED` | ti-ban | Danger | "Limite du plan atteinte — {ressource} bloquée" | Upgrader |

*Système*

| Type | Icône | Sévérité | Titre | Action |
|---|---|---|---|---|
| `EXPORT_READY` | ti-download | Info | "Export {type} prêt" | Télécharger |
| `IMPORT_COMPLETED` | ti-upload | Info | "Import terminé — {X} contacts importés" | Voir résultats |
| `IMPORT_FAILED` | ti-upload-x | Danger | "Import échoué — voir les erreurs" | Voir erreurs |
| `SIRENE_COMPANY_RADIEE` | ti-building-off | Warning | "Entreprise {nom} radiée (SIRET {siret})" | Voir contact |
| `ONBOARDING_COMPLETE` | ti-confetti | Info | "Configuration terminée — bienvenue !" | Dashboard |

#### Filtres & groupement

- **Tabs** : Toutes | Non lues | Factures | PDP | E-reporting | Équipe | Système
- **Groupement temporel** : Aujourd'hui / Cette semaine / Ce mois / Plus ancien
- **Recherche** : full-text dans les titres et descriptions (debounced)
- **Filtre sévérité** : Toutes / Info / Warning / Danger / Succès

#### Pagination & chargement
- Chargement initial : 20 notifications
- "Charger plus" (scroll infini ou bouton) : +20 par appel
- Turbo Frame pour le rechargement de la liste

#### Mise à jour temps réel
- Nouvelles notifications poussées via **Mercure SSE**
  (topic `/users/{user_id}/notifications`)
- Insertion en haut de liste avec animation (slide-down)
- Compteur badge dans la navbar mis à jour en temps réel
- Toast popup pour les notifications de sévérité `DANGER` :
  apparaît en bas à droite, dismiss automatique après 8s (ou manuel)

#### Actions groupées
- Checkbox multi-sélection
- "Marquer comme lues" (sélection)
- "Ignorer" / "Supprimer" (sélection)
- "Tout marquer comme lu" (toutes les notifications)

#### Bloc — Préférences de notification (section bas de page, anchor #preferences)

**Configuration par type** (tableau avec toggles)

Pour chaque type de notification, l'utilisateur configure :
- **In-app** (dans l'application) : toggle on/off
- **Email** : toggle on/off
- **Délai email** : Immédiat / Digest quotidien / Digest hebdomadaire

Groupes de configuration :

| Groupe | Types inclus |
|---|---|
| Factures émises | INVOICE_SENT, ACKNOWLEDGED, REJECTED, PAID, OVERDUE, DUE_SOON |
| Factures reçues | RECEIVED_INVOICE_NEW, PARSE_ERROR, DUPLICATE |
| PDP & transmissions | PDP_CONNECTION_ERROR, RESTORED, RETRY, CERTIFICATE_EXPIRING |
| E-reporting | BATCH_DUE, ACCEPTED, REJECTED, LATE |
| Paiements | PAYMENT_RECORDED, EXPORT_READY |
| Équipe | MEMBER_JOINED, REVOKED, ROLE_CHANGED |
| Limites plan | PLAN_LIMIT_APPROACHING, REACHED |

**Paramètres globaux**
- Heure du digest quotidien (si activé) : select heure (défaut 08h00)
- Email de destination (défaut : email du compte, modifiable)
- "Pause des notifications" : toggle avec durée (1h / 4h / 24h / indéfini)
  → toutes les notifications in-app et email suspendues pendant la durée

#### Edge cases UX
- Aucune notification : illustration + message "Tout est calme — aucune notification"
- Notifications de sévérité DANGER non lues depuis > 24h :
  badge rouge clignotant dans la navbar (animation CSS subtile)
- Email de notification bounced (retour erreur SMTP) :
  notification in-app "Votre email de notification est invalide. [Mettre à jour →]"
- Mercure SSE déconnecté :
  badge discret "Mise à jour auto suspendue" + reconnexion automatique avec backoff
- Notification avec lien vers entité supprimée (ex : facture brouillon supprimée) :
  affichage du message mais lien grisé "Entité non disponible"

### Composants UI
- Liste notifications (Twig + Turbo Frame `<turbo-frame id="notifications-list">`)
- Tabs filtres (Stimulus `NotificationFilterController`)
- Checkbox multi-sélection (Stimulus `BulkSelectController` réutilisé)
- Toast Mercure (Stimulus `MercureToastController`)
- Tableau préférences (Stimulus `NotificationPreferenceController`)
- Scroll infini ou bouton "Charger plus" (Stimulus `InfiniteScrollController`)
- Badge compteur navbar (Turbo Stream mise à jour)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `NotificationController::index()` | Listing + compteurs |
| `NotificationRepository::findByUser()` | Notifications filtrées paginées |
| `NotificationService::markAsRead()` | Marquer lue(s) |
| `NotificationService::dismiss()` | Ignorer (soft delete) |
| `NotificationService::markAllAsRead()` | Tout marquer lu |
| `NotificationPreferenceService` | Lecture + mise à jour préférences |
| `MercurePublisher` | Topic `/users/{user_id}/notifications` |
| `DigestEmailService` | Envoi digest quotidien/hebdomadaire (Messenger scheduled) |

**Endpoints**
```
GET  /notifications                              → page principale
GET  /notifications?tab={type}&unread={bool}    → filtrée

POST /api/notifications/{id}/read               → marquer lue
POST /api/notifications/read-all                → tout marquer lu
POST /api/notifications/{id}/dismiss            → ignorer
POST /api/notifications/dismiss-selected        → ignorer sélection

GET  /api/notifications/count                   → { unread: int } (polling fallback)

PUT  /api/notifications/preferences             → mise à jour préférences
```

### Entités Doctrine

| Entité | Champs |
|---|---|
| `Notification` | `id`, `tenant_id`, `user_id` (null = toute l'équipe), `type`, `severity` (INFO\|WARNING\|DANGER\|SUCCESS), `title`, `description`, `action_url`, `action_label`, `payload` (json), `read_at`, `dismissed_at`, `created_at` |
| `NotificationPreference` | `id`, `user_id`, `tenant_id`, `notification_type`, `in_app_enabled`, `email_enabled`, `email_digest` (IMMEDIATE\|DAILY\|WEEKLY) |

---

# PARTIE 2 — ADMIN SAAS

Pages réservées au super-administrateur de la plateforme SaaS.  
Accessibles uniquement avec `ROLE_SUPER_ADMIN`.  
Hors contexte tenant — pas de `TenantFilter` actif sur ces pages.  
URL préfixée `/admin/*`, firewall séparé dans `security.yaml`.

---

## Sommaire Admin

| Page | URL | Rôles |
|---|---|---|
| Liste des tenants | `/admin/tenants` | SUPER_ADMIN |
| Détail tenant | `/admin/tenants/{id}` | SUPER_ADMIN |
| Logs système | `/admin/logs` | SUPER_ADMIN |

---

## 1. `/admin/tenants` — Liste des tenants

### Rôles autorisés
`ROLE_SUPER_ADMIN` uniquement.

### Objectif
Superviser l'ensemble des organisations (tenants) de la plateforme SaaS,
monitorer leur activité, détecter les anomalies et intervenir si nécessaire.

### Features / fonctionnalités

#### Tableau de bord global (KPIs plateforme)

**Cards métriques globales**

| KPI | Calcul |
|---|---|
| Tenants actifs | COUNT tenants avec facture < 30 jours |
| Nouveaux ce mois | COUNT tenants créés ce mois |
| Factures émises (mois) | SUM toutes factures `SENT`+ toutes tenants |
| Volume total TTC (mois) | SUM montants toutes factures `ACKNOWLEDGED`+ |
| Transmissions PDP en erreur | COUNT `PdpTransmission.status = ERROR` actives |
| Batches e-reporting en retard | COUNT `EReportingBatch.status = LATE` |

**Graphiques**
- Courbe : nouveaux tenants par mois (12 mois)
- Barres : factures émises par mois (toutes tenants confondues)

#### Filtres & recherche
- Recherche : nom organisation, SIRET, email OWNER, slug
- Filtre plan : FREE / PRO / ENTERPRISE
- Filtre statut : Actif / Suspendu / En onboarding / Supprimé (soft)
- Filtre PDP : configurée / non configurée / en erreur
- Filtre activité : actif derniers 7j / 30j / 90j / inactif

#### Tableau des tenants

| Colonne | Détail | Triable |
|---|---|---|
| Organisation | Nom + slug | ✓ |
| SIRET | | ✓ |
| Plan | Badge FREE/PRO/ENTERPRISE | ✓ |
| OWNER | Email + nom | ✓ |
| Membres | Nb utilisateurs actifs | ✓ |
| Créé le | Date inscription | ✓ |
| Dernière activité | Dernière facture émise | ✓ |
| Factures (mois) | COUNT factures ce mois | ✓ |
| Statut PDP | Badge connexion PDP | ✗ |
| Statut | Actif / Suspendu / Onboarding | ✓ |
| Actions | Voir \| Suspendre \| Impersonate | ✗ |

**Action — Impersonate (connexion en tant que)**
- Bouton "Se connecter en tant que…" (avec icône distincte)
- Modale confirmation : "Vous allez accéder à l'organisation {nom}
  en tant que super-admin. Toutes vos actions seront loguées."
- Crée une session impersonate avec `ROLE_PREVIOUS_ADMIN` (SwitchUser Symfony)
- Bannière persistante dans l'application : "Mode Super-Admin — vous naviguez
  dans {organisation}. [Quitter →]"
- `SuperAdminLog` : `impersonation.started` + `impersonation.ended`

**Action — Suspendre un tenant**
- Modale avec motif obligatoire (select + texte libre)
- Post-suspension :
  - `Tenant.status = SUSPENDED`
  - Toutes les sessions du tenant invalidées
  - Email notification aux OWNER et ADMIN du tenant
  - Les factures existantes restent accessibles en lecture seule
  - Les transmissions PDP sont bloquées
- `SuperAdminLog` : `tenant.suspended`

#### Edge cases UX
- Tenant en onboarding incomplet (> 7 jours) : badge orange "Onboarding bloqué"
- Tenant avec transmissions PDP en erreur depuis > 24h : badge rouge
- Tenant proche de sa limite plan : barre progression dans la colonne
- Recherche SIRET invalide (pas 14 chiffres) : validation inline

### Composants UI
- KPI cards globales (Twig)
- Graphiques Vue 3 + Chart.js (nouveaux tenants, volume factures)
- Tableau avec filtres Stimulus (Turbo Frame)
- Modale impersonate avec confirmation
- Modale suspension avec motif
- Bannière impersonate (layout global, visible sur toutes les pages)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `AdminTenantController::index()` | Listing + KPIs globaux |
| `TenantRepository::findAllWithStats()` | Requête multi-tenant sans filtre |
| `SuperAdminStatsService` | Calcul KPIs plateforme |
| `ImpersonationService` | Switch user + log |
| `TenantSuspensionService` | Suspension + invalidation sessions + email |
| `SuperAdminLogger` | Toutes les actions super-admin |

### Entités Doctrine
`Tenant`, `TenantMembership`, `User`, `Invoice`, `PdpTransmission`,
`EReportingBatch`, `SuperAdminLog`

---

## 2. `/admin/tenants/{id}` — Détail d'un tenant

### Rôles autorisés
`ROLE_SUPER_ADMIN` uniquement.

### Objectif
Vue exhaustive d'un tenant spécifique : données légales, utilisation,
santé technique, historique des actions super-admin.

### Features / fonctionnalités

#### En-tête
- Nom organisation + SIRET + plan + statut
- Boutons : Impersonate | Suspendre/Réactiver | Supprimer (soft)
- Lien "← Retour à la liste"

#### Onglet "Informations"

**Sous-bloc Identité**
- Toutes les données `Tenant` en lecture seule
- Date de création, date dernière modification
- Slug, UUID interne
- Configuration PDP (masquée partiellement — clé API tronquée)
- Régime TVA, plan actuel

**Sous-bloc Membres**
- Liste de tous les `TenantMembership` avec rôles
- Email, nom, rôle, date d'entrée, dernière connexion

**Sous-bloc Onboarding**
- Statut onboarding : `ORGANISATION` / `PREFERENCES` / `COMPLETED`
- Date de complétion
- PDP configurée : oui/non + statut

#### Onglet "Utilisation & métriques"

**Compteurs d'utilisation**

| Ressource | Utilisé | Limite plan | % |
|---|---|---|---|
| Factures ce mois | 47 | 100 | 47% |
| Utilisateurs actifs | 3 | 5 | 60% |
| Stockage S3 | 245 Mo | 1 Go | 24% |
| Transmissions PDP (mois) | 42 | illimité | — |

**Statistiques détaillées**
- Factures par statut (barres horizontales)
- Évolution des factures émises (courbe 12 mois)
- Volume TTC mensuel
- Taux de succès PDP (%)
- Batches e-reporting : liste avec statuts

#### Onglet "Santé technique"

**Statut PDP temps réel**
- Test de connexion PDP déclenché à l'affichage (résultat en temps réel)
- Dernière transmission réussie / en erreur
- Transmissions en attente (`PENDING`) ou en erreur (`ERROR`)
- Liste des 10 dernières `PdpTransmission` avec statuts

**Workers & queues**
- Messages Messenger en attente pour ce tenant
- Messages en erreur (failed) pour ce tenant
- Bouton "Retenter les messages échoués" (scope tenant)

**Erreurs récentes**
- 10 dernières exceptions Sentry (ou équivalent) taggées avec `tenant_id`
- Lien vers le tableau de bord de monitoring (Sentry/Datadog)

#### Onglet "Historique super-admin"

Timeline de toutes les actions super-admin sur ce tenant :
- Impersonations (début + fin + durée)
- Suspensions / réactivations (avec motif)
- Modifications manuelles (si applicable)
- Emails envoyés (de quoi, à qui, quand)

Provient de `SuperAdminLog` filtré sur `tenant_id`.

#### Actions super-admin sensibles

**Modifier le plan manuellement**
- Select FREE / PRO / ENTERPRISE
- Date de fin (optionnel — pour les essais gratuits)
- Motif (obligatoire)
- `SuperAdminLog` : `tenant.plan_changed_manually`

**Réinitialiser la configuration PDP**
- Efface les credentials PDP (pour re-configuration par l'OWNER)
- Email automatique à l'OWNER : "Votre configuration PDP a été réinitialisée"
- `SuperAdminLog` : `tenant.pdp_reset`

**Envoyer un email à l'équipe**
- Formulaire : objet + corps (Markdown)
- Destinataires : OWNER uniquement / OWNER + ADMIN / Toute l'équipe
- `SuperAdminLog` : `tenant.email_sent`

**Suppression définitive (soft delete)**
- Uniquement si `Tenant.deleted_at` déjà renseigné (déjà demandé par l'OWNER)
- Confirmation : saisie du SIRET du tenant
- Déclenche la purge immédiate (ou planification)
- `SuperAdminLog` : `tenant.deleted`

#### Edge cases UX
- Tenant suspendu : bannière rouge + toutes actions limitées sauf réactivation
- Tenant en cours d'impersonation par un autre super-admin :
  badge "En cours d'impersonation par {email}"
- Workers en erreur > 10 messages : alerte rouge dans l'onglet Santé
- Stockage S3 > 90% limite : alerte warning

### Composants UI
- Onglets Bootstrap (Turbo Frame par onglet, lazy)
- Barres progression utilisation (Bootstrap)
- Graphiques utilisation (Vue 3, lazy-loaded)
- Timeline super-admin log (Twig)
- Modales actions sensibles avec confirmation
- Badge impersonation live

### Appels API / services Symfony

| Service | Action |
|---|---|
| `AdminTenantController::show()` | Données tenant + onglets |
| `TenantUsageService` | Calcul métriques utilisation |
| `PdpConnectionTester` | Test connexion PDP à la demande |
| `MessengerInspector` | Messages pending/failed par tenant |
| `SuperAdminActionService` | Plan, PDP reset, email, suppression |
| `SuperAdminLogger` | Toutes les actions |

### Entités Doctrine
`Tenant`, `TenantMembership`, `User`, `Invoice`, `PdpTransmission`,
`EReportingBatch`, `SuperAdminLog`, `ExportJob`

---

## 3. `/admin/logs` — Logs système

### Rôles autorisés
`ROLE_SUPER_ADMIN` uniquement.

### Objectif
Consulter les logs d'audit globaux (toutes tenants), les logs des actions
super-admin et les erreurs techniques de la plateforme.

### Features / fonctionnalités

#### Tabs principaux

**Onglet "Audit global" (AuditLog toutes tenants)**

Tableau cross-tenant des `AuditLog` :

| Date | Tenant | Utilisateur | Action | Entité | IP |
|---|---|---|---|---|---|
| 15/03 14:32 | ACME SAS | marie@… | invoice.validated | Invoice #42 | 82.x.x.x |
| 15/03 14:31 | Dupont | jean@… | contact.created | Contact #12 | 91.x.x.x |

- Filtres : tenant (autocomplétion), action (select type), utilisateur,
  entité, période, IP
- Recherche full-text dans les payloads JSON
- Export CSV de l'audit (avec filtres actifs)
- Pagination 50 par page

**Onglet "Actions super-admin" (SuperAdminLog)**

Tableau dédié aux actions effectuées par les super-admins :

| Date | Super-admin | Action | Tenant ciblé | Détails |
|---|---|---|---|---|
| 15/03 15:00 | admin@platform.fr | impersonation.started | ACME SAS | Durée: 12min |
| 15/03 14:00 | admin@platform.fr | tenant.suspended | Dupont SARL | Motif: fraude |

- Toutes les impersonations, suspensions, modifications manuelles
- Non filtrable par tenant (vue globale super-admin)
- Export CSV

**Onglet "Transmissions PDP" (global)**

Vue cross-tenant de toutes les `PdpTransmission` :

| Date | Tenant | Facture | PDP | Statut | Latence |
|---|---|---|---|---|---|
| 15/03 15:32 | ACME SAS | FAC-42 | Chorus Pro | ACKNOWLEDGED | 1.2s |
| 15/03 15:28 | Dupont | FAC-18 | Sovos | ERROR | — |

- Filtres : tenant, PDP, statut, période
- Taux de succès global en haut (%)
- Détection des PDPs avec taux d'erreur anormal
- Alerte si une PDP spécifique a > 10% d'erreurs sur les dernières 24h

**Onglet "E-reporting DGFiP" (global)**

Vue cross-tenant des `EReportingBatch` :

| Période | Tenant | Statut | Soumis le | Réf DGFiP |
|---|---|---|---|---|
| Mars 2026 | ACME SAS | ACCEPTED | 20/04/2026 | DGF-789456 |
| Mars 2026 | Dupont SARL | LATE | — | — |

- Filtres : tenant, statut, période
- Compteur batches en retard (alerte si > 0)

**Onglet "Erreurs techniques"**

Agrégation des erreurs applicatives (depuis Sentry, Datadog ou logs Symfony) :

- Top 10 erreurs par fréquence (dernières 24h)
- Chaque erreur : message, fichier, ligne, nb occurrences, tenants impactés
- Lien vers le dashboard de monitoring externe
- Filtres : niveau (ERROR/CRITICAL/WARNING), période, tenant

**Onglet "Workers & queues"**

Supervision Symfony Messenger :

| Queue | Messages pending | Messages failed | Workers actifs | Dernier message |
|---|---|---|---|---|
| default | 3 | 0 | 2 | il y a 30s |
| pdp_transmission | 0 | 2 | 1 | il y a 2min |
| ereporting | 5 | 0 | 1 | il y a 1min |

- Bouton "Retenter tous les failed" (avec confirmation)
- Bouton "Purger la queue {nom}" (DANGER, avec confirmation saisie)
- Lien vers le dashboard Horizon/Messenger UI si configuré

#### Edge cases UX
- Logs volumineux (> 100k entrées) : pagination stricte + export async
- Recherche full-text dans payloads JSON lente :
  indicateur de chargement + timeout 10s
- Onglet Erreurs sans connexion Sentry :
  message "Monitoring externe non configuré — configurez Sentry/Datadog
  dans les variables d'environnement"
- Queue avec failed > 50 : badge rouge + alerte email automatique
  envoyée aux super-admins

### Composants UI
- Tabs Bootstrap (Turbo Frame par onglet, lazy)
- Tableaux filtrables Stimulus (Turbo Frame)
- Graphiques métriques globales (Vue 3, lazy)
- Code viewer payload JSON (Prism.js)
- Modales confirmation actions dangereuses (purge queue)
- Badge alerte workers failed

### Appels API / services Symfony

| Service | Action |
|---|---|
| `AdminLogController::index()` | Onglets logs |
| `AuditLogRepository::findAllTenants()` | Audit cross-tenant (sans TenantFilter) |
| `SuperAdminLogRepository::findAll()` | Logs actions super-admin |
| `PdpTransmissionRepository::findAllTenants()` | Transmissions cross-tenant |
| `EReportingBatchRepository::findAllTenants()` | Batches cross-tenant |
| `MessengerMonitorService` | Stats queues + failed messages |
| `ErrorAggregationService` | Erreurs depuis Sentry/Datadog API |
| `SuperAdminLogger` | `logs.exported` |

**Endpoints**
```
GET /admin/logs?tab={audit|superadmin|pdp|ereporting|errors|workers}
GET /admin/logs/audit?tenant={id}&action={type}&from={date}&to={date}
GET /admin/logs/transmissions?tenant={id}&pdp={name}&status={s}
POST /admin/logs/workers/{queue}/retry-failed
POST /admin/logs/workers/{queue}/purge        → confirmation SIRET requis
GET /admin/logs/export?type={tab}&{filtres}   → CSV async
```

### Entités Doctrine
`AuditLog`, `SuperAdminLog`, `PdpTransmission`, `EReportingBatch`,
`Tenant` (lecture cross-tenant sans TenantFilter)

---

## Notes transversales — Admin SAAS

### Isolation du firewall super-admin

```yaml
# security.yaml
firewalls:
    admin:
        pattern: ^/admin
        lazy: true
        provider: app_user_provider
        custom_authenticators:
            - App\Security\SuperAdminAuthenticator
        # Session séparée, pas de remember_me
        # Re-authentification requise toutes les 2h
        stateless: false

    main:
        pattern: ^/
        # Firewall principal — tenants
```

Le super-admin ne peut **jamais** accéder aux routes `/dashboard`, `/invoices`, etc.
directement avec son compte — uniquement via l'impersonation.

### SuperAdminLog vs AuditLog

| Aspect | AuditLog | SuperAdminLog |
|---|---|---|
| Portée | Par tenant | Cross-tenant |
| Auteur | User du tenant | Super-admin |
| Stockage | Table `audit_logs` (par tenant) | Table `super_admin_logs` (globale) |
| Accès | OWNER du tenant + super-admin | Super-admin uniquement |
| Rétention | 10 ans (légal) | 5 ans (interne) |
| TenantFilter | Actif | Désactivé |

### Sécurité de l'impersonation
- L'impersonation est loguée dans `SuperAdminLog` avec timestamp précis
- Toutes les actions effectuées en mode impersonation sont loguées dans
  `AuditLog` avec un flag `is_impersonated = true` + `impersonated_by`
- Le tenant impersonné peut voir dans son propre `AuditLog` qu'une action
  a été effectuée par un super-admin (transparence)
- La session impersonate expire après 30 minutes d'inactivité (forcé)
- Impossible de s'impersonner soi-même ou un autre super-admin

### Rate limiting admin
Toutes les routes `/admin/*` sont soumises à un rate limiting strict :
- 100 requêtes / minute / IP
- 10 exports / heure / super-admin
- Blocage temporaire 15 min si dépassement
