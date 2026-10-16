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
