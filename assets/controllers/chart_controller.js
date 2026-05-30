/**
 * Stimulus controller — Chart.js depuis le bundle webpack (plus de CDN)
 *
 * Usage :
 *   <canvas data-controller="chart"
 *           data-chart-config-value='{"type":"bar","data":{...},"options":{...}}'>
 *   </canvas>
 *
 * Chart.js est importé dans admin.js et exposé via window.Chart.
 * Pour les pages qui n'ont pas admin.js, Chart est également exposé
 * dans app.js si besoin.
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = {
        config: { type: Object, default: {} },
    }

    connect() {
        if (typeof Chart === 'undefined') {
            console.warn('[chart] Chart.js non disponible. Vérifiez que admin.js est chargé.')
            return
        }

        // Détruire le chart précédent si Turbo navigue sur la même page
        this.disconnect()

        // Appliquer les defaults visuels de l'application
        Chart.defaults.font.family     = "'Inter', 'Helvetica Neue', sans-serif"
        Chart.defaults.font.size       = 12
        Chart.defaults.color           = '#6b7280'
        Chart.defaults.plugins.legend.display = false

        this.chart = new Chart(this.element, this.configValue)
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy()
            this.chart = null
        }
    }

    // Action : mettre à jour les données sans recréer le chart
    update(event) {
        if (!this.chart) return
        const { labels, data } = event.detail
        this.chart.data.labels                    = labels
        this.chart.data.datasets[0].data          = data
        this.chart.update('active')
    }
}
