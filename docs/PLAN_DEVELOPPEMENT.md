# Rezohelp — Plan de développement par lots

> Référence fonctionnelle : `docs/CONTEXTE.md` (les numéros § renvoient à ce document). Règles de code : `CLAUDE.md`.
> Chaque lot se termine par sa **Définition de terminé** commune + ses **critères d'acceptation** propres, puis un commit et un tag `lot-N`.

---

## Définition de terminé (commune à tous les lots)

1. Tout le périmètre du lot est implémenté conformément aux § cités.
2. Tests Pest écrits pour chaque règle métier, Action, Job, Policy et route du lot (Feature + Unit), **y compris les cas d'erreur et d'autorisation**.
3. Les trois commandes passent sans erreur :
   ```bash
   ./vendor/bin/pint --test
   ./vendor/bin/phpstan analyse
   php artisan test --parallel
   ```
4. `php artisan migrate:fresh --seed` fonctionne sur une base vide.
5. Aucun appel réseau réel dans les tests ; les drivers `fake` et `log` sont utilisés.
6. `docs/AVANCEMENT.md` est mis à jour (fait, tests ajoutés, écarts, points à vérifier par Alex).
7. Toute décision prise faute de précision dans le CONTEXTE est notée dans `docs/DECISIONS.md` (date, sujet, choix, raison).
8. Commit `lot N : <titre>` puis tag `lot-N`.

---

## Lot 0 — Socle technique

**Contenu**
- Projet Laravel 13 (PHP 8.3+), locale `fr`, fuseau `Africa/Abidjan`, Carbon en français.
- Paquets du `CLAUDE.md`, Pest, Larastan niveau 6, Pint.
- Tailwind 4 + Alpine via Vite ; **deux entrées** : `resources/js/app.js` + `resources/css/app.css` (public), `resources/js/admin.js` + `resources/css/admin.css` (jQuery, DataTables, Select2, SweetAlert2).
- Tokens de couleur `@theme` (brand `#0E5E6F`, emploi bleu, service orange, formation vert, fond `#EEF1F4`).
- Cache `memcached` avec repli `CACHE_STORE=file` si l'extension est absente ; sessions et files en `database`.
- Trait `HorodatageFrancais`, dossier `app/Enums`, interfaces `PasserellePaiement`, `FournisseurSms`, `SourceVeille`, `ClientIA` avec drivers `fake` / `log`, liés dans un `ServiceProvider` selon `.env`.
- `.env.example` complet et commenté.
- Intégration continue GitHub Actions : pint, phpstan, tests sur MySQL 8.
- Fichiers `docs/AVANCEMENT.md` et `docs/DECISIONS.md` créés.

**Critères d'acceptation**
- `composer run dev` démarre ; la page d'accueil affiche le layout vide en français.
- La CI est verte.

---

## Lot 1 — Base de données, modèles, référentiels

**Références** : §7, §13.2, §14.4.

**Contenu**
- **Toutes** les migrations du §7 (y compris monétisation), index et clés étrangères compris.
- Modèles avec `$table`, `$fillable`, casts (enums, E.164, JSON), relations, scopes (`publiees()`, `duType()`, `dansCommune()`), factories pour chaque modèle.
- Enums :
  - `TypePublication`, `SousTypeService`, `StatutPublication`, `TypeContrat`, `ModeCandidature`, `ModeInscription`, `Modalite`, `FrequenceAlerte` ;
  - `TypeReponse`, `StatutReponse`, `MotifSignalement`, `MotifRejet`, `NiveauConfiance`, `ConfianceUtilisateur` ;
  - `CibleOffre`, `StatutAbonnement`, `OrigineAbonnement`, `StatutCommande`, `TypeProduit`, `TypeMiseEnAvant`, `CleDroit`.
- Seeders :
  - pays (§9 : CI, SN, ML, BF, BJ, TG, GN, NE, CM, FR) ;
  - **les 13 communes d'Abidjan** (§5) ;
  - catégories et sous-catégories pour les 3 types ;
  - rôles et permissions (§13.2) ;
  - `parametres` (toutes les clés citées aux §11, §13 et §14) ;
  - `PlansSeeder` (grille §14.4) ;
  - sources de veille ;
  - compte administrateur depuis `.env` (`ADMIN_TELEPHONE`, `ADMIN_MOT_DE_PASSE`).
- `DemoSeeder` (local uniquement) : 60 publications réalistes réparties sur les 3 types et les communes, 20 utilisateurs, entreprises, alertes, réactions.

**Tests obligatoires** : chaque factory crée un modèle valide ; les relations ; unicité des index (`source_empreinte`, `telephone`, `code_court`) ; un seul plan `est_defaut` par cible.

**Critères d'acceptation** : `migrate:fresh --seed` puis `db:seed --class=DemoSeeder` fonctionnent, et les données sont cohérentes dans la base.

