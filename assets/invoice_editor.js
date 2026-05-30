/*
 * assets/invoice_editor.js
 * Entry point Webpack Encore pour l'éditeur de lignes de factures (Vue 3)
 * Chargé via {{ encore_entry_script_tags('invoice_editor') }}
 * dans templates/invoices/new.html.twig et edit.html.twig
 *
 * Voir TASK-F003 pour l'implémentation complète des composants Vue 3.
 * Ce stub permet au build webpack de passer sans erreur.
 */

import { createApp, ref, computed } from 'vue';

/**
 * Stub minimal — éditeur de lignes de facture.
 * Remplace le JavaScript vanilla inline dans new.html.twig / edit.html.twig.
 *
 * Fonctionnalités du stub :
 *  - Recalcul HT/TVA/TTC en temps réel sur les champs existants
 *  - Gestion add/remove ligne via les boutons déjà dans le DOM
 *  - Compatible avec le formulaire HTML existant (pas de rupture)
 *
 * L'implémentation Vue 3 complète (InvoiceEditor.vue, InvoiceLine.vue)
 * sera faite dans TASK-F003.
 */

// ── Montage sur les pages factures ──────────────────────────────────────────

document.addEventListener('turbo:load', initInvoiceEditor);
document.addEventListener('DOMContentLoaded', initInvoiceEditor);

