// Stimulus controller — copier une valeur dans le presse-papier
// Usage : <button data-controller="copy" data-copy-value-value="APIKEY123" data-copy-label-value="Copier la clé">
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        value: String,
        label: { type: String, default: "Copier" },
        labelCopied: { type: String, default: "Copié !" },
    }

    async copy(event) {
        event.preventDefault()
        try {
            await navigator.clipboard.writeText(this.valueValue)
            const original = this.element.textContent
            this.element.textContent = this.labelCopiedValue
            setTimeout(() => { this.element.textContent = original }, 1500)
        } catch {
            // Fallback pour les navigateurs sans clipboard API
            const ta = document.createElement("textarea")
            ta.value = this.valueValue
            ta.style.position = "fixed"
            ta.style.opacity  = "0"
            document.body.appendChild(ta)
            ta.select()
            document.execCommand("copy")
            document.body.removeChild(ta)
        }
    }
}
