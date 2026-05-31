# Tasks Frontend & Qualité — Facturation Électronique
## Issu de l'analyse complète du 30 mai 2026

> **Contexte** : Les 22 tasks backend initiales sont toutes terminées.
> Ce fichier recense les gaps identifiés lors de l'audit frontend :
> build npm manquant, sécurité formulaires, Vue 3, UX, tests manquants.
>
> Les tasks sont classées par priorité :
> 🔴 **BLOQUANT** — L'app ne démarre pas ou est vulnérable
> 🟠 **HAUTE** — Fonctionnalité incomplète ou risque de régression
> 🟡 **MOYENNE** — Qualité du code ou UX dégradée
> 🟢 **BASSE** — Amélioration, refactoring, dette technique

---

## TASK-F001 — Build npm : entry points et compilation Webpack
**Priorité :** 🔴 BLOQUANT
**Statut :** `terminé`
**Estimation :** 2-3 heures

### Problème
`public/build/` est vide. `node_modules/` est absent.
Les appels `{{ encore_entry_link_tags('app') }}` dans `base.html.twig` lèvent
une `EntrypointNotFoundException` au premier chargement de n'importe quelle page.
**L'application ne peut pas démarrer.**

### Cause
- `npm install` n'a jamais été exécuté dans cet environnement
- `assets/admin.js` est déclaré dans `webpack.config.js` mais **n'existe pas**
- `assets/invoice_editor.js` est déclaré dans `webpack.config.js` mais **n'existe pas**
- Sans ces deux fichiers, `npm run dev` échoue avec `Module not found`

### Ce qu'il faut faire

#### 1. Créer `assets/admin.js` (entry point super-admin)
```javascript
// assets/admin.js
// Point d'entrée spécifique aux pages super-admin (/admin/*)
// Chargé uniquement dans templates/admin/base.html.twig si créé,
// ou dans admin/*.html.twig via {% block javascripts %}

import './app.js'; // hériter du bundle principal

// DataTables pour les tableaux admin paginés côté client
import DataTable from 'datatables.net-bs5';
window.DataTable = DataTable;

// Graphiques analytics (stats tenants, MRR, etc.)
import Chart from 'chart.js/auto';
window.Chart = Chart;
```

#### 2. Créer `assets/invoice_editor.js` (entry point éditeur factures)
```javascript
// assets/invoice_editor.js
// Point d'entrée Vue 3 pour l'éditeur de lignes de facture
// Voir TASK-F003 pour l'implémentation complète Vue 3

import { createApp } from 'vue';

// Stub temporaire — à remplacer par TASK-F003
// Permet au build de passer sans erreur en attendant les composants .vue
const editorMount = document.getElementById('invoice-editor-app');
if (editorMount) {
    createApp({
        data() { return { ready: false }; },
        mounted() { this.ready = true; },
        template: '<slot v-if="ready" />'
    }).mount(editorMount);
}
```

#### 3. Exécuter le build
```bash
npm install
npm run dev         # développement avec source maps
npm run build       # production avec minification
```

#### 4. Vérifier le résultat
```
public/build/
  ├── app.js
  ├── app.css
  ├── admin.js
  ├── invoice_editor.js
  ├── runtime.js
  └── manifest.json   ← indispensable pour encore_entry_link_tags()
```

#### 5. Ajouter `.gitignore`
```
/public/build/*
!/public/build/.gitkeep
/node_modules/
```

### Critères de validation
- [ ] `npm run dev` se termine sans erreur
- [ ] `public/build/manifest.json` est généré
- [ ] La page `/dashboard` se charge sans exception PHP
- [ ] Bootstrap 5 est appliqué (sidebar visible et stylée)
- [ ] Aucun `net::ERR_ABORTED 404` sur les assets dans la console navigateur

---

## TASK-F002 — Stimulus : sidebar_controller et enregistrement des controllers
**Priorité :** 🔴 BLOQUANT
**Statut :** `terminé`
**Estimation :** 1-2 heures

### Problème
La sidebar utilise `data-controller="sidebar"` dans `templates/partials/_sidebar.html.twig`
mais **`assets/controllers/sidebar_controller.js` n'existe pas**.
Résultat : le bouton hamburger (mobile) ne fonctionne pas. Sur mobile, la sidebar
ne peut pas s'ouvrir/fermer.

De plus, `assets/controllers.json` ne déclare **aucun** des 6 controllers créés.
La découverte automatique via `@symfony/stimulus-bundle` devrait les charger,
mais le fichier `controllers.json` est mal configuré pour ça.

### Controllers existants (assets/controllers/)
```
chart_controller.js        ← data-controller="chart"
confirm_controller.js      ← data-controller="confirm"
copy_controller.js         ← data-controller="copy"
flash_controller.js        ← data-controller="flash"
invoice_lines_controller.js ← data-controller="invoice-lines"
siret_lookup_controller.js  ← data-controller="siret-lookup"
```

### Ce qu'il faut faire

#### 1. Créer `assets/controllers/sidebar_controller.js`
```javascript
// assets/controllers/sidebar_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    // Ouvre/ferme la sidebar en mode mobile
    toggle() {
        this.element.classList.toggle('open');
        document.body.classList.toggle('sidebar-open');
    }

    // Ferme la sidebar si on clique en dehors (overlay)
    close() {
        this.element.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }
}
```

#### 2. Ajouter l'overlay mobile dans `base.html.twig`
```html
{# Overlay mobile — ferme la sidebar au clic sur le fond #}
<div class="sidebar-overlay d-lg-none"
     data-action="click->sidebar#close"
     data-turbo-permanent></div>
```

#### 3. Ajouter dans `app.scss`
```scss
// Overlay sidebar mobile
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    z-index: 999;
}

body.sidebar-open .sidebar-overlay {
    display: block;
}
```

