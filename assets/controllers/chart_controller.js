// Stimulus controller — initialiser Chart.js sur un canvas
// Usage : <canvas data-controller="chart" data-chart-config-value='{"type":"bar","data":{...}}'>
import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = { config: Object }

    connect() {
        this.loadChart()
    }

    disconnect() {
        this.chart?.destroy()
    }

    async loadChart() {
        if (typeof Chart === "undefined") {
            await new Promise((resolve, reject) => {
                const s = document.createElement("script")
                s.src = "https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
                s.onload = resolve
                s.onerror = reject
                document.head.appendChild(s)
            })
        }
        this.chart = new Chart(this.element, this.configValue)
    }
}
