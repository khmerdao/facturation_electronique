# Section E-REPORTING — Transmissions DGFiP

Pilotage des obligations d'e-reporting : déclaration périodique à la DGFiP
des transactions **hors périmètre e-invoicing** (B2C, ventes à l'international,
transactions avec des entreprises étrangères non assujetties en France)
et des **données de paiement** associées.

Obligatoire dès le **1er septembre 2026** pour les entreprises soumises à la réforme.

---

## Sommaire

| Page | URL | Rôles |
|---|---|---|
| Tableau de bord e-reporting | `/e-reporting` | Tous (action ACCOUNTANT+) |

---

## `/e-reporting` — Tableau de bord e-reporting & statuts transmissions DGFiP

### Rôles autorisés
`OWNER`, `ADMIN`, `ACCOUNTANT`, `VIEWER`
Actions de soumission et correction masquées pour `VIEWER`.

### Objectif
Superviser l'ensemble des obligations d'e-reporting : constitution des batches
périodiques, suivi de leur transmission à la DGFiP via la PDP/PPF, et gestion
des rejets ou corrections.

---

### Rappel réglementaire (affiché en haut de page)

Encadré informatif rétractable :

> **E-reporting vs E-invoicing**
> - **E-invoicing** : transmission de la facture électronique entre assujettis
>   via PDP/PPF (B2B France). Géré dans la section "Factures émises".
> - **E-reporting** : déclaration périodique à la DGFiP des données de transactions
>   et de paiements **non couvertes par l'e-invoicing** :
>   - Ventes B2C (particuliers)
>   - Ventes à des entreprises étrangères
>   - Ventes intracommunautaires hors périmètre
>   - Données de paiement sur ces mêmes transactions
> - Périodicité : **mensuelle** (régime normal) ou **trimestrielle** (régime simplifié)
> - Transmission via la PDP configurée ou directement via le PPF

---

### Features / fonctionnalités

#### Sélecteur de période & vue d'ensemble

**Sélecteur** (haut de page) : année courante (défaut) / année précédente / période personnalisée

**Tableau de bord annuel — grille des batches**

Vue calendaire annuelle (12 mois sur une ligne ou grille) montrant le statut
de chaque batch mensuel d'e-reporting :

| Mois | Transactions | Paiements | Statut batch | Échéance | Actions |
|---|---|---|---|---|---|
| Janvier 2026 | 142 opérations | 89 paiements | `ACCEPTED` ✓ | 24/02/2026 | Voir \| Télécharger |
| Février 2026 | 98 opérations | 67 paiements | `ACCEPTED` ✓ | 24/03/2026 | Voir \| Télécharger |
| Mars 2026 | 156 opérations | 112 paiements | `SUBMITTED` ⏳ | 24/04/2026 | Voir \| Suivre |
| Avril 2026 | 201 opérations | 143 paiements | `DRAFT` | 24/05/2026 | Préparer \| Soumettre |
| Mai 2026 | — | — | `NOT_STARTED` | 24/06/2026 | — |

**Statuts de batch**

| Statut | Couleur | Signification |
|---|---|---|
| `NOT_STARTED` | Gris | Période future ou pas de données encore |
| `DRAFT` | Bleu | Données agrégées, non encore soumises |
| `READY` | Amber | Vérifié, prêt à soumettre |
| `SUBMITTED` | Bleu clair | Transmis à la DGFiP via PDP/PPF |
| `ACCEPTED` | Vert | Accepté par la DGFiP |
| `REJECTED` | Rouge | Rejeté par la DGFiP (corrections requises) |
| `LATE` | Rouge foncé | Échéance dépassée, non soumis |

**Alertes prioritaires** (au-dessus de la grille si applicable)
- Batch `LATE` : "⛔ Le batch {mois} n'a pas été soumis — échéance dépassée.
  Soumettez-le immédiatement pour limiter les pénalités."
- Batch `REJECTED` : "⚠ Le batch {mois} a été rejeté par la DGFiP.
  Des corrections sont requises. [Voir les erreurs →]"
- Batch `DRAFT` avec échéance dans ≤ 7 jours : "⏰ Le batch {mois} doit être
  soumis avant le {date}."

---

#### Détail d'un batch (clic sur une ligne ou un mois)

Panneau latéral ou page dédiée `/e-reporting/{batch_id}` :

##### Sous-section — Données de transactions (e-reporting opérations)

Récapitulatif des transactions non couvertes par l'e-invoicing :

**Tableau par type de transaction**

