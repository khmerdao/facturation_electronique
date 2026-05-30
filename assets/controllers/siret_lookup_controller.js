// Stimulus controller — lookup SIRET via API Sirene
// Usage : <form data-controller="siret-lookup" data-siret-lookup-url-value="/contacts/api-sirene">
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values  = { url: String }
    static targets = ["input", "name", "status"]

    async lookup() {
        const siret = this.inputTarget.value.replace(/\s/g, "")
        if (siret.length !== 14) return

        this.statusTarget.textContent = "Vérification…"
        this.statusTarget.className   = "form-text text-muted"

        try {
            const res  = await fetch(`${this.urlValue}/${siret}`)
            const data = await res.json()

            if (!res.ok || data.error) {
                this.statusTarget.textContent = "SIRET introuvable dans la base Sirene."
                this.statusTarget.className   = "form-text text-danger"
                return
            }

            // Pré-remplir le nom si vide
            if (this.hasNameTarget && !this.nameTarget.value && data.name) {
                this.nameTarget.value = data.name
            }

            const status = data.active ? "Établissement actif ✓" : "Établissement fermé ⚠"
            this.statusTarget.textContent = status
            this.statusTarget.className   = `form-text ${data.active ? "text-success" : "text-warning"}`
        } catch {
            this.statusTarget.textContent = "Erreur lors de la vérification Sirene."
            this.statusTarget.className   = "form-text text-danger"
        }
    }
}