#### 4. Mettre à jour `assets/controllers.json`
```json
{
    "controllers": {
        "@symfony/ux-turbo": {
            "turbo-stream": {
                "enabled": true,
                "fetch": "eager"
            }
        },
        "@symfony/stimulus-bundle": {}
    },
    "entrypoints": []
}
```
> Note : `@symfony/stimulus-bundle` avec un objet vide active la découverte
> automatique des controllers dans `assets/controllers/`. Vérifier que
> `config/packages/stimulus.yaml` existe et pointe vers le bon dossier.

#### 5. Vérifier `config/packages/stimulus.yaml`
```yaml
# config/packages/stimulus.yaml
stimulus:
    controllers_dir: '%kernel.project_dir%/assets/controllers'
```

### Critères de validation
- [ ] Sidebar s'ouvre sur mobile (< 992px) au clic hamburger
- [ ] Sidebar se ferme au clic sur l'overlay
- [ ] Aucune erreur `[STIMULUS] Missing target element` dans la console
- [ ] `data-controller="confirm"` fonctionne sur les boutons de suppression
- [ ] `data-controller="flash"` auto-dismiss les alertes après 5s

---

## TASK-F003 — Vue 3 : éditeur de lignes de factures
**Priorité :** 🟠 HAUTE
**Statut :** `terminé (migré Stimulus)`
**Estimation :** 2-3 jours

### Problème
Les templates `invoices/new.html.twig` et `invoices/edit.html.twig` contiennent
**du JavaScript vanilla inline** (querySelector, parseFloat, DOM manipulation)
pour gérer l'ajout/suppression de lignes et le calcul en temps réel.
Ce code est fragile, non réutilisable et difficile à maintenir.

