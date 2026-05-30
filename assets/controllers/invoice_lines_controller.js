// Stimulus controller — gestion dynamique des lignes de facture
// Usage : <tbody data-controller="invoice-lines">
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["body", "totalHt", "totalTva", "totalTtc"]

    connect() {
        this.index = this.bodyTarget.querySelectorAll("tr").length
        this.updateTotals()
    }

    addLine(event) {
        event.preventDefault()
        const tbody = this.bodyTarget
        const firstRow = tbody.querySelector("tr")
        if (!firstRow) return

        const newRow = firstRow.cloneNode(true)
        // Mettre à jour les index dans les name
        newRow.querySelectorAll("input, select").forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${this.index}]`)
            el.value = el.defaultValue || ""
        })
        newRow.querySelector(".line-amount")?.textContent && (newRow.querySelector(".line-amount").textContent = "0,00 €")
        tbody.appendChild(newRow)
        this.index++
        this.updateTotals()
    }

    removeLine(event) {
        event.preventDefault()
        const row = event.target.closest("tr")
        if (this.bodyTarget.querySelectorAll("tr").length <= 1) return
        row.remove()
        this.updateTotals()
    }

    recalculate(event) {
        this.updateTotals()
    }

    updateTotals() {
        let totalHt = 0, totalTva = 0
        this.bodyTarget.querySelectorAll("tr").forEach(row => {
            const qty   = parseFloat(row.querySelector("[name*=quantity]")?.value) || 0
            const price = parseFloat(row.querySelector("[name*=unit_price]")?.value) || 0
            const disc  = parseFloat(row.querySelector("[name*=discount]")?.value) || 0
            const tva   = parseFloat(row.querySelector("[name*=tva_rate]")?.value) || 0
            const ht    = Math.round(qty * price * (1 - disc/100) * 100) / 100
            const tvaAmt = Math.round(ht * tva/100 * 100) / 100
            const amtEl = row.querySelector(".line-amount")
            if (amtEl) amtEl.textContent = ht.toFixed(2).replace(".", ",") + " €"
            totalHt  += ht
            totalTva += tvaAmt
        })
        const fmt = v => v.toFixed(2).replace(".", ",") + " €"
        if (this.hasTotalHtTarget)  this.totalHtTarget.textContent  = fmt(totalHt)
        if (this.hasTotalTvaTarget) this.totalTvaTarget.textContent = fmt(totalTva)
        if (this.hasTotalTtcTarget) this.totalTtcTarget.textContent = fmt(totalHt + totalTva)
    }
}