| Type | Nb opérations | CA HT total | TVA totale | Détail |
|---|---|---|---|---|
| Ventes B2C France | 142 | 14 250,00 € | 2 850,00 € | Voir lignes |
| Livraisons intracommunautaires | 7 | 8 400,00 € | 0,00 € | Voir lignes |
| Exportations hors UE | 3 | 2 100,00 € | 0,00 € | Voir lignes |
| Prestations à l'étranger | 5 | 3 200,00 € | 0,00 € | Voir lignes |
| **Total** | **157** | **27 950,00 €** | **2 850,00 €** | |

**Détail par ligne** (expandable) :
- N° facture (ou référence transaction si sans facture formelle)
- Date
- Type d'opération
- Montant HT par taux de TVA
- Montant TVA
- Mode de règlement (si connu)
- Statut de saisie : `AUTO` (extrait des factures) / `MANUAL` (saisi manuellement)

**Ajout manuel de transactions** (ACCOUNTANT+)
- Bouton "Ajouter une transaction"
- Pour les ventes B2C sans facture formelle (ex : ticket de caisse, vente en ligne)
- Formulaire : type, date, montant HT par taux, TVA, mode règlement, référence
- Utile pour les commerçants avec un volume B2C hors système de facturation

##### Sous-section — Données de paiement (e-reporting paiements)

Récapitulatif des paiements reçus sur les transactions B2C/internationales :

| Date paiement | Référence facture | Montant encaissé | Mode paiement DGFiP | Statut |
|---|---|---|---|---|
| 05/03/2026 | TKT-20260305-001 | 48,00 € | 40 — Carte | `AUTO` |
| 08/03/2026 | FAC-B2C-042 | 120,00 € | 30 — Virement | `AUTO` |

- Ces données sont alimentées automatiquement depuis `/invoices/{id}/payment`
  (flag `ereporting = true` sur les paiements concernés)
- Bouton "Ajouter un paiement manuellement" (pour les encaissements hors système)

##### Sous-section — Validation & vérifications pré-soumission

Checklist automatique avant soumission (ACCOUNTANT+) :

| Vérification | Statut |
|---|---|
| Toutes les transactions de la période sont incluses | ✓ / ⚠ Vérifiez |
| Montants TVA cohérents avec la déclaration CA3 | ✓ / ⚠ Écart détecté |
| Codes modes de paiement DGFiP renseignés | ✓ / ✗ X manquants |
| Période close (aucune facture modifiable sur ce mois) | ✓ / ⚠ X brouillons ouverts |
| PDP configurée et accessible | ✓ / ✗ PDP hors ligne |

- Si toutes les vérifications sont `✓` : bouton "Marquer comme prêt" disponible
- Si vérifications `✗` (bloquantes) : soumission impossible avec liste des corrections
- Si vérifications `⚠` (avertissements) : soumission possible avec confirmation

##### Sous-section — Soumission à la DGFiP

**Bouton "Soumettre à la DGFiP"** (ACCOUNTANT+, batch en statut `READY`)

Flux de soumission :
1. Génération du fichier XML e-reporting (format DGFiP normalisé)
2. Validation XSD du fichier généré
3. Transmission via la PDP configurée (message Symfony Messenger)
4. Statut batch → `SUBMITTED`
5. Horodatage soumission stocké
6. `AuditLog` : `ereporting_batch.submitted`

**Suivi post-soumission**
- Accusé de réception DGFiP (via retour PDP) : statut → `ACCEPTED`
  - Référence DGFiP (`dgfip_ref`) stockée
  - Date d'acceptation
- Rejet DGFiP : statut → `REJECTED`
  - Code erreur DGFiP + message explicatif
  - Liste des lignes en erreur avec description
  - Bouton "Corriger et resoumettre"

**Mise à jour temps réel** : Turbo Stream + Mercure sur le topic
`/tenants/{tenant_id}/ereporting/{batch_id}`

---

#### Onglet — Historique des transmissions e-invoicing

> Distinct de l'e-reporting mais affiché ici pour centraliser le suivi DGFiP

Tableau de toutes les transmissions PDP/PPF de factures émises :

| N° Facture | Client | Date transmission | PDP | ID externe | Statut | Latence |
|---|---|---|---|---|---|---|
| FAC-2026-0042 | ACME SAS | 15/03/2026 14:32 | Chorus Pro | CHR-789456 | `ACKNOWLEDGED` ✓ | 1m 23s |
| FAC-2026-0043 | Dupont SARL | 15/03/2026 15:01 | Chorus Pro | — | `REJECTED` ✗ | — |

**Filtres** : période, PDP, statut (`PENDING/SENT/ACKNOWLEDGED/REJECTED/ERROR`)

**Actions par ligne**
- Voir le détail → `/invoices/{id}` onglet "Historique"
- Retransmettre (si `REJECTED` ou `ERROR`)
- Voir la réponse brute de la PDP (JSON/XML, modale)

**Statistiques de transmission (période sélectionnée)**
- Taux de succès (%) : `ACKNOWLEDGED / total transmissions`
- Délai moyen d'accusé de réception
- Nombre de rejets avec répartition par motif

