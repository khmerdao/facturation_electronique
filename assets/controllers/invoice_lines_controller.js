/**
 * Stimulus controller — Éditeur de lignes de facture
 *
 * Usage dans le template :
 *   <div data-controller="invoice-lines"
 *        data-invoice-lines-products-value="{{ products|json_encode|e('html_attr') }}">
 *
 * Targets :
 *   tbody       — <tbody> du tableau de lignes
 *   totalHt     — élément affichant le total HT
 *   totalTva    — élément affichant le total TVA
 *   totalTtc    — élément affichant le total TTC
 *   tvaDetails  — conteneur des lignes de décomposition TVA
 *   addBtn      — bouton "Ajouter une ligne"
 *   commentBtn  — bouton "Commentaire"
 *
 * Actions dans le HTML :
 *   data-action="click->invoice-lines#addLine"
 *   data-action="click->invoice-lines#addComment"
 *   data-action="click->invoice-lines#removeLine"
 *   data-action="input->invoice-lines#recalculate"
 *   data-action="change->invoice-lines#fillFromCatalog"
 */

import { Controller } from "@hotwired/stimulus"
import Sortable        from "sortablejs"

export default class extends Controller {

    static targets = ["tbody", "totalHt", "totalTva", "totalTtc", "tvaDetails"]
    static values  = { products: { type: Array, default: [] } }

    // ── Taux TVA légaux FR ─────────────────────────────────────────────────

    static TVA_RATES = [
        { value: "20.00", label: "20 %" },
        { value: "10.00", label: "10 %" },
        { value: "5.50",  label: "5,5 %" },
        { value: "2.10",  label: "2,1 %" },
        { value: "0.00",  label: "0 %" },
    ]

    // ── Lifecycle ─────────────────────────────────────────────────────────

    connect() {
        // Drag & drop SortableJS sur le tbody
        this.sortable = Sortable.create(this.tbodyTarget, {
            handle:     ".drag-handle",
            animation:  150,
            ghostClass: "sortable-ghost",
            chosenClass:"sortable-chosen",
            onEnd: () => this.reindexLines(),
        })

        this.recalculate()
    }

    disconnect() {
        this.sortable?.destroy()
    }

    // ── Ajouter une ligne normale ──────────────────────────────────────────

    addLine(event) {
        event.preventDefault()
        const idx = this.tbodyTarget.querySelectorAll("tr[data-line]").length
        this.tbodyTarget.insertAdjacentHTML("beforeend", this.lineTemplate(idx, false))
        this.recalculate()
    }

    // ── Ajouter une ligne commentaire ─────────────────────────────────────

    addComment(event) {
        event.preventDefault()
        const idx = this.tbodyTarget.querySelectorAll("tr[data-line]").length
        this.tbodyTarget.insertAdjacentHTML("beforeend", this.lineTemplate(idx, true))
        this.recalculate()
    }

    // ── Supprimer une ligne ────────────────────────────────────────────────

    removeLine(event) {
        event.preventDefault()
        const rows = this.tbodyTarget.querySelectorAll("tr[data-line]")
        if (rows.length <= 1) return          // Toujours au moins 1 ligne
        event.target.closest("tr").remove()
        this.reindexLines()
        this.recalculate()
    }

    // ── Pré-remplir depuis le catalogue ──────────────────────────────────

    fillFromCatalog(event) {
        const productId = event.target.value
        const product   = this.productsValue.find(p => p.id === productId)
        if (!product) return

        const row = event.target.closest("tr")
        this.setField(row, "description",  product.label)
        this.setField(row, "reference",    product.reference ?? "")
        this.setField(row, "unit_price",   product.unit_price)
        this.setField(row, "unit",         product.unit)
        this.setField(row, "tva_rate",     product.tva_rate)

        this.recalculate()
    }

    // ── Recalculer tous les totaux ────────────────────────────────────────

