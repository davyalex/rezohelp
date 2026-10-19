# Resohelph — Avancement

> Questions ouvertes pour Alex : aucune pour le moment.

| Lot | Titre | État |
|---|---|---|
| 0 | Socle technique | terminé (tag `lot-0`) |
| 1 | Base de données, modèles, référentiels | terminé (tag `lot-1`) |
| 2 | Authentification par téléphone | terminé (tag `lot-2`) |
| 3 | Fil public et pages de publication | terminé (tag `lot-3`) |
| 4 | Publication par les utilisateurs | terminé (tag `lot-4`) |
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
