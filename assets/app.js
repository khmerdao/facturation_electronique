/*
 * Point d'entrée principal Webpack Encore
 * Chargé sur toutes les pages de l'application
 */

// ── Bootstrap 5 ─────────────────────────────────────────────────────────────
import 'bootstrap/dist/css/bootstrap.min.css';
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;  // accessible depuis Twig et les controllers Stimulus

// ── CSS de l'application ────────────────────────────────────────────────────
import './styles/app.scss';

// ── Hotwire Turbo (navigation SPA-like) ─────────────────────────────────────
import '@hotwired/turbo';

// ── Stimulus (controllers légers) ───────────────────────────────────────────
import { startStimulusApp } from '@symfony/stimulus-bundle';
const app = startStimulusApp();

// ── Enregistrement global des controllers Stimulus ──────────────────────────
// Les controllers sont auto-découverts depuis assets/controllers/
// Voir assets/controllers.json pour la liste

// ── Utilitaires globaux ──────────────────────────────────────────────────────
// Flatpickr (sélecteur de date — locale fr)
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';
flatpickr.localize(French);
window.flatpickr = flatpickr;

// Tom Select (combobox avancée pour les sélecteurs)
import TomSelect from 'tom-select';
window.TomSelect = TomSelect;

export { app };
