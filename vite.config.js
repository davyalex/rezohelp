import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Site public : Alpine + JS vanilla, budget < 50 Ko.
                'resources/css/app.css',
                'resources/js/app.js',
                // Back-office uniquement : jQuery, DataTables, Select2, SweetAlert2.
                'resources/css/admin.css',
                'resources/js/admin.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        // PWA (CONTEXTE §15) : service worker écrit à la racine de public/ (portée « / ») et enregistré
        // par app.js. Les règles de cache sont dans workbox.runtimeCaching.
        VitePWA({
            injectRegister: false,
            registerType: 'autoUpdate',
            outDir: 'public',
            filename: 'sw.js',
            scope: '/',
            base: '/',
            // Manifeste statique versionné : public/manifest.webmanifest.
            manifest: false,
            workbox: {
                // Assets versionnés du site public seulement (cache-first via le précache) ; jamais le bundle admin.
                globDirectory: 'public/build',
                globPatterns: ['assets/app-*.{js,css}'],
                modifyURLPrefix: { '': '/build/' },
                additionalManifestEntries: [{ url: '/hors-ligne', revision: String(Date.now()) }],
                inlineWorkboxRuntime: true,
                cleanupOutdatedCaches: true,
                navigateFallback: null,
                // Seules les requêtes GET sont mises en cache : les réponses POST ne le sont jamais.
                runtimeCaching: [
                    {
                        // Fragments du fil : affichés tout de suite depuis le cache, rafraîchis en arrière-plan.
                        urlPattern: ({ url, sameOrigin }) => sameOrigin && url.pathname === '/fil/suite',
                        handler: 'StaleWhileRevalidate',
                        options: { cacheName: 'fil-suite', expiration: { maxEntries: 40, maxAgeSeconds: 10 * 60 } },
                    },
                    {
                        // Espace membre, admin, clics publicitaires, publication, paiements, connexion : jamais en cache.
                        urlPattern: ({ request, url, sameOrigin }) => sameOrigin && request.mode === 'navigate'
                            && /^\/(mon-espace|admin|pub|paiements|publier|connexion|inscription|deconnexion|verification-telephone|changer-mot-de-passe|mot-de-passe-oublie|reinitialiser-mot-de-passe)(\/|$)/.test(url.pathname),
                        handler: 'NetworkOnly',
                        options: { precacheFallback: { fallbackURL: '/hors-ligne' } },
                    },
                    {
                        // Autres pages : le réseau d'abord, la dernière version connue hors connexion, sinon la page hors ligne.
                        urlPattern: ({ request, sameOrigin }) => sameOrigin && request.mode === 'navigate',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'pages',
                            networkTimeoutSeconds: 4,
                            expiration: { maxEntries: 30, maxAgeSeconds: 24 * 60 * 60 },
                            precacheFallback: { fallbackURL: '/hors-ligne' },
                        },
                    },
                    {
                        urlPattern: ({ request, sameOrigin }) => sameOrigin && request.destination === 'image',
                        handler: 'CacheFirst',
                        options: { cacheName: 'images', expiration: { maxEntries: 120, maxAgeSeconds: 30 * 24 * 60 * 60 } },
                    },
                ],
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