    recalculate() {
        let totalHt  = 0
        const tvaMap = {}

        this.tbodyTarget.querySelectorAll("tr[data-line]").forEach(row => {
            const isComment = row.dataset.comment === "1"
            if (isComment) return

            const qty   = parseFloat(this.getField(row, "quantity"))   || 0
            const price = parseFloat(this.getField(row, "unit_price"))  || 0
            const disc  = parseFloat(this.getField(row, "discount"))   || 0
            const tva   = parseFloat(this.getField(row, "tva_rate"))   || 0

            const ht     = Math.round(qty * price * (1 - disc / 100) * 100) / 100
            const tvaAmt = Math.round(ht * tva / 100 * 100) / 100

            // Afficher le montant HT de la ligne
            const amtCell = row.querySelector(".line-amount")
            if (amtCell) amtCell.textContent = this.fmt(ht)

            totalHt += ht

            if (tva > 0) {
                const key = tva.toFixed(2)
                tvaMap[key] = tvaMap[key] || { base: 0, tva: 0 }
                tvaMap[key].base = Math.round((tvaMap[key].base + ht)     * 100) / 100
                tvaMap[key].tva  = Math.round((tvaMap[key].tva  + tvaAmt) * 100) / 100
            }
        })

        const totalTva = Object.values(tvaMap).reduce((s, v) => s + v.tva, 0)
        const totalTtc = Math.round((totalHt + totalTva) * 100) / 100

        // Mettre à jour les totaux dans le DOM
        if (this.hasTotalHtTarget)  this.totalHtTarget.textContent  = this.fmt(totalHt)
        if (this.hasTotalTvaTarget) this.totalTvaTarget.textContent = this.fmt(totalTva)
        if (this.hasTotalTtcTarget) this.totalTtcTarget.textContent = this.fmt(totalTtc)

        // Décomposition TVA par taux
        if (this.hasTvaDetailsTarget) {
            this.tvaDetailsTarget.innerHTML = Object.entries(tvaMap)
                .sort((a, b) => parseFloat(b[0]) - parseFloat(a[0]))
                .map(([rate, amounts]) => `
                    <div class="d-flex justify-content-between mb-1 text-muted small">
                        <span>TVA ${parseFloat(rate)} %</span>
                        <span>${this.fmt(amounts.tva)}</span>
                    </div>`)
                .join("")
        }

        // Synchroniser les ID legacy (utilisés par d'autres scripts éventuels)
        const legacyHt  = document.getElementById("total-ht")
        const legacyTva = document.getElementById("total-tva")
        const legacyTtc = document.getElementById("total-ttc")
        if (legacyHt)  legacyHt.textContent  = this.fmt(totalHt)
        if (legacyTva) legacyTva.textContent = this.fmt(totalTva)
        if (legacyTtc) legacyTtc.textContent = this.fmt(totalTtc)
    }

    // ── Réindexer les lignes après drag & drop ou suppression ────────────

    reindexLines() {
        this.tbodyTarget.querySelectorAll("tr[data-line]").forEach((row, idx) => {
            row.querySelectorAll("input[name], select[name]").forEach(el => {
                el.name = el.name.replace(/lines\[\d+\]/, `lines[${idx}]`)
            })
            row.dataset.lineIndex = idx
        })
    }

    // ── Générer le HTML d'une nouvelle ligne ──────────────────────────────