---

#### Onglet — Configuration e-reporting

Paramètres spécifiques à l'e-reporting (lecture seule ici, édition dans `/settings/pdp`) :

- Périodicité : Mensuelle / Trimestrielle (selon régime TVA)
- PDP utilisée pour la transmission e-reporting
- Identifiant de déclarant DGFiP
- Date de début d'assujettissement (1er sept. 2026 ou date ultérieure)
- Statut connexion PDP pour l'e-reporting (peut différer de l'e-invoicing)
- Lien "Modifier la configuration →" → `/settings/pdp`

---

#### Edge cases UX

**Batch sans données**
- Mois sans aucune transaction B2C/internationale :
  - Statut `EMPTY` (variante de `DRAFT`)
  - Batch vide à soumettre quand même si l'entreprise est assujettie
    (déclaration néant obligatoire selon les textes DGFiP)
  - Bouton "Soumettre une déclaration néant"
  - Message : "Aucune transaction hors e-invoicing ce mois-ci.
    Une déclaration néant doit néanmoins être transmise."

**Rejet DGFiP avec corrections multiples**
- Affichage structuré des erreurs par ligne de transaction
- Chaque erreur avec : code DGFiP, description, ligne concernée, correction suggérée
- Formulaire d'édition des lignes en erreur directement dans l'interface
- Possibilité de corriger sans retoucher les factures sources
  (les corrections sont des `EReportingCorrection` liées au batch)

**Batch `LATE` (échéance dépassée)**
- Soumission toujours possible mais bandeau rouge permanent "HORS DÉLAI"
- Avertissement : "Ce batch est soumis hors délai. Les pénalités éventuelles
  sont à l'appréciation de l'administration fiscale."
- Champ "Motif du retard" (optionnel, joint à la transmission)

**PDP hors ligne lors de la soumission**
- Retry automatique toutes les 30 min pendant 24h (Messenger avec retry policy)
- Notification email + in-app après chaque retry échoué
- Après 24h sans succès : alerte critique + intervention manuelle requise
- Statut batch reste `SUBMITTED` (en attente d'accusé) pendant les retries

**Incohérence avec la déclaration CA3**
- Si le total TVA de l'e-reporting diffère du total TVA des factures (> 1€ d'écart) :
  - Avertissement non bloquant "Le montant TVA du batch ({X}€) diffère de votre
    synthèse TVA ({Y}€). Vérifiez les transactions manuelles et les ajustements."
  - Lien → `/tax` pour comparaison

**Transition de régime TVA**
- Si le tenant passe de mensuel à trimestriel en cours d'année :
  - Batches existants conservent leur périodicité d'origine
  - Nouveaux batches créés selon la nouvelle périodicité
  - Message informatif sur la page

### Composants UI
- Grille annuelle des batches (Twig + classes Bootstrap, colorée par statut)
- Panneau détail batch (Turbo Frame `<turbo-frame id="batch-detail">`)
- Checklist validation (Twig, icônes Tabler)
- Tableau transmissions e-invoicing avec Turbo Frame
- Statistiques transmissions (Vue 3 `<TransmissionStats>`, lazy-loaded)
- Formulaire ajout transaction manuelle (Bootstrap modal)
- Formulaire correction batch rejeté (Bootstrap modal + Stimulus)
- Badges statut batch (composant Twig `_ereporting_status_badge.html.twig`)
- Toast temps réel (Turbo Stream + Mercure sur topic batch)

### Appels API / services Symfony

| Service | Action |
|---|---|
| `EReportingController::index()` | Grille annuelle + batches |
| `EReportingBatchRepository::findByTenantAndYear()` | Batches de l'année |
| `EReportingAggregator::buildBatch()` | Agrégation transactions/paiements → batch |
| `EReportingValidator::validate()` | Checklist pré-soumission |
| `EReportingXmlGenerator` | Génération fichier XML DGFiP |
| `EReportingSubmissionService` | Transmission via PDP (async Messenger) |
| `EReportingCorrectionService` | Application corrections sur batch rejeté |
| `PdpTransmissionRepository::findForEInvoicing()` | Historique transmissions e-invoicing |
| `TransmissionStatsService` | Taux succès, délais, répartition rejets |
| `AuditLogger` | `ereporting_batch.submitted`, `ereporting_batch.corrected` |
| `NotificationService` | Alertes batch rejeté, late, retry PDP |
| `MercurePublisher` | Topic `/tenants/{id}/ereporting/{batch_id}` |