---

## Lot 2 — Authentification par téléphone

**Références** : §9, maquette « Inscription ».

**Contenu**
- `TelephoneService` (normalisation E.164, formats national et international, validation par pays) et composant `x-champ-telephone` (sélecteur de pays + numéro).
- Fortify configuré sur `telephone` : inscription, connexion, déconnexion, « se souvenir de moi ».
- **OTP** :
  - envoi via `FournisseurSms` (driver `log` en local) ;
  - code à 6 chiffres, valable 10 min, 5 essais ;
  - vérification du numéro (`telephone_verifie_le`) ;
  - mot de passe oublié par OTP.
- Rate limiting (§9), `Password::min(8)->uncompromised()`.
- Changement de mot de passe obligatoire si `doit_changer_mot_de_passe`.
- Comptes suspendus refusés à la connexion avec un message clair.
- `redirect()->intended()` conservé, y compris le paramètre d'alerte pré-remplie (§4.2).

**Tests obligatoires** :
- `0708091011`, `07 08 09 10 11` et `+225 07 08 09 10 11` mènent au même compte ;
- formats SN et FR ;
- doublon refusé ;
- rate limit ;
- OTP expiré ou faux ;
- compte suspendu ;
- mot de passe compromis refusé.

**Critères d'acceptation** : s'inscrire avec un numéro ivoirien, se déconnecter, se reconnecter en tapant le numéro dans un autre format, réinitialiser le mot de passe (code lu dans `storage/logs`).

---

## Lot 3 — Fil public et pages de publication

**Références** : §5, §8, maquettes « Fil mobile » et « Fil desktop ».

**Contenu**
- Layout public (en-tête, onglets, filtres, navigation basse mobile, trois colonnes desktop) et composant **`x-carte-publication`** fidèle à la maquette (badges par type, faits clés, source, bandeau anti-arnaque, compteurs, actions).
- **Fil par curseur** :
  - `/`, `/emplois`, `/services`, `/formations` ;
  - filtres commune, contrat et « aujourd'hui » ;
  - première salve rendue par le serveur ;
  - `GET /fil/suite` (fragment HTML + `X-Curseur-Suivant`) ;
  - `IntersectionObserver`, squelettes, bouton de secours, `aria-live` ;
  - bandeau « N nouvelles publications ».
