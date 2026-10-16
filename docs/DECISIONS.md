# Resohelph — Décisions prises en cours de développement

> Format : date · sujet · options · choix · raison. Les écarts avec `docs/CONTEXTE.md` sont notés ici, jamais dans le CONTEXTE.

---

### D-001 · 2026-10-16 · Emplacement des documents de référence
- **Options** : laisser `CONTEXTE.md` et `PLAN_DEVELOPPEMENT.md` à la racine ; les déplacer dans `docs/`.
- **Choix** : déplacés dans `docs/`, contenu inchangé.
- **Raison** : `CLAUDE.md` et `PROMPT_CLAUDE_CODE.md` les référencent sous `docs/`.

### D-002 · 2026-10-16 · Base de données des tests
- **Options** : SQLite en mémoire (squelette Laravel) ; MySQL 8.
- **Choix** : MySQL 8 (`resohelph_test`, une base par processus avec `--parallel`).
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
- **Choix** : quand `quotas_actifs = false`, deviennent illimitées : `alertes.max`, `emploi.offres_mois`, `service.offres_actives`, `formation.offres_actives`, `publication.photos_max`, `reponses.demandes_mois`. Restent soumises aux plans : les canaux (`canal_sms`, `canal_whatsapp`), `alertes.sms_par_jour`, `publication.remontees_mois`, `publication.duree_max_jours`, et les booléens.
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
