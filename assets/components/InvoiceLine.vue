<template>
  <tr
    :class="['invoice-line', { 'is-comment': line.is_comment, 'dragging': isDragging }]"
    :data-index="index"
  >
    <!-- ── Poignée drag & drop ─────────────────────────────────────── -->
    <td class="drag-handle text-muted ps-2" style="width:28px;cursor:grab">
      <svg width="12" height="16" viewBox="0 0 12 16" fill="currentColor" opacity=".4">
        <circle cx="3" cy="3" r="1.5"/><circle cx="9" cy="3" r="1.5"/>
        <circle cx="3" cy="8" r="1.5"/><circle cx="9" cy="8" r="1.5"/>
        <circle cx="3" cy="13" r="1.5"/><circle cx="9" cy="13" r="1.5"/>
      </svg>
    </td>

    <!-- ── Ligne commentaire ───────────────────────────────────────── -->
    <template v-if="line.is_comment">
      <td colspan="6" class="py-2 px-2">
        <input
          type="text"
          v-model="line.description"
          class="form-control form-control-sm font-italic"
          placeholder="Commentaire, sous-titre de section…"
        />
      </td>
      <td class="text-end text-muted small px-2">—</td>
    </template>

    <!-- ── Ligne normale ───────────────────────────────────────────── -->
    <template v-else>

      <!-- Désignation + catalogue -->
      <td class="py-2 px-2" style="min-width:200px">
        <!-- Sélecteur catalogue (si des produits sont disponibles) -->
        <select
          v-if="products.length > 0"
          v-model="line.product_id"
          @change="fillFromCatalog"
          class="form-select form-select-sm mb-1"
        >
          <option value="">— Catalogue —</option>
          <option
            v-for="p in products"
            :key="p.id"
            :value="p.id"
          >{{ p.label }} ({{ p.reference }})</option>
        </select>

        <!-- Description principale -->
        <input
          type="text"
          v-model="line.description"
          class="form-control form-control-sm"
          :class="{ 'is-invalid': showErrors && !line.description.trim() }"
          placeholder="Description *"
        />
        <div v-if="showErrors && !line.description.trim()" class="invalid-feedback">
          La description est obligatoire.
        </div>

        <!-- Référence (optionnel) -->
        <input
          type="text"
          v-model="line.reference"
          class="form-control form-control-sm mt-1"
          placeholder="Référence (optionnel)"
        />
      </td>

      <!-- Quantité -->
      <td class="py-2 px-1" style="width:80px">
        <input
          type="number"
          v-model="line.quantity"
          @input="emit('recalculate')"
          class="form-control form-control-sm text-end"
          :class="{ 'is-invalid': showErrors && parseFloat(line.quantity) <= 0 }"
          min="0.01"
          step="0.01"
        />
      </td>

      <!-- Unité -->
      <td class="py-2 px-1" style="width:60px">
        <input
          type="text"
          v-model="line.unit"
          class="form-control form-control-sm text-center"
          placeholder="U"
          maxlength="10"
        />
      </td>

      <!-- Prix unitaire HT -->
      <td class="py-2 px-1" style="width:105px">
        <input
          type="number"
          v-model="line.unit_price"
          @input="emit('recalculate')"
          class="form-control form-control-sm text-end"
          min="0"
          step="0.0001"
        />
      </td>

      <!-- Remise % -->
      <td class="py-2 px-1" style="width:70px">
        <input
          type="number"
          v-model="line.discount"
          @input="emit('recalculate')"
          class="form-control form-control-sm text-end"
          min="0"
          max="100"
          step="0.01"
          placeholder="0"
        />
      </td>

      <!-- TVA % -->
      <td class="py-2 px-1" style="width:80px">
        <select
          v-model="line.tva_rate"
          @change="emit('recalculate')"
          class="form-select form-select-sm"
        >
          <option v-for="r in TVA_RATES" :key="r.value" :value="r.value">
            {{ r.label }}
          </option>
        </select>
      </td>

      <!-- Montant HT calculé (lecture seule) -->
      <td class="py-2 px-2 text-end fw-semibold small align-middle" style="width:95px">
        {{ fmtMoney(amountHt) }}
      </td>

    </template>

    <!-- ── Supprimer la ligne ───────────────────────────────────────── -->
    <td class="py-2 px-1 text-center align-middle" style="width:36px">
      <button
        type="button"
        class="btn btn-link btn-sm text-danger p-0 lh-1"
        @click="emit('remove')"
        title="Supprimer"
        :disabled="isOnlyLine"
      >
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
      </button>
    </td>

  </tr>
</template>

<script setup>
import { computed } from 'vue'

// ── Props & Emits ────────────────────────────────────────────────────────────

const props = defineProps({
  /** Objet ligne (v-model) */
  line:       { type: Object, required: true },
  /** Index dans le tableau des lignes */
  index:      { type: Number, required: true },
  /** Catalogue produits [{id, label, reference, unit_price, unit, tva_rate}] */
  products:   { type: Array,  default: () => [] },
  /** Afficher les erreurs (après tentative de soumission) */
  showErrors: { type: Boolean, default: false },
  /** Empêcher la suppression si c'est la seule ligne */
  isOnlyLine: { type: Boolean, default: false },
  /** En cours de drag */
  isDragging: { type: Boolean, default: false },
})

const emit = defineEmits(['update:line', 'remove', 'recalculate'])

// ── Taux TVA légaux FR ────────────────────────────────────────────────────────

const TVA_RATES = [
  { value: '20.00', label: '20 %' },
  { value: '10.00', label: '10 %' },
  { value: '5.50',  label: '5,5 %' },
  { value: '2.10',  label: '2,1 %' },
  { value: '0.00',  label: '0 %' },
]

// ── Calcul montant HT ─────────────────────────────────────────────────────────

const amountHt = computed(() => {
  if (props.line.is_comment) return 0
  const qty   = parseFloat(props.line.quantity)   || 0
  const price = parseFloat(props.line.unit_price) || 0
  const disc  = parseFloat(props.line.discount)   || 0
  return Math.round(qty * price * (1 - disc / 100) * 100) / 100
})

// ── Catalogue — pré-remplissage ───────────────────────────────────────────────

function fillFromCatalog () {
  const product = props.products.find(p => p.id === props.line.product_id)
  if (!product) return

  // Mettre à jour la ligne via l'objet (réactivité Vue 3)
  props.line.description = product.label
  props.line.unit_price  = product.unit_price
  props.line.unit        = product.unit
  props.line.tva_rate    = product.tva_rate
  props.line.reference   = product.reference ?? ''
  emit('recalculate')
}

// ── Format monétaire ─────────────────────────────────────────────────────────

function fmtMoney (value) {
  return value.toFixed(2).replace('.', ',') + ' €'
}
</script>
