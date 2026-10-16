# Resohelph — Avancement

> Questions ouvertes pour Alex : aucune pour le moment.

| Lot | Titre | État |
|---|---|---|
| 0 | Socle technique | terminé (tag `lot-0`) |
| 1 | Base de données, modèles, référentiels | à faire |
| 2 | Authentification par téléphone | à faire |
| 3 | Fil public et pages de publication | à faire |
| 4 | Publication par les utilisateurs | à faire |
| 5 | Mon espace, alertes et notifications | à faire |
| 6 | Abonnements, paiements, SMS, visibilité payante | à faire |
| 7 | Back-office | à faire |
| 8 | Import de la veille et enrichissement IA | à faire |
| 9 | PWA, performance, accessibilité, sécurité | à faire |
| 10 | Recette finale et mise en production | à faire |

---

## Lot 0 — Socle technique

### Plan d'implémentation

- **Dépôt** : `git init`, documents de référence rangés dans `docs/` (contenu inchangé).
- **Paquets PHP** : `laravel/fortify`, `propaganistas/laravel-phone`, `spatie/laravel-permission`, `spatie/laravel-medialibrary`, `yajra/laravel-datatables`, `google/apiclient` (services limités à Drive), `barryvdh/laravel-dompdf` ; dev : `pestphp/pest`, `pestphp/pest-plugin-laravel`, `larastan/larastan`.
- **Paquets JS** : `alpinejs` (public) ; `jquery`, `datatables.net-dt`, `select2`, `sweetalert2` (admin uniquement).
- **Configuration** : locale `fr`, fuseau `Africa/Abidjan`, Carbon en français, traductions `lang/fr/*` ; cache `memcached` avec repli `file` si l'extension manque ; sessions et files `database` ; `config/resohelph.php` (drivers, compte admin).
- **Vite** : entrées `resources/css/app.css` + `resources/js/app.js` (public) et `resources/css/admin.css` + `resources/js/admin.js` (admin) ; tokens `@theme` ; aucune police web.
- **Code** :
  - `app/Models/Concerns/HorodatageFrancais.php` ;
  - `app/Enums/StatutPaiementPasserelle.php` ;
  - `app/Contracts/{PasserellePaiement,FournisseurSms,SourceVeille,ClientIA}.php` ;
  - objets de données `app/Donnees/*` ;
  - drivers `app/Services/Paiement/FakePasserelle.php`, `app/Services/Sms/LogFournisseurSms.php`, `app/Services/Veille/FakeSourceVeille.php`, `app/Services/IA/FakeClientIA.php` ;
  - `app/Providers/IntegrationsServiceProvider.php` (liaison selon `.env`) ;
  - `AppServiceProvider` : `preventLazyLoading()`, `preventSilentlyDiscardingAttributes()` hors production, locale Carbon.
- **Vues** : `layouts/public.blade.php` (layout vide, `lang="fr"`) et `accueil.blade.php`.
- **Qualité** : `phpstan.neon` (niveau 6), `pint.json`, Pest, `phpunit.xml` sur MySQL (`resohelph_test`).
- **CI** : `.github/workflows/ci.yml` (Pint, Larastan, build Vite, tests sur MySQL 8).
- **Tests** : page d'accueil en français, localisation (fuseau, Carbon), trait d'horodatage, liaison des drivers, chaque driver fake / log.

### Fait

- Dépôt Git initialisé ; `docs/CONTEXTE.md` et `docs/PLAN_DEVELOPPEMENT.md` déplacés (contenu inchangé).
- Paquets installés avec des contraintes de version explicites ; `google/apiclient-services` réduit au service Drive (`Google\Task\Composer::cleanup`).
- Locale `fr`, fuseau `Africa/Abidjan`, Carbon en français, traductions `lang/fr` (validation, auth, mots de passe, pagination ; message de la règle `phone`).
- Cache `memcached` par défaut, repli automatique sur `file` sans l'extension (`config/cache.php`) ; sessions et files en `database`.
- Vite : deux entrées (public / admin). Tokens `@theme` : brand `#0E5E6F`, fond `#EEF1F4`, emploi bleu, service orange, formation vert. Polices système, focus visible, `prefers-reduced-motion`. DataTables configuré par défaut en `serverSide` et en français.
- Interfaces `PasserellePaiement`, `FournisseurSms`, `SourceVeille`, `ClientIA` + objets de données `app/Donnees/*` ; drivers `fake` (paiement, veille, IA) et `log` (SMS → `storage/logs/sms.log`), liés par `IntegrationsServiceProvider` selon `.env` (driver inconnu = exception explicite).
- `HorodatageFrancais`, modèle `Utilisateur` (table `utilisateurs`, `mot_de_passe`, téléphone masqué à la sérialisation) et `config/auth.php` (voir D-008).
- Fortify publié mais **toutes ses fonctionnalités désactivées** jusqu'au lot 2 (`username = telephone`).
- Migrations techniques : `sessions`, `cache`, `jobs`, tables Spatie Permission et `media`.
- Layout public vide en français (`lang="fr"`, lien d'évitement) et page d'accueil.
- `.env.example` complet et commenté ; CI GitHub Actions (Pint, Larastan, build Vite, tests parallèles et `migrate:fresh --seed` sur MySQL 8).
- Build : JS public 19 Ko gzip (Alpine), JS admin 113 Ko gzip, chargé seulement par le back-office.

### Tests ajoutés (28 tests, 9 fichiers)

- `tests/Feature/AccueilTest.php` (2) · `tests/Feature/IntegrationsTest.php` (5)
- `tests/Unit/LocalisationTest.php` (3) · `tests/Unit/HorodatageFrancaisTest.php` (2) · `tests/Unit/UtilisateurTest.php` (3)
- `tests/Unit/Services/FakePasserelleTest.php` (5) · `LogFournisseurSmsTest.php` (1) · `FakeSourceVeilleTest.php` (4) · `FakeClientIATest.php` (3)

### Écarts avec le CONTEXTE

- Tests sur MySQL plutôt que SQLite (D-002). Telescope et Laravel Boost non installés (D-005). Budget JS mesuré en gzip (D-007). `Utilisateur` avancé au lot 0 (D-008).

### À vérifier par Alex

- [ ] `composer install && npm install`, puis `php artisan migrate --seed` sur une base `resohelph` vide.
- [ ] `composer run dev` démarre (serveur, Vite, file) et `http://localhost:8000` affiche le layout vide en français.
- [ ] Après le premier `git push` sur GitHub, la CI est verte.
