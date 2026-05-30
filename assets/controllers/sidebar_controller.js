/**
 * Stimulus controller — Sidebar mobile
 *
 * Usage (déjà en place dans templates/partials/_sidebar.html.twig) :
 *   <aside data-controller="sidebar" ...>
 *
 * Actions déclenchées depuis le header :
 *   data-action="click->sidebar#toggle"   → bouton hamburger
 *   data-action="click->sidebar#close"    → overlay
 *
 * CSS attendu dans app.scss (ajouté dans cette task) :
 *   .app-sidebar.open { transform: translateX(0) }
 *   .sidebar-overlay.visible { display: block }
 */

import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    // ── Connexion ──────────────────────────────────────────────────────────

    connect() {
        // Créer l'overlay s'il n'existe pas encore
        if (!document.getElementById('sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.id    = 'sidebar-overlay';
            overlay.classList.add('sidebar-overlay', 'd-lg-none');
            overlay.setAttribute('data-action', 'click->sidebar#close');
            document.body.appendChild(overlay);
        }

        // Fermer la sidebar sur navigation Turbo (changement de page)
        this.boundClose = this.close.bind(this);
        document.addEventListener('turbo:before-cache', this.boundClose);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.boundClose);
    }

    // ── Actions ────────────────────────────────────────────────────────────

    /** Bascule l'état ouvert/fermé de la sidebar (bouton hamburger) */
    toggle() {
        const isOpen = this.element.classList.contains('open');
        isOpen ? this.close() : this.open();
    }

    /** Ouvre la sidebar et affiche l'overlay */
    open() {
        this.element.classList.add('open');
        document.body.classList.add('sidebar-open');
        document.getElementById('sidebar-overlay')?.classList.add('visible');
        // Accessibilité : piège le focus dans la sidebar sur mobile
        this.element.setAttribute('aria-expanded', 'true');
    }

    /** Ferme la sidebar et masque l'overlay */
    close() {
        this.element.classList.remove('open');
        document.body.classList.remove('sidebar-open');
        document.getElementById('sidebar-overlay')?.classList.remove('visible');
        this.element.setAttribute('aria-expanded', 'false');
    }
}
