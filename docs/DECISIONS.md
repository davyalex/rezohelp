# Rezohelp — Décisions prises en cours de développement

> Format : date · sujet · options · choix · raison. Les écarts avec `docs/CONTEXTE.md` sont notés ici, jamais dans le CONTEXTE.

---

### D-001 · 2026-10-16 · Emplacement des documents de référence
- **Options** : laisser `CONTEXTE.md` et `PLAN_DEVELOPPEMENT.md` à la racine ; les déplacer dans `docs/`.
- **Choix** : déplacés dans `docs/`, contenu inchangé.
- **Raison** : `CLAUDE.md` et `PROMPT_CLAUDE_CODE.md` les référencent sous `docs/`.

### D-002 · 2026-10-16 · Base de données des tests
- **Options** : SQLite en mémoire (squelette Laravel) ; MySQL 8.
- **Choix** : MySQL 8 (`rezohelp_test`, une base par processus avec `--parallel`).
- **Raison** : le fil et la recherche utilisent FULLTEXT et des index propres à MySQL ; la CI tourne aussi sur MySQL 8. Les tests vérifient ainsi le comportement réel.

### D-003 · 2026-10-16 · Accès au cache
- **Options** : `Cache::` direct pour les verrous et les droits ; tout passer par `CacheFil`.
- **Choix** : **tout** accès au cache applicatif passe par `App\Services\CacheFil` (fil, cartes, référentiels, paramètres, droits, verrous `Cache::lock`).
- **Raison** : `CLAUDE.md` interdit `Cache::` hors de `CacheFil` ; les verrous demandés (`ActiverCommande`, clés chaudes) sont donc exposés par `CacheFil`.

### D-004 · 2026-10-16 · Trait `HorodatageFrancais`
- **Options** : constantes `CREATED_AT` / `UPDATED_AT` dans le trait ; surcharge des accesseurs.
- **Choix** : le trait surcharge `getCreatedAtColumn()` et `getUpdatedAtColumn()` et déclare la constante `DELETED_AT`.
- **Raison** : PHP refuse une constante de trait qui redéfinit `Model::CREATED_AT` ; `DELETED_AT` n'existe pas dans `Model`, il peut donc venir du trait (lu par `SoftDeletes`).