    lineTemplate(idx, isComment) {
        const tvaOptions = this.constructor.TVA_RATES
            .map(r => `<option value="${r.value}" ${r.value === "20.00" ? "selected" : ""}>${r.label}</option>`)
            .join("")

        const catalogOptions = this.productsValue.length > 0
            ? `<select class="form-select form-select-sm mb-1"
                       name="lines[${idx}][product_id]"
                       data-action="change->invoice-lines#fillFromCatalog">
                   <option value="">— Catalogue —</option>
                   ${this.productsValue.map(p =>
                       `<option value="${p.id}">${this.esc(p.label)} (${this.esc(p.reference)})</option>`
                   ).join("")}
               </select>`
            : `<input type="hidden" name="lines[${idx}][product_id]" value="">`

        if (isComment) {
            return `
            <tr data-line data-comment="1" data-line-index="${idx}">
                <td class="drag-handle text-muted ps-2" style="cursor:grab">${this.dragIcon()}</td>
                <td colspan="6" class="py-2 px-2">
                    <input type="text"
                           name="lines[${idx}][description]"
                           class="form-control form-control-sm fst-italic"
                           placeholder="Commentaire, sous-titre de section…"
                           data-action="input->invoice-lines#recalculate">
                    <input type="hidden" name="lines[${idx}][is_comment]"  value="1">
                    <input type="hidden" name="lines[${idx}][quantity]"    value="0">
                    <input type="hidden" name="lines[${idx}][unit]"        value="U">
                    <input type="hidden" name="lines[${idx}][unit_price]"  value="0">
                    <input type="hidden" name="lines[${idx}][discount]"    value="0">
                    <input type="hidden" name="lines[${idx}][tva_rate]"    value="0">
                    <input type="hidden" name="lines[${idx}][product_id]"  value="">
                    <input type="hidden" name="lines[${idx}][position]"    value="${idx}">
                </td>
                <td class="text-end text-muted small px-2 align-middle">—</td>
                <td class="text-center align-middle px-1">
                    <button type="button"
                            class="btn btn-link btn-sm text-danger p-0"
                            data-action="click->invoice-lines#removeLine"
                            title="Supprimer">${this.trashIcon()}</button>
                </td>
            </tr>`
        }

        return `
        <tr data-line data-comment="0" data-line-index="${idx}">
            <td class="drag-handle text-muted ps-2 align-middle" style="cursor:grab">${this.dragIcon()}</td>
            <td class="py-2 px-2" style="min-width:200px">
                ${catalogOptions}
                <input type="text"
                       name="lines[${idx}][description]"
                       class="form-control form-control-sm"
                       placeholder="Description *"
                       required
                       data-action="input->invoice-lines#recalculate">
                <input type="text"
                       name="lines[${idx}][reference]"
                       class="form-control form-control-sm mt-1"
                       placeholder="Référence (optionnel)">
                <input type="hidden" name="lines[${idx}][is_comment]"  value="0">
                <input type="hidden" name="lines[${idx}][position]"    value="${idx}">
            </td>
            <td class="py-2 px-1" style="width:80px">
                <input type="number"
                       name="lines[${idx}][quantity]"
                       class="form-control form-control-sm text-end"
                       value="1" min="0.01" step="0.01"
                       data-action="input->invoice-lines#recalculate">
            </td>
            <td class="py-2 px-1" style="width:60px">
                <input type="text"
                       name="lines[${idx}][unit]"
                       class="form-control form-control-sm text-center"
                       value="U" maxlength="10">
            </td>
            <td class="py-2 px-1" style="width:105px">
                <input type="number"
                       name="lines[${idx}][unit_price]"
                       class="form-control form-control-sm text-end"
                       value="0" min="0" step="0.0001"
                       data-action="input->invoice-lines#recalculate">
            </td>
            <td class="py-2 px-1" style="width:70px">
                <input type="number"
                       name="lines[${idx}][discount]"
                       class="form-control form-control-sm text-end"
                       value="0" min="0" max="100" step="0.01"
                       placeholder="0"
                       data-action="input->invoice-lines#recalculate">
            </td>
            <td class="py-2 px-1" style="width:80px">
                <select name="lines[${idx}][tva_rate]"
                        class="form-select form-select-sm"
                        data-action="change->invoice-lines#recalculate">
                    ${tvaOptions}
                </select>
            </td>
            <td class="py-2 px-2 text-end fw-semibold small align-middle line-amount"
                style="width:95px">0,00 €</td>
            <td class="py-2 px-1 text-center align-middle" style="width:36px">
                <button type="button"
                        class="btn btn-link btn-sm text-danger p-0"
                        data-action="click->invoice-lines#removeLine"
                        title="Supprimer">${this.trashIcon()}</button>
            </td>
        </tr>`
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    getField(row, name) {
        return row.querySelector(`[name*="${name}"]`)?.value ?? ""
    }

    setField(row, name, value) {
        const el = row.querySelector(`[name*="${name}"]`)
        if (el) el.value = value
    }

    fmt(value) {
        return value.toFixed(2).replace(".", ",") + " €"
    }

    esc(str) {
        return (str ?? "").replace(/[&<>"']/g, c =>
            ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#x27;" }[c])
        )
    }

    dragIcon() {
        return `<svg width="12" height="16" viewBox="0 0 12 16" fill="currentColor" opacity=".4">
            <circle cx="3" cy="3" r="1.5"/><circle cx="9" cy="3" r="1.5"/>
            <circle cx="3" cy="8" r="1.5"/><circle cx="9" cy="8" r="1.5"/>
            <circle cx="3" cy="13" r="1.5"/><circle cx="9" cy="13" r="1.5"/>
        </svg>`
    }

    trashIcon() {
        return `<svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>`
    }
}
