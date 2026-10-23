# CLAUDE.md — Rezohelp

Plateforme d'emploi, de home service / petits métiers et de formation à Abidjan, présentée comme un **fil d'actualité façon Facebook**, mobile d'abord.
Contexte complet (fonctionnel, données, cache, alertes, publication, import, admin, abonnements) : **@docs/CONTEXTE.md** — à lire avant toute nouvelle fonctionnalité.
Plan de développement par lots et critères d'acceptation : **docs/PLAN_DEVELOPPEMENT.md**. Avancement : **docs/AVANCEMENT.md**. Décisions prises en cours de route : **docs/DECISIONS.md**.

## Stack (non négociable)

- **Laravel 13**, PHP 8.3+, MySQL 8 (utf8mb4).
- **Site public** : Blade + Tailwind CSS 4 (Vite) + Alpine.js. JS vanilla pour le fil infini.
- **Back-office** : Blade + Tailwind + **jQuery, Yajra DataTables (mode serveur), Select2, SweetAlert2**, uniquement via l'entrée Vite `resources/js/admin.js` / `resources/css/admin.css`.
- **Interdits partout** : Filament, Livewire, Inertia, React, Vue, Bootstrap.
- **Interdits sur le site public** : jQuery, DataTables, Select2, SweetAlert2 (budget JS < 50 Ko).
- Cache **Memcached** ; sessions et files en **database**.
- Paquets : `laravel/fortify`, `propaganistas/laravel-phone`, `spatie/laravel-permission`, `spatie/laravel-medialibrary`, `yajra/laravel-datatables`, `google/apiclient`, `barryvdh/laravel-dompdf` (reçus PDF). Tests en **Pest**, analyse statique **Larastan** (niveau 6).
- Avant d'ajouter une dépendance : demander.

## Commandes

```bash
composer install && npm install
php artisan migrate --seed           # pays, communes d'Abidjan, catégories, rôles, admin
composer run dev                     # serveur + vite + queue + logs
php artisan test                     # Pest
./vendor/bin/pint                    # formatage (obligatoire avant commit)
php artisan veille:importer          # import manuel de la veille Drive
php artisan fil:vider-cache          # incrémente fil:version
php artisan publications:expirer     # expiration (planifiée chaque jour à 02h00)
php artisan abonnements:traiter      # relances J-3/J-1/J0, grâce, expirations (planifiée chaque heure)
./vendor/bin/phpstan analyse         # Larastan niveau 6
```

## Conventions de nommage — FRANÇAIS

- **Tables, colonnes, modèles, enums, valeurs de données, routes (URL et noms), vues, libellés** : en français, sans accents dans les identifiants (`publiee_le`, `modifie_le`).
  - Tables : `utilisateurs`, `publications`, `alertes`, `communes`, `categories`, `entreprises`…
  - Routes : `/emplois`, `/services`, `/formations`, `/publication/{slug}`, `/mon-espace/alertes` ; noms : `fil.index`, `alertes.store`…
- **Chaque modèle déclare `protected $table`** : le pluraliseur de Laravel est anglais, ne jamais s'y fier.
- Horodatages : trait `App\Models\Concerns\HorodatageFrancais` (`cree_le`, `modifie_le`, `supprime_le`) sur tous les modèles métier.
- Le modèle d'auth est `App\Models\Utilisateur` (table `utilisateurs`, colonne `mot_de_passe` → surcharger `getAuthPasswordName()`). Configurer `config/auth.php` en conséquence.
- Tables techniques du framework et de Spatie : **noms anglais d'origine** (`jobs`, `sessions`, `notifications`, `cache`, `roles`, `media`…).
- Les valeurs fixes passent par des **Enums PHP** backed string (`TypePublication::Emploi = 'emploi'`) castés dans les modèles. Pas de chaînes magiques.
- Le code technique hors domaine (mots-clés Laravel, méthodes de framework) reste standard.

## Architecture

- Contrôleurs **fins** : validation dans `FormRequest`, logique dans `app/Actions/*` (une classe = une action, méthode `executer()`) ou `app/Services/*`.
- Autorisation via `Policy` + rôles Spatie. Ne jamais vérifier un rôle en dur dans une vue sans `@can`.
- Effets de bord (alertes, cache, enrichissement IA) via **événements + jobs en file**, jamais en synchrone dans la requête.
- `$fillable` explicite, aucun `$guarded = []`.
- `Model::preventLazyLoading()` et `preventSilentlyDiscardingAttributes()` actifs hors production.

## Fil infini — règles

- **Toujours `cursorPaginate()`**, tri `tri_le DESC, id DESC` (`tri_le` = publication ou dernière remontée).
- Les cartes sponsorisées sont **injectées hors curseur** par `EmplacementsSponsorises` (paramètre `?deja=N`). **Jamais `paginate()` / `OFFSET`** sur le fil.
- Première salve rendue côté serveur. Suites via `GET /fil/suite`, qui renvoie **un fragment HTML** (`fil/_cartes.blade.php`) + l'en-tête `X-Curseur-Suivant`.
- Le fil ne lit que les colonnes dénormalisées de `publications` (pas de jointure sur les tables `details_*` pour les cartes).
- Garder le bouton « Charger plus » de secours et `aria-live="polite"`.

