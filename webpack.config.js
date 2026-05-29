const Encore = require('@symfony/webpack-encore');

// ─────────────────────────────────────────────────────────────────────────────
// Webpack Encore — Build frontend (Symfony 7)
// Stack : Bootstrap 5 + Hotwire Turbo + Stimulus + Vue 3
// ─────────────────────────────────────────────────────────────────────────────

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'development');
}

Encore
    // ─── Répertoires de sortie ───────────────────────────────────────────────
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    // ─── Entry points ────────────────────────────────────────────────────────
    // Point d'entrée principal (chargé sur toutes les pages)
    .addEntry('app', './assets/app.js')
    // Point d'entrée admin (super-admin uniquement)
    .addEntry('admin', './assets/admin.js')
    // Page d'édition de facture (composants Vue 3 lourds)
    .addEntry('invoice_editor', './assets/invoice_editor.js')

    // ─── CSS ─────────────────────────────────────────────────────────────────
    .addStyleEntry('styles', './assets/styles/app.scss')

    // ─── Optimisations ───────────────────────────────────────────────────────
    // Extrait les dépendances partagées dans un chunk "vendor"
    .splitEntryChunks()
    // Génère un runtime chunk (requis pour splitEntryChunks)
    .enableSingleRuntimeChunk()
    // Génère un manifest.json pour le versioning des assets
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // ─── Loaders ─────────────────────────────────────────────────────────────
    // SCSS / Bootstrap 5
    .enableSassLoader()
    .enablePostCssLoader()

    // Vue 3 (Composition API)
    .enableVueLoader(() => {}, {
        runtimeCompilerBuild: false,  // Utilise la version runtime uniquement
    })

    // TypeScript (optionnel — peut être activé si besoin)
    // .enableTypeScriptLoader()

    // ─── Hotwire Turbo + Stimulus ─────────────────────────────────────────────
    .enableStimulusBridge('./assets/controllers.json')

    // ─── Polyfills ───────────────────────────────────────────────────────────
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

    // ─── Production ──────────────────────────────────────────────────────────
    .configureTerserPlugin((options) => {
        options.terserOptions = {
            compress: {
                drop_console: Encore.isProduction(),
            },
        };
    })
;

module.exports = Encore.getWebpackConfig();
