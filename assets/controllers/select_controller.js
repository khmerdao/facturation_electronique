/**
 * Stimulus controller — TomSelect (combobox avec recherche)
 *
 * Usage :
 *   <select data-controller="select"
 *           data-select-placeholder-value="— Sélectionner un client —"
 *           data-select-searchable-value="true"
 *           data-select-create-value="false">
 *     <option value="">— Sélectionner —</option>
 *     ...
 *   </select>
 *
 * Valeurs Stimulus :
 *   placeholder-value  — texte affiché quand rien n'est sélectionné
 *   searchable-value   — activer la recherche (true par défaut)
 *   create-value       — permettre la création d'options (false par défaut)
 *   max-items-value    — max items sélectionnables (1 par défaut = single)
 *
 * TomSelect est exposé globalement via assets/app.js :
 *   import TomSelect from 'tom-select';
 *   window.TomSelect = TomSelect;
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        placeholder: { type: String, default: 'Sélectionner…' },
        searchable:  { type: Boolean, default: true },
        create:      { type: Boolean, default: false },
        maxItems:    { type: Number, default: 1 },
    }

    connect() {
        if (typeof TomSelect === 'undefined') {
            console.warn('[select] TomSelect non disponible. Vérifiez que app.js est chargé.')
            return
        }

        // Récupérer la valeur initiale avant que TomSelect prenne le contrôle
        const initialValue = this.element.value

        this.tomSelect = new TomSelect(this.element, {
            placeholder:    this.placeholderValue,
            create:         this.createValue,
            allowEmptyOption: true,
            maxItems:       this.maxItemsValue,

            // Désactiver la recherche si non souhaité
            controlInput:   this.searchableValue ? undefined : null,

            // Tri alphabétique des options
            sortField:      { field: 'text', direction: 'asc' },

            // Rendu personnalisé des options vides
            render: {
                no_results: () => '<div class="no-results px-3 py-2 text-muted small">Aucun résultat</div>',
                option_create: (data) =>
                    `<div class="create px-3 py-1">Ajouter <strong>${data.input}</strong>&hellip;</div>`,
            },

            // Fermer au blur (important avec Turbo)
            closeAfterSelect: this.maxItemsValue === 1,
        })

        // Restaurer la valeur initiale
        if (initialValue) {
            this.tomSelect.setValue(initialValue, true)
        }
    }

    disconnect() {
        this.tomSelect?.destroy()
        this.tomSelect = null
    }

    // Action : vider la sélection
    clear() {
        this.tomSelect?.clear()
    }
}