## Cache (Memcached) — règles

- Passer **uniquement** par `App\Services\CacheFil` ; aucun `Cache::` direct dans les contrôleurs ou les vues.
- **Clés versionnées, pas de tags** : `fil:v{version}:…`, `carte:{id}:{modifie_le_ts}`, `ref:*`.
- Invalidation **dans les Observers uniquement** (`PublicationObserver` incrémente `fil:version`).
- **Jamais de données propres à l'utilisateur dans le cache partagé** (j'aime, enregistré, notifications). Elles se chargent en une requête pour les ids visibles.
- Compteurs (`nb_jaime`…) : `increment()` / `decrement()` atomiques, sans invalider la carte.
- Clés chaudes : `Cache::lock()` contre l'effet ruée.

## Téléphone et authentification

- Identifiant = **téléphone + mot de passe**. Toujours valider avec `phone:{pays_code},mobile` et **stocker en E.164**.
- Normaliser la saisie avant toute recherche (connexion, unicité) via `App\Services\TelephoneService`.
- Affichage : format national pour le pays de l'utilisateur, international sinon.
- Pays par défaut : `CI` (+225). Liste des pays dans la table `pays` (cache `ref:pays`).
- Ne jamais exposer le téléphone d'un membre ; seul `details_service.telephone_contact` est public.
- Rate limit : 5 tentatives par minute (téléphone + IP) sur la connexion et l'OTP.

## Front-end

- Mobile-first (360 px de référence), navigation basse sur mobile, trois colonnes à partir de `lg`.
- Composants Blade dans `resources/views/components/` (`x-carte-publication`, `x-badge-type`, `x-bouton`, `x-champ-telephone`…). Réutiliser avant de créer.
- Couleurs par type : emploi = bleu, service = orange, formation = vert (tokens dans `resources/css/app.css` via `@theme`).
- Images : medialibrary → WebP, `loading="lazy"`, `width`/`height` obligatoires.
- Pas de police web bloquante ; budget JS < 50 Ko.
- Accessibilité AA : labels, focus visible, cibles ≥ 44 px, `alt`, `prefers-reduced-motion`.
- Textes de l'interface en français, au vouvoiement, simples. Dates relatives (« il y a 2 h ») via Carbon `fr`. Montants en FCFA : `150 000 FCFA`.

## PWA

- `vite-plugin-pwa` (Workbox) : cache-first pour les assets, stale-while-revalidate pour `/fil/suite`, page hors ligne.
- **Ne jamais mettre en cache** `/mon-espace/*`, `/admin/*` ni les réponses POST.

## Back-office (`/admin`) — règles

Spécification complète : CONTEXTE.md §13.

- Contrôleurs dans `App\Http\Controllers\Admin\*`, Actions dans `app/Actions/Admin/*`, vues dans `resources/views/admin/*`, composants `x-admin.*`.
- Comptes de l'équipe **séparés des membres** : modèle `App\Models\Administrateur` (table `administrateurs`, garde `admin`), connexion `/admin/connexion` (nom d'utilisateur + mot de passe). Ne jamais donner de rôle d'équipe à un `Utilisateur` (D-066).
- Groupe protégé par `auth:admin`, puis `can:<permission>` par module. Catalogue des permissions et rôles livrés dans `config/admin.php` (lu via `App\Support\PermissionsAdmin`) ; super administrateur initial dans `.env` (`ADMIN_*`). Vues : `@can`, jamais `@role`.
- Dans le code admin, l'auteur des actions vient de `ControleurAdmin::admin()` (type `Administrateur`).
- Listes : **Yajra DataTables, toujours `serverSide`**, une classe par liste dans `App\DataTables\*`. Pas de chargement de toute une table côté client.
- Sélections avec recherche ou multiples : **Select2** (Ajax pour les grandes listes).
- Confirmations d'actions destructives et toasts : **SweetAlert2**.
- Chaque Action admin utilise le trait `JournaliseActivite`, qui écrit dans `journal_activites`.
- Aucune page admin n'est mise en cache, sauf les compteurs du tableau de bord (`admin:compteurs`, 5 min).
- Les paramètres se lisent via `Parametre::valeur('cle')` (cache `ref:parametres`), jamais en dur dans le code.

## Publication par les utilisateurs — règles

Spécification complète : CONTEXTE.md §11.