Audit du JS inline actuel :
- Calcul HT/TVA/TTC sur `input` : dupliqué dans new et edit
- Ajout de ligne : clone DOM naïf (index `[N]` recalculé manuellement)
- Pas de catalog lookup (précharger les données produit depuis l'API)
- Pas de validation front (quantité négative, prix vide)

### Ce qu'il faut faire

#### 1. Créer `assets/components/InvoiceLine.vue`
Composant unitaire pour une ligne de facture.
Props : `modelValue` (objet ligne), `products` (catalogue), `index`.
Emits : `update:modelValue`, `remove`, `move-up`, `move-down`.

```vue
<template>
  <tr class="invoice-line" :class="{ 'is-comment': line.is_comment }">
    <td>
      <!-- Sélecteur produit catalogue -->
      <select v-if="!line.is_comment"
              v-model="line.product_id"
              @change="fillFromProduct"
              class="form-select form-select-sm mb-1">
        <option value="">— Produit catalogue —</option>
        <option v-for="p in products" :key="p.id"
                :value="p.id"
                :data-price="p.unit_price"
                :data-tva="p.tva_rate"
                :data-unit="p.unit">
          {{ p.label }}
        </option>
      </select>
      <input type="text" v-model="line.description"
             class="form-control form-control-sm"
             :placeholder="line.is_comment ? 'Commentaire / sous-total' : 'Description *'"
             required />
      <input type="text" v-model="line.reference"
             class="form-control form-control-sm mt-1"
             placeholder="Référence" v-if="!line.is_comment" />
    </td>
    <!-- ... autres colonnes ... -->
    <td class="text-end fw-semibold small">
      {{ formatMoney(amountHt) }}
    </td>
  </tr>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps(['modelValue', 'products', 'index'])
const emit  = defineEmits(['update:modelValue', 'remove'])

const line = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
})

const amountHt = computed(() => {
    if (line.value.is_comment) return 0
    const qty   = parseFloat(line.value.quantity)  || 0
    const price = parseFloat(line.value.unit_price) || 0
    const disc  = parseFloat(line.value.discount)   || 0
    return Math.round(qty * price * (1 - disc / 100) * 100) / 100
})

function fillFromProduct(event) {
    const opt = event.target.selectedOptions[0]
    if (!opt.dataset.price) return
    line.value.unit_price = opt.dataset.price
    line.value.tva_rate   = opt.dataset.tva
    line.value.unit       = opt.dataset.unit
    const prod = props.products.find(p => p.id === line.value.product_id)
    if (prod) line.value.description = prod.label
}

const formatMoney = (v) => v.toFixed(2).replace('.', ',') + ' €'
</script>
```

#### 2. Créer `assets/components/InvoiceEditor.vue`
Composant parent qui gère le tableau de lignes et les totaux.

Fonctionnalités :
- `v-for` sur les lignes avec clé stable (UUID ou index)
- Drag & drop pour réordonner les lignes (via SortableJS ou glisser natif)
- Ajout de ligne normale / ligne commentaire
- Calcul total HT, TVA par taux, TTC en temps réel
- Hidden inputs `lines[N][...]` pour la soumission du formulaire HTML classique
- Chargement du catalogue via `GET /api/products` au montage

```vue
<template>
  <div>
    <table class="table">
      <thead>...</thead>
      <tbody>
        <InvoiceLine
          v-for="(line, idx) in lines"
          :key="line._key"
          v-model="lines[idx]"
          :products="products"
          :index="idx"
          @remove="removeLine(idx)"
        />
      </tbody>
    </table>

    <div class="d-flex gap-2 mb-4">
      <button type="button" @click="addLine">+ Ligne</button>
      <button type="button" @click="addComment">+ Commentaire</button>
    </div>

    <!-- Hidden inputs pour soumission classique -->
    <template v-for="(line, idx) in lines" :key="line._key">
      <input type="hidden" :name="`lines[${idx}][description]`" :value="line.description" />
      <input type="hidden" :name="`lines[${idx}][quantity]`"    :value="line.quantity" />
      <!-- ... etc ... -->
    </template>

    <!-- Totaux -->
    <div class="invoice-totals">
      <div>Total HT : {{ formatMoney(totalHt) }}</div>
      <div v-for="(tva, rate) in tvaBreakdown">TVA {{ rate }}% : {{ formatMoney(tva) }}</div>
      <div class="fw-bold">Total TTC : {{ formatMoney(totalTtc) }}</div>
    </div>
  </div>
</template>
```

#### 3. Mettre à jour `assets/invoice_editor.js`
```javascript
import { createApp } from 'vue'
import InvoiceEditor from './components/InvoiceEditor.vue'

const mount = document.getElementById('invoice-editor-app')
if (mount) {
    const initialLines = JSON.parse(mount.dataset.lines || '[]')
    createApp(InvoiceEditor, { initialLines }).mount(mount)
}
```

#### 4. Modifier `templates/invoices/new.html.twig`
Remplacer le tableau HTML statique + JS inline par :
```html
{{ encore_entry_script_tags('invoice_editor') }}

<div id="invoice-editor-app"
     data-lines="{{ []|json_encode }}">
</div>
```

#### 5. Modifier `templates/invoices/edit.html.twig`
```html
{{ encore_entry_script_tags('invoice_editor') }}

<div id="invoice-editor-app"
     data-lines="{{ invoice.lines|map(l => {
         description: l.description,
         quantity: l.quantity,
         unit_price: l.unitPrice,
         tva_rate: l.tvaRate,
         discount: l.discount,
         unit: l.unit,
         product_id: l.product ? l.product.id : null,
         is_comment: l.isComment
     })|json_encode }}">
</div>
```

### Critères de validation
- [ ] Ajout de ligne sans rechargement de page
- [ ] Suppression de ligne avec recalcul immédiat
- [ ] Sélection produit catalogue pré-remplit prix/TVA/unité
- [ ] Totaux HT/TVA/TTC recalculés à chaque frappe
- [ ] Formulaire soumissible en POST (hidden inputs bien remplis)
- [ ] Fonctionne en mode édition (lignes pré-chargées)
- [ ] Pas de régression sur la création/modification de factures

---

## TASK-F004 — Sécurité formulaires : CSRF sur 23 templates
**Priorité :** 🔴 BLOQUANT
**Statut :** `terminé`
**Estimation :** 3-4 heures

### Problème
**23 templates** contenant des formulaires POST n'ont pas de token CSRF.
**8 controllers** traitant des POST ne vérifient pas `isCsrfTokenValid()`.
Vulnérabilité : n'importe quel site tiers peut soumettre des formulaires
au nom d'un utilisateur connecté (Cross-Site Request Forgery).

### Templates sans token CSRF (23 fichiers)
```
templates/admin/tenant_show.html.twig
templates/auth/forgot_password.html.twig
templates/auth/register.html.twig
templates/auth/reset_password.html.twig
templates/billing/index.html.twig
templates/billing/upgrade.html.twig
templates/contacts/edit.html.twig
templates/contacts/new.html.twig
templates/e_reporting/index.html.twig
templates/e_reporting/show.html.twig
templates/invoices/edit.html.twig
templates/invoices/new.html.twig
templates/onboarding/organisation.html.twig
templates/onboarding/preferences.html.twig
templates/payments/record.html.twig
templates/products/edit.html.twig
templates/products/new.html.twig
templates/received_invoices/show.html.twig
templates/settings/organisation.html.twig
templates/settings/pdp.html.twig
templates/settings/sequences.html.twig
templates/tax/exports.html.twig
templates/tax/index.html.twig
```

### Ce qu'il faut faire

#### 1. Convention de nommage des tokens
```
Formulaire de création  → csrf_token('create_ENTITY')
Formulaire d'édition    → csrf_token('edit_ENTITY_' ~ entity.id)
Action POST unitaire    → csrf_token('ACTION_ENTITY_' ~ entity.id)
```

#### 2. Ajouter dans chaque formulaire POST
```html
<input type="hidden" name="_token"
       value="{{ csrf_token('edit_contact_' ~ contact.id) }}">
```

#### 3. Vérifier dans chaque controller
```php
// Dans chaque méthode qui traite un POST modifiant des données
if (!$this->isCsrfTokenValid('edit_contact_' . $contact->getId(), $request->request->get('_token'))) {
    throw $this->createAccessDeniedException('Token CSRF invalide.');
}
```

#### 4. Controllers à corriger
| Controller | Action(s) POST concernée(s) |
|---|---|
| `ContactController` | `new`, `edit`, `archive` |
| `ProductController` | `new`, `edit`, `archive` |
| `PaymentController` | `record`, `cancel` |
| `SettingsController` | `organisation`, `sequences`, `pdp` |
| `OnboardingController` | `organisation`, `preferences` |
| `RegisterController` | `register` |
| `ForgotPasswordController` | `forgot`, `reset` |
| `TenantSelectorController` | `select` |

#### 5. Note sur les formulaires d'auth
Pour les formulaires de connexion/inscription, Symfony gère nativement
le CSRF via `security.yaml` (`enable_csrf: true` sur le firewall).
Vérifier que c'est activé avant d'ajouter manuellement.

### Critères de validation
- [ ] 0 formulaire POST sans `csrf_token()` dans les templates
- [ ] 0 controller POST sans `isCsrfTokenValid()` pour les actions destructives
- [ ] Test manuel : modifier un formulaire et changer le token → erreur 403
- [ ] Les formulaires d'auth (login, register) sont protégés par le firewall Symfony

---

## TASK-F005 — FormTypes Symfony : validation côté serveur
**Priorité :** 🟠 HAUTE
**Statut :** `terminé`
**Estimation :** 2-3 jours

### Problème
**0 FormType PHP** n'existe dans `src/Form/`.
Tous les controllers hydratable directement depuis `$request->request->all()`
sans aucune contrainte Symfony Validator.
Conséquences :
- Des données invalides peuvent être enregistrées en base (SIRET incorrect, email mal formé, montant négatif)
- Les erreurs ne sont pas affichées à l'utilisateur de façon structurée
- Les validators créés en TASK-015 (`ValidSiret`, `ValidIban`, etc.) ne sont **jamais appelés**

### FormTypes à créer

#### 1. `src/Form/ContactType.php`
```php
// Champs : name*, type*, siret (ValidSiret), tva_intra (ValidTvaIntra),
//          email (Email), billing_email, phone, iban (ValidIban), bic (ValidBic),
//          website (Url), pdp_identifier, notes, adresse embedded
// Contraintes : name NotBlank + Length(max:255), siret longueur 14
```

#### 2. `src/Form/ProductType.php`
```php
// Champs : reference* (unique par tenant), label*, description,
//          unit_price* (Positive), unit, tva_rate (Range 0-100),
//          type (Choice parmi ProductType::cases()), accounting_code, notes
```

#### 3. `src/Form/InvoiceType.php`
```php
// Champs : contact (EntityType), sequence (EntityType), issue_date* (Date),
//          due_date (Date, après issue_date), currency (Choice EUR/USD/GBP),
//          format (Choice parmi InvoiceFormat::cases()), subject, client_reference,
//          client_notes, internal_notes
// Sous-formulaire : lines (CollectionType<InvoiceLineType>)
```

#### 4. `src/Form/InvoiceLineType.php`
```php
// Champs : description* (NotBlank), reference, quantity* (Positive, max 9999),
//          unit (NotBlank), unit_price* (GreaterThanOrEqual 0),
//          discount (Range 0-100), tva_rate (Choice parmi taux légaux FR),
//          is_comment (CheckboxType), product (EntityType nullable)
// Validation contextuelle : si is_comment = false → description obligatoire
```

#### 5. `src/Form/PaymentType.php`
```php
// Champs : amount* (Positive, LessThanOrEqual = restant dû),
//          date* (Date, LessThanOrEqual today), mode* (Choice PaymentMode::cases()),
//          reference, notes
// Contrainte custom : amount ne peut pas dépasser le restant dû
```

#### 6. `src/Form/OrganisationSettingsType.php`
```php
// Champs : name*, siret (ValidSiret), tva_intra (ValidTvaIntra),
//          billing_email (Email), phone, iban (ValidIban), bic (ValidBic),
//          logo (FileType, mimeTypes: image/*, maxSize: 2M), adresse embedded
```

#### 7. `src/Form/PdpSettingsType.php`
```php
// Champs : mode (Choice PdpMode::cases()), pdp_name, endpoint_url (Url),
//          emitter_id, api_key (PasswordType, required=false)
// Validation : si mode=PDP_PARTNER → endpoint_url et emitter_id obligatoires
```

### Intégration dans les controllers
```php
// Remplacer la logique d'hydratation manuelle par :
$form = $this->createForm(ContactType::class, $contact);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $this->em->flush();
    return $this->redirectToRoute('app_contacts_show', ['id' => $contact->getId()]);
}

return $this->render('contacts/edit.html.twig', [
    'contact' => $contact,
    'form'    => $form,
]);
```

### Affichage des erreurs dans les templates
```html
{# Remplacer les <input> manuels par le rendu Symfony Form #}
{{ form_start(form) }}
{{ form_errors(form) }}
<div class="mb-3">
    {{ form_label(form.name) }}
    {{ form_widget(form.name, {'attr': {'class': 'form-control'}}) }}
    {{ form_errors(form.name) }}
</div>
{{ form_end(form) }}
```

> **Note** : Bootstrap 5 form theme est déjà configuré dans `twig.yaml`
> (`form_themes: ['bootstrap_5_layout.html.twig']`).
> Les erreurs seront rendues automatiquement dans le style Bootstrap.

### Critères de validation
- [ ] Création d'un contact avec SIRET invalide → erreur affichée, pas de flush
- [ ] Création d'une facture sans lignes → erreur affichée
- [ ] Paiement avec montant > restant dû → erreur affichée
- [ ] Tous les `ValidSiret`, `ValidIban`, `ValidBic` sont bien appelés via les FormTypes
- [ ] `0 $request->request->all()` dans les controllers couverts par des FormTypes

---

## TASK-F006 — Migration des filtres Twig : |money, |date_fr, |siret_format
**Priorité :** 🟡 MOYENNE
**Statut :** `terminé`
**Estimation :** 2-3 heures

### Problème
Les extensions Twig `MoneyExtension`, `DateFrExtension` et `SiretExtension`
ont été créées en TASK-016 mais **ne sont utilisées nulle part dans les templates**.
Les templates utilisent à la place les filtres natifs répétitifs et non-DRY :

```
45 occurrences de |number_format(2, ',', ' ')  → à migrer vers |money
51 occurrences de |date('d/m/Y')               → à migrer vers |date_fr
0  utilisation de |siret_format sur les SIRETs  → à ajouter
```

### Migrations à effectuer

#### Filtres monétaires
```twig
{# AVANT #}
{{ invoice.totalTtc|number_format(2, ',', ' ') }} €

{# APRÈS #}
{{ invoice.totalTtc|money }}
{# ou avec devise variable : #}
{{ invoice.totalTtc|money(invoice.currency) }}
```

Fichiers concernés (36 occurrences sur 15 templates) :
```
invoices/show.html.twig          (9 occurrences)
tax/index.html.twig              (6 occurrences)
dashboard/index.html.twig        (4 occurrences)
received_invoices/show.html.twig (3 occurrences)
payments/record.html.twig        (3 occurrences)
e_reporting/show.html.twig       (2 occurrences)
+ 9 autres templates              (1 occurrence chacun)
```

#### Filtres de date
```twig
{# AVANT #}
{{ invoice.issueDate|date('d/m/Y') }}
{{ invoice.dueDate|date('d/m/Y H:i') }}

{# APRÈS #}
{{ invoice.issueDate|date_fr }}
{{ invoice.dueDate|date_fr }}         {# "15 janvier 2026" #}
{{ invoice.updatedAt|date_relative }} {# "il y a 3 jours" #}
```

#### Filtres SIRET
```twig
{# AVANT #}
{{ contact.siret }}                      {# "35600000000048" #}

{# APRÈS #}
{{ contact.siret|siret_format }}         {# "356 000 000 00048" #}
```

#### Badges statuts (InvoiceStatusExtension)
```twig
{# AVANT (répété 8 fois dans les templates) #}
<span class="badge badge-{{ invoice.status.value }}">
    {{ invoice.status.label() }}
</span>

{# APRÈS #}
{{ invoice_status_badge(invoice.status) }}
```

### Critères de validation
- [ ] `0` occurrence de `|number_format` dans les templates applicatifs
- [ ] `0` occurrence de `|date('d/m/Y')` dans les templates applicatifs
- [ ] Les SIRET s'affichent formatés "XXX XXX XXX XXXXX" partout
- [ ] Les montants affichent la devise correctement (EUR par défaut)
- [ ] `invoice_status_badge()` remplace les balises `<span class="badge">` manuelles

---

## TASK-F007 — Intégration des bibliothèques JS déclarées dans package.json
**Priorité :** 🟡 MOYENNE
**Statut :** `terminé`
**Estimation :** 1-2 jours

### Problème
`package.json` déclare 13 dépendances mais **aucune n'est utilisée dans les templates** :

| Bibliothèque | Cas d'usage prévu | Statut |
|---|---|---|
| `flatpickr` | Sélecteur de date FR sur tous les `<input type="date">` | ❌ non utilisé |
| `tom-select` | Combobox avancée pour les sélecteurs contact/produit | ❌ non utilisé |
| `chart.js` | Graphique CA dashboard (CDN inline actuellement) | ❌ via CDN |
| `sortablejs` | Réordonnement des lignes de facture | ❌ non utilisé |
| `axios` | Requêtes AJAX dans les Stimulus controllers | ❌ non utilisé |
| `luxon` | Formatage avancé des dates | ❌ non utilisé |
| `dompurify` | Sanitisation HTML avant injection dans le DOM | ❌ non utilisé |

### Ce qu'il faut faire

#### 1. Flatpickr sur les champs de date
Tous les `<input type="date">` natifs ont un rendu différent selon les navigateurs.
Flatpickr est déjà importé dans `app.js` et `window.flatpickr` est exposé.
Il faut créer un Stimulus controller `datepicker_controller.js` :

```javascript
// assets/controllers/datepicker_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = { locale: { type: String, default: 'fr' } }

    connect() {
        this.picker = flatpickr(this.element, {
            dateFormat: "Y-m-d",          // valeur envoyée au serveur
            altInput: true,
            altFormat: "d/m/Y",           // affiché à l'utilisateur
            locale: this.localeValue,
            allowInput: true,
        })
    }

    disconnect() {
        this.picker?.destroy()
    }
}
```

Puis dans les templates, remplacer :
```html
{# AVANT #}
<input type="date" name="issue_date" class="form-control" value="...">

{# APRÈS #}
<input type="date" name="issue_date" class="form-control"
       data-controller="datepicker" value="...">
```

#### 2. Tom Select sur les sélecteurs contact/produit
Les `<select>` de plus de 10 options dans les formulaires de factures
bénéficient d'une recherche intégrée. Créer `select_controller.js` :

```javascript
// assets/controllers/select_controller.js
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        placeholder: { type: String, default: "Sélectionner..." },
        searchable: { type: Boolean, default: true },
    }

    connect() {
        this.tomSelect = new TomSelect(this.element, {
            placeholder: this.placeholderValue,
            allowEmptyOption: true,
            sortField: { field: "text", direction: "asc" },
        })
    }

    disconnect() {
        this.tomSelect?.destroy()
    }
}
```

#### 3. Chart.js : remplacer le CDN par le bundle
Dans `dashboard/index.html.twig` et `tax/index.html.twig`, supprimer :
```html
{# SUPPRIMER le lazy-load CDN #}
<script>
const s = document.createElement('script');
s.src = 'https://cdnjs.cloudflare.com/.../chart.umd.min.js';
...
</script>
```
Remplacer par le `chart_controller.js` Stimulus déjà créé :
```html
<canvas data-controller="chart"
        data-chart-config-value="{{ chartConfig|json_encode }}">
</canvas>
```

#### 4. SortableJS pour les lignes de facture
À intégrer dans `InvoiceEditor.vue` (TASK-F003) pour le drag & drop des lignes.

### Critères de validation
- [ ] Les champs de date affichent le format français (jj/mm/aaaa)
- [ ] Les sélecteurs contact/produit ont une recherche intégrée
- [ ] Chart.js chargé depuis le bundle (pas CDN) dans le dashboard
- [ ] `0` balise `<script>` lazy-loadant Chart.js depuis CDN dans les templates

---

## TASK-F008 — UX : Turbo Streams sur les actions POST critiques
**Priorité :** 🟡 MOYENNE
**Statut :** `terminé`
**Estimation :** 2-3 jours

### Problème
Toutes les actions POST redirigent vers une URL avec `redirectToRoute()`.
Résultat : **rechargement complet de la page** pour chaque action.
Hotwire Turbo est inclus dans le bundle mais les **Turbo Streams sont à 0 utilisation**.

### Actions candidates aux Turbo Streams

#### 1. Marquer une notification comme lue (déjà partiellement fait en AJAX)
```php
// NotificationController::markRead()
// Actuellement : retourne HTTP 204 (no content)
// Objectif : retourner un TurboStream qui retire la notification de la liste

use Symfony\UX\Turbo\TurboBundle;

public function markRead(Notification $notif): Response
{
    $notif->setReadAt(new \DateTimeImmutable());
    $this->em->flush();

    if ($request->headers->get('Accept') === TurboBundle::STREAM_MEDIA_TYPE) {
        return $this->render('notifications/_remove_item.stream.html.twig', [
            'notif' => $notif,
        ], new Response(headers: ['Content-Type' => TurboBundle::STREAM_MEDIA_TYPE]));
    }

    return new Response('', 204);
}
```

```twig
{# templates/notifications/_remove_item.stream.html.twig #}
<turbo-stream action="remove" target="notif-{{ notif.id }}"></turbo-stream>
<turbo-stream action="replace" target="unread-badge">
    {# badge mis à jour #}
</turbo-stream>
```

#### 2. Paiement enregistré → mise à jour inline du reste dû
```php
// PaymentController::record() — retourner un stream au lieu d'une redirect
// La section "Montants" de invoices/show.html.twig est mise à jour
// sans recharger toute la page
```

#### 3. Validation de facture → badge statut mis à jour
```twig
{# Turbo Frame autour du badge de statut dans invoices/show.html.twig #}
<turbo-frame id="invoice-status-{{ invoice.id }}">
    <span class="badge badge-{{ invoice.status.value }}">{{ invoice.status.label() }}</span>
</turbo-frame>
```

#### 4. Compteur de notifications dans le header
```twig
{# _header.html.twig — actualiser toutes les 30s via Turbo Frame avec src= #}
<turbo-frame id="notifications-count"
             src="{{ path('app_notifications_api_count') }}"
             refresh="morph">
    {# chargé automatiquement par Turbo #}
</turbo-frame>
```

### Critères de validation
- [ ] Marquer notification lue : suppression instantanée sans rechargement
- [ ] Badge compteur notifications rafraîchi automatiquement
- [ ] Enregistrement paiement : montant restant dû mis à jour inline
- [ ] Aucun rechargement de page complet pour ces 3 actions

---

## TASK-F009 — SCSS : compléter les styles manquants
**Priorité :** 🟡 MOYENNE
**Statut :** `terminé`
**Estimation :** 1 jour

### Problème
Le fichier SCSS actuel (151 lignes) ne couvre que le layout de base.
Des composants utilisés dans les templates n'ont **aucun style dédié** :

| Composant manquant | Utilisé dans |
|---|---|
| `.avatar` (initiales utilisateur) | `_header.html.twig` (ligne 41) |
| Responsive tables (scroll horizontal) | tous les tableaux sur mobile |
| Formulaires — état focus/error/success | tous les formulaires |
| Skeleton loaders | chargements async (Turbo Frames) |
| Page d'erreur 404/500 | aucun template d'erreur Symfony |
| Print CSS — facture PDF screen | `templates/pdf/invoice.html.twig` |
| Breadcrumb settings pages | `settings/_nav.html.twig` |
| États vides (empty states) | listes sans données |

### Ce qu'il faut faire

#### Sections à ajouter dans `app.scss`

```scss
// ── Avatar (initiales utilisateur header) ─────────────────────────────────
.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-size: .75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: $primary;
    color: #fff;
    text-transform: uppercase;
}

// ── Tables responsive mobile ─────────────────────────────────────────────
.table-responsive {
    @include media-breakpoint-down(md) {
        -webkit-overflow-scrolling: touch;
    }
}

// ── Empty states ─────────────────────────────────────────────────────────
.empty-state {
    padding: 4rem 2rem;
    text-align: center;
    color: $secondary;

    &__icon { opacity: .25; margin-bottom: 1rem; }
    &__title { font-weight: 600; margin-bottom: .5rem; }
    &__description { font-size: .875rem; }
}

// ── Skeleton loader ───────────────────────────────────────────────────────
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
    border-radius: $border-radius;
    height: 1em;
}

@keyframes skeleton-loading {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

// ── Print (facture) ────────────────────────────────────────────────────────
@media print {
    .app-sidebar, .app-header, .no-print { display: none !important; }
    .app-content { margin-left: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
}
```

### Critères de validation
- [ ] Avatar avec initiales visible dans le header sur tous les navigateurs
- [ ] Les tableaux sont scrollables horizontalement sur mobile (< 768px)
- [ ] Les états vides ont un rendu cohérent avec icône + texte
- [ ] `@media print` masque sidebar et header lors de l'impression d'une facture

---

## TASK-F010 — Tests PHPUnit : couverture des controllers web et services manquants
**Priorité :** 🟠 HAUTE
**Statut :** `terminé`
**Estimation :** 2-3 jours

### Problème
La couverture de tests est partielle. 11 features sur 23 ne sont pas testées :

| Feature non testée | Type de test recommandé |
|---|---|
| `InvoiceDuplicateService` | Unitaire |
| `EReportingAggregatorService` | Unitaire |
| `ExportService` (FEC + CSV) | Unitaire |
| `ValidBic` + `ValidTvaIntra` validators | Unitaire |
| `NotificationService` | Unitaire |
| `StripeService` | Unitaire (avec mock HttpClient) |
| `PdpDispatchService` | Unitaire (avec mock HttpClient) |
| `ContactController` (web) | Fonctionnel WebTestCase |
| `ProductController` (web) | Fonctionnel WebTestCase |
| `ApiContactController` (API) | Fonctionnel WebTestCase |
| `PlanLimitCheckerIntegration` | Intégration KernelTestCase |

### Ce qu'il faut créer

#### Tests unitaires

**`InvoiceDuplicateServiceTest`**
- `duplicate()` crée une copie DRAFT sans numéro avec les mêmes lignes
- `duplicate()` n'inclut pas les paiements
- `createCreditNote()` inverse les montants
- `createCreditNote()` échoue si statut non ACKNOWLEDGED/PAID

**`EReportingAggregatorServiceTest`**
- `aggregate()` crée des transactions par type (B2C, INTRACOM, EXPORT)
- `aggregate()` génère un lot "nil" si aucune transaction
- `calculateDeadline()` : +1 mois à la fin de période (DataProvider 4 périodes)
- `detectPeriodicity()` : "2026-09" → MONTHLY, "2026-T3" → QUARTERLY

**`ExportServiceTest`**
- `generateFecContent()` produit 18 colonnes pipe-séparées
- L'en-tête FEC contient exactement les colonnes art. A47 A-1 CGI
- `generateCsvContent()` produit des lignes semicolon-séparées
- `fecAmount()` convertit "1234.56" → "1234,56"
- `sanitizeFec()` supprime les pipes et retours chariot

**`StripeServiceTest`** (mock HttpClientInterface)
- `constructWebhookEvent()` valide signature HMAC correcte
- `constructWebhookEvent()` rejette signature expirée (> 300s)
- `constructWebhookEvent()` rejette signature incorrecte
- `mapPriceIdToPlan()` retourne Plan::PRO pour `$stripePricePro`
- `syncSubscription()` downgrade vers FREE si status = "canceled"

#### Tests fonctionnels

**`ContactControllerTest`** (WebTestCase)
- Accès liste sans auth → redirect /login
- Création contact avec SIRET invalide → 422 (quand FormType ajouté)
- Création contact valide → 201 + redirect
- Modification contact autre tenant → 403/404

**`ApiContactControllerTest`** (WebTestCase)
- `GET /api/contacts` retourne pagination + X-Total-Count
- `GET /api/contacts?type=client` filtre correctement
- `POST /api/contacts` avec données invalides → 422 RFC 7807
- `POST /api/contacts` avec données valides → 201 avec l'objet créé

#### Test d'intégration

**`PlanLimitCheckerIntegrationTest`** (KernelTestCase + vraie DB)
- Crée 20 factures sur un tenant FREE → `canCreateInvoice()` retourne false
- Crée 19 factures → `canCreateInvoice()` retourne true
- `getUsageSummary()` retourne les bons pourcentages

### Objectif : 250+ assertions au total (actuellement 188)

### Critères de validation
- [ ] `php bin/phpunit --testsuite Unit` → 100% vert
- [ ] `php bin/phpunit --testsuite Functional` → 100% vert
- [ ] 250+ assertions au total
- [ ] `ValidBic` et `ValidTvaIntra` sont bien testés
- [ ] `StripeService::constructWebhookEvent()` testé contre les 3 cas d'échec

---

## TASK-F011 — Performance : lazy loading et optimisations
**Priorité :** 🟢 BASSE
**Statut :** `nouveau`
**Estimation :** 1 jour

### Problème
Plusieurs requêtes N+1 identifiées dans les templates et des chargements
inutiles de données en mémoire.

### Ce qu'il faut faire

#### 1. Requêtes N+1 à corriger

**`InvoiceRepository::findByFilters()`** : charge les contacts en N requêtes
```php
// Ajouter JOIN dans la requête DQL
$qb->leftJoin('i.contact', 'c')->addSelect('c')
   ->leftJoin('i.lines', 'l')->addSelect('l');
```

**`ContactRepository::findAllActive()`** : lazy-load des adresses
```php
// Utiliser FETCH JOIN pour l'embeddable Address
$qb->addSelect('a') // Address est un embeddable, pas besoin de JOIN
```

#### 2. Pagination des listes admin
`TenantRepository::findAllWithStats()` retourne **tous** les tenants en mémoire.
Ajouter une pagination avec `Paginator` Doctrine.

#### 3. Cache des extensions Twig
`MoneyExtension::formatMoney()` crée un `NumberFormatter` à chaque appel.
```php
// Utiliser un cache interne
private array $formatters = [];

public function formatMoney(string|float|null $amount, string $currency = 'EUR'): string
{
    $this->formatters[$currency] ??= new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);
    return $this->formatters[$currency]->formatCurrency((float) $amount, $currency);
}
```

#### 4. HTTP caching sur les assets statiques
Dans `config/packages/framework.yaml` :
```yaml
framework:
    assets:
        version_strategy: 'Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy'
        json_manifest_path: '%kernel.project_dir%/public/build/manifest.json'
```

### Critères de validation
- [ ] `EXPLAIN` sur la requête `findByFilters` montre 0 requête N+1
- [ ] La liste admin est paginée (max 50 tenants en mémoire)
- [ ] `NumberFormatter` instancié 1 seule fois par devise par requête

---

## TASK-F012 — Documentation API : OpenAPI / Swagger
**Priorité :** 🟢 BASSE
**Statut :** `nouveau`
**Estimation :** 1 jour

### Problème
L'API REST (7 controllers, ~20 endpoints) n'a pas de documentation générée.
Les développeurs qui intègrent l'API doivent lire le code source.

### Ce qu'il faut faire

#### 1. Installer NelmioApiDocBundle
```bash
composer require nelmio/api-doc-bundle
```

#### 2. Annoter les controllers API
```php
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

#[OA\Tag(name: 'Invoices')]
#[Route('/api/invoices', name: 'api_invoices_')]
final class ApiInvoiceController extends AbstractApiController
{
    #[OA\Get(
        path: '/api/invoices',
        summary: 'Liste les factures émises',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succès',
                headers: [new OA\Header(header: 'X-Total-Count', schema: new OA\Schema(type: 'integer'))]),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function list(Request $request): JsonResponse { ... }
```

#### 3. Configurer `nelmio_api_doc.yaml`
```yaml
nelmio_api_doc:
    areas:
        path_patterns: ['^/api']
    documentation:
        info:
            title: 'API Facturation Électronique'
            description: 'API REST pour la gestion des factures, contacts et abonnements.'
            version: '1.0.0'
        securitySchemes:
            BearerAuth:
                type: http
                scheme: bearer
                bearerFormat: JWT
        security:
            - BearerAuth: []
```

#### 4. Créer `docs/api/` avec exemples curl
```markdown
# Authentification
curl -X POST /api/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.test","password":"password"}'

# Lister les factures
curl /api/invoices \
  -H "Authorization: Bearer {TOKEN}"
```

### Critères de validation
- [ ] `GET /api/doc` retourne une UI Swagger interactive
- [ ] Tous les endpoints sont documentés avec leurs paramètres
- [ ] Les schémas de réponse correspondent au code réel
- [ ] Un exemple `curl` fonctionne depuis la documentation

---

## Récapitulatif et ordre de traitement recommandé

### Sprint 1 — Faire fonctionner (1-2 jours)
| Task | Priorité | Estimation |
|---|---|---|
| **TASK-F001** — Build npm (entry points + compilation) | 🔴 BLOQUANT | 2-3h |
| **TASK-F002** — Stimulus sidebar_controller + controllers.json | 🔴 BLOQUANT | 1-2h |

### Sprint 2 — Sécuriser (3-4 jours)
| Task | Priorité | Estimation |
|---|---|---|
| **TASK-F004** — CSRF sur 23 templates | 🔴 BLOQUANT | 3-4h |
| **TASK-F005** — FormTypes + validation serveur | 🟠 HAUTE | 2-3 jours |

### Sprint 3 — Fonctionnalité Vue 3 + Tests (3-4 jours)
| Task | Priorité | Estimation |
|---|---|---|
| **TASK-F003** — Vue 3 éditeur de lignes | 🟠 HAUTE | 2-3 jours |
| **TASK-F010** — Tests PHPUnit manquants | 🟠 HAUTE | 2-3 jours |

### Sprint 4 — Qualité & UX (3-4 jours)
| Task | Priorité | Estimation |
|---|---|---|
| **TASK-F006** — Migrer filtres Twig | 🟡 MOYENNE | 2-3h |
| **TASK-F007** — Intégrer bibliothèques JS | 🟡 MOYENNE | 1-2 jours |
| **TASK-F008** — Turbo Streams | 🟡 MOYENNE | 2-3 jours |
| **TASK-F009** — SCSS composants manquants | 🟡 MOYENNE | 1 jour |

### Sprint 5 — Optimisation & Documentation (2 jours)
| Task | Priorité | Estimation |
|---|---|---|
| **TASK-F011** — Performance N+1 + caches | 🟢 BASSE | 1 jour |
| **TASK-F012** — Documentation OpenAPI | 🟢 BASSE | 1 jour |

---

## Journal des modifications

| Date | Task | Action |
|---|---|---|
| 2026-05-30 | TASK-F003 migration | Suppression Vue 3 — migration vers invoice_lines_controller.js (Stimulus) |
| 2026-05-30 | TASK-F003 | Terminé — InvoiceEditor.vue + InvoiceLine.vue + SortableJS drag&drop + catalog lookup |
| 2026-05-30 | TASK-F006 | Terminé — 32 |money, 20 |date_fr, 6 |siret_format, 6 invoice_status_badge migrés |
| 2026-05-30 | TASK-F007 | Terminé — datepicker (7 champs), TomSelect (12 selects), Chart.js bundle (CDN supprimé) |
| 2026-05-30 | TASK-F008 | Terminé — Turbo Streams notifications + statut facture |
| 2026-05-30 | TASK-F009 | Terminé — 419 lignes SCSS : avatar, empty-state, skeleton, print, responsive, toast |
| 2026-05-30 | TASK-F003 migration | Suppression Vue 3 — migration vers invoice_lines_controller.js (Stimulus) |
| 2026-05-30 | TASK-F003 | Terminé — InvoiceEditor.vue + InvoiceLine.vue + SortableJS drag&drop + catalog lookup |
| 2026-05-30 | TASK-F006/F007/F008/F009 | Terminées — filtres Twig, datepicker+select+chart câblés, Turbo Streams, SCSS 646L |
| 2026-05-30 | TASK-F010 | Terminé — 21 fichiers de test, 351 assertions (objectif 250 dépassé) |
| 2026-05-30 | TASK-F004 résiduel | 6 controllers web corrigés (InvoiceController, TaxController, ReceivedInvoiceController, NotificationController, AdminController, EReportingController) |
| 2026-05-30 | TASK-F005 | 8 FormTypes créés + 4 controllers refactorisés, validators TASK-015 désormais actifs |
| 2026-05-30 | TASK-F001 + F002 + F004 | Terminées — Build npm entry points, sidebar_controller, CSRF 23 templates + 9 controllers |
| 2026-05-30 | TASK-F001 à TASK-F012 | Création initiale suite à l'analyse frontend complète |