function initInvoiceEditor() {
    const mount = document.getElementById('invoice-editor-app');
    if (!mount || mount.dataset.vueInitialized) return;
    mount.dataset.vueInitialized = 'true';

    // Lire les lignes initiales depuis data-lines (mode édition)
    let initialLines = [];
    try {
        initialLines = JSON.parse(mount.dataset.lines || '[]');
    } catch (e) {
        console.warn('[invoice_editor] Impossible de parser data-lines', e);
    }

    const app = createApp({
        setup() {
            const lines = ref(initialLines.length > 0 ? initialLines : [createEmptyLine()]);

            // ── Computed totaux ───────────────────────────────────────────
            const totalHt = computed(() =>
                lines.value.reduce((sum, l) => sum + calcLineHt(l), 0)
            );

            const totalTva = computed(() =>
                lines.value.reduce((sum, l) => {
                    const ht  = calcLineHt(l);
                    const tva = parseFloat(l.tva_rate) || 0;
                    return sum + Math.round(ht * tva / 100 * 100) / 100;
                }, 0)
            );

            const totalTtc = computed(() =>
                Math.round((totalHt.value + totalTva.value) * 100) / 100
            );

            const tvaBreakdown = computed(() => {
                const map = {};
                lines.value.forEach(l => {
                    if (l.is_comment) return;
                    const rate = l.tva_rate || '20.00';
                    const ht   = calcLineHt(l);
                    const tva  = Math.round(ht * parseFloat(rate) / 100 * 100) / 100;
                    if (!map[rate]) map[rate] = { base: 0, tva: 0 };
                    map[rate].base = Math.round((map[rate].base + ht) * 100) / 100;
                    map[rate].tva  = Math.round((map[rate].tva + tva) * 100) / 100;
                });
                return map;
            });

            // ── Actions ───────────────────────────────────────────────────
            function addLine() {
                lines.value.push(createEmptyLine());
            }

            function addComment() {
                lines.value.push({ ...createEmptyLine(), is_comment: true });
            }

            function removeLine(idx) {
                if (lines.value.length <= 1) return;
                lines.value.splice(idx, 1);
            }

            // ── Helpers ───────────────────────────────────────────────────
            function fmt(v) {
                return v.toFixed(2).replace('.', ',') + ' €';
            }

            return {
                lines,
                totalHt,
                totalTva,
                totalTtc,
                tvaBreakdown,
                addLine,
                addComment,
                removeLine,
                fmt,
            };
        },

        template: `
<div class="invoice-editor">

    <!-- Tableau des lignes -->
    <div class="table-responsive mb-2">
        <table class="table align-middle mb-0" id="lines-table">
            <thead class="table-light">
                <tr>
                    <th style="width:32%">Désignation</th>
                    <th style="width:9%" class="text-end">Qté</th>
                    <th style="width:7%">Unité</th>
                    <th style="width:13%" class="text-end">PU HT</th>
                    <th style="width:8%" class="text-end">Rem. %</th>
                    <th style="width:8%" class="text-end">TVA %</th>
                    <th style="width:13%" class="text-end">Montant HT</th>
                    <th style="width:5%"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, idx) in lines" :key="idx"
                    :class="{ 'table-info': line.is_comment }">

                    <td v-if="line.is_comment" colspan="6">
                        <!-- Ligne commentaire -->
                        <input type="text"
                               v-model="line.description"
                               :name="'lines[' + idx + '][description]'"
                               class="form-control form-control-sm"
                               placeholder="Commentaire / sous-total…">
                        <input type="hidden" :name="'lines[' + idx + '][is_comment]'" value="1">
                        <input type="hidden" :name="'lines[' + idx + '][quantity]'" value="0">
                        <input type="hidden" :name="'lines[' + idx + '][unit_price]'" value="0">
                        <input type="hidden" :name="'lines[' + idx + '][discount]'" value="0">
                        <input type="hidden" :name="'lines[' + idx + '][tva_rate]'" value="0">
                        <input type="hidden" :name="'lines[' + idx + '][unit]'" value="U">
                    </td>

                    <template v-else>
                        <td>
                            <input type="text"
                                   v-model="line.description"
                                   :name="'lines[' + idx + '][description]'"
                                   class="form-control form-control-sm mb-1"
                                   placeholder="Description *"
                                   required>
                            <input type="text"
                                   v-model="line.reference"
                                   :name="'lines[' + idx + '][reference]'"
                                   class="form-control form-control-sm"
                                   placeholder="Référence">
                            <input type="hidden" :name="'lines[' + idx + '][is_comment]'" value="0">
                        </td>
                        <td>
                            <input type="number"
                                   v-model="line.quantity"
                                   :name="'lines[' + idx + '][quantity]'"
                                   class="form-control form-control-sm text-end"
                                   step="0.01" min="0">
                        </td>
                        <td>
                            <input type="text"
                                   v-model="line.unit"
                                   :name="'lines[' + idx + '][unit]'"
                                   class="form-control form-control-sm">
                        </td>
                        <td>
                            <input type="number"
                                   v-model="line.unit_price"
                                   :name="'lines[' + idx + '][unit_price]'"
                                   class="form-control form-control-sm text-end"
                                   step="0.0001" min="0">
                        </td>
                        <td>
                            <input type="number"
                                   v-model="line.discount"
                                   :name="'lines[' + idx + '][discount]'"
                                   class="form-control form-control-sm text-end"
                                   step="0.01" min="0" max="100">
                        </td>
                        <td>
                            <input type="number"
                                   v-model="line.tva_rate"
                                   :name="'lines[' + idx + '][tva_rate]'"
                                   class="form-control form-control-sm text-end"
                                   step="0.01" min="0" max="100">
                        </td>
                        <td class="text-end fw-semibold small">
                            {{ fmt(calcLineHt(line)) }}
                        </td>
                    </template>

                    <td class="text-center">
                        <button type="button"
                                class="btn btn-sm btn-outline-danger py-0 px-1"
                                @click="removeLine(idx)"
                                title="Supprimer">×</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Boutons d'ajout -->
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="btn btn-sm btn-outline-primary" @click="addLine">
            + Ajouter une ligne
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addComment">
            + Commentaire
        </button>
    </div>

    <!-- Totaux -->
    <div class="row justify-content-end">
        <div class="col-md-5 col-lg-4">
            <div class="card border-0 bg-light">
                <div class="card-body py-3 px-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total HT</span>
                        <span class="fw-semibold" id="total-ht">{{ fmt(totalHt) }}</span>
                    </div>
                    <div v-for="(amounts, rate) in tvaBreakdown" :key="rate"
                         class="d-flex justify-content-between mb-1 small text-muted">
                        <span>TVA {{ rate }}%</span>
                        <span>{{ fmt(amounts.tva) }}</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold fs-6">
                        <span>Total TTC</span>
                        <span id="total-ttc">{{ fmt(totalTtc) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>`,

        methods: {
            calcLineHt(line) {
                return calcLineHt(line);
            }
        }
    });

    app.mount(mount);
}

// ── Helpers hors composant ─────────────────────────────────────────────────

function createEmptyLine() {
    return {
        description: '',
        reference:   '',
        quantity:    '1',
        unit:        'U',
        unit_price:  '0',
        discount:    '0',
        tva_rate:    '20.00',
        is_comment:  false,
        product_id:  null,
    };
}

function calcLineHt(line) {
    if (line.is_comment) return 0;
    const qty   = parseFloat(line.quantity)   || 0;
    const price = parseFloat(line.unit_price) || 0;
    const disc  = parseFloat(line.discount)   || 0;
    return Math.round(qty * price * (1 - disc / 100) * 100) / 100;
}
