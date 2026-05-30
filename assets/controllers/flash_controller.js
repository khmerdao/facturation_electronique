// Stimulus controller — auto-dismiss des alertes flash
// Usage : <div data-controller="flash" data-flash-delay-value="5000">
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = { delay: { type: Number, default: 5000 } }

    connect() {
        if (this.delayValue > 0) {
            this.timer = setTimeout(() => this.dismiss(), this.delayValue)
        }
    }

    disconnect() {
        clearTimeout(this.timer)
    }

    dismiss() {
        this.element.style.transition = "opacity 0.3s"
        this.element.style.opacity   = "0"
        setTimeout(() => this.element.remove(), 300)
    }
}
