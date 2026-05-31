<template>
  <div class="invoice-editor">

    <!-- ── Tableau des lignes ─────────────────────────────────────────── -->
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-2">
        <thead class="table-light">
          <tr>
            <th style="width:28px"></th><!-- drag handle -->
            <th>Désignation</th>
            <th class="text-end" style="width:80px">Qté</th>
            <th class="text-center" style="width:60px">Unité</th>
            <th class="text-end" style="width:105px">PU HT</th>
            <th class="text-end" style="width:70px">Rem. %</th>
            <th style="width:80px">TVA</th>
            <th class="text-end" style="width:95px">HT</th>
            <th style="width:36px"></th><!-- supprimer -->
          </tr>
        </thead>
        <tbody ref="tbodyRef">
          <InvoiceLine
            v-for="(line, idx) in lines"
            :key="line._key"
            :line="line"
            :index="idx"
            :products="products"
            :show-errors="showErrors"
            :is-only-line="lines.length === 1 && !line.is_comment"
            :is-dragging="draggingIndex === idx"
            @remove="removeLine(idx)"
            @recalculate="computeTotals"
          />
        </tbody>
      </table>
    </div>

    <!-- ── Boutons d'ajout ────────────────────────────────────────────── -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
      <button type="button" class="btn btn-sm btn-outline-primary" @click="addLine">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20" class="me-1">
          <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
        </svg>
        Ajouter une ligne
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary" @click="addComment">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20" class="me-1">
          <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/>
        </svg>
        Commentaire
      </button>
    </div>

    <!-- ── Totaux ─────────────────────────────────────────────────────── -->
    <div class="row justify-content-end">
      <div class="col-md-5 col-lg-4">
        <div class="card border-0 bg-light">
          <div class="card-body py-3 px-4">

            <!-- Total HT -->
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Total HT</span>
              <span class="fw-semibold" id="vue-total-ht">{{ fmtMoney(totalHt) }}</span>
            </div>

            <!-- TVA par taux -->
            <div
              v-for="(amounts, rate) in tvaBreakdown"
              :key="rate"
              class="d-flex justify-content-between mb-1 text-muted small"
            >
              <span>TVA {{ rate }}%</span>
              <span>{{ fmtMoney(amounts.tva) }}</span>
            </div>

            <hr class="my-2" />

            <!-- Total TTC -->
            <div class="d-flex justify-content-between fw-bold fs-6">
              <span>Total TTC</span>
              <span class="text-primary" id="vue-total-ttc">{{ fmtMoney(totalTtc) }}</span>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- ── Hidden inputs pour soumission POST ─────────────────────────── -->
    <!-- Vue 3 gère l'état ; ces inputs transmettent les valeurs au controller PHP -->
    <template v-for="(line, idx) in lines" :key="'hidden-' + line._key">
      <input type="hidden" :name="`lines[${idx}][description]`" :value="line.description" />
      <input type="hidden" :name="`lines[${idx}][reference]`"   :value="line.reference ?? ''" />
      <input type="hidden" :name="`lines[${idx}][quantity]`"    :value="line.is_comment ? '0' : line.quantity" />
      <input type="hidden" :name="`lines[${idx}][unit]`"        :value="line.unit" />
      <input type="hidden" :name="`lines[${idx}][unit_price]`"  :value="line.is_comment ? '0' : line.unit_price" />
      <input type="hidden" :name="`lines[${idx}][discount]`"    :value="line.is_comment ? '0' : (line.discount || '0')" />
      <input type="hidden" :name="`lines[${idx}][tva_rate]`"    :value="line.is_comment ? '0' : line.tva_rate" />
      <input type="hidden" :name="`lines[${idx}][is_comment]`"  :value="line.is_comment ? '1' : '0'" />
      <input type="hidden" :name="`lines[${idx}][product_id]`"  :value="line.product_id ?? ''" />
      <input type="hidden" :name="`lines[${idx}][position]`"    :value="idx" />
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import Sortable from 'sortablejs'
import InvoiceLine from './InvoiceLine.vue'

// ── Props ────────────────────────────────────────────────────────────────────

const props = defineProps({
  /** Lignes initiales sérialisées depuis Twig (mode édition) */
  initialLines: { type: Array, default: () => [] },
  /** Catalogue produits passé via data-products dans le template */
  initialProducts: { type: Array, default: () => [] },
})

// ── État ─────────────────────────────────────────────────────────────────────

const lines        = ref([])
const products     = ref(props.initialProducts)
const showErrors   = ref(false)
const draggingIndex = ref(null)
const tbodyRef     = ref(null)

// ── Initialisation ────────────────────────────────────────────────────────────

