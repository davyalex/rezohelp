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
