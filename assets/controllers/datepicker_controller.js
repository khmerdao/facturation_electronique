/**
 * Stimulus controller — Date picker Flatpickr (locale française)
 *
 * Usage :
 *   <input type="date" name="issue_date"
 *          data-controller="datepicker"
 *          data-datepicker-min-value="2020-01-01"
 *          data-datepicker-max-value="today">
 *
 * Valeurs Stimulus :
 *   min-value  — date minimum (format YYYY-MM-DD ou "today")
 *   max-value  — date maximum
 *   mode-value — "single" (défaut), "range", "multiple"
 *
 * Flatpickr est exposé globalement via assets/app.js :
 *   import flatpickr from 'flatpickr';
 *   window.flatpickr = flatpickr;
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        min:  { type: String, default: '' },
        max:  { type: String, default: '' },
        mode: { type: String, default: 'single' },
    }

    connect() {
        // Attendre que flatpickr soit disponible (chargé via app.js)
        if (typeof flatpickr === 'undefined') {
            console.warn('[datepicker] flatpickr non disponible. Vérifiez que app.js est chargé.')
            return
        }

        this.picker = flatpickr(this.element, {
            // Valeur envoyée au serveur : YYYY-MM-DD (standard HTML)
            dateFormat:  'Y-m-d',
            // Valeur affichée à l'utilisateur : DD/MM/YYYY
            altInput:    true,
            altFormat:   'd/m/Y',
            // Langue française (importée et localisée dans app.js)
            locale:      'fr',
            // Mode de sélection
            mode:        this.modeValue,
            // Bornes
            minDate:     this.minValue  || undefined,
            maxDate:     this.maxValue  || undefined,
            // Désactiver l'animation pour les Turbo Frames
            animate:     false,
            // Fermer au clic en dehors
            clickOpens:  true,
            // Permettre la saisie directe
            allowInput:  true,
            // Accessibilité
            disableMobile: false,
        })
    }

    disconnect() {
        this.picker?.destroy()
        this.picker = null
    }

    // Action externe pour reset la valeur
    clear() {
        this.picker?.clear()
    }
}
