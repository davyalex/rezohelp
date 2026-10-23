# Rezohelp — Avancement

> Questions ouvertes pour Alex : aucune pour le moment.

| Lot | Titre | État |
|---|---|---|
| 0 | Socle technique | terminé (tag `lot-0`) |
| 1 | Base de données, modèles, référentiels | terminé (tag `lot-1`) |
| 2 | Authentification par téléphone | terminé (tag `lot-2`) |
| 3 | Fil public et pages de publication | terminé (tag `lot-3`) |
| 4 | Publication par les utilisateurs | terminé (tag `lot-4`) |
| 5 | Mon espace, alertes et notifications | terminé (tag `lot-5`) |
| 6 | Abonnements, paiements, SMS, visibilité payante | terminé (tag `lot-6`) |
| 7 | Back-office | terminé (tag `lot-7`) |
| 8 | Import de la veille et enrichissement IA | terminé (tag `lot-8`) |
| 9 | PWA, performance, accessibilité, sécurité | en cours |
| 10 | Recette finale et mise en production | à faire |

---

## Lot 0 — Socle technique

### Plan d'implémentation

- **Dépôt** : `git init`, documents de référence rangés dans `docs/` (contenu inchangé).
- **Paquets PHP** : `laravel/fortify`, `propaganistas/laravel-phone`, `spatie/laravel-permission`, `spatie/laravel-medialibrary`, `yajra/laravel-datatables`, `google/apiclient` (services limités à Drive), `barryvdh/laravel-dompdf` ; dev : `pestphp/pest`, `pestphp/pest-plugin-laravel`, `larastan/larastan`.
- **Paquets JS** : `alpinejs` (public) ; `jquery`, `datatables.net-dt`, `select2`, `sweetalert2` (admin uniquement).
- **Configuration** : locale `fr`, fuseau `Africa/Abidjan`, Carbon en français, traductions `lang/fr/*` ; cache `memcached` avec repli `file` si l'extension manque ; sessions et files `database` ; `config/rezohelp.php` (drivers, compte admin).
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
- **Qualité** : `phpstan.neon` (niveau 6), `pint.json`, Pest, `phpunit.xml` sur MySQL (`rezohelp_test`).
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

- [ ] `composer install && npm install`, puis `php artisan migrate --seed` sur une base `rezohelp` vide.
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

- `tests/Unit/Modeles/FactoriesTest.php` (61 : une factory par modèle, `$table` déclaré dans chaque modèle, états)
- `tests/Unit/Modeles/RelationsTest.php` (7) · `tests/Unit/Modeles/UniciteTest.php` (9) · `tests/Unit/Modeles/ParametreTest.php` (9) · `tests/Unit/Modeles/TelephoneE164Test.php` (3)
- `tests/Unit/Services/TelephoneServiceTest.php` (19 : CI sous 5 formats, SN, FR, saisies invalides, mobile, affichage, empreinte)
- `tests/Feature/SeedersTest.php` (8)

### Écarts avec le CONTEXTE

- Colonnes ajoutées (D-011, D-012), pivots sans modèle (D-013), cast maison et format ivoirien par paires (D-014), valeurs de grille non précisées (D-016), paramètres supplémentaires (D-017).

### À vérifier par Alex

- [ ] `php artisan migrate:fresh --seed` puis `php artisan db:seed --class=DemoSeeder` sans erreur.
- [ ] Dans la base : 13 communes d'Abidjan, 9 plans dont 4 « par défaut » (un par cible), l'administrateur avec le rôle `administrateur`.
- [ ] Les prix et limites de `PlansSeeder` correspondent à la grille §14.4 (les valeurs ajoutées sont listées en D-016).
- [ ] La liste des mots à risque et les plafonds de salaire (`ParametresSeeder`) vous conviennent.

---

## Lot 2 — Authentification par téléphone

### Plan d'implémentation

- **Migration** : `codes_otp` (téléphone E.164, objet, empreinte du code, essais, expiration, utilisation).
- **Enums** : `ObjetOtp` (verification, reinitialisation, changement_numero), `ResultatOtp`.
- **Fortify** : chemins `/connexion`, `/inscription`, `/deconnexion` ; vues Blade maison ; `authenticateUsing` (normalisation via `TelephoneService`, refus des comptes suspendus) ; limiteur `connexion` 5/min (téléphone normalisé + IP) ; action `App\Actions\Auth\CreerCompte`.
- **OTP** : `App\Actions\Otp\EnvoyerCodeOtp` / `VerifierCodeOtp` (6 chiffres, 10 min, 5 essais, code haché) ; SMS via `App\Actions\Sms\PlanifierSms` + job chiffré `EnvoyerSms` (journal `sms_envoyes`, code masqué) ; limiteur `otp` 5/min.
- **Écrans** : connexion, inscription, vérification du numéro, mot de passe oublié (OTP), réinitialisation, changement obligatoire.
- **Middlewares** : `VerifierCompteActif` (déconnecte un compte suspendu), `ExigerChangementMotDePasse`.
- **Composants** : `x-champ-telephone`, `x-champ`, `x-bouton`, `x-message`.
- **Tests** : trois formats CI → même compte, SN et FR, doublon, limites de débit, OTP expiré / faux / trop d'essais, compte suspendu, mot de passe compromis, `intended()` conservé, changement obligatoire.

### Fait