**Endpoints**
```
GET  /e-reporting
     → grille annuelle des batches

GET  /e-reporting/{batch_id}
     → détail batch (ou Turbo Frame si _turbo_frame)

POST /api/ereporting/{batch_id}/mark-ready
     → statut DRAFT → READY (après vérifications)

POST /api/ereporting/{batch_id}/submit
     → génération XML + dispatch Messenger
     → { job_id }

POST /api/ereporting/{batch_id}/transactions
     Body: { type, date, amount_ht, tva, mode, reference }
     → ajout transaction manuelle

POST /api/ereporting/{batch_id}/corrections
     Body: [{ line_id, field, corrected_value }]
     → application corrections batch rejeté

GET  /api/ereporting/{batch_id}/xml
     → téléchargement XML généré (URL S3 signée)

GET  /api/ereporting/transmissions?period={p}&status={s}
     → historique transmissions e-invoicing filtré
```

### Entités Doctrine

| Entité | Champs concernés |
|---|---|
| `EReportingBatch` | `id`, `tenant_id`, `period` (YYYY-MM), `periodicity` (MONTHLY\|QUARTERLY), `type` (TRANSACTIONS\|PAYMENTS\|BOTH), `status`, `dgfip_ref`, `submitted_at`, `accepted_at`, `rejected_at`, `reject_reason` (json), `xml_s3_key`, `xml_hash`, `late`, `late_reason` |
| `EReportingTransaction` | `id`, `batch_id`, `tenant_id`, `invoice_id` (nullable), `type` (B2C\|INTRACOM\|EXPORT\|FOREIGN_SERVICE), `date`, `amount_ht_by_rate` (json), `total_tva`, `payment_mode_dgfip`, `reference`, `source` (AUTO\|MANUAL) |
| `EReportingPaymentLine` | `id`, `batch_id`, `payment_id` (nullable), `date_valeur`, `amount`, `mode_dgfip`, `invoice_reference`, `source` (AUTO\|MANUAL) |
| `EReportingCorrection` | `id`, `batch_id`, `line_id`, `field`, `old_value`, `corrected_value`, `corrected_by`, `corrected_at` |
| `PdpTransmission` | Historique e-invoicing (lecture) |
| `AuditLog` | Traçabilité |

---

## Notes transversales — Section E-REPORTING

### Périmètre exact de l'e-reporting (clarification réglementaire)

```
Transactions INCLUSES dans l'e-reporting (non couvertes par l'e-invoicing) :

1. Ventes B2C (particuliers français)
   → Pas de facture électronique obligatoire entre assujetti et particulier
   → Données agrégées par période (pas ligne par ligne)

2. Ventes à des assujettis étrangers établis hors France
   → Livraisons intracommunautaires de biens
   → Prestations de services à des assujettis UE (B2B intracom)
   → Exportations hors UE

3. Données de paiement sur ces mêmes transactions
   → Date, montant, moyen de paiement (codes DGFiP normalisés)

Transactions EXCLUES de l'e-reporting (couvertes par l'e-invoicing) :
   → Toutes les transactions B2B entre assujettis français
   → Gérées via PdpTransmission dans la section "Factures émises"
```

### Format XML DGFiP e-reporting

Le fichier XML de transmission suit le schéma XSD publié par la DGFiP.
Structure principale :

```xml
<DeclarationEReporting>
  <Declarant>
    <SIRET>...</SIRET>
    <PeriodeDebut>2026-03-01</PeriodeDebut>
    <PeriodeFin>2026-03-31</PeriodeFin>
  </Declarant>
  <OperationsTransactions>
    <Transaction type="B2C">
      <DateOperation>2026-03-05</DateOperation>
      <MontantHT taux="20">100.00</MontantHT>
      <MontantTVA>20.00</MontantTVA>
    </Transaction>
    ...
  </OperationsTransactions>
  <DonneesPaiements>
    <Paiement>
      <DateValeur>2026-03-05</DateValeur>
      <Montant>120.00</Montant>
      <MoyenPaiement>40</MoyenPaiement>
    </Paiement>
    ...
  </DonneesPaiements>
</DeclarationEReporting>
```

### Agrégation automatique vs saisie manuelle

L'`EReportingAggregator` construit automatiquement le batch mensuel en :
1. Récupérant les `Invoice` de type B2C ou international émises sur la période
2. Récupérant les `Payment` avec `ereporting = true` de la période
3. Créant les `EReportingTransaction` et `EReportingPaymentLine` correspondantes

Les transactions manuelles (ventes B2C sans facture formelle) sont saisies
directement dans le batch via le formulaire d'ajout.

### Déclaration néant
Si aucune transaction n'est détectée pour la période mais que le tenant est
assujetti depuis le 1er septembre 2026, un batch `EMPTY` est créé automatiquement
en début de mois suivant par le job `CreateMonthlyEReportingBatchJob`.
L'ACCOUNTANT doit le soumettre comme déclaration néant (batch vide valide selon
le schéma DGFiP).
