/**
 * assets/invoice_editor.js
 * =============================================================================
 * Entry point Webpack Encore — Éditeur de lignes de factures (Vue 3)
 *
 * Chargé via {{ encore_entry_script_tags('invoice_editor') }}
 * dans templates/invoices/new.html.twig et templates/invoices/edit.html.twig
 *
 * Architecture :
 *   InvoiceEditor.vue  → composant racine (lignes + totaux + drag & drop)
 *   InvoiceLine.vue    → composant ligne unitaire
 *
 * Données passées depuis Twig :
 *   data-lines     — JSON des lignes existantes (mode édition) ou "[]"
 *   data-products  — JSON du catalogue produits du tenant courant
 * =============================================================================
 */

import { createApp } from 'vue'
import InvoiceEditor from './components/InvoiceEditor.vue'

// ── Montage (compatible Turbo) ─────────────────────────────────────────────

function mount () {
  const el = document.getElementById('invoice-editor-app')
  if (!el || el.dataset.vueInitialized) return
  el.dataset.vueInitialized = 'true'

  let initialLines    = []
  let initialProducts = []

  try {
    initialLines    = JSON.parse(el.dataset.lines    || '[]')
  } catch (e) {
    console.warn('[invoice_editor] Impossible de parser data-lines', e)
  }

  try {
    initialProducts = JSON.parse(el.dataset.products || '[]')
  } catch (e) {
    console.warn('[invoice_editor] Impossible de parser data-products', e)
  }

  const app = createApp(InvoiceEditor, {
    initialLines,
    initialProducts,
  })

  if (process.env.NODE_ENV !== 'production') {
    window.__invoiceEditor = app
  }

  app.mount(el)
}

document.addEventListener('turbo:load', mount)
document.addEventListener('DOMContentLoaded', mount)