- Fortify : inscription (`CreerCompte` : nom, prénoms, pays actif, mobile valide pour le pays, numéro libre une fois normalisé, mot de passe ≥ 8 et non compromis), connexion (`AuthentifierParTelephone` : normalisation E.164 avec le pays choisi, refus des comptes suspendus avec date et motif), déconnexion, « Rester connecté », `redirect()->intended()` conservé (y compris après l'inscription et la vérification).
- Limiteurs `connexion` et `otp` : 5 tentatives par minute par numéro normalisé + IP (un membre connecté est identifié par son compte).
- OTP : `EnvoyerCodeOtp` (6 chiffres, 10 min, annule le code précédent, empreinte HMAC) et `VerifierCodeOtp` (5 essais puis blocage, usage unique), via `PlanifierSms` → job chiffré `EnvoyerSms` → `FournisseurSms` (journal `sms_envoyes`, code masqué).
- Écrans : connexion, inscription, vérification du numéro (renvoi du code, « Vérifier plus tard »), mot de passe oublié (réponse identique que le compte existe ou non), nouveau mot de passe, changement obligatoire.
- Middlewares web : `VerifierCompteActif` (déconnecte un compte suspendu en cours de session), `ExigerChangementMotDePasse` (`doit_changer_mot_de_passe`).
- Écouteurs : `EnregistrerDerniereConnexion` (`derniere_connexion_le`), `EnvoyerCodeVerificationTelephone` (après l'inscription).
- Composants : `x-champ-telephone` (pays actifs avec drapeau et indicatif, CI par défaut), `x-champ`, `x-bouton`, `x-message`, `x-carte-formulaire` ; en-tête avec « Se connecter » / « Se déconnecter ».
- Vérifié en réel (serveur local + `curl`) : inscription → code lisible dans `storage/logs/sms.log` → déconnexion → connexion avec « +225 05 06 07 08 09 ».

### Tests ajoutés (63 tests, 7 fichiers ; total 208)

- `tests/Feature/Auth/InscriptionTest.php` (15) · `ConnexionTest.php` (16) · `VerificationTelephoneTest.php` (8) · `MotDePasseOublieTest.php` (9) · `ChangementMotDePasseTest.php` (6)
- `tests/Unit/Actions/CodeOtpTest.php` (6) · `tests/Feature/Composants/ChampTelephoneTest.php` (2) ; `CodeOtp` ajouté au test des factories.
- Appels à « Have I Been Pwned » simulés dans `tests/TestCase.php` (`declarerCompromis()`), `Http::preventStrayRequests()` actif pour toute la suite ; double `tests/Support/FournisseurSmsEnMemoire.php`.

### Écarts avec le CONTEXTE

- Noms de routes Fortify et champ `password` (D-020) ; vérification du numéro proposée sans être bloquante (D-021) ; OTP hors plage silencieuse (D-022).
- Fournisseur SMS de production : **à choisir (§18.3)** — les drivers `orange` / `twilio` arrivent au lot 6 ; identifiants à fournir.

### À vérifier par Alex

- [ ] S'inscrire avec un numéro ivoirien (ex. « 05 06 07 08 09 ») : arrivée sur « Vérifiez votre numéro ».
- [ ] Lancer `php artisan queue:work --stop-when-empty` (ou `composer run dev`) puis lire le code dans `storage/logs/sms.log`, le saisir : numéro vérifié.
- [ ] Se déconnecter, se reconnecter en tapant « +225 05 06 07 08 09 » (autre format) : connexion réussie.
- [ ] « Mot de passe oublié ? » : recevoir le code dans `storage/logs/sms.log`, choisir un nouveau mot de passe, se connecter avec.
- [ ] Six mauvaises tentatives de connexion en moins d'une minute : la sixième est refusée (« Trop de tentatives »).

---

## Lot 3 — Fil public et pages de publication

### Plan d'implémentation

- **Données** : colonnes dénormalisées ajoutées à `publications` pour que les cartes ne lisent jamais `details_*` : `faits_cles` (JSON), `type_contrat`, `auteur_verifie`, `image_url` / `image_largeur` / `image_hauteur` ; service `FaitsCles` + helper `Montant::fcfa()`.
- **Cache** : `CacheFil` complet (`fil:version`, salves `fil:v{n}:…`, cartes `carte:{id}:{ts}`, compteurs, verrous `Cache::lock`) ; `PublicationObserver` (incrémente `fil:version`) ; gabarit de carte partagé avec jetons (compteurs, « il y a… », état J'aime / enregistré, CSRF) remplacés à chaque requête : aucune donnée de l'utilisateur dans le cache.
- **Fil** : `FiltresFil` + `FilRequest`, `FilService` (`cursorPaginate(12)` sur `tri_le DESC, id DESC`), `FilController` (`/`, `/emplois`, `/services`, `/formations`, `/recherche`, `/fil/suite` fragment + `X-Curseur-Suivant`, `/fil/nouvelles`), JS vanilla `fil.js` (IntersectionObserver, squelettes, « Charger plus », `aria-live`, bandeau « N nouvelles publications »).
- **Publication** : `PublicationController@show` (`/publication/{slug}`, policy `view`, JSON-LD `JobPosting`, Open Graph), `/p/{code}`, partage WhatsApp compté.
- **Interactions** : actions `BasculerJaime`, `BasculerEnregistrement`, `PublierCommentaire`, `SupprimerCommentaire`, `SignalerPublication` ; `SignalementObserver` (masquage à `seuil_masquage_signalements`) + notification des modérateurs.
- **Recherche** : FULLTEXT (mode booléen) + job `EnregistrerRecherche`.
- **SEO** : `sitemap.xml`, `robots.txt` ; pages d'erreur en français.
- **Composants** : `x-carte-publication`, `x-badge-type`, layout public (en-tête, onglets, puces, trois colonnes, navigation basse).
- **Tests** : curseur sans doublon ni trou (avec insertion entre deux salves), filtres combinés, invalidation du cache, absence de données personnelles dans le cache, 404, JSON-LD, masquage après 3 signalements, interactions, recherche.

### Fait

- Fil par curseur : `/`, `/emplois`, `/services`, `/formations`, `/recherche` ; filtres commune, contrat, catégorie (avec ses métiers), « Aujourd'hui », combinables ; `cursorPaginate(12)` sur `tri_le DESC, id DESC` ; première salve rendue par le serveur ; `/fil/suite` (fragment + `X-Curseur-Suivant`, `Cache-Control: public, max-age=60` pour les visiteurs) ; `/fil/nouvelles` pour le bandeau « N nouvelles publications ».
- `fil.js` : IntersectionObserver (800 px avant la fin), squelettes, « Charger plus » de secours, `aria-live="polite"` / `aria-busy`, « Vous êtes à jour 🎉 ». JS public : 1,3 Ko gzip + Alpine 19 Ko gzip.
- `CacheFil` : version du fil, salves (5 min), gabarits de cartes (24 h), référentiels, compteurs (10 min), verrou anti-ruée sur chaque recalcul. `PublicationObserver` incrémente `fil:version` (publication, dépublication, suppression, restauration).
- `x-carte-publication` (avatar ou initiale, entreprise vérifiée, « il y a 2 h · Cocody », badge de type, menu Enregistrer / Signaler, extrait 4 lignes + « Voir plus », puces, « via Emploi.ci », bandeau de prévention sur les emplois, image différée avec dimensions, compteurs, J'aime optimiste, Commenter, Partager, bouton principal par type) et `x-badge-type`.
- Page détail : contenu complet, détails par type, contact selon le mode (lien, e-mail, téléphone, WhatsApp si l'artisan l'autorise ; jamais le numéro d'un demandeur), lien « Voir l'annonce » pour la veille, bandeaux clôturée / expirée, commentaires (publier, répondre sur un niveau, supprimer les siens ; la modération peut supprimer), signalement, JSON-LD `JobPosting`, Open Graph, canonique. `/p/{code}` (301) et partage WhatsApp compté.
- Signalement : `SignalementObserver` masque la publication au 3e signalement « arnaque » non traité (seuil réglable) et notifie les modérateurs (`PublicationMasquee`, canal `database`, en file).
- Recherche FULLTEXT (mode booléen, préfixes, insensible aux accents) + job `EnregistrerRecherche`.
- `sitemap.xml` (publications en ligne, en cache), `robots.txt` (exclut `/mon-espace`, `/admin`, fragments), pages d'erreur 403 / 404 / 419 / 429 / 500 / 503 en français, image Open Graph par défaut.
- Layout public : en-tête (recherche, connexion), onglets, puces de filtre défilantes, trois colonnes à partir de `lg` (catégories, fil 640 px, communes populaires et formations récentes), navigation basse mobile.
- DemoSeeder : faits clés, type de contrat et entreprise vérifiée calculés pour les 60 publications.
- Vérifié en réel : pages du fil, fragment suivant (12 cartes + curseur), JSON-LD, lien court, 404, partage.

### Tests ajoutés (72 tests, 10 fichiers ; total 280)

- `tests/Feature/Fil/FilCurseurTest.php` (salves 12/12/6 sans doublon ni trou, insertion entre deux salves, remontée, exclusions) · `FiltresFilTest.php` · `CacheFilTest.php` (clé versionnée, invalidation à la publication / dépublication / suppression, compteurs sans invalidation, aucune donnée personnelle dans le cache, date relative fraîche) · `FilSuiteTest.php`
- `tests/Feature/Publications/PageDetailTest.php` (404, auteur et modération, clôturée, JSON-LD valide et échappé, Open Graph, téléphones, veille, lien court, partage) · `InteractionsTest.php` · `SignalementTest.php` (masquage à 3, motifs et traités exclus, seuil paramétrable, un par membre)
- `tests/Feature/ReferencementTest.php` · `tests/Unit/Services/FaitsClesTest.php` · `tests/Recherche/RechercheTest.php` (suite sans transaction)

### Écarts avec le CONTEXTE

- Colonnes dénormalisées (D-025), gabarit à jetons (D-026), bouton principal vers la page détail (D-027), visibilité des pages (D-028), signalement réservé aux membres (D-029).
- Bloc « Créer une alerte », cloche, entrées Publier / Alertes / Mon espace : lots 4 et 5 ; « À la une » / « Sponsorisé » : lot 6 (D-031).
- À faire au lot 4 : valider en `http(s)` / e-mail les contacts saisis (`contact_candidature`, `contact_inscription`) qui deviennent des liens.

### À vérifier par Alex

- [ ] `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder`, puis `composer run dev`.
- [ ] Sur mobile (360 px, outils de développement du navigateur) : le fil s'affiche sans saut, le défilement charge les suites automatiquement, « Vous êtes à jour 🎉 » en fin de fil.
- [ ] Les onglets et les puces (commune, contrat, « Aujourd'hui ») filtrent le fil ; en desktop, les trois colonnes apparaissent.
- [ ] « Partager » ouvre WhatsApp avec le titre et le lien court `/p/…`.
- [ ] Connecté : J'aime change tout de suite, Enregistrer, commenter, répondre, supprimer son commentaire, signaler.
- [ ] Une offre d'emploi passe le test des résultats enrichis de Google (code source : bloc `application/ld+json`).

---

## Lot 4 — Publication par les utilisateurs

### Plan d'implémentation

- **Droits (version minimale)** : `App\Services\Droits::pour($titulaire)` → `peut()`, `limite()`, `restant()`, `verifierOuEchouer()` ; plans par défaut des cibles du titulaire (utilisateur : membre + artisan ; entreprise : recruteur + organisme), combinaison max / -1 / OU, `quotas_actifs`, consommation calculée par requête ; `QuotaAtteintException`. Abonnements, crédits et cache au lot 6.
- **Actions** (`app/Actions/Publication`) : `PublierPublication` (transaction : entreprise créée en ligne, publication, détails, médias, champs dénormalisés, `slug`, `code_court`, `tri_le`), `AnalyserRisquePublication` (§11.5), `DeciderStatutPublication` (§11.6), `AttribuerRoleAuteur`, `ModifierPublication`, `CloturerPublication`, `ProlongerPublication`, `DupliquerPublication`, `SupprimerPublication`, `RenvoyerPublication`, `RepondrePublication`, `ChangerStatutReponse`, `MettreAJourConfiance`.
- **Événement** `PublicationPubliee` ; **job** `EnrichirPublicationIA` (`ClientIA`, journal `appels_ia`, remise en attente sous `seuil_remise_en_attente`) ; écouteur de conversion médias (image de la carte en WebP).
- **FormRequests** : `PublierEmploiRequest`, `PublierServiceOffreRequest`, `PublierServiceDemandeRequest`, `PublierFormationRequest` (base commune), `RepondreRequest`, `ChangerStatutReponseRequest`, `CloturerRequest`.
- **Policies** : `PublicationPolicy@create` (suspendu, restreint, `max_publications_par_jour`), `update` / `delete` / `cloturer` (auteur), `repondre` (connecté, pas l'auteur, publiée) ; `ReponsePolicy@update` (auteur de la publication).
- **Écrans** : choix du type, formulaires des 4 parcours (blocs repliables, compteurs, brouillon navigateur Alpine + `localStorage`, « Enregistrer en brouillon »), aperçu par `x-carte-publication`, confirmation, « Quota atteint », « Mes publications » (statuts, motif de rejet, statistiques, actions), réponses reçues, formulaires de réponse sur la page détail.
- **Notifications** (database) : nouvelle réponse, statut de réponse modifié, publication remise en attente.
- **Tests** : chaque ligne du tableau §11.6, chaque règle du §11.5, quota journalier, doublon sur 7 jours, téléphone d'un demandeur jamais exposé, réponse unique, modification par un auteur `nouveau` puis `fiable`, prolongation limitée à 3, droits minimaux.

### Fait

- **Parcours** `/publier` → `/publier/{type}` (emploi, service-offre, service-demande, formation) → aperçu → enregistrement → confirmation (« en ligne » + partage WhatsApp, ou « visible après vérification, généralement en moins de 2 h »). Un visiteur est envoyé à la connexion puis ramené au formulaire choisi.
- **Formulaire unique** des quatre parcours : blocs repliables, compteur de caractères, aide sous les champs, employeur / organisme (une de mes entreprises, création en ligne avec logo, particulier, nom libre), contacts selon le mode (e-mail, lien `http(s)` uniquement, téléphone normalisé en E.164), zones d'intervention, « WhatsApp : même numéro », titre proposé pour une offre de service, photos limitées par `publication.photos_max` et converties en WebP 640 px pour la carte (image dénormalisée avec ses dimensions).
- **Brouillon** dans le navigateur (`localStorage` toutes les 5 s, restauration, « Recommencer à zéro ») et brouillon serveur. **Aperçu** rendu par `x-carte-publication` via `RenduCarte`, sans rien écrire en base.
- **Actions** : `PublierPublication` (transaction), `AnalyserRisquePublication` (expressions à risque paramétrables, téléphone ou WhatsApp dans une offre d'emploi, salaire au-dessus du plafond du contrat, majuscules > 50 %, liens raccourcis), `DeciderStatutPublication` (tableau §11.6), `DetecterDoublon` (similarité ≥ 90 % sur 7 jours), `AttribuerRoleAuteur`, `MettreAJourConfiance` (fiable à 3 validations, restreint à 2 rejets pour arnaque ou un signalement retenu), `ModifierPublication`, `CloturerPublication`, `ProlongerPublication`, `DupliquerPublication`, `RepondrePublication`, `ChangerStatutReponse`.
- **Anti-spam** : policy `create` (suspendu, restreint, `max_publications_par_jour`, avec le message affiché), limiteur `publication` (`max_envois_par_heure`), doublon bloqué avec invitation à prolonger.
- **Droits (minimal)** et **« Quota atteint »** quand `quotas_actifs` est activé et le quota épuisé.
- **Mes publications** : badges de statut, motif de rejet, statistiques (vues, j'aime, partages, réponses), modifier / corriger et renvoyer / compléter un brouillon, clôturer, prolonger, dupliquer, supprimer, partager.
- **Réponses** : candidature et inscription (message, numéro partagé au choix), contact quand l'artisan masque son numéro, « Je suis disponible » (numéro toujours transmis, quota `reponses.demandes_mois`), une seule par personne ; contact externe compté ; réponses reçues avec numéro seulement s'il est partagé ; vue / retenue / refusée.
- **Notifications** (database, en file) : nouvelle réponse, statut de réponse modifié, publication remise en attente. Événement `PublicationPubliee` (après la transaction) pour les alertes du lot 5.
- **Job** `EnrichirPublicationIA` (driver `fake`) : score, signaux, journal `appels_ia`, remise en attente sous 40.
- Navigation : « + Publier » (en-tête desktop, navigation basse) et « Mes publications ».
- Vérifié en réel : connexion d'un membre de démo, ouverture des 4 formulaires et de « Mes publications », publication d'une demande de service (en attente).

### Tests ajoutés (111 tests, 7 fichiers ; total 391)

- `tests/Feature/Publication/DeciderStatutPublicationTest.php` (9 : chaque ligne du tableau §11.6)
- `tests/Unit/Actions/AnalyserRisquePublicationTest.php` (20 : chaque règle du §11.5, faux positifs, paramètres)
- `tests/Feature/Publication/PublierTest.php` (32 : 4 parcours, entreprise en ligne, publication directe, « frais de dossier », brouillon, validations, contacts, quota journalier, limiteur horaire, doublon 7 jours, numéro du demandeur jamais exposé, aperçu, quota atteint, compte restreint, photos)
- `tests/Feature/Publication/MesPublicationsTest.php` (22 : modification par un auteur nouveau puis fiable, expiration, rejet corrigé, brouillon, clôture, prolongation limitée à 3, duplication, suppression, accès croisé, photos)
- `tests/Feature/Publication/ReponsesTest.php` (14 : réponse unique, types, quota, numéro partagé ou non, statuts, accès croisé)
- `tests/Feature/Publication/ConfianceEtIATest.php` (8) · `tests/Unit/Services/DroitsTest.php` (6)
- Données de test partagées : `tests/Support/donnees-publication.php`.

### Écarts avec le CONTEXTE

- Titulaires des plans par défaut (D-033), demandes de service hors quota (D-034), brouillon serveur validé (D-035), règles de modification (D-036), réponses et prolongations (D-037), score IA des publications natives (D-038).
- Notifications « publication validée / rejetée » : avec la modération (lot 7) ; « expiration dans 3 jours » : avec `publications:expirer` (lot 7). Options d'achat de l'écran « Quota atteint » : lot 6.

### À vérifier par Alex

- [ ] Depuis un mobile (360 px), publier un exemple de chacun des 4 types ; la première publication part « en attente ».
- [ ] Une publication contenant « frais de dossier » part en attente ; le signal `expression_a_risque` est enregistré (visible en modération au lot 7).
- [ ] Couper la connexion pendant la saisie puis revenir : le brouillon est restauré.
- [ ] « Voir l'aperçu dans le fil » affiche la même carte que le fil.
- [ ] Dans « Mes publications » : modifier, clôturer, prolonger (3 fois au plus), dupliquer, supprimer.
- [ ] Avec un second compte : postuler, « Je suis disponible » sur une demande, puis retrouver la réponse et le numéro chez l'auteur.

---

## Lot 5 — Mon espace, alertes et notifications

### Plan d'implémentation

- **Mon espace** (`/mon-espace`) : accueil et menu (`x-menu-espace`), profil (nom, prénoms, commune, e-mail, photo ; nom et avatar dénormalisés rafraîchis sur les publications), changement de numéro par code SMS (`ObjetOtp::ChangementNumero`), suppression du compte avec anonymisation (`AnonymiserUtilisateur`, réutilisée par l'admin au lot 7), notifications (cloche + pastille, liste, lu / non lu, tout marquer), publications enregistrées.
- **Alertes** (`/mon-espace/alertes`, routes `alertes.*`) : `AlerteRequest`, `AlertePolicy` (`create` → `Droits` / `alertes.max`, `update`, `delete`), actions `CreerAlerte`, `ModifierAlerte`, `BasculerAlerte` (pause / reprise, limite revérifiée) ; création pré-remplie depuis les filtres du fil, y compris après une inscription (`/alertes/nouvelle` mémorise la page visée puis envoie vers `/inscription`).
- **Correspondance** : écouteur de `PublicationPubliee` → job `CorrespondreAlertes` (critères en SQL, mots-clés tous présents, auteur exclu, anti-doublon `alertes_publications`) ; notification `AlerteCorrespondante` (instantanée) ; commande `alertes:recap-quotidien` planifiée à 7h00 → `RecapitulatifAlertes`. Canal SMS préparé au lot 6.
- **Fil** : bloc « Créer une alerte » après la 5e carte de la première salve et dans la colonne de droite ; navigation basse complète (Fil · Recherche · Publier · Alertes · Mon espace) ; cloche dans l'en-tête.
- **Tests** : correspondance par type, catégorie (et sous-catégories), commune, contrat, mots-clés ; pas de double notification ; alertes en pause ignorées ; récapitulatif quotidien ; limite d'alertes ; pré-remplissage après inscription ; notifications ; profil, changement de numéro, anonymisation ; accès croisé.

### Fait

- **Mon espace** (`/mon-espace`) : accueil avec compteurs, menu commun (`x-menu-espace`), profil (prénoms, nom, commune, e-mail, photo ; nom et avatar recopiés sur les publications en son nom par le job `RafraichirAuteurPublications`), changement de numéro par code SMS envoyé au nouveau numéro (`ChangerNumero`), changement de mot de passe, suppression du compte avec mot de passe (`AnonymiserUtilisateur`), notifications (liste paginée, ouverture qui marque lue et ne suit que les liens internes, « Tout marquer comme lu »), publications enregistrées (cartes du fil).
- **Alertes** (`/mon-espace/alertes`) : création, modification, pause / reprise, suppression ; nom généré d'après les critères (« Électricité · Cocody ») ; au moins un critère ; limite `alertes.max` via `Droits` (`AlertePolicy@create`, revérifiée à la reprise) ; « Voir les résultats » ouvre le fil filtré ; création pré-remplie depuis les filtres du fil, y compris pour un visiteur (`/alertes/nouvelle` → inscription → vérification du numéro → formulaire pré-rempli).
- **Correspondance** : `PublicationPubliee` → `DeclencherCorrespondanceAlertes` → job `CorrespondreAlertes` (`CorrespondanceAlertes` : type, catégorie avec sous-catégories, commune, contrat en SQL ; mots-clés tous présents ; auteur et comptes suspendus exclus ; anti-doublon `alertes_publications`) ; notification `AlerteCorrespondante` (une par membre et par publication) ; alertes quotidiennes regroupées par `alertes:recap-quotidien`, planifiée à 7h00 (`RecapitulatifAlertes`).
- **Fil** : bloc « Créer une alerte » après la 5e carte de la première salve et dans la colonne de droite ; cloche avec pastille dans l'en-tête ; navigation basse complète (Fil · Recherche · Publier · Alertes · Mon espace).

### Tests ajoutés (40 tests, 4 fichiers ; total 431)

- `tests/Feature/Alertes/CorrespondanceAlertesTest.php` (12 : critères combinés, sous-catégories, contrat, mots-clés, pause, auteur, suspension, pas de double notification, plusieurs alertes d'un même membre, quotidienne, publication retirée, déclenchement par l'événement et par une vraie publication)
- `tests/Feature/Alertes/RecapitulatifQuotidienTest.php` (5) · `tests/Feature/Alertes/GestionAlertesTest.php` (13 : critères, pré-remplissage, parcours visiteur → inscription → alerte, limite `alertes.max`, pause, accès croisé)
- `tests/Feature/Espace/EspaceTest.php` (10 : accueil, cloche et notifications, lien externe refusé, enregistrés, profil, changement de numéro, anonymisation, bloc d'alerte dans le fil)

### Écarts avec le CONTEXTE

- Règles de correspondance (D-039), récapitulatif (D-040), parcours « Créer une alerte » d'un visiteur (D-041), suppression du compte (D-042).
- Canal SMS des alertes : préparé, activé au lot 6 (`alertes.canal_sms`). Incitation « Recevez-la aussi par SMS » : lot 6.

### À vérifier par Alex

- [ ] Sans compte, filtrer le fil sur « Home service » et « Cocody », cliquer « Créer une alerte » : arrivée sur l'inscription.
- [ ] S'inscrire (code dans `storage/logs/sms.log`) : le formulaire d'alerte est pré-rempli, créer « Électricien · Cocody ».
- [ ] Avec un autre compte (membre fiable, ou valider la publication en base en attendant la modération du lot 7), publier une offre d'électricien à Cocody, lancer `php artisan queue:work --stop-when-empty` : la pastille de la cloche apparaît et la notification mène à la publication.
- [ ] Mettre l'alerte en pause : plus de notification. `php artisan alertes:recap-quotidien` envoie le récapitulatif d'une alerte quotidienne.
- [ ] Profil : changer de photo et de nom, changer de numéro (code SMS), supprimer un compte de test.

---

## Lot 6 — Abonnements dynamiques, paiements, SMS, visibilité payante

### Plan d'implémentation

- **Données** (avant mise en production, D-009) : `abonnements.relances_envoyees`, `alertes_publications.sms_en_attente`, `publications.auteur_badge`, table `sequences` (numéros de commande et de reçu sans trou).
- **Droits complet** : plans par défaut + abonnements `essai | actif | grace` en cours + crédits non consommés ; cache `droits:v{version}:{type}:{id}` (10 min) via `CacheFil` ; `AbonnementObserver` (oubli de la clé, badge des cartes), `PlanObserver` / `PlanLimiteObserver` (version) ; consommation d'un crédit quand le quota du plan est épuisé.
- **Essais** : `DemarrerEssai` (plan avec essai, numéro vérifié, un essai par empreinte SHA-256 et par famille, à vie). **Cycle de vie** : commande `abonnements:traiter` (chaque heure) : relances J-3 / J-1 / J0, passage en grâce (`jours_grace`), expiration, mise en pause des alertes au-delà de `alertes.max`.
- **Commandes et paiement** : `CreerCommande` (montant figé, TVA, numéro `CMD-AAAA-000001`), `InitierPaiement`, webhook `POST /paiements/notification/{passerelle}` (signature, journal `notifications_paiement`, revérification auprès de la passerelle, verrou, idempotence), `ActiverCommande` (abonnement créé ou prolongé à la suite, essai remplacé, crédit, remontée, mise en avant, reçu `REC-AAAA-000001`, notification), page de retour auto-actualisée, guichet simulé du driver `fake`, driver **`cinetpay`** (initialisation, vérification, jeton `x-token`), reçus HTML et PDF.
- **SMS** : `SmsChannel` + `MessageSms` (GSM-7 sans accents, 160 caractères), plage silencieuse 21h00 → 7h00, `AlerteCorrespondante` par SMS si `alertes.canal_sms` et numéro vérifié, plafond `alertes.sms_par_jour` puis récapitulatif SMS à 19h00 (`alertes:recap-sms`), drivers **`orange`** et **`twilio`**.
- **Visibilité** : `RemonterPublication` (une fois par 24 h, remontées incluses `publication.remontees_mois` ou payées), mises en avant `une_commune` (bloc « À la une ») et `sponsorise_fil` (`EmplacementsSponsorises`, `?deja=N`, hors curseur, une fois par salve, rotation par dernière impression), étiquettes « Sponsorisé » / « À la une », impressions et clics cumulés en cache puis vidés toutes les 5 min, suspension et reprise avec la publication, badge « Pro » / « Entreprise ».
- **Pages** : `/tarifs`, « Mon abonnement » (plans, échéances, compteurs restants, renouveler, commandes et reçus), achats de produits depuis « Mes publications », incitations contextuelles, écran « Quota atteint » avec les options d'achat.
- **Tests** : droits combinés, `quotas_actifs`, crédits, essai unique même après suppression, webhook rejoué / signature invalide / montant falsifié, renouvellement cumulatif, grâce puis expiration, SMS tronqué sans accents, plage silencieuse, carte sponsorisée sans doublon ni rupture du curseur, publication rejetée jamais mise en avant, drivers réels avec `Http::fake()`.

### Fait

- **Droits complets** (`App\Services\Droits`) : plans par défaut + abonnements `essai | actif | grace` commencés + crédits non consommés et non expirés ; consommation d'un crédit (`imputer`) quand une publication dépasse le plan ; consommation SMS du jour (`alertes.sms_par_jour`) ; cache `droits:v{version}:{type}:{id}` (10 min, `CacheFil`) ; `AbonnementObserver` (oubli de la clé + job `RafraichirBadges`), `PlanObserver` sur `Plan` et `PlanLimite` (version).
- **Essais** (`DemarrerEssai`, `POST /essais`) : plan actif avec essai et famille, cible du titulaire, numéro vérifié, un essai par empreinte SHA-256 et par famille, à vie (survit à la suppression du compte), numéro du propriétaire pour une entreprise ; concurrence couverte par la clé primaire.
- **Cycle de vie** (`abonnements:traiter`, chaque heure) : relances J-3 / J-1 / J0 une seule fois (`relances_envoyees`, notification `FinAbonnementProche` en base + SMS), pas de relance si une période suivante est déjà payée, pass payé → grâce (`jours_grace`) → expiré, essai → expiré, alertes au-delà de `alertes.max` mises en pause (les plus anciennes restent actives).
- **Commandes et paiement** : `CreerCommande` (titulaire = soi ou ses entreprises, article actif et payant, cible, publication en ligne du titulaire pour une mise en avant, montant TTC figé, TVA et HT, numéro `CMD-AAAA-000001` sans trou via la table `sequences`), `InitierPaiement` → guichet ; webhook `POST /paiements/notification/{passerelle}` (exempté de CSRF, journal `notifications_paiement`, 403 si signature invalide, `TraiterNotificationPaiement` : verrou, revérification auprès de la passerelle, montant comparé, idempotent) ; `ActiverCommande` (reçu `REC-AAAA-000001`, abonnement créé ou ajouté à la suite, essai ou grâce remplacé, crédit, remontée, mise en avant, notification `CommandePayee`) ; page de retour qui ne fait que lire le statut, actualisée toutes les 3 s pendant 60 s ; guichet simulé du driver `fake` (hors production) ; driver **`cinetpay`** (initialisation, `payment/check`, jeton `x-token`).
- **Reçus** : `/mon-espace/recus/{commande}` (page imprimable) et `/pdf` (dompdf, A5), mentions légales du paramètre `mentions_legales_recu`, réservés à l'acheteur d'une commande payée.
- **SMS** : `TexteSms` (GSM-7 sans accents, guillemets et typographie convertis, 160 caractères, lien court toujours entier), canal `SmsChannel` (numéro vérifié uniquement), plage silencieuse 21h00 → 7h00 dans `PlanifierSms` (l'envoi est différé à 7h00 ; OTP exclus), `AlerteCorrespondante` par SMS si `alertes.canal_sms` et sous `alertes.sms_par_jour`, sinon `alertes_publications.sms_en_attente` puis un seul SMS récapitulatif à 19h00 (`alertes:recap-sms`) ; drivers **`orange`** (jeton OAuth mis en cache) et **`twilio`**.
- **Visibilité payante** : `RemonterPublication` (24 h entre deux remontées, incluses `publication.remontees_mois` ou payées ; payée hors ligne → appliquée au retour) ; `EmplacementsSponsorises` : cartes « Sponsorisé » sur le fil général en 1re position puis toutes les `frequence_sponsorise` cartes (`?deja=N`, hors curseur, une fois par salve, jamais une carte déjà dans la salve, rotation par dernière impression) et bloc « À la une » (`max_cartes_une`) au-dessus d'un fil filtré sur la commune ou la catégorie ; étiquettes par le jeton `⟦etiquette⟧` hors cache ; impressions (serveur) et clics (`sendBeacon`) cumulés en cache puis reportés toutes les 5 min (`mises-en-avant:compter`) ; `BasculerMisesEnAvant` suspend et reprend avec la durée restante (`PublicationObserver`) ; badge « Pro » / « Entreprise » sur les cartes (`auteur_badge`).
- **Pages et incitations** : `/tarifs` (plans visibles et actifs groupés par cible, produits à l'unité) ; « Mon abonnement » (formules en cours, échéance, grâce, compteurs « 2 / 10 », crédits, essais et achats, renouvellement, historique et reçus) ; « Recevez-la aussi par SMS — 14 jours gratuits » après la création d'une alerte ; « Il vous reste 1 offre d'emploi gratuite ce mois-ci » ; écran « Quota atteint » avec achat d'une offre et essai par titulaire ; boutons de remontée (incluse ou payée), « À la une » et « Sponsoriser » dans « Mes publications » avec affichages et clics ; statistiques avancées (`statistiques.avancees`). Incitations fermables (`x-incitation`).

### Tests ajoutés (95 tests, 10 fichiers ; total 526)

- `tests/Feature/Abonnements/PaiementTest.php` (15 : montant figé et TVA, articles refusés, activation au seul webhook, webhook rejoué, signature invalide, montant falsifié, refus, driver inconnu, renouvellement à la suite, essai remplacé, guichet simulé, accès croisé, reçu HTML et PDF)
- `tests/Feature/Abonnements/EssaiTest.php` (8 : essai en un clic, une famille = un essai, essai unique même après suppression du compte, numéro vérifié, plan sans essai, entreprise, entreprise d'un autre, incitation)
- `tests/Feature/Abonnements/CycleAbonnementsTest.php` (5 : relances J-3 / J-1 / J0, abonnement renouvelé, grâce puis expiration, essai expiré, alertes en trop mises en pause)
- `tests/Unit/Services/DroitsAbonnementsTest.php` (9 : essai / actif / grâce, statuts ignorés, combinaison, cache invalidé, crédits, imputation, `quotas_actifs`)
- `tests/Feature/Abonnements/ProduitsTest.php` (10 : remontée payée et délai, mises en avant refusées, « À la une », mise en avant en pause, remontée payée hors ligne, crédit d'offre, remontée incluse, sans formule, accès croisé)
- `tests/Feature/Abonnements/PagesEtIncitationsTest.php` (8 : `/tarifs`, « Mon abonnement », incitation SMS, « Quota atteint », dernière offre, « Mes publications », statistiques avancées, badge Pro)
- `tests/Feature/Alertes/AlertesSmsTest.php` (11 : sans droit, SMS d'alerte, numéro non vérifié, plafond puis récapitulatif de 19h00, plafond avec SMS encore en file, plage silencieuse, OTP)
- `tests/Unit/Support/TexteSmsTest.php` (5) · `tests/Unit/Services/DriversReelsTest.php` (10 : CinetPay, Orange, Twilio avec `Http::fake()`)
- `tests/Feature/Fil/SponsorisesTest.php` (14 : positions et curseur intact, pas de doublon, rotation, publication hors ligne / mise en avant suspendue ou terminée, fil général seulement, « À la une », suspension et reprise, impressions et clics, clic sur une mise en avant terminée)

### Écarts avec le CONTEXTE

- Décisions D-043 à D-053.
- Admin « Plans et paiements » (module K : plans, limites, produits, abonnements offerts, commandes, « Revérifier le statut », remboursement, revenus, coût SMS) : lot 7.
- Incitation « Remontez votre annonce » **après la validation** : elle accompagnera la notification « publication validée » de la modération (lot 7) ; le bouton de remontée est déjà dans « Mes publications ».
- **Identifiants à fournir** (dans `.env`, jamais commités) : CinetPay (`CINETPAY_API_KEY`, `CINETPAY_SITE_ID`, `CINETPAY_SECRET`) ; SMS Orange (`ORANGE_SMS_CLIENT_ID`, `ORANGE_SMS_CLIENT_SECRET`, `ORANGE_SMS_EXPEDITEUR`) **ou** Twilio (`TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_EXPEDITEUR`).

### À vérifier par Alex

- [ ] `/tarifs` : seuls les plans actifs et visibles apparaissent (au lancement : alertes SMS 7 j et 30 j, remontée).
- [ ] Créer une alerte : l'encart « Recevez-la aussi par SMS — 14 jours gratuits » s'affiche ; « Essayer gratuitement » ouvre « Mon abonnement » avec l'essai en cours (refusé si le numéro n'est pas vérifié).
- [ ] « Mon abonnement » → « Choisir · 1 000 FCFA » : la page de paiement propose le guichet simulé ; « Simuler un paiement réussi » → reçu `REC-2026-…`, PDF téléchargeable ; un second achat s'ajoute à la suite de la période.
- [ ] Avec un essai SMS et un numéro vérifié, publier une offre correspondant à une alerte puis `php artisan queue:work --stop-when-empty` : le SMS apparaît dans `storage/logs/sms.log` (sans accents, avec le lien `/p/…`) ; après 21h00, il part à 7h00.
- [ ] Activer les produits `une_commune_7j` et `sponsorise_fil_7j` en base, acheter une mise en avant depuis « Mes publications » : carte « Sponsorisé » en tête du fil, « À la une » sur le fil de la commune ; `php artisan mises-en-avant:compter` reporte les affichages.
- [ ] Mettre `quotas_actifs = 1` : écran « Quota atteint » avec les options d'achat et d'essai ; message « Il vous reste 1 offre ».
- [ ] `php artisan abonnements:traiter` : relances, grâce et expiration (dates à ajuster en base).
- [ ] Recette CinetPay en bac à sable : vérifier la liste des champs signés du jeton `x-token` (D-053) et la notification réelle.

---

## Lot 7 — Back-office

### Plan d'implémentation

- **Socle** : `routes/admin.php` (préfixe `/admin`, noms `admin.*`, `auth` + `role:administrateur|moderateur`, puis `can:<permission>` par module, en-tête `Cache-Control: no-store`) ; alias de middleware Spatie ; layout `admin/layout.blade.php` (barre latérale repliable selon `@can`, compteurs « en attente » et « signalements » dans l'en-tête) ; composants `x-admin.*` (`table`, `flash`, `badge-statut`, `carte-stat`, `filtres`) ; `admin.js` : listes DataTables **côté serveur** déclarées par attributs `data-*` (pas de script en ligne), filtres transmis à l'Ajax, Select2 (Ajax pour utilisateurs, entreprises, publications), SweetAlert2 pour les confirmations et les messages.
- **Listes** : une classe par liste dans `App\DataTables\*` (base `TableAdmin` sur Yajra `DataTables::eloquent`), colonnes d'actions en vues partielles `admin/*/_actions.blade.php`.
- **Journal** : trait `JournaliseActivite` sur toutes les actions `app/Actions/Admin/*` → `journal_activites` (auteur, action, sujet, avant / après, motif, IP).
- **A — Tableau de bord** : `StatistiquesService` (publiées aujourd'hui par type, en attente, signalements ouverts, inscrits jour / 7 j, alertes actives, notifications du jour), cache `admin:compteurs` 5 min via `CacheFil` ; dernier import et bandeau rouge après 9 h sans import réussi ; graphique 30 jours en SVG ; raccourcis.
- **B — Modération** : liste des `en_attente` (score croissant, cases à cocher, lot valider / rejeter), détail (carte identique au fil, signaux surlignés, source, historique de l'auteur, publications similaires), raccourcis clavier V / R / D / →, passage automatique à la suivante ; actions `ValiderPublication` (publiée, `publiee_le` et `tri_le`, `PublicationPubliee`, notification à l'auteur avec incitation de remontée), `RejeterPublication` (motif `MotifRejet` + texte), `MarquerDoublon`, `ModererLot`.
- **C — Publications** : liste filtrable (type, statut, source, commune, catégorie, auteur, période, texte), modification, dépublication, prolongation, suppression ; commandes `publications:expirer` (02h00, avec la notification « expire dans 3 jours ») et `publications:purger` (hebdomadaire, supprimées depuis 90 j) ; `fil:vider-cache`.
- **D — Signalements** : liste groupée par publication, décision `retablir | supprimer | supprimer_et_suspendre` (signalements traités, retenus ou non).
- **E — Veille** : historique des imports et détail, sources (CRUD, publications importées et taux de rejet sur 30 j, source « Inconnue »), coût IA par mois ; boutons d'import au lot 8.
- **F — Utilisateurs et entreprises** : recherche par téléphone normalisé ou par nom, fiche (rôles, publications, alertes, signalements reçus, sanctions), modification, suspension (motif, date de fin, publications dépubliées ; jamais soi-même), réactivation, mot de passe temporaire affiché une fois, rôles (jamais retirer son propre rôle d'administrateur), levée de la restriction de confiance, anonymisation ARTCI ; entreprises : vérification, fusion de deux fiches.
- **G — Référentiels et paramètres** : catégories (arbre, icône, ordre par flèches, activation), communes, pays ; paramètres typés (`modifie_par`) ; vider le cache du fil et des référentiels.
- **K — Plans et paiements** : plans (formulaire + éditeur de limites de toutes les clés `CleDroit` ; suppression interdite si souscrit), produits, abonnements (offrir avec motif, prolonger, résilier), commandes (détail, payload, revérifier, rembourser, reçu, export CSV), revenus (CA du mois par source, abonnements actifs par plan, essais et conversion, échéances à 7 j, coût SMS et IA).
- **Tests** : accès modérateur / administrateur par module ; valider et rejeter (événement, notifications, confiance) ; actions groupées ; auto-suspension interdite ; anonymisation ; plan souscrit non supprimable ; limite modifiée → droits recalculés ; une ligne de journal par action ; listes DataTables (JSON serveur, filtres) ; expiration et purge.

### Fait

- **Socle** : `routes/admin.php` chargé par `bootstrap/app.php` (préfixe `/admin`, noms `admin.*`, `auth` + `role:administrateur|moderateur` + `PasDeCache`, puis `can:<permission>` par module) ; layout `admin/layout.blade.php` (menu selon `@can`, compteurs « en attente » et « signalements » exacts à chaque page, menu repliable sur mobile) ; composants `x-admin.table`, `x-admin.filtres`, `x-admin.flash`, `x-admin.badge-statut`, `x-admin.carte-stat` ; `admin.js` sans aucun script en ligne dans les vues : listes DataTables **serveur** déclarées par `data-datatable`, filtres envoyés à chaque requête, Select2 (Ajax `/admin/recherche/*`), confirmations et toasts SweetAlert2, raccourcis de modération ; base `App\DataTables\TableAdmin` et 13 listes ; contrôleurs `Admin\*` (base `ControleurAdmin`) ; actions `app/Actions/Admin/*` avec le trait `JournaliseActivite` (auteur, action, sujet, avant / après, motif, IP) ; lien « Administration » dans l'en-tête public selon `@can`.
- **A — Tableau de bord** : `StatistiquesService` (publiées aujourd'hui par type, en attente, signalements ouverts, inscrits jour / 7 j, alertes actives, notifications du jour ; cache `admin:compteurs` 5 min via `CacheFil::admin`), dernier import et bandeau rouge après 9 h sans import réussi, graphique 30 jours en SVG (aucune bibliothèque), raccourcis.
- **B — Modération** : file des publications en attente (score croissant, filtres type / source, cases à cocher, lot valider / refuser), détail (carte identique au fil, texte complet, signaux de risque surlignés, lien source, historique de l'auteur, publications similaires), raccourcis **V / R / D / →**, passage automatique à la suivante ; `ValiderPublication` (publiée, `publiee_le` et `tri_le` à la première mise en ligne, `PublicationPubliee`, notification `PublicationValidee` avec l'incitation « Remontez votre annonce » pour un artisan), `RejeterPublication` (motif `MotifRejet` + précision, obligatoire pour « Autre » ; notification `PublicationRejetee`), `MarquerDoublon`, `ModererLot`.
- **C — Publications** : liste filtrable (type, statut, source, commune, catégorie, auteur via Select2, période, titre), modification (titre, texte, catégorie, commune, expiration ; extrait recalculé), dépublication (retour en modération, auteur prévenu), prolongation (+ `jours_prolongation`, une publication expirée revient en ligne), suppression ; commandes `publications:expirer` (02h00, avec la notification `PublicationExpireBientot` à 3 jours), `publications:purger` (dimanche 03h00, `jours_purge_publications`) et `fil:vider-cache`.
- **D — Signalements** : liste groupée par publication (nombre, motifs, premier signalement), fiche, décisions `retablir | supprimer | supprimer_et_suspendre` (`TraiterSignalements` : signalements traités et retenus ou non, publication masquée remise en ligne, confiance recalculée, suspension définitive de l'auteur).
- **E — Veille** : historique des imports (lien Drive, compteurs, statut, erreur) et détail avec les publications créées ; sources (création, modification, domaine normalisé, importées, taux de rejet sur 30 j) ; coût de l'API IA par mois (`appels_ia`).
- **F — Utilisateurs et entreprises** : recherche par numéro normalisé ou par nom, fiche (rôles, publications, alertes, signalements reçus, formules, historique des actions de l'équipe), modification, suspension (motif, date de fin facultative, publications retirées du fil) et réactivation, mot de passe temporaire affiché une seule fois, rôles par Select2, levée de la restriction de confiance (`restriction_levee_le`), suppression avec anonymisation (`AnonymiserUtilisateur`) ; un administrateur ne peut ni se suspendre, ni retirer son propre rôle, ni anonymiser son compte ; entreprises : liste, fiche, badge « vérifiée » (recopié sur les cartes), fusion de deux fiches (publications, abonnements, crédits et commandes rattachés).
- **G — Référentiels et paramètres** : arbre des catégories (type → catégorie → sous-catégorie, icône, activation, flèches haut / bas), communes et pays (activation, ordre, ajout), paramètres typés (entier, booléen, texte, HTML, JSON validé ; `modifie_par`), boutons « Vider le cache du fil » et « Vider les référentiels ».
- **K — Plans et paiements** : plans (formulaire + éditeur de toutes les clés `CleDroit` : oui / non, nombre ou « illimité » ; un seul plan par défaut par cible ; suppression refusée si souscrit ou par défaut), produits, abonnements (liste filtrable par plan, statut et échéance ; offrir avec motif, prolonger, résilier), commandes (détail avec la charge utile et les notifications reçues, « Revérifier le statut », remboursement manuel qui arrête abonnement, crédits et mises en avant, reçu, export CSV des filtres), revenus (CA du mois par source, abonnements actifs par plan, essais et conversion, échéances à 7 jours, coûts SMS et IA).
- **Démo** : `DemoSeeder` ajoute 20 annonces à modérer (scores et signaux variés) et 4 signalements, dont 3 « arnaque » qui masquent une publication.

### Tests ajoutés (107 tests, 6 fichiers ; total 633)

- `tests/Feature/Admin/AccesAdminTest.php` (51 : chaque page pour l'administrateur et sans cache, accès du modérateur limité à ses modules, refus aux membres et visiteurs, listes DataTables en JSON serveur, fiches de détail)
- `tests/Feature/Admin/ModerationTest.php` (11 : validation avec événement, notification, journal et incitation de remontée ; remise en ligne sans remontée ; rejet et motif ; précision pour « Autre » ; restriction après 2 rejets pour arnaque ; doublon ; publication suivante ; décision refusée hors attente ; lots ; recherche Select2)
- `tests/Feature/Admin/UtilisateursTest.php` (15 : recherche par numéro sous 5 formes ou par nom, suspension et réactivation, auto-suspension interdite, rôle d'administrateur conservé, droits réservés à l'administrateur, mot de passe temporaire, anonymisation ARTCI, levée durable de restriction, modification journalisée, vérification et fusion d'entreprises)
- `tests/Feature/Admin/SignalementsTest.php` (5) · `tests/Feature/Admin/PlansEtPaiementsTest.php` (12 : prix et limite modifiés avec effet immédiat, « illimité », plan par défaut unique, validations, plan souscrit non supprimable, offrir / refuser une autre cible, prolonger et résilier, revérification idempotente, remboursement, export CSV, revenus)
- `tests/Feature/Admin/PublicationsEtReferentielsTest.php` (13 : filtres, modification journalisée, dépublication / prolongation / suppression, `publications:expirer`, `publications:purger`, catégories et flèches, rattachement refusé, communes et pays désactivés, paramètres typés et invalides, boutons de cache, sources, tableau de bord)

### Écarts avec le CONTEXTE

- Décisions D-054 à D-057.
- Veille : les boutons « Importer maintenant » et « Relancer » ainsi que le détail ligne par ligne d'un import arrivent avec l'import réel (lot 8).
- Modules V2 non développés (§13.3 H, I, J sauf l'écriture du journal) : statistiques, modération des commentaires, vue des alertes, consultation du journal, jobs échoués et santé, publications épinglées.
- Le formulaire d'édition d'une publication ne change pas le statut : il passe par les actions dédiées (modérer, dépublier, prolonger), pour garder une seule porte vers chaque statut.

### À vérifier par Alex

- [ ] Se connecter avec le compte administrateur du `.env`, ouvrir `/admin` : compteurs, graphique, bandeau d'import en retard.
- [ ] Modération : modérer les 20 annonces de démo au clavier (V, R, D, →) ; essayer un lot.
- [ ] Signalements : la publication signalée 3 fois « arnaque » est masquée ; tester les trois décisions.
- [ ] Utilisateurs : rechercher `07 08 09 10 11` et `+225…` ; suspendre puis réactiver un membre ; réinitialiser un mot de passe et se connecter avec ; vérifier qu'on ne peut pas se suspendre soi-même.
- [ ] Plans : modifier le prix d'« Alertes SMS 30 jours » et une limite d'« Artisan gratuit », puis vérifier `/tarifs` et « Mon abonnement » d'un membre ; offrir « Artisan Pro » à un artisan.
- [ ] Donner le rôle `moderateur` à un membre : il ne voit que ses modules (pas de plans, paramètres, référentiels ni veille).
- [ ] Paramètres : modifier `frequence_sponsorise`, vider le cache du fil.

---

## Lot 8 — Import de la veille et enrichissement IA

### Plan d'implémentation

- **Sources** : driver `google_drive` (`google/apiclient`, compte de service, fichiers du dossier — Google Sheets exportés en CSV, CSV téléchargés ; le client Google passe par le client HTTP de Laravel pour que `Http::fake()` couvre les tests) ; driver `fake` existant (CSV de `tests/Fixtures/veille/`).
- **Lancement** : `veille:importer` (01h20, 09h20, 17h20) et bouton « Importer maintenant » (job en file) → action `LancerImportVeille` : fichiers déjà présents dans `imports_veille` ignorés, une ligne `imports_veille` par nouveau fichier, un job `ImporterFichierVeille` par fichier ; bouton « Relancer » (action `RelancerImportVeille`, sans risque grâce aux empreintes).
- **Import d'un fichier** (`ImporterFichierVeille`) : lecture CSV tolérante (BOM, `,` ou `;`, en-têtes sans accents), pour chaque ligne (`ImporterLigneVeille`) : URL normalisée (`UrlSource` : https, hôte en minuscules sans `www.`, paramètres de suivi retirés, paramètres restants triés, sans fragment ni slash final) → `source_empreinte` → doublon ignoré ; type depuis « Catégorie » ; catégorie fine depuis « Domaine » ; commune depuis « Lieu » ; source par domaine (sous-domaines compris), sinon « Inconnue » ; détails (contrat, durée, prix, lien de candidature ou d'inscription) ; **résumé seulement**, limité, jamais le texte source complet ; statut `en_attente` ; journal ligne par ligne (`lignes_import_veille` : importée, doublon, rejetée et sa raison) et compteurs `imports_veille`.
- **Enrichissement** (`EnrichirPublicationIA`, en file) : pour une publication de la veille, texte de carte et catégorie fine proposés par l'IA, score et signaux ; décision `DeciderPublicationVeille` : en ligne seulement si la source est active, de confiance **haute**, autorisée à la publication automatique **et** score ≥ `seuil_publication_auto`, sinon reste en attente ; échec de l'IA → reste en attente.
- **Driver `claude`** (`ClaudeClientIA`, client HTTP Laravel) : API Messages, modèle `claude-haiku-5-5` (configurable), sortie structurée `output_config.format` (schéma JSON), réponse revalidée strictement (`ReponseIAInvalideException`), lecture du bloc `text` (la réflexion est active par défaut), nouvelles tentatives sur 429 / 529 / 5xx et erreurs réseau avec `retry-after` et temporisation croissante, jetons et durée dans `appels_ia` avec le coût estimé.
- **Back-office** : boutons « Importer maintenant » et « Relancer », détail ligne par ligne d'un import.
- **Tests** : fichier déjà importé ignoré ; doublon d'URL (paramètres de suivi, slash final, `www.`, http/https) ; les 3 catégories ; commune et source ; publication automatique seulement si confiance haute **et** score ≥ seuil ; ligne rejetée ; réponse IA invalide (JSON, schéma, refus, `max_tokens`) ; 429 / 529 rejoués ; résumé limité ; driver Google Drive avec `Http::fake()` ; boutons du back-office.

### Fait

- **Lancement** : commande `veille:importer` planifiée à 01h20, 09h20 et 17h20 ; action `LancerImportVeille` : les fichiers déjà présents dans `imports_veille` sont ignorés (y compris deux lancements simultanés, grâce à l'unicité de `fichier_drive_id`), une ligne `imports_veille` et un job `ImporterFichierVeille` par nouveau fichier ; bouton « Importer maintenant » (job `LancerImportVeilleEnFile`, unique, jamais pendant la requête HTTP) et « Relancer » dans le back-office.
- **Lecture** : `LecteurCsvVeille` (BOM, séparateur `,` ou `;`, cellules sur plusieurs lignes, en-têtes avec ou sans accents, dans n'importe quel ordre).
- **Import d'une ligne** (`ImporterLigneVeille`) : URL normalisée par `UrlSource` (https, hôte en minuscules sans `www.`, paramètres `utm_*`, `fbclid`, `gclid`… retirés, paramètres restants triés, sans fragment ni slash final) → `source_empreinte` (SHA-256) → doublon écarté ; `CorrespondanceVeille` : type depuis « Catégorie », catégorie depuis « Domaine », commune depuis « Lieu », source par domaine ou sous-domaine (sinon « Inconnue », confiance basse), contrat, durée, prix et tarif lus dans « Contrat/Durée/Prix » ; détails créés (candidature ou inscription par le lien d'origine ; aucun numéro tiré d'une annonce externe) et faits clés de la carte ; **résumé limité à 600 caractères**, jamais le texte source complet ; statut `en_attente` ; journal ligne par ligne `lignes_import_veille` (importée → publication, doublon → publication existante, rejetée → raison : colonne vide, lien invalide, catégorie inconnue, source désactivée) ; compteurs de `imports_veille`, statut `erreur` et message si le fichier est illisible. Un import rejoué recompte ses propres annonces comme importées et ne les renvoie pas à l'IA.
- **Enrichissement** (`EnrichirPublicationIA`) : pour une annonce de la veille, texte de carte réécrit par l'IA (extrait de 220 caractères), catégorie proposée seulement si « Domaine » n'a rien donné, score et signaux ; `DeciderPublicationVeille` : mise en ligne automatique seulement si la source est active, de confiance **haute**, autorisée à publier seule **et** que le score atteint `seuil_publication_auto` (70) — sinon l'annonce reste en attente de modération ; mise en ligne → `PublicationPubliee` (alertes, cache) ; échec de l'IA → l'annonce reste en attente et l'échec est journalisé dans `appels_ia` avec l'import.
- **Driver `claude`** (`ClaudeClientIA`, client HTTP Laravel, sans SDK — CONTEXTE §6) : `POST /v1/messages`, modèle `claude-haiku-5-5` (variable `CLAUDE_MODELE`), effort `low`, sortie structurée `output_config.format` (schéma JSON : `texte_carte`, `categorie` dans la liste fournie ou nulle, `score_fiabilite`, `signaux_risque` dans une liste fermée), consignes qui traitent l'annonce comme une donnée et non comme une instruction ; lecture du bloc `text` (la réflexion est active par défaut sur ce modèle) ; validation stricte (`ReponseIAInvalideException` : refus, réponse tronquée, JSON invalide, score hors 0–100, signal ou catégorie inconnus, texte vide) ; nouvelles tentatives sur 408, 429 (avec `retry-after`), 5xx, 529 et erreurs réseau, temporisation croissante (`CLAUDE_TENTATIVES`, `CLAUDE_ATTENTE_MS`) ; jetons d'entrée et de sortie, durée et coût estimé dans `appels_ia`.
- **Driver `google_drive`** (`GoogleDriveSourceVeille`, `google/apiclient` limité à Drive) : compte de service, fichiers du dossier (Google Sheets et CSV, pages successives, Drive partagés compris), export CSV d'un Google Sheet, téléchargement d'un CSV ; le client Google passe par le client HTTP de Laravel (délais et `Http::fake()` en test) ; message clair si les identifiants manquent.
- **Back-office** : détail d'un import ligne par ligne (sort, lien d'origine, publication créée ou existante avec son score et son statut), boutons « Importer maintenant » et « Relancer l'import ».

### Tests ajoutés (45 tests, 5 fichiers ; total 679)

- `tests/Unit/Support/VeilleSupportTest.php` (12 : même empreinte malgré le suivi, le slash final, `www.`, http et le fragment ; paramètres utiles gardés ; liens refusés ; CSV avec BOM, `;` et cellule sur plusieurs lignes ; fichier de démonstration)
- `tests/Feature/Veille/ImportVeilleTest.php` (11 : import des 3 catégories avec commune, source et détails ; publication automatique seulement si confiance haute **et** score ≥ seuil ; seuil relevé ou source sans publication automatique ; fichier déjà importé ignoré ; doublon d'URL depuis un autre fichier ; lignes rejetées et leur raison ; source « Inconnue » ; résumé limité ; échec de l'IA ; import rejoué ; fichier illisible)
- `tests/Unit/Services/ClaudeClientIATest.php` (14 : requête Messages et lecture après le bloc de réflexion, 9 réponses invalides, 429 avec `retry-after` puis 529, abandon après 3 tentatives, pas de nouvelle tentative sur 400, choix par `IA_DRIVER`)
- `tests/Unit/Services/GoogleDriveSourceVeilleTest.php` (4 : liste paginée du dossier, export d'un Google Sheet et téléchargement d'un CSV, identifiants manquants) · `tests/Feature/Veille/VeilleAdminTest.php` (4 : importer maintenant en file, relancer, détail des lignes, accès réservé)

### Écarts avec le CONTEXTE

- Décisions D-058 à D-061.
- **Identifiants à fournir** (dans `.env`, jamais commités) : `GOOGLE_DRIVE_FICHIER_IDENTIFIANTS` (chemin du fichier JSON du compte de service, hors du dépôt), `GOOGLE_DRIVE_DOSSIER_ID` (dossier « Offres Emploi Abidjan » partagé avec l'adresse du compte de service), `CLAUDE_API_KEY` ; puis `VEILLE_DRIVE_DRIVER=google_drive` et `IA_DRIVER=claude`.
- L'authentification du compte de service (échange du jeton Google) n'est pas couverte par les tests : `Http::fake()` répond avant le middleware d'authentification de Google. Elle est à vérifier une fois avec les vrais identifiants.

### À vérifier par Alex

- [ ] Sans identifiants : `php artisan veille:importer` puis `php artisan queue:work --stop-when-empty` importe le fichier de démonstration : 2 annonces en ligne (Emploi.ci, Orange Digital Center), 1 en attente (Facebook) ; `/admin/veille/imports` montre le détail ligne par ligne.
- [ ] Relancer la commande : « Aucun nouveau fichier. » ; « Relancer l'import » dans l'admin ne crée aucun doublon.
- [ ] Avec les vrais identifiants dans `.env` (`VEILLE_DRIVE_DRIVER=google_drive`, `IA_DRIVER=claude`) : lancer `veille:importer` sur le dossier « Offres Emploi Abidjan » ; les offres apparaissent en ligne ou restent en attente selon la source et le score ; le coût IA du mois s'affiche dans la page Veille.
- [ ] En modération, une annonce de la veille montre son score, ses signaux et le lien vers l'annonce d'origine.
- [ ] Ajuster les sources (niveau de confiance, publication automatique) et le paramètre `seuil_publication_auto` selon les premiers imports.

### Complément (22/10/2026) — réévaluation des annonces en attente

- `ReevaluerAnnoncesVeille` (D-063) : commande `veille:reevaluer`, bouton « Réévaluer les annonces en attente » (job `ReevaluerAnnoncesVeilleEnFile`, unique, journalisé `veille.reevaluation_lancee`) et lancement automatique en file après chaque ajout ou modification d'une source. Chaque annonce en attente est rattachée à la source déclarée pour son domaine, puis mise en ligne si `DeciderPublicationVeille` l'autorise ; les annonces retirées après une mise en ligne, sans score ou expirées ne sont jamais reprises. `DeciderPublicationVeille::mettreEnLigne()` est partagé avec `EnrichirPublicationIA`.
- Tests (+4 ; total 693) : rattachement et mise en ligne après la déclaration d'un domaine de confiance haute ; aucune remise en ligne d'une annonce retirée, non notée, expirée ou sous le seuil ; bouton en file et journalisé ; réévaluation après l'ajout d'une source.
- Base locale : les données de démonstration (20 membres, 80 publications, l'import de test et ce qui en dépendait) ont été supprimées ; il reste l'administrateur et les imports réels du dossier Drive. Sauvegarde avant nettoyage : `storage/app/private/sauvegardes/rezohelp-avant-nettoyage-demo-2026-10-22.sql`. `DatabaseSeeder` ne crée aucune donnée de démonstration (seulement `db:seed --class=DemoSeeder`).
- [ ] À vérifier par Alex : déclarer dans « Sources de la veille » les domaines qui le méritent (abbih.com, projobivoire.com, goafricaonline.com, jobivoire.ci…), puis lancer `php artisan queue:work --stop-when-empty` : leurs annonces en attente passent en ligne si la source est de confiance haute avec publication automatique.

### Complément (22/10/2026) — notation gratuite et catégories

- Driver `regles` (`ReglesClientIA`, D-064) : score par les règles anti-arnaque et la liste `mots_a_risque`, sans IA externe ni coût ; `.env` local passé à `IA_DRIVER=regles`. `veille:reevaluer --renoter` renote en file les annonces en ligne ou en attente.
- `CorrespondanceVeille::categorie` réécrite pour les domaines composés (mot à mot, synonymes par type, « Autres métiers » à défaut pour un emploi).
- Base locale : catégories des 294 annonces recalculées depuis les fichiers Drive (218 corrigées, dont 203 offres d'emploi mises à tort en « Informatique et numérique ») ; scores recalculés avec `regles` : 293 à 85, 1 à 55 (frais demandés) ; journal `appels_ia` du driver `fake` vidé.
- Tests (+25 ; total 718) : `tests/Feature/Veille/ReglesEtCategoriesVeilleTest.php` (driver choisi par `IA_DRIVER`, score par signal, liste `mots_a_risque` de l'admin, extrait gardé et aucun appel journalisé, annonce retirée jamais remise en ligne, renotation en file, 19 domaines réels).
- [ ] À vérifier par Alex : 5 annonces de service sans catégorie (« Gardiennage / Sécurité » ×3, « Élevage », « Reprographie ») : créer si besoin une catégorie de service « Gardiennage et sécurité » dans Admin → Catégories, puis relancer l'import concerné ou corriger ces annonces à la main.
- [ ] À vérifier par Alex : après une modification de `.env`, redémarrer le worker (`php artisan queue:restart`, puis relancer `queue:work`) : un worker déjà lancé garde l'ancienne configuration.

### Complément (22/10/2026) — date de clôture, texte public et page détail

- `ResumeVeille` (D-067) : date de clôture lue dans le résumé, enregistrée en `details_emploi.date_limite` et `expire_le` ; notes de la veille retirées du texte public et rangées dans `publications.note_veille` (nouvelle colonne) ; phrase de clôture retirée (la date s'affiche à part) ; annonce déjà close non importée. Contrat (premier cité, rien plutôt qu'« Autre ») et salaire lus dans « Contrat/Durée/Prix ».
- `publications:expirer` : expiration à la date limite de candidature, annonces de la veille closes pendant la modération retirées de la file, suppression des annonces de la veille `jours_suppression_veille` jours après la clôture (nouveau paramètre, 5).
- Page détail : bloc « Informations clés » en tête (date limite avec les jours restants, contrat, lieu, salaire…), bouton d'action juste en dessous, puis la description ; initiale de l'auteur et « via Emploi.ci » dans l'en-tête. En-tête du site : loupe dans la recherche, « Publier une annonce » séparé et à contour.
- Base locale : les 294 annonces reprises depuis les fichiers Drive (161 dates de clôture, 71 notes de veille rangées à part, 13 salaires) ; 10 annonces déjà closes sont sorties du fil.
- Tests (+30) : `tests/Unit/Support/ResumeVeilleTest.php` (20 : formes de la date, année suivante, dates douteuses ignorées, notes et phrase de clôture retirées), `tests/Feature/Veille/ImportVeilleTest.php` (+6 : date, salaire et notes à l'import, contrat non précisé, annonce close non importée, expiration puis suppression à J+5, date limite d'un emploi, suppression désactivée), `tests/Feature/Publications/FicheDetailTest.php` (5 : ordre de la page, notes jamais affichées, dernier jour, annonce close, en-tête).
- [ ] À vérifier par Alex : ouvrir une offre de la veille (par exemple « Agent de centre d'appel ») : date limite et jours restants en tête, bouton « Voir l'annonce et postuler », texte sans « à vérifier ». Sur ordinateur, la recherche et le bouton « Publier une annonce » ne se confondent plus.
- [ ] À vérifier par Alex : la note de la veille n'est pas encore affichée dans l'écran de modération (back-office en cours de refonte dans une autre session).

---

## Lot 9 — PWA, performance, accessibilité, sécurité

### Plan d'implémentation

- **PWA** (`vite-plugin-pwa`, Workbox, listé dans CLAUDE.md) : manifeste (nom, icônes 192 / 512 / maskable, `theme_color`, `display: standalone`, `lang: fr`), service worker servi à la racine (`/sw.js`, portée `/`) : *cache-first* pour les assets versionnés (précache), *stale-while-revalidate* pour `/fil/suite`, *network-first* pour les pages, page hors ligne `/hors-ligne` ; **jamais de cache** pour `/mon-espace/*`, `/admin/*`, `/paiements/*`, `/publier*` ni les réponses POST ; invitation discrète à installer l'application après 2 visites.
- **En-têtes** : middleware `EnTetesSecurite` sur toutes les réponses web : CSP (`default-src 'self'`, scripts avec nonce Vite, `frame-ancestors 'none'`, `form-action` limité au site et au guichet CinetPay, `object-src 'none'`, `base-uri 'self'`), HSTS en HTTPS, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` ; `Cache-Control: no-store` sur `/mon-espace`, `/publier` et les paiements.
- **Performance** : revue des requêtes (aucun N+1, nombre de requêtes du fil borné et testé), `EXPLAIN` des requêtes du fil sur les index, images avec `width` / `height` et `loading="lazy"`, budget JS public < 50 Ko vérifié par un script en CI.
- **Accessibilité** : revue du parcours public (lien d'évitement, focus visible, libellés, cibles ≥ 44 px, `aria-live`, `prefers-reduced-motion`, contrastes).
- **Sécurité** : revue des Policies sur toutes les routes, tests d'accès croisé sur chaque ressource d'un membre, envois de fichiers (types et tailles), limites de débit.
- **Tests** : accès croisé (publications, réponses, alertes, notifications, commentaires, reçus, commandes), en-têtes de sécurité présents, `no-store` sur « Mon espace », manifeste et service worker servis, nombre de requêtes du fil.

### Fait

- **PWA** : `vite-plugin-pwa` (Workbox) ; service worker `/sw.js` (portée `/`) ; précache des assets publics et de `/hors-ligne` ; *stale-while-revalidate* sur `/fil/suite`, *network-first* sur les pages ; rien en cache pour `/mon-espace`, `/admin`, `/paiements`, `/publier` et l'authentification ; manifeste statique `public/manifest.webmanifest`, icônes 192 / 512 / maskable ; invitation à installer à partir de la 2e visite ; jeton CSRF de la page imposé aux envois.
- **En-têtes** : middleware `EnTetesSecurite` (CSP avec nonce Vite, HSTS en HTTPS, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP) ; `Cache-Control: no-store` sur toutes les pages connectées.
- **Performance** :
  - nombre de requêtes du fil, de sa suite et de la page détail indépendant du nombre de cartes ou de commentaires (tests) ;
  - `EXPLAIN` des requêtes du fil sur la base réelle : 5 à 6 requêtes par page. L'accueil et `/fil/suite` utilisent `publications_statut_tri_le_id_index`, `/emplois` et `/services` `publications_type_statut_tri_le_id_index`, le filtre commune `publications_commune_id_statut_tri_le_id_index`, toujours en *Backward index scan* sans tri en mémoire. Seule la recherche plein texte trie en mémoire, ce qui est attendu pour un index FULLTEXT.
  - budget JS vérifié par `npm run budget-js` en local et en CI (D-068) : 22 Ko gzip.
- **Sécurité** :
  - accès croisé testé sur chaque ressource d'un membre : publications (modifier, clôturer, prolonger, dupliquer, supprimer, remonter, confirmation), réponses reçues, alertes, notifications, commentaires, reçus, suivi et simulation de paiement, achat, essai et publication au nom de l'entreprise d'un autre, contenu de « Mon espace » ;
  - envois de fichiers : photo de plus de 5 Mo, SVG, fichier PHP renommé en `.jpg` (type lu dans le contenu) et GIF refusés ; logo et photo de profil limités à 2 Mo, sans SVG ;
  - limite de débit ajoutée sur l'inscription (D-068).
- **Accessibilité** : puces de filtre en `aria-current` (D-068) ; texte indicatif de la recherche assombri (contraste ≥ 4,5:1 sur fond gris).
- **Lighthouse mobile** (base locale, 294 annonces réelles, `php artisan serve`, assets compilés) :
  - `/` : Performance 94, Accessibilité 100, Bonnes pratiques 100, SEO 100 ;
  - page détail : 99, 100, 100, 100.

### Tests ajoutés (56 tests, 6 fichiers)

- `tests/Feature/Securite/EnTetesEtPwaTest.php` (10 : en-têtes, HSTS et `upgrade-insecure-requests` en HTTPS, `no-store` sur « Mon espace » et la publication, page hors ligne sans donnée de compte, manifeste et icônes)
- `tests/Feature/Securite/AccesCroiseTest.php` (28 : 20 routes essayées par un intrus, ressources intactes après les tentatives, achat et essai au nom d'une autre entreprise, publication au nom d'une autre entreprise, 5 pages de « Mon espace » sans donnée d'un autre membre)
- `tests/Feature/Securite/EnvoisFichiersTest.php` (11) · `tests/Feature/Securite/LimitesDebitTest.php` (2) · `tests/Feature/Fil/PerformanceFilTest.php` (4) · `tests/Feature/Fil/FiltresFilTest.php` (+1 : filtres en `aria-current`)

### Écarts avec le CONTEXTE

- Décisions D-062 (CSP et Alpine) et D-068 (budget JS mesuré en gzip, limite des inscriptions, filtres accessibles).
- Nouveau paramètre `max_inscriptions_par_heure` : après un déploiement, lancer `php artisan db:seed --class=ParametresSeeder --force`, qui ajoute les nouvelles clés sans écraser les valeurs modifiées dans l'admin. Un paramètre absent lève une erreur, volontairement.
- Cinq actions de « Mon espace » n'ont pas de limite de débit propre (clôturer, prolonger, dupliquer une publication, mettre en pause une alerte, « tout marquer comme lu ») : elles sont réservées au propriétaire connecté et sans effet hors de son compte.

### À vérifier par Alex

- [ ] Installer l'application sur un téléphone Android (Chrome → « Installer l'application ») : icône, ouverture plein écran.
- [ ] En mode avion, rouvrir l'application : la page « Vous êtes hors connexion » s'affiche ; « Mon espace » n'est jamais servi depuis le cache.
- [ ] Lighthouse mobile sur `/` et sur une page détail en préproduction (HTTPS, Memcached) : Performance ≥ 90, Accessibilité ≥ 95, Bonnes pratiques ≥ 95, SEO ≥ 95.
- [ ] `npm run build && npm run budget-js` : moins de 50 Ko gzip.