- Quatre parcours : `emploi`, `service-offre`, `service-demande`, `formation`. Une FormRequest par parcours.
- `PublierPublication` fait tout dans **une transaction** : publication + détails + médias + champs dénormalisés (`extrait`, `slug`, `auteur_nom_affiche`, `auteur_avatar_url`).
- Le statut se décide **uniquement** via `DeciderStatutPublication` (première publication, signaux de risque, confiance, entreprise vérifiée — §11.6). Jamais de `statut = publiee` écrit ailleurs.
- `AnalyserRisquePublication` (règles) est synchrone ; le score IA vient ensuite, en file.
- L'aperçu utilise **le même composant** `x-carte-publication` que le fil.
- Le téléphone d'un demandeur de service n'est **jamais** affiché ; les coordonnées passent par `reponses`.
- Quotas et limites lus dans `parametres` (`max_publications_par_jour`…).

## Abonnements, droits et paiements — règles

Spécification complète : CONTEXTE.md §14.

- **Aucun prix, quota, durée ou essai écrit en dur.** Tout vient de `plans`, `plan_limites`, `produits` et `parametres`.
- Toute vérification de capacité ou de quota passe par `App\Services\Droits` (`peut`, `limite`, `restant`, `verifierOuEchouer`) avec l'enum `CleDroit`. Jamais de `if ($user->plan === ...)`.
- Les droits effectifs combinent le plan par défaut de la cible et tous les abonnements `essai | actif | grace` (max pour les entiers, `-1` illimité, OU pour les booléens) + les crédits.
- `quotas_actifs = false` ⇒ toutes les clés de quota sont illimitées.
- **Paiement** : interface `PasserellePaiement` (drivers `cinetpay`, `fake`). Activation **uniquement** via le webhook revérifié auprès de l'API de la passerelle, de façon idempotente (`ActiverCommande` + `Cache::lock`). Jamais sur la seule redirection de retour.
- Montants : entiers en FCFA TTC ; TVA et HT stockés sur la commande ; reçus numérotés séquentiellement.
- **SMS** : interface `FournisseurSms` (drivers `log`, `orange`, `twilio`). GSM-7 sans accents, ≤ 160 caractères, lien `/p/{code_court}`, aucun envoi entre 21h00 et 7h00.
- Un essai par empreinte SHA-256 du numéro et par famille de plan (`essais_utilises`) ; numéro vérifié obligatoire.
- Payer ne contourne jamais la modération. Toute carte payée porte « Sponsorisé » ou « À la une ».

## Services externes en développement et en test

- `PAIEMENT_DRIVER=fake`, `SMS_DRIVER=log`, `VEILLE_DRIVE_DRIVER=fake` (fichiers CSV de `tests/Fixtures/veille/`), `IA_DRIVER=fake` (score déterministe).
- Tests : `Http::fake()` pour CinetPay, Claude et Google ; `Notification::fake()`, `Queue::fake()`, `Bus::fake()` selon le cas. **Aucun appel réseau réel dans les tests.**

## Import de la veille (Drive)

- `veille:importer` (planifiée à 01h20, 09h20 et 17h20) : fichiers déjà présents dans `imports_veille` ignorés.
- Doublons : `source_empreinte` = sha256 de l'URL normalisée (sans query de tracking, sans slash final), **unique** en base.
- Publication automatique seulement si `sources_veille.niveau_confiance = haut` **et** `score_fiabilite >= 70` ; sinon `en_attente`.
- Afficher un résumé + un lien vers la source, jamais le texte intégral copié.
- Identifiants Google et clé API Claude : uniquement dans `.env`, jamais commités.

## Tests et qualité

- Chaque Action, Job et règle métier a son test Pest (Feature pour les routes, Unit pour les services).
- Cas obligatoires :
  - normalisation des numéros (CI, SN, FR) ;
  - fil par curseur (aucun doublon entre deux salves) ;
  - invalidation du cache après publication ;
  - correspondance des alertes ;
  - déduplication de l'import ;
  - décision de statut d'une publication (chaque ligne du tableau §11.6) ;
  - détection des signaux de risque (§11.5) ;
  - calcul des droits (combinaison des plans, illimité, crédits, `quotas_actifs`) ;
  - essai unique par numéro ;
  - webhook de paiement idempotent et revérifié ;
  - injection des cartes sponsorisées sans doublon dans le fil.
- `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse` et `php artisan test --parallel` doivent passer avant de déclarer une tâche terminée.

## Déploiement

- Git → `composer install --no-dev --optimize-autoloader` (**jamais `composer update` en prod**) → `php artisan migrate --force` → `php artisan optimize` → `php artisan view:cache`.
- Cron : `schedule:run` chaque minute ; files : `queue:work --stop-when-empty` (mutualisé) ou Supervisor (VPS).

## À ne pas faire

- Ajouter Filament, Livewire ou Bootstrap ; charger jQuery ou les plugins admin sur le site public.
- Utiliser la pagination classique sur le fil.
- Laisser Laravel deviner un nom de table.
- Appeler `Cache::` hors de `CacheFil`.
- Faire un traitement lourd dans la requête HTTP.
- Stocker un téléphone non normalisé.