onMounted(() => {
  // Charger les lignes initiales (mode édition) ou une ligne vide
  if (props.initialLines.length > 0) {
    lines.value = props.initialLines.map(l => ({ ...l, _key: genKey() }))
  } else {
    lines.value = [createEmptyLine()]
  }

  // Initialiser le drag & drop SortableJS
  nextTick(() => {
    if (tbodyRef.value) {
      Sortable.create(tbodyRef.value, {
        handle:    '.drag-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onStart: (evt) => { draggingIndex.value = evt.oldIndex },
        onEnd:   (evt) => {
          draggingIndex.value = null
          // Réordonner le tableau Vue selon le déplacement DOM
          const moved = lines.value.splice(evt.oldIndex, 1)[0]
          lines.value.splice(evt.newIndex, 0, moved)
        },
      })
    }
  })

  // Synchroniser les totaux dans la sidebar Twig (éléments statiques)
  computeTotals()
})

// ── Calculs ───────────────────────────────────────────────────────────────────

const totalHt = computed(() =>
  lines.value.reduce((sum, l) => sum + calcLineHt(l), 0)
)

const totalTva = computed(() =>
  lines.value.reduce((sum, l) => {
    if (l.is_comment) return sum
    const ht  = calcLineHt(l)
    const tva = parseFloat(l.tva_rate) || 0
    return sum + Math.round(ht * tva / 100 * 100) / 100
  }, 0)
)

const totalTtc = computed(() =>
  Math.round((totalHt.value + totalTva.value) * 100) / 100
)

const tvaBreakdown = computed(() => {
  const map = {}
  lines.value.forEach(l => {
    if (l.is_comment) return
    const rate = l.tva_rate || '20.00'
    const ht   = calcLineHt(l)
    const tva  = Math.round(ht * parseFloat(rate) / 100 * 100) / 100
    if (!map[rate]) map[rate] = { base: 0, tva: 0 }
    map[rate].base = Math.round((map[rate].base + ht)  * 100) / 100
    map[rate].tva  = Math.round((map[rate].tva  + tva) * 100) / 100
  })
  // Trier par taux décroissant (20% en premier)
  return Object.fromEntries(
    Object.entries(map).sort((a, b) => parseFloat(b[0]) - parseFloat(a[0]))
  )
})

function computeTotals () {
  // Mettre à jour aussi les éléments statiques de la sidebar Twig
  // pour que les totaux s'affichent même si l'utilisateur a désactivé JS partiellement
  const htEl  = document.getElementById('total-ht')
  const tvaEl = document.getElementById('total-tva')
  const ttcEl = document.getElementById('total-ttc')
  if (htEl)  htEl.textContent  = fmtMoney(totalHt.value)
  if (tvaEl) tvaEl.textContent = fmtMoney(totalTva.value)
  if (ttcEl) ttcEl.textContent = fmtMoney(totalTtc.value)
}

// ── Mutations ────────────────────────────────────────────────────────────────

function addLine () {
  lines.value.push(createEmptyLine())
}

function addComment () {
  lines.value.push(createCommentLine())
}

function removeLine (idx) {
  if (lines.value.length <= 1) return
  lines.value.splice(idx, 1)
  computeTotals()
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function createEmptyLine () {
  return {
    _key:        genKey(),
    description: '',
    reference:   '',
    quantity:    '1',
    unit:        'U',
    unit_price:  '0.00',
    discount:    '0',
    tva_rate:    '20.00',
    is_comment:  false,
    product_id:  null,
  }
}

function createCommentLine () {
  return { ...createEmptyLine(), is_comment: true, quantity: '0', unit_price: '0.00', tva_rate: '0.00' }
}

function calcLineHt (line) {
  if (line.is_comment) return 0
  const qty   = parseFloat(line.quantity)   || 0
  const price = parseFloat(line.unit_price) || 0
  const disc  = parseFloat(line.discount)   || 0
  return Math.round(qty * price * (1 - disc / 100) * 100) / 100
}

function fmtMoney (value) {
  return value.toFixed(2).replace('.', ',') + ' €'
}

let _keyCounter = 0
function genKey () { return ++_keyCounter }

// ── Exposition pour validation externe ───────────────────────────────────────

defineExpose({ lines, totalHt, totalTtc, showErrors })
</script>

<style scoped>
/* Drag & drop */
.sortable-ghost   { opacity: .3; background: #e0edff; }
.sortable-chosen  { box-shadow: 0 2px 12px rgba(37,99,235,.15); }
.drag-handle:hover { opacity: 1 !important; color: #2563eb; }

/* Ligne commentaire */
.is-comment td { background: rgba(14,165,233,.04); }
.is-comment input { font-style: italic; color: #64748b; }

/* Responsive — cacher colonnes secondaires sur mobile */
@media (max-width: 576px) {
  .table th:nth-child(4),
  .table td:nth-child(4),
  .table th:nth-child(5),
  .table td:nth-child(5) { display: none; }
}
</style>
