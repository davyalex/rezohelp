# Resohelph — Avancement

> Questions ouvertes pour Alex : aucune pour le moment.

| Lot | Titre | État |
|---|---|---|
| 0 | Socle technique | terminé (tag `lot-0`) |
| 1 | Base de données, modèles, référentiels | terminé (tag `lot-1`) |
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

---

## Lot 1 — Base de données, modèles, référentiels

### Plan d'implémentation

- **Migrations** (§7, §13.4, §14) : `pays`, `communes`, `utilisateurs` (+ `commune_id`), `categories`, `entreprises`, `sources_veille`, `imports_veille`, `publications` (index du §7 + FULLTEXT), `details_emploi|service|formation`, `alertes` + `alertes_publications`, `reactions`, `commentaires`, `enregistrements`, `reponses`, `signalements`, `parametres`, `journal_activites`, `recherches`, `appels_ia`, `plans`, `plan_limites`, `produits`, `commandes`, `abonnements`, `essais_utilises`, `credits`, `notifications_paiement`, `mises_en_avant`, `sms_envoyes`, `notifications`.
- **Enums** : les 21 du plan + `SourcePublication`, `StatutCommentaire`, `StatutImportVeille`, `UniteTarif`, `TypeParametre`, `TypeReaction`, `MotifSms`, `StatutSms`, `TypeArticle`, `PasserelleCommande`, `StatutAppelIa` (trait `AvecLibelles` : libellés français, `options()`, `valeurs()`).
- **Modèles** : 30 modèles avec `$table`, `$fillable`, casts, relations typées, scopes `publiees()`, `duType()`, `dansCommune()` ; morph map française.
- **Services** : `TelephoneService`, cast `TelephoneE164`, `CacheFil` (partie `ref:*`), `ReferentielObserver`, `Parametre::valeur()`.
- **Seeders** : pays, communes, catégories, rôles et permissions, paramètres, plans et produits, sources de veille, administrateur ; `DemoSeeder`.
- **Tests** : factories, relations, unicités, plan par défaut unique, paramètres et cache, téléphone, seeders.

### Fait

- 21 migrations (toutes les tables du §7, monétisation comprise), clés étrangères et index ; unicité d'un plan par défaut par cible garantie en base (colonne générée, D-012).
- 33 enums backed string, dont `CleDroit` (14 clés, `estBooleen()`, `estQuota()` — D-015).
- 30 modèles et 30 factories (états : `emploi`, `serviceOffre`, `serviceDemande`, `formation`, `publiee`, `enAttente`, `veille`, `fiable`, `restreint`, `suspendu`, `parDefaut`…).
- Médias : `photo` (utilisateur), `logo` (entreprise), `photos` (publication) avec conversions WebP 96 px et 640 px.
- `TelephoneService` (normalisation E.164, mobile, formats national / international, empreinte SHA-256) et cast `TelephoneE164`.
- `CacheFil::referentiel()` / `oublierReferentiel()` ; invalidation de `ref:pays|communes|categories|parametres` par `ReferentielObserver`.
- `Parametre::valeur()` + accesseurs typés `entier()`, `booleen()`, `texte()`, `liste()` (clé inconnue = exception).
- Seeders idempotents : 10 pays, 13 communes, 37 catégories et sous-catégories de service, 22 catégories emploi / formation, 16 permissions et 6 rôles, 26 paramètres, 9 plans + limites, 4 produits, 8 sources (dont « Inconnue »), administrateur depuis `.env`.
- `DemoSeeder` : 20 membres, 8 entreprises (3 vérifiées), 60 publications (25 emplois, 15 offres et 5 demandes de service, 15 formations dont 3 issues de la veille, 9 en attente), réactions, commentaires, 10 alertes dont « Électricien · Cocody ».

### Tests ajoutés (116 tests, 8 fichiers ; total 144)

- `tests/Unit/Modeles/FactoriesTest.php` (60 : une factory par modèle, `$table` déclaré dans chaque modèle, états)
- `tests/Unit/Modeles/RelationsTest.php` (7) · `tests/Unit/Modeles/UniciteTest.php` (9) · `tests/Unit/Modeles/ParametreTest.php` (9) · `tests/Unit/Modeles/TelephoneE164Test.php` (3)
- `tests/Unit/Services/TelephoneServiceTest.php` (20 : CI sous 5 formats, SN, FR, saisies invalides, mobile, affichage, empreinte)
- `tests/Feature/SeedersTest.php` (8)

### Écarts avec le CONTEXTE

- Colonnes ajoutées (D-011, D-012), pivots sans modèle (D-013), cast maison et format ivoirien par paires (D-014), valeurs de grille non précisées (D-016), paramètres supplémentaires (D-017).

### À vérifier par Alex

- [ ] `php artisan migrate:fresh --seed` puis `php artisan db:seed --class=DemoSeeder` sans erreur.
- [ ] Dans la base : 13 communes d'Abidjan, 9 plans dont 4 « par défaut » (un par cible), l'administrateur avec le rôle `administrateur`.
- [ ] Les prix et limites de `PlansSeeder` correspondent à la grille §14.4 (les valeurs ajoutées sont listées en D-016).
- [ ] La liste des mots à risque et les plafonds de salaire (`ParametresSeeder`) vous conviennent.
