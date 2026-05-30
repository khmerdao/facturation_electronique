// Stimulus controller — modale de confirmation avant action destructive
// Usage : <button data-controller="confirm" data-confirm-message-value="Supprimer ?">
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        message: { type: String, default: "Êtes-vous sûr de vouloir continuer ?" },
        title:   { type: String, default: "Confirmation" },
    }

    confirm(event) {
        if (!window.confirm(`${this.titleValue}\n\n${this.messageValue}`)) {
            event.preventDefault()
            event.stopImmediatePropagation()
        }
    }
}