### D-005 · 2026-10-16 · Laravel Boost, Pail, Telescope
- **Options** : installer `laravel/boost` (demandé par `AGENTS.md` du squelette), Telescope (cité au §8) ; ne pas les installer.
- **Choix** : non installés.
- **Raison** : dépendances absentes de la liste de `CLAUDE.md` (« avant d'ajouter une dépendance : demander ») et non indispensables. `laravel/pail` (déjà dans le squelette) ne fonctionne pas sous Windows (pas de `pcntl`) : `composer run dev` lance serveur + Vite + file ; les journaux se lisent dans `storage/logs`.

### D-006 · 2026-10-16 · Version de jQuery
- **Options** : jQuery 4 ; jQuery 3.7.
- **Choix** : jQuery 3.7.1.
- **Raison** : Select2 4.1 utilise des API retirées de jQuery 4 (`$.trim`…).

### D-007 · 2026-10-16 · Mesure du budget JS public (< 50 Ko)
- **Options** : taille minifiée brute ; taille transférée (gzip).
- **Choix** : taille **transférée (gzip)**, vérifiée sur le build Vite.
- **Raison** : Alpine.js, imposé par `CLAUDE.md`, pèse à lui seul ≈ 54 Ko minifié mais ≈ 19 Ko gzip. Un budget brut serait impossible à tenir avec la pile choisie ; c'est la taille transférée qui compte en 3G/4G.

### D-008 · 2026-10-16 · Modèle `Utilisateur` dès le lot 0
- **Options** : attendre le lot 1 ; créer le modèle d'authentification au lot 0.
- **Choix** : `App\Models\Utilisateur` + table `utilisateurs` (colonnes §7 et §13.4, hors `commune_id`) et `config/auth.php` dès le lot 0. Relations, rôles, médias, `commune_id` et factory au lot 1.
- **Raison** : le socle (configuration d'authentification, trait `HorodatageFrancais`) doit être utilisé et analysé par Larastan ; le squelette `User` anglais a été supprimé.

### D-009 · 2026-10-16 · Migrations avant la première mise en production
- **Choix** : tant qu'aucune base de production n'existe, les migrations peuvent être réordonnées ou complétées d'un lot à l'autre (ex. `commune_id` ajouté à `utilisateurs` au lot 1) plutôt que d'empiler des migrations correctives.
- **Raison** : historique de schéma lisible ; `migrate:fresh --seed` reste la référence. À partir de la mise en production, toute évolution passe par une nouvelle migration.

### D-010 · 2026-10-16 · Media Library Pro
- **Choix** : `media-library.temporary_upload_model = null`.
- **Raison** : la version Pro n'est pas utilisée ; le paquet gère explicitement la valeur `null`.

### D-011 · 2026-10-16 · Motif de rejet
- **Options** : une colonne texte libre `motif_rejet` ; motif prédéfini + texte libre.
- **Choix** : `publications.motif_rejet` (enum `MotifRejet`, §13.B) + `publications.motif_rejet_detail` (texte libre, facultatif).
- **Raison** : §13.B prévoit des motifs prédéfinis « + texte libre » ; l'enum permet les statistiques et la règle « 2 rejets pour arnaque ⇒ restreint » (§11.6).

### D-012 · 2026-10-16 · Colonnes ajoutées au modèle de données du §7
- `publications.source_veille_id` et `publications.import_veille_id` : nécessaires au §13.E (offres et taux de rejet par source, détail d'un import).
- `signalements.retenu` (booléen, null tant que non traité) : la règle « aucun signalement retenu » du §11.6 a besoin de distinguer un signalement fondé d'un signalement écarté.
- `mises_en_avant.derniere_impression_le` : rotation « pondérée par l'ancienneté de la dernière impression » (§14.9).
- `plans.cible_par_defaut` : colonne générée `IF(est_defaut, cible, NULL)` avec index unique, qui garantit en base **un seul plan par défaut par cible**.
- `categories.actif`, `communes.ordre` et horodatages `cree_le` / `modifie_le` sur les référentiels : activation et ordre demandés au §13.G.
- `utilisateurs.telephone` nullable : l'anonymisation ARTCI (§13.F) efface le numéro.
- `signalements` unique `(publication_id, utilisateur_id)` : un membre ne peut signaler qu'une fois la même publication (protège le masquage automatique à 3 signalements).

### D-013 · 2026-10-16 · Tables de liaison sans modèle
- **Choix** : `enregistrements` et `alertes_publications` sont manipulées comme pivots `belongsToMany` (`publicationsEnregistrees`, `enregistreePar`, `alertes`, `publications`), sans modèle Eloquent ni factory.
- **Raison** : ce sont de pures tables de jointure à clé composée, qu'Eloquent ne gère pas comme modèles. `EssaiUtilise` (clé composée) a un modèle en insertion / lecture seule.

### D-014 · 2026-10-16 · Cast E.164 et `TelephoneService`
- **Options** : `E164PhoneNumberCast` de laravel-phone (renvoie un objet `PhoneNumber`) ; cast maison renvoyant une chaîne.
- **Choix** : cast `App\Casts\TelephoneE164` qui normalise via `TelephoneService` et renvoie la chaîne E.164 ; un numéro invalide lève une exception. `TelephoneService` est livré dès le lot 1 (le seeder administrateur en a besoin).
- **Raison** : une chaîne simplifie les recherches, les empreintes SHA-256 et l'envoi de SMS. Affichage : les numéros ivoiriens sont groupés par deux (« 07 08 09 10 11 ») au lieu du format libphonenumber (« 07 08 09 1011 »), comme dans le CONTEXTE.

### D-015 · 2026-10-16 · Clés de quota (`CleDroit::estQuota()`)
- **Choix** : quand `quotas_actifs = false`, deviennent illimitées : `alertes.max`, `emploi.offres_mois`, `service.offres_actives`, `formation.offres_actives`, `reponses.demandes_mois`. Restent soumises aux plans : les canaux (`canal_sms`, `canal_whatsapp`), `alertes.sms_par_jour`, `publication.remontees_mois`, `publication.photos_max` et `publication.duree_max_jours` (limites de taille d'une annonce, révisé au lot 4), et les booléens.
- **Raison** : §14.5 ne libère que les « clés de quota ». Rendre illimités les SMS ou les remontées incluses coûterait de l'argent ou viderait le produit `remontee`, actif dès le lancement (§14.12).

### D-016 · 2026-10-16 · Valeurs non précisées de la grille §14.4
- `publication.duree_max_jours` : 60 jours dans les plans gratuits, 90 dans les plans payants.
- `publication.photos_max` : 2 pour `membre_gratuit` et `organisme_gratuit`, 5 pour `entreprise_30j` et `organisme_pro_30j`.
- `membre_gratuit.emploi.offres_mois = 1` : c'est la limite « particulier : 1 » du §14.4 (un particulier publie en son nom propre, le titulaire est donc l'utilisateur).
- `alertes_sms_30j` porte aussi l'essai de 14 jours ; la famille `alertes_sms` garantit un seul essai pour les deux plans.
- Produits : `remontee` et `une_commune_7j` ciblent les artisans, `offre_emploi_unite` et `sponsorise_fil_7j` les recruteurs (regroupement sur `/tarifs`).
- Activation initiale = phase « Lancement » du §14.12.

### D-017 · 2026-10-16 · Paramètres supplémentaires
- **Choix** : en plus des clés citées, `parametres` contient les seuils chiffrés du CONTEXTE : `seuil_remise_en_attente` (40), `publications_pour_fiable` (3), `rejets_arnaque_pour_restreint` (2), `max_envois_par_heure` (10), `jours_doublon` (7), `seuil_similarite_doublon` (90), `max_prolongations` (3), `jours_prolongation` (30), `max_cartes_une` (2), `heures_entre_remontees` (24), `jours_purge_recherches` (180), `jours_purge_publications` (90).
- **Raison** : règle « aucune valeur en dur » ; ces seuils deviennent réglables dans l'admin. Le seeder n'écrase jamais une valeur modifiée.

### D-018 · 2026-10-16 · Relations polymorphes
- **Choix** : `Relation::enforceMorphMap()` avec des alias français (`utilisateur`, `entreprise`, `publication`…) pour `titulaire_type`, `sujet_type`, `model_type` (rôles, médias) et `notifiable_type`.
- **Raison** : valeurs de données en français (CLAUDE.md) et indépendance vis-à-vis des noms de classes.

### D-019 · 2026-10-16 · Larastan et casts
- **Choix** : `parseModelCastsMethod: true` dans `phpstan.neon`.
- **Raison** : sans cette option, Larastan ignore les casts déclarés dans `casts()` (typée `array<string, string>`) et considère les enums et dates comme des chaînes. L'option rend l'analyse plus stricte, le niveau 6 est inchangé.

### D-020 · 2026-10-16 · Routes et champs de Fortify
- **Choix** : chemins français via `fortify.paths` (`/connexion`, `/inscription`, `/deconnexion`) ; les **noms** de route restent ceux du framework (`login`, `register`, `logout`, `login.store`, `register.store`) et le champ du mot de passe s'appelle `password` (avec `password_confirmation`), libellé « mot de passe ».
- **Raison** : Fortify et le middleware `auth` de Laravel s'appuient sur ces noms et sur le champ `password` (`LoginRequest`). Les autres routes d'authentification, maison, ont des noms français (`mot-de-passe.oublie`, `telephone.verification`…).

### D-021 · 2026-10-16 · Vérification du numéro
- **Options** : bloquer le compte tant que le numéro n'est pas vérifié ; proposer la vérification sans bloquer.
- **Choix** : un code est envoyé à l'inscription et l'écran de vérification s'affiche, avec « Vérifier plus tard ». Le numéro vérifié sera exigé là où le CONTEXTE l'impose (essai gratuit §14.6, canal SMS §14.8). Une réinitialisation de mot de passe réussie vaut vérification (le code a prouvé la possession du numéro).
- **Raison** : le §1 veut une inscription simple ; le §9 ne rend pas la vérification obligatoire pour consulter, réagir ou publier.

### D-022 · 2026-10-16 · Codes OTP et plage silencieuse
- **Choix** : la règle « aucun envoi entre 21h00 et 7h00 » (§14.8) ne s'applique pas aux codes OTP.
- **Raison** : un code est demandé par le membre lui-même, à l'instant ; le retarder au matin empêcherait de s'inscrire ou de récupérer son compte le soir.

### D-023 · 2026-10-16 · Confidentialité des codes OTP
- **Choix** : seule une empreinte HMAC du code est stockée (`codes_otp.empreinte_code`) ; le journal `sms_envoyes` garde le message avec le code masqué (`******`) ; le texte réel voyage dans un job chiffré (`ShouldBeEncrypted`). Le driver `log` écrit le code en clair dans `storage/logs/sms.log` (local uniquement). Après une réinitialisation par code, les autres sessions du membre sont fermées et le jeton « se souvenir de moi » est renouvelé.
- **Raison** : une copie de la base ou de la file ne doit pas permettre de détourner un compte.

### D-024 · 2026-10-16 · Rôle à l'inscription
- **Choix** : tout nouveau compte reçoit le rôle `membre` ; les rôles `recruteur`, `artisan`, `organisme` viendront de la première publication (§11.1, lot 4).

### D-025 · 2026-10-16 · Colonnes dénormalisées des cartes
- **Choix** : `publications.faits_cles` (JSON, 4 puces au plus), `type_contrat` (copie de `details_emploi`, indexé pour le filtre « contrat »), `auteur_verifie`, `image_url` / `image_largeur` / `image_hauteur`. Calculés par `App\Services\FaitsCles` à l'enregistrement.
- **Raison** : `CLAUDE.md` interdit toute jointure sur `details_*` pour les cartes du fil, alors que la carte affiche contrat, salaire, tarif, durée et prix (§5).

### D-026 · 2026-10-16 · Gabarit de carte à jetons
- **Options** : HTML complet en cache (compteurs et « il y a 2 h » figés 24 h, état « J'aime » impossible) ; pas de cache des cartes ; gabarit partagé + valeurs injectées.
- **Choix** : `carte:{id}:{modifie_le}` contient un gabarit avec des jetons (compteurs, état J'aime / enregistré, date relative, CSRF, bandeau de prévention) remplacés à chaque requête par `RenduCarte` ; l'état du visiteur est chargé en **une** requête (`UNION` réactions + enregistrements) pour les cartes visibles. Les crochets des jetons sont retirés des textes saisis par les membres.
- **Raison** : respecte « jamais de données propres à l'utilisateur dans le cache partagé » tout en affichant des compteurs et des dates justes, avec ou sans JavaScript.

### D-027 · 2026-10-16 · Bouton principal de la carte
- **Choix** : « Postuler », « Contacter », « Je suis disponible » et « S'inscrire » mènent à la section « Répondre » de la page détail, qui présente le bon mode (lien, e-mail, téléphone, WhatsApp, formulaire Rezohelp au lot 4).
- **Raison** : la carte ne lit pas `details_*` (mode de candidature, téléphone affiché ou non) ; la page détail les charge.

### D-028 · 2026-10-16 · Visibilité de la page détail
- **Choix** : visibles par tous : `publiee`, `cloturee` et `expiree` (avec un bandeau). Les autres statuts renvoient **404**, sauf pour l'auteur et la modération (`PublicationPolicy@view`).
- **Raison** : §11.7 garde la page d'une publication clôturée visible ; le plan exige 404 pour une publication non publiée.

### D-029 · 2026-10-16 · Signalement
- **Choix** : réservé aux membres connectés, un signalement par membre et par publication (index unique). Seuls les signalements « arnaque » non traités comptent pour le masquage (`seuil_masquage_signalements`).
- **Raison** : le §2 ne donne pas l'action de signaler au visiteur ; empêche qu'un seul compte (ou un robot) masque une annonce.

### D-030 · 2026-10-16 · Recherche plein texte
- **Choix** : `MATCH … AGAINST` en mode booléen, chaque mot de 3 lettres ou plus requis avec recherche par préfixe (`+plomb*`), repli sur `titre LIKE` pour une saisie plus courte ; tri chronologique du fil (curseur). Le journal `recherches` (avec le nombre de résultats) est écrit par un job en file. Les tests de recherche tournent sans transaction (`tests/Recherche`, `DatabaseTruncation`) car InnoDB n'indexe qu'au COMMIT.

### D-031 · 2026-10-16 · Éléments du fil livrés avec leurs lots
- **Choix** : le bloc « Créer une alerte », la cloche des notifications et les entrées « Publier », « Alertes », « Mon espace » de la navigation basse sont ajoutés avec les lots 4 et 5, quand leurs routes existent ; « À la une » / « Sponsorisé » au lot 6. La colonne de droite affiche en attendant les communes populaires et les formations récentes.
- **Raison** : pas de lien mort ni de code conditionnel provisoire.

### D-032 · 2026-10-16 · « Charger plus » sans JavaScript et compteurs de consultation
- **Choix** : sans JavaScript, « Charger plus » ouvre la page avec `?curseur=…` (salve suivante). Les vues et les partages sont comptés après l'envoi de la réponse (`defer`), sans modifier `modifie_le`.

### D-033 · 2026-10-19 · Titulaires des plans par défaut (`Droits`, version minimale)
- **Choix** : un **utilisateur** cumule les plans par défaut `membre` et `artisan` ; une **entreprise** cumule `recruteur` et `organisme` (maximum des entiers, -1 l'emporte, OU des booléens). Abonnements, crédits et cache des droits arrivent au lot 6.
- **Raison** : un membre doit pouvoir publier sa première offre de service avant d'avoir le rôle `artisan` (attribué à cette publication, §11.1) ; une entreprise peut recruter et former. Conséquence : `reponses.demandes_mois` vaut 5 (artisan) pour tout membre.

### D-034 · 2026-10-19 · Quotas et demande de service
- **Choix** : une demande de service (« Je cherche un prestataire ») ne consomme aucun quota d'annonces ; `service.offres_actives` ne compte que les offres. Avant le formulaire, le quota est vérifié pour le membre et ses entreprises existantes ; il est revérifié à l'envoi pour le titulaire choisi.
- **Raison** : le §14.3 décrit `service.offres_actives` comme les annonces de l'artisan ; un client qui cherche un plombier ne doit pas être bloqué. Limite connue : créer une nouvelle entreprise dans le formulaire repart du plan par défaut ; la fusion des doublons d'entreprises (lot 7) et la modération encadrent cet usage.

### D-035 · 2026-10-19 · Brouillon serveur
- **Choix** : « Enregistrer en brouillon » applique les mêmes validations que la publication, sans analyse de risque, sans décision ni quota. Le brouillon navigateur (Alpine + `localStorage`, toutes les 5 s) garde, lui, une saisie incomplète. Publier un brouillon passe par `PublierPublication` et la policy `create`.
- **Raison** : le schéma impose titre, contenu et identifiants ; une saisie partielle est déjà protégée par le navigateur.

### D-036 · 2026-10-19 · Modification d'une publication
- **Choix** : (1) une modification ne repousse jamais l'expiration (seule « Prolonger » le fait) ; (2) de nouvelles photos **remplacent** les anciennes, pour rester sous `publication.photos_max` ; (3) pour un auteur `fiable`, la relance de l'analyse de risque qui trouve un signal renvoie la publication en attente ; (4) `ModifierPublication` ne fait que repasser en attente, jamais en ligne (mise en ligne : `DeciderStatutPublication` ou modération) ; (5) une publication clôturée ou expirée ne se modifie plus : on la duplique.
- **Raison** : éviter les prolongations ou les photos illimitées détournées, et garder une seule porte d'entrée vers le statut `publiee`.

### D-037 · 2026-10-19 · Prolongation, clôture, réponses
- **Choix** : seule une publication en ligne se prolonge (`jours_prolongation`, `max_prolongations`) ; la clôture accepte une publication en ligne ou en attente, avec un motif (« Poste pourvu », « Plus disponible », « Complet », « Autre »). Pour un contact ou une proposition (« Je suis disponible »), le numéro du répondant est toujours transmis à l'auteur (seul moyen de le joindre avant la messagerie V2) ; pour une candidature ou une inscription, c'est une case à cocher. Un clic vers un contact externe (lien, e-mail, téléphone) est compté dans `nb_reponses`.

### D-038 · 2026-10-19 · Score IA d'une publication native
- **Choix** : pour une publication d'un membre, `EnrichirPublicationIA` ne réécrit pas le texte (réservé à la veille, lot 8) : il enregistre `score_fiabilite`, complète `signaux_risque`, journalise l'appel dans `appels_ia` (coût estimé d'après `CLAUDE_COUT_ENTREE_FCFA` / `CLAUDE_COUT_SORTIE_FCFA`) et remet en attente une publication en ligne sous `seuil_remise_en_attente`.

### D-039 · 2026-10-19 · Correspondance des alertes
- **Choix** : un critère vide accepte tout ; une alerte sur une catégorie principale couvre ses sous-catégories (métiers) ; **tous** les mots-clés doivent apparaître en début de mot dans le titre ou le contenu (sans accents ni casse) ; l'auteur de la publication et les comptes suspendus sont exclus ; un membre n'est notifié qu'une fois par publication même si plusieurs de ses alertes correspondent ; une alerte doit avoir au moins un critère.
- **Raison** : le §10 laisse le choix FULLTEXT / LIKE ; les critères structurés restent en SQL (index), les mots-clés sont vérifiés sur les seules alertes candidates. Une alerte sans critère équivaudrait à recevoir tout le fil.

### D-040 · 2026-10-19 · Récapitulatif quotidien
- **Choix** : une notification par alerte quotidienne (nombre, 5 premiers titres, lien vers le fil filtré) ; les publications retirées du fil entre-temps sont écartées mais leurs paires sont soldées ; aucune notification immédiate pour une alerte quotidienne.

### D-041 · 2026-10-19 · « Créer une alerte » sans compte
- **Choix** : `/alertes/nouvelle` mémorise le formulaire pré-rempli comme page visée (`url.intended`) puis envoie vers `/inscription` ; après l'inscription et la vérification du numéro (ou « Vérifier plus tard »), le membre arrive sur le formulaire pré-rempli et confirme.
- **Raison** : parcours §4.2 ; l'alerte n'est pas créée sans confirmation, pour que le membre voie et ajuste ses critères.

### D-042 · 2026-10-19 · Suppression du compte par le membre
- **Choix** : confirmation par le mot de passe. Sont effacés : nom, prénoms, téléphone, e-mail, commune, photo, rôles, alertes, notifications, enregistrements, codes OTP, sessions ; le compte est supprimé (soft delete) et ne peut plus se connecter. Sont conservés sous « Utilisateur supprimé » : publications, commentaires, réactions, réponses (sans numéro) ; les entreprises restent ; l'empreinte des essais gratuits reste (§14.6). Le numéro libéré peut servir à un nouveau compte.

### D-043 · 2026-10-19 · Titulaire d'un achat
- **Choix** : on achète pour soi ou pour une de ses entreprises (champ `entreprise_id`). Un plan doit viser une cible du titulaire (utilisateur : membre / artisan ; entreprise : recruteur / organisme, D-033). Un produit ne vérifie pas la cible : l'offre d'emploi à l'unité vaut aussi pour un particulier qui recrute, et une remontée ou une mise en avant exige une publication **en ligne** du titulaire.
- **Raison** : la colonne `produits.cible` sert au classement ; un particulier bloqué à 1 offre doit pouvoir en acheter une.

### D-044 · 2026-10-19 · Webhook de paiement
- **Choix** : `GET` répond 200 sans rien traiter (contrôle de disponibilité de l'URL par la passerelle) ; chaque `POST` est journalisé dans `notifications_paiement` ; signature invalide → 403 ; référence absente → 422 ; une exception → 500, pour que la passerelle renvoie la notification (rien n'est activé à moitié : `ActiverCommande` est transactionnel). La commande est cherchée par `reference_passerelle` ; le statut revérifié et le montant comparé décident seuls. La page de retour ne déclenche jamais d'activation. Le guichet simulé n'existe qu'avec le driver `fake`, hors production.

### D-045 · 2026-10-19 · Renouvellement et essai
- **Choix** : un nouvel achat du même plan pendant une période **payée** commence à sa fin ; s'il n'y a qu'un essai ou une période en grâce, celle-ci est close (`expire`) et le plan payé démarre tout de suite. Les relances ne partent pas quand une période suivante est déjà payée.

### D-046 · 2026-10-19 · Remontée payée
- **Choix** : le délai de 24 h (`heures_entre_remontees`) est contrôlé **à la commande** ; à l'activation, la remontée payée est appliquée sans nouveau contrôle (le membre a payé). Si la publication est sortie du fil entre-temps, la remontée est gardée en pause (`suspendue_le`) et appliquée à son retour en ligne. Les remontées incluses (`publication.remontees_mois`) se déclenchent depuis « Mes publications » sans paiement.

### D-047 · 2026-10-19 · Emplacements « Sponsorisé »
- **Choix** : « fil général » = sans commune, catégorie, contrat, « Aujourd'hui » ni recherche ; l'onglet de type est permis et ne montre que des cartes sponsorisées du même type. Les rangs se comptent sur les cartes du fil seules : une carte sponsorisée avant la carte n° k quand `(deja + rang) % frequence_sponsorise = 0`. Une carte déjà présente dans la salve n'est pas injectée. Rotation : la mise en avant affichée le moins récemment passe en premier (`derniere_impression_le`, mise à jour à l'affichage). Un fragment contenant une carte sponsorisée est servi en `private, no-store`. Sans JavaScript, « Charger plus » transmet aussi `deja`.

### D-048 · 2026-10-19 · Bloc « À la une »
- **Choix** : uniquement sur la première salve d'un fil filtré sur une commune (la commune de la mise en avant) et / ou une catégorie (celle de la publication), hors recherche ; au plus `max_cartes_une` cartes, même rotation ; ces cartes ne sont pas répétées dans la salve affichée (le curseur, lui, ne change pas).

### D-049 · 2026-10-19 · Affichages et clics
- **Choix** : un affichage est compté quand le serveur insère la carte ; un clic est envoyé par `navigator.sendBeacon` (avec le jeton CSRF) vers `POST /mises-en-avant/{id}/clic`, une fois par carte et par page, sans retarder la navigation (sans JavaScript, le clic n'est pas compté). Seule une mise en avant en cours compte. Report en base toutes les 5 minutes ; l'index des compteurs en cache est mis à jour sous verrou.

### D-050 · 2026-10-19 · Plafond SMS et récapitulatif de 19h00
- **Choix** : `alertes.sms_par_jour` compte les SMS de motif `alerte` du jour ; au-delà, la correspondance est notée `sms_en_attente` et la notification reste dans « Mon espace ». À 19h00, un seul SMS par membre (« N autre(s) publication(s) correspondent à vos alertes aujourd'hui », lien vers les notifications), qui ne compte pas dans le plafond ; les correspondances sont soldées même si le canal SMS a été perdu entre-temps. `AlerteCorrespondante` est envoyée directement par le job `CorrespondreAlertes` (déjà en file) pour que la ligne `sms_envoyes` existe aussitôt et que le plafond reste exact ; l'envoi réel du SMS reste en file.

### D-051 · 2026-10-19 · Statistiques avancées
- **Choix** : tout auteur voit vues, j'aime, partages et réponses (§11.7) ainsi que les affichages et clics de ses mises en avant (§14.9). `statistiques.avancees` ajoute les commentaires, le taux de réponse (réponses / vues) et les j'aime pour 100 vues.

### D-052 · 2026-10-19 · Incitations
- **Choix** : l'encart SMS ne s'affiche que juste après la création d'une alerte, si un essai qui ouvre `alertes.canal_sms` est encore disponible pour ce numéro ; l'écran « Quota atteint » propose, pour chaque titulaire possible, l'offre à l'unité (emploi) et l'essai d'une formule qui relève la limite ; « Il vous reste 1 offre » s'affiche quand le meilleur reste parmi les titulaires possibles vaut 1. Une incitation fermée reste fermée dans ce navigateur (`localStorage`, simple confort).

### D-053 · 2026-10-19 · Reçus et CinetPay
- **Choix** : les mentions légales du reçu acceptent des balises simples sans attribut (`p`, `br`, `strong`, `em`, `ul`, `li`) ; PDF A5 par dompdf ; reçu visible pour une commande payée ou remboursée. Le jeton `x-token` de CinetPay est calculé sur les champs `cpm_*` dans l'ordre de la documentation CinetPay : **à confirmer en bac à sable** avant la mise en production.

### D-054 · 2026-10-22 · Mise en ligne par une décision humaine
- **Choix** : `DeciderStatutPublication` reste le seul à décider **automatiquement** de la mise en ligne d'une publication soumise par un membre. Les décisions humaines du back-office (validation en modération, rétablissement après signalements, prolongation d'une publication expirée) mettent aussi en ligne, chacune par son action admin journalisée. La validation pose `publiee_le` et `tri_le` à la première mise en ligne seulement : une publication remise en ligne garde sa place dans le fil.
- **Raison** : la règle de CLAUDE.md porte sur la publication par les utilisateurs (§11) ; le §13.3.B prévoit explicitement « Valider → statut = publiee ». Ne pas remonter une publication réhabilitée évite une remontée gratuite.

### D-055 · 2026-10-22 · Dépublier
- **Choix** : « Dépublier » remet la publication **en attente de modération** (et non clôturée), l'auteur est prévenu (`PublicationRemiseEnAttente`) et ses mises en avant sont suspendues.
- **Raison** : une publication retirée par l'équipe ne doit plus être visible, même par sa page (une clôture la laisserait consultable) ; l'équipe décide ensuite de la valider ou de la refuser avec un motif.

### D-056 · 2026-10-22 · Suspension d'un compte
- **Choix** : la suspension retire du fil les publications en ligne du membre (retour en attente), sans les refuser ; la réactivation ne les remet pas en ligne automatiquement (la modération les revoit). Un compte est suspendu jusqu'à une date ou définitivement ; il est déconnecté à sa prochaine requête (`VerifierCompteActif`).
- **Raison** : « ses publications sont dépubliées » (§13.3.F) sans perdre leur contenu si la suspension était une erreur.

### D-057 · 2026-10-22 · Écrans du back-office
- **Choix** : (1) l'arbre des catégories est rendu par le serveur (liste hiérarchique bornée) ; toutes les autres listes sont des DataTables côté serveur. (2) Les listes sont déclarées par attributs `data-*` lus par `admin.js` plutôt que par les scripts générés de Yajra Html, pour qu'aucune vue admin ne contienne de script en ligne (CSP du lot 9). (3) Le graphique du tableau de bord est un SVG rendu par le serveur, sans Chart.js. (4) La levée d'une restriction de confiance enregistre `utilisateurs.restriction_levee_le` : les rejets et signalements antérieurs ne comptent plus. (5) Un paramètre (clé textuelle) est journalisé par sa clé dans les données, `journal_activites.sujet_id` étant numérique.

### D-058 · 2026-10-22 · Publication automatique de la veille
- **Choix** : une annonce de la veille entre en attente à l'import ; après l'enrichissement IA, `DeciderPublicationVeille` (seul point de décision pour la veille, comme `DeciderStatutPublication` pour les membres) la met en ligne si **toutes** ces conditions sont réunies : source active, `niveau_confiance = haut`, `publication_auto` coché, `score_fiabilite >= seuil_publication_auto`. Le score décidant la mise en ligne, aucune annonce de la veille n'est publiée sans score (IA en échec → en attente).
- **Raison** : la règle de CLAUDE.md (« haut **et** score ≥ 70 ») fixe des conditions nécessaires ; `publication_auto` (déjà présent dans `sources_veille`) sert d'interrupteur par source, réglable dans l'admin sans toucher au niveau de confiance.

### D-059 · 2026-10-22 · Normalisation des URL de la veille
- **Choix** : https, hôte en minuscules sans `www.`, port gardé, chemin sans slash final, sans fragment ; paramètres retirés : `utm_*`, `fbclid`, `gclid`, `dclid`, `msclkid`, `igshid`, `mc_cid`, `mc_eid`, `ref_src`, `_ga`, `_gl`, `yclid` ; les autres paramètres sont gardés et triés (ils désignent souvent l'annonce : `?id=42`). Seuls les liens http(s) sont acceptés.

### D-060 · 2026-10-22 · Contenu d'une annonce de la veille
- **Choix** : la publication garde le résumé fourni par la veille, limité à 600 caractères (jamais le texte source), et le lien d'origine ; l'extrait de la carte est le texte réécrit par l'IA (220 caractères). La catégorie vient de la colonne « Domaine » ; celle proposée par l'IA ne sert que si « Domaine » ne correspond à aucune catégorie. Une annonce de service externe n'affiche aucun numéro (contact par le lien d'origine). Le type « Home service / petits métiers » devient une **offre** de service. Les champs « Contrat/Durée/Prix » sont lus par mots-clés (CDI, CDD, stage, intérim, freelance ; montant en FCFA ; « gratuit »).

### D-061 · 2026-10-22 · Driver Claude
- **Choix** : appel HTTP direct de l'API Messages par le client HTTP de Laravel (CONTEXTE §6 « via HTTP client Laravel » ; le SDK PHP d'Anthropic serait une dépendance non listée), modèle `claude-haiku-5-5` par défaut (CONTEXTE : modèle Haiku), effort `low` (analyse courte), `max_tokens` 4 000 pour laisser la place à la réflexion active par défaut, sortie structurée par schéma JSON revalidée côté serveur. Les nouvelles tentatives immédiates (429 avec `retry-after`, 529, 5xx, réseau) s'ajoutent à celles du job (30 s, 2 min, 10 min).

### D-062 · 2026-10-22 · CSP et Alpine
- **Choix** : la CSP autorise les scripts du site avec le nonce posé par Vite, plus `'unsafe-eval'`, nécessaire à la version standard d'Alpine qui évalue les expressions `x-data` et `@click`. Aucun script en ligne sans nonce n'est accepté.
- **Raison** : la version CSP d'Alpine (`@alpinejs/csp`) supprimerait `'unsafe-eval'` mais oblige à réécrire toutes les expressions en composants enregistrés ; c'est une dépendance non listée, à proposer seulement avec accord.

### D-063 · 2026-10-22 · Réévaluation des annonces de la veille en attente
- **Choix** : `ReevaluerAnnoncesVeille` (commande `veille:reevaluer`, bouton « Réévaluer les annonces en attente » de la page Veille, et lancement automatique en file après chaque ajout ou modification d'une source) rattache chaque annonce en attente à la source déclarée pour son domaine, puis applique `DeciderPublicationVeille` avec le score déjà calculé. Ne sont jamais reprises : les annonces déjà mises en ligne puis remises en attente (`publiee_le` renseigné : signalements, dépublication, score en baisse), celles sans score et celles expirées. Une annonce mise en ligne ainsi part en tête du fil (`publiee_le` = `tri_le` = maintenant) et déclenche `PublicationPubliee`.
- **Raison** : sans cela, une annonce importée avant la déclaration de sa source restait en modération pour toujours ; l'IA n'est pas rappelée (pas de coût), et une annonce retirée par l'équipe ou par les signalements ne revient jamais seule.

### D-064 · 2026-10-22 · Notation gratuite de la veille et catégories composées
- **Choix** : (1) driver IA `regles` (`IA_DRIVER=regles`, choisi par Alex pour le lancement, sans service payant) : le score de fiabilité vient des règles anti-arnaque des membres (`AnalyserRisquePublication`, liste `mots_a_risque` des paramètres) : 85 sans signal, 30 de moins par signal ; le texte de la carte n'est pas réécrit, aucune catégorie n'est devinée et rien n'est journalisé dans `appels_ia`. Le driver `claude` reste disponible en changeant cette seule variable. (2) `CorrespondanceVeille::categorie` compare chaque terme du domaine (« Commerce / Vente », « IT / Réseaux ») mot à mot au nom des catégories, avec une table de synonymes par type ; le meilleur total l'emporte, puis la sous-catégorie, puis le terme cité en premier ; à défaut, « Autres métiers » pour un emploi, sinon aucune catégorie. L'ancienne recherche par sous-chaîne rattachait « IT » à « Finance et comptabilité » et « Déménagement » à « Ménage ». (3) `veille:reevaluer --renoter` renote en file les annonces en ligne ou en attente avec le driver actuel. (4) `DeciderPublicationVeille` refuse toute annonce déjà mise en ligne puis retirée.
- **Raison** : la veille est déjà rédigée par Claude dans Drive ; l'IA côté site n'apporte qu'un score, que les règles fournissent sans coût ni envoi de données à un tiers. Le driver `fake` attribuait la première catégorie de la liste aux domaines non reconnus (203 offres d'emploi en « Informatique et numérique »).

### D-065 · 2026-10-22 · Interface du fil, des comptes et du back-office
- **Choix** : (1) le bouton coloré des cartes devient « Voir plus » vers la page détail, où se trouvent les vraies actions (Postuler, Contacter, Je suis disponible, S'inscrire) ; le lien « Voir plus » sous l'extrait est retiré (doublon). (2) Les onglets et filtres du fil sont placés dans la colonne centrale, alignés sur les cartes ; la commune passe d'une rangée de 13 puces à une liste déroulante compacte (le filtre `?commune=` reste, utile pour le home service et les alertes) ; « Emplois par contrat » remplace « Communes populaires ». (3) Sur « Tout », les catégories sont regroupées par type dans des groupes dépliables. (4) Comptes : menus déroulants pour le visiteur (connexion, inscription) et le membre ; œil afficher / masquer sur tous les mots de passe ; indicatif court « CI +225 » (nom du pays au survol et pour les lecteurs d'écran). (5) Spinner et anti double envoi sur tout bouton qui envoie un formulaire (`activerSpinners`), site et admin. (6) Admin : barre latérale groupée, réductible aux icônes ; état gardé dans les cookies non chiffrés `admin_menu_reduit` et `admin_menu_groupes`, lus au rendu pour éviter tout clignotement.
- **Raison** : demandes d'Alex (revue UX) ; CONTEXTE §5 prévoyait le bouton principal selon le type sur la carte, mais il ne faisait qu'ouvrir le détail. Après déploiement, les gabarits de cartes déjà en cache (`carte:{id}:{modifie_le}`, 24 h) gardent l'ancien bouton jusqu'à expiration : vider le cache applicatif.

### D-066 · 2026-10-22 · Comptes de l'équipe séparés des membres
- **Choix** : (1) l'équipe a sa propre table `administrateurs` (modèle `Administrateur`, garde `admin`) ; un membre du site n'a plus aucun rôle d'équipe. Connexion sur `/admin/connexion` par **nom d'utilisateur + mot de passe**, 5 essais par minute (nom + IP) ; déconnexion du back-office sans fermer la session membre ; un visiteur ou un membre qui ouvre `/admin` est renvoyé vers cette page. (2) Les mots de passe créés ou réinitialisés par l'équipe sont **générés** (8 caractères, au moins une minuscule, une majuscule et un chiffre, sans caractères ambigus), affichés une seule fois, jamais journalisés, et doivent être changés à la première connexion (`AdministrateurActif`). Un compte suspendu est déconnecté au clic suivant ; un changement de mot de passe ferme les autres sessions (`AuthenticateSession`). (3) Rôles Spatie sur la garde `admin` : `super_administrateur` (tout, y compris les permissions ajoutées plus tard : `Gate::before`), `administrateur` et `moderateur`, plus des rôles libres créés dans l'admin ; un rôle par compte. Nouvelles permissions `administrateurs.gerer` et `roles.gerer`, données par défaut au seul super administrateur. Garde-fous : seul un super administrateur gère un super administrateur ou attribue ce rôle ; personne ne se suspend, ne se supprime ni ne change son propre rôle ; il reste toujours un super administrateur actif ; les rôles livrés ne se suppriment pas, le super administrateur ne se modifie pas, un rôle attribué ne se supprime pas. (4) Configuration : catalogue des permissions (groupe, libellé, rôles par défaut) et libellés des rôles dans `config/admin.php` ; super administrateur initial dans `.env` (`ADMIN_NOM_UTILISATEUR`, `ADMIN_NOM`, `ADMIN_PRENOMS`, `ADMIN_EMAIL`, `ADMIN_MOT_DE_PASSE` ; sans mot de passe, il est généré et affiché une fois par le seeder). Le seeder n'écrase jamais les permissions réglées dans l'admin. (5) Audit : `journal_activites.utilisateur_id` devient `administrateur_id` ; `signalements.traite_par` et `parametres.modifie_par` pointent vers `administrateurs` (anciennes valeurs vidées : elles désignaient des membres). (6) Côté site : l'équipe connectée au back-office voit une publication en attente et peut supprimer un commentaire (`commentaires.moderer`) ; les notifications « publication masquée » vont aux administrateurs (cloche de l'en-tête admin) ; le reçu d'une commande se télécharge depuis l'admin. (7) Tableaux : actions de ligne dans un menu « ⋯ » (API popover, jamais coupé par le défilement) ; barre latérale animée, barre de défilement fine, lien actif ramené au centre.
- **Raison** : demande d'Alex : dissocier la gestion de l'équipe de celle des membres, et gérer rôles et permissions (super administrateur, administrateur) sans toucher au code.

### D-067 · 2026-10-22 · Date de clôture des annonces de la veille et page détail
- **Choix** : (1) `ResumeVeille` lit la date de clôture dans le résumé de la veille (« Clôture le 30/10 », « Jusqu'au 31/10 », « Candidatures jusqu'au 05/12 », « avant le 02/11 », « Clôture le 30-31/10 », « Expire aujourd'hui »), l'année venant de la date de publication ; une date que la veille juge douteuse, antérieure à la publication ou à plus de 6 mois est ignorée. Pour un emploi, elle va dans `details_emploi.date_limite` (affichée sur la carte et en tête de la page détail, avec le nombre de jours restants) ; pour tous les types, `expire_le` = fin du jour de clôture. Une annonce déjà close n'est pas importée. (2) Les notes de la veille (« Détail non ouvert ce passage, à vérifier », « Pas de date limite affichée », « (aussi sur Projobivoire) ») et la phrase qui n'annonce que la clôture sont retirées du texte public ; les notes vont dans `publications.note_veille`, pour la modération. (3) `publications:expirer` retire aussi les offres d'emploi dont la date limite est passée, ainsi que les annonces de la veille closes pendant leur modération ; une annonce de la veille expirée est supprimée (corbeille, puis purge à 90 jours) `jours_suppression_veille` jours après sa clôture (5 par défaut, 0 = jamais). Les publications des membres ne sont jamais supprimées automatiquement : leur auteur peut les prolonger. (4) « Contrat/Durée/Prix » : premier contrat cité (« CDD / CDI » → CDD), aucun contrat plutôt qu'« Autre » s'il n'est pas précisé, salaire lu (« 200 000 à 300 000 F CFA », « moins de 150 000 F CFA » ; une prime n'est pas un salaire). (5) Page détail : bloc « Informations clés » en tête (date limite, contrat, lieu, salaire, expérience… ; rien d'affiché quand l'information manque), bouton d'action juste en dessous (« Voir l'annonce et postuler » pour la veille, plus de bouton une fois l'annonce close), puis la description. (6) En-tête : loupe dans le champ de recherche, largeur bornée, et bouton « Publier une annonce » à contour, séparé des icônes du compte, pour ne plus le confondre avec la validation de la recherche.
- **Raison** : demandes d'Alex : la date de clôture doit être une information enregistrée, l'annonce doit disparaître après la clôture, et les notes internes de la veille n'ont pas à être lues par le public.

### D-068 · 2026-10-23 · Budget JS, inscriptions et filtres accessibles
- **Choix** : (1) le budget JS public de 50 Ko (CLAUDE.md, CONTEXTE §8) se mesure **compressé en gzip**, comme il transite sur le réseau : `npm run budget-js` additionne l'entrée `resources/js/app.js` et ses imports statiques (22 Ko gzip, 62 Ko non compressé), échoue au-delà de 50 Ko et si une bibliothèque du back-office (jQuery, DataTables, Select2, SweetAlert2) apparaît dans le JS public ; l'étape tourne en CI après le build. (2) Inscription limitée à `max_inscriptions_par_heure` comptes par heure depuis une même IP (10 par défaut, 0 = sans limite) ; seules les inscriptions réussies comptent. La limite reste large parce que de nombreux abonnés mobiles partagent une même IP publique. (3) Les puces de filtre du fil sont des liens : leur état actif s'annonce par `aria-current="true"` ; `aria-pressed` reste réservé aux vrais boutons (J'aime, Enregistrer).
- **Raison** : Alpine, imposé par la stack, pèse à lui seul environ 44 Ko non compressé, donc un budget de 50 Ko non compressé serait intenable. L'inscription était la seule entrée anonyme sans limite de débit (Fortify n'en prévoit pas). `aria-pressed` sur un lien faisait échouer l'audit Lighthouse `aria-allowed-attr`.

### D-069 · 2026-10-23 · Demandes de prestation visibles, longueurs libres, confirmations du site, source en lien
- **Choix** :
  1. **Demandes de prestation** :
     - nouvel onglet « Demandes » et page `/demandes` (`fil.demandes`, filtre `sous_type=demande`, index `type, sous_type, statut, tri_le, id`), avec un bandeau qui explique comment répondre ou publier son besoin ;
     - encadré « Ils cherchent un prestataire » (3 dernières demandes et leur nombre) sur « Tout » et « Home service » : dans la colonne de droite, et dans le fil après la 2e carte sur mobile ;
     - carte d'une demande : bordure orange, pastille pleine « Demande de prestation » et bouton « Je suis disponible » vers la réponse.
  2. **Titre et description sans contrainte de longueur** :
     - plus de minimum, à la demande d'Alex (le CONTEXTE §11.4 prévoyait 10–100 et 80–3 000 caractères) ;
     - seuls des plafonds techniques restent, sans compteur : 255 caractères pour le titre (colonne élargie de 150 à 255), 15 000 pour la description ;
     - même règle dans l'édition du back-office ; un titre long est coupé à 3 lignes sur la carte.
  3. **Confirmation des suppressions** sur le site public : boîte du site (`<dialog>`, `resources/js/confirmation.js`, attribut `data-confirmer`) au lieu de la boîte du navigateur ; le back-office garde SweetAlert2.
  4. **Origine d'une annonce de la veille** :
     - sur la carte, un lien « Voir l'offre sur Educarrière ↗ » ouvre directement l'annonce source ;
     - sur la page détail, le nom de la source est un lien, et le bouton principal devient « Postuler sur Educarrière ».
- **Raison** : demandes d'Alex. Les demandes, peu nombreuses, se perdaient parmi les offres de services.

### D-070 · 2026-10-23 · Tests de bout en bout
- **Choix** :
  1. **Outillage** : `pestphp/pest-plugin-browser` v4 (la v5 exige PHP 8.4) et Playwright avec Chromium, en dépendances de développement seulement, choisis par Alex. Le plugin demande l'extension PHP `sockets`, activée dans le `php.ini` de Laragon et dans la CI.
  2. **Organisation** : tests dans `tests/Browser`, base `Tests\NavigateurTestCase` qui recharge le vrai build Vite (les autres tests s'en passent). La suite tourne avec le reste (`php artisan test --parallel`), après `npm run build`.
  3. **Service worker** : il n'est pas enregistré quand le navigateur est piloté par un outil de test (`navigator.webdriver`), sinon il intercepte les navigations et bloque les tests. Un visiteur n'est jamais concerné.
  4. **Envois multipart** : le serveur de test du plugin ne lit pas encore ces envois (« @TODO files »). Dans les parcours sans photo, le formulaire de publication est passé en urlencoded par un script ; les photos restent couvertes par les tests Feature.
  5. **Clics** : sur un bouton ambigu, cibler un sélecteur précis (`form[action$="/valider"] button`) plutôt qu'un texte : un clic par texte peut attendre indéfiniment un élément masqué.
- **Raison** : lot 10 du plan, avec les 5 parcours clés à tester dans un vrai navigateur.
