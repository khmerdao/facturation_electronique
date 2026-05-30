/*
 * assets/admin.js
 * Entry point Webpack Encore pour les pages super-admin (/admin/*)
 * Chargé via {{ encore_entry_script_tags('admin') }} dans les templates admin
 */

// ── Bundle principal (Bootstrap, Turbo, Stimulus, Flatpickr, TomSelect) ─────
import './app.js';

// ── Chart.js — graphiques analytics super-admin ────────────────────────────
import {
    Chart,
    BarController,
    LineController,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(
    BarController,
    LineController,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Tooltip,
    Legend,
);

window.Chart = Chart;