- `CacheFil` + `PublicationObserver` (§8) : clés versionnées, cache des cartes, `Cache::lock`.
- Page détail `/publication/{slug}` : JSON-LD **`JobPosting`** pour les emplois, Open Graph pour le partage WhatsApp, lien court `/p/{code}`.
- Recherche FULLTEXT + journal `recherches` (en file).
- Réactions (J'aime optimiste), commentaires (publier, répondre, supprimer les siens), enregistrements, partage WhatsApp (`nb_partages`), signalement avec `SignalementObserver` (masquage à 3 signalements, §13.D).
- `sitemap.xml` et `robots.txt`.

**Tests obligatoires** :
- **aucun doublon ni trou entre deux salves**, même si une publication est ajoutée entre deux appels ;
- filtres combinés ;
- invalidation du cache à la publication et à la dépublication ;
- les données personnelles ne sont jamais dans le cache partagé ;
- une publication non publiée renvoie 404 ;
- JSON-LD valide ;
- masquage automatique après 3 signalements.

**Critères d'acceptation** : sur mobile (360 px) avec les données de démo, le fil se charge sans saut, le défilement charge les suites, les filtres fonctionnent, le partage WhatsApp ouvre le bon texte.

---

## Lot 4 — Publication par les utilisateurs

**Références** : §11 complet.

**Contenu**
- Les 4 parcours, FormRequests, création d'entreprise dans le formulaire, brouillon (navigateur + serveur), **aperçu par le vrai composant**.
- `PublierPublication` (transaction, champs dénormalisés, `code_court`, `tri_le`), `AnalyserRisquePublication`, `DeciderStatutPublication`, `AttribuerRoleAuteur`, anti-spam et anti-doublon.
- Les vérifications de quota s'appuient sur `Droits` : **dans ce lot, implémenter une version minimale de `Droits` qui lit les plans par défaut**. La version complète arrive au lot 6.
- « Mes publications » : statuts, statistiques, modifier (règles de remise en modération), clôturer, prolonger, dupliquer, supprimer.
- Réponses (§11.8) : postuler, contacter, « Je suis disponible », s'inscrire, et la gestion des réponses par l'auteur.
- Policies et notifications à l'auteur.
- Job `EnrichirPublicationIA` branché sur `ClientIA` (driver `fake`).

**Tests obligatoires** :
- **chaque ligne du tableau §11.6** ;
- chaque règle de risque du §11.5 ;
- quota journalier ;
- doublon sur 7 jours ;
- le téléphone d'un demandeur de service n'est jamais exposé ;
- une réponse unique par personne ;
- modification par un auteur `nouveau` puis `fiable` ;
- prolongation au maximum 3 fois.

**Critères d'acceptation** : publier un exemple de chacun des 4 types depuis un mobile ; la première publication part en attente ; une publication contenant « frais de dossier » part en attente avec le signal visible.

---

## Lot 5 — Mon espace, alertes et notifications

**Références** : §10, maquette « Mon espace ».

**Contenu**
- Mon espace : profil (modification, changement de numéro avec OTP, suppression du compte avec anonymisation), notifications (cloche, pastille, lu / non lu, tout marquer), enregistrés.
- Alertes : création depuis les filtres du fil (pré-remplie, y compris après l'inscription), CRUD, pause, limite `alertes.max`.
- `CorrespondreAlertes` (à la publication) et récapitulatif quotidien à 7h00 (`alertes:recap-quotidien`) ; anti-doublon `alertes_publications`.
- Notifications `database` ; le **canal SMS est préparé mais activé au lot 6**.

**Tests obligatoires** : correspondance par type, catégorie, commune, contrat et mots-clés ; pas de double notification ; alertes en pause ignorées ; récapitulatif quotidien ; limite d'alertes.

**Critères d'acceptation** : créer l'alerte « Électricien · Cocody » depuis le fil sans compte, s'inscrire, retrouver l'alerte créée, publier une annonce correspondante avec un autre compte, voir la notification apparaître.

---

## Lot 6 — Abonnements dynamiques, paiements, SMS, visibilité payante

**Références** : §14 complet.

**Contenu**
- `Droits` complet : combinaison des plans, `-1`, crédits, `quotas_actifs`, cache versionné, `QuotaAtteintException` et écran « Quota atteint ».
- Essais (`essais_utilises`, numéro vérifié), `abonnements:traiter` (relances J-3, J-1, J0, grâce, expiration, pause des alertes en trop).
- Commandes :
  - `PasserellePaiement` avec les drivers **`cinetpay`** (initialisation, vérification) et `fake` ;
  - webhook signé, revérifié et idempotent ;
  - `ActiverCommande` (renouvellement à la suite de la période en cours) ;
  - page de retour avec actualisation automatique ;
  - reçus HTML et PDF numérotés.
- **SMS** : `SmsChannel`, driver `log` + driver de production choisi (§18), translittération GSM-7, plafond quotidien + récapitulatif de 19h00, plage silencieuse 21h00 → 7h00, journal `sms_envoyes`, liens `/p/{code}`.
- **Visibilité** : remontée (`tri_le`, une fois toutes les 24 h), `une_commune`, `sponsorise_fil` via `EmplacementsSponsorises` (`?deja=N`), suspension et reprise, impressions et clics en différé.
- Pages `/tarifs`, « Mon abonnement », incitations contextuelles (§14.11).

**Tests obligatoires** :
- droits combinés (gratuit + essai + payant ; entiers, `-1`, booléens) ;
- `quotas_actifs = false` ;
- crédits consommés ;
- **un seul essai par numéro et par famille, même après suppression du compte** ;
- webhook rejoué deux fois = une seule activation ;
- webhook à la signature invalide rejeté ;
- montant falsifié côté client sans effet ;
- renouvellement cumulatif ;
- grâce puis expiration ;
- SMS de plus de 160 caractères tronqué proprement et sans accents ;
- plage silencieuse ;
- carte sponsorisée injectée sans doublon et sans casser le curseur ;
- publication rejetée jamais mise en avant.

**Critères d'acceptation** (driver `fake`) :
- démarrer l'essai SMS de 14 jours et recevoir l'alerte dans le log SMS ;
- activer `quotas_actifs`, publier 3 offres puis voir l'écran « Quota atteint » ;
- acheter une offre à l'unité, puis acheter une remontée ;
- télécharger le reçu PDF.

---

## Lot 7 — Back-office

**Références** : §13 complet (modules A à K) + §14.10.

**Contenu**
- Layout admin, bundle `admin.js` (jQuery, DataTables en mode serveur, Select2, SweetAlert2), permissions et menus selon `@can`.
- Modules MVP :
  - A — tableau de bord ;
  - B — **modération avec raccourcis clavier** ;
  - C — publications et `publications:expirer` ;
  - D — signalements ;
  - E — veille et sources (écrans ; l'import réel arrive au lot 8) ;
  - F — utilisateurs et entreprises ;
  - G — référentiels et paramètres, boutons de vidage de cache ;
  - K — plans (éditeur de limites), produits, abonnements, commandes, revenus.
- Écriture du journal d'audit (`JournaliseActivite`) dès ce lot.

**Tests obligatoires** :
- un modérateur ne peut pas accéder aux modules réservés à l'administrateur ;
- valider ou rejeter déclenche les bons événements et notifications ;
- actions groupées ;
- un admin ne peut pas se suspendre lui-même ;
- l'anonymisation ARTCI ;
- un plan souscrit ne peut pas être supprimé ;
- modifier une limite invalide le cache des droits ;
- chaque action admin écrit une ligne de journal.

**Critères d'acceptation** : modérer 20 publications de démo au clavier ; modifier le prix et une limite d'un plan, puis vérifier l'effet immédiat côté utilisateur ; offrir un abonnement à un artisan.

---

## Lot 8 — Import de la veille et enrichissement IA

**Références** : §12, §11.5.

**Contenu**
- `SourceVeille` :
  - driver `google_drive` (compte de service, liste du dossier, export CSV) ;
  - driver `fake` (lit `tests/Fixtures/veille/*.csv`).
- `veille:importer` (planifiée), `ImporterFichierVeille` : normalisation des URL, empreinte, correspondance catégorie et commune, source inconnue → confiance basse, décision de publication, compteurs `imports_veille`.
- `ClientIA` driver `claude` (API Claude Haiku via le client HTTP Laravel) :
  - texte de la carte, catégorie fine, `score_fiabilite`, `signaux_risque` ;
  - réponse JSON validée strictement ;
  - journal `appels_ia` avec coût estimé ;
  - nouvelles tentatives avec temporisation croissante ; en cas d'échec, la publication passe en `en_attente`.

**Tests obligatoires** : fichier déjà importé ignoré ; doublon d'URL (avec paramètres de suivi et slash final) ; correspondance des 3 catégories ; publication automatique seulement si la confiance est haute **et** le score ≥ seuil ; réponse IA invalide gérée ; aucun texte source copié intégralement (résumé limité).

**Critères d'acceptation** : avec les vrais identifiants dans `.env` local, lancer `veille:importer` sur le dossier Drive « Offres Emploi Abidjan » et voir les offres apparaître ou rester en attente selon les règles.

---

## Lot 9 — PWA, performance, accessibilité, sécurité

**Références** : §8, §15, §16.

**Contenu**
- `vite-plugin-pwa` : manifest, icônes, stratégies de cache, **page hors ligne**, exclusion de `/mon-espace` et `/admin`, invitation à installer après 2 visites.
- Performance :
  - audit des requêtes (aucun N+1, `preventLazyLoading`) ;
  - index vérifiés avec `EXPLAIN` sur les requêtes du fil ;
  - images WebP et `width`/`height` ;
  - budget JS public < 50 Ko (vérifier la taille du build) ;
  - en-têtes de cache.
- Accessibilité AA : contrastes, focus, labels, navigation au clavier sur tout le parcours public.
- Sécurité :
  - en-têtes CSP, HSTS, `X-Frame-Options`, `Referrer-Policy` ;
  - revue des Policies sur toutes les routes ;
  - tentatives d'accès croisé (modifier la publication d'un autre) ;
  - envois de fichiers (types et tailles) ;
  - limites de débit.

**Tests obligatoires** : test de non-accès croisé sur chaque ressource du membre ; en-têtes de sécurité présents ; pages `/mon-espace` envoyées avec `Cache-Control: no-store`.

**Critères d'acceptation** : **Lighthouse mobile** sur `/` et sur une page détail, avec les données de démo : Performance ≥ 90, Accessibilité ≥ 95, Bonnes pratiques ≥ 95, SEO ≥ 95. Installation de la PWA sur Android et affichage de la page hors ligne en mode avion.

---

## Lot 10 — Recette finale et mise en production

**Contenu**
- **Tests de bout en bout** (Pest 4 *browser testing* si disponible, sinon Laravel Dusk) sur les parcours clés :
  1. visiteur → fil → filtre → détail → partage ;
  2. inscription → alerte → notification ;
  3. publication artisan → modération → fil ;
  4. quota atteint → achat (fake) → publication ;
  5. essai SMS → fin d'essai → pass.
- `docs/DEPLOIEMENT.md` :
  - serveur (PHP, extensions dont `memcached`, MySQL, Memcached, Supervisor) ;
  - `.env` de production ;
  - cron, Supervisor et commandes de déploiement ;
  - sauvegardes quotidiennes de la base et des médias ;
  - procédure de retour arrière.
- `docs/RECETTE.md` : la checklist manuelle complète pour Alex (tous les critères d'acceptation des lots, en une seule liste à cocher).
- Vérification finale : `migrate:fresh --seed` sur un environnement de préproduction, suite complète verte, Lighthouse OK.

**Critères d'acceptation** : la checklist `RECETTE.md` est entièrement cochée par Alex en préproduction.
