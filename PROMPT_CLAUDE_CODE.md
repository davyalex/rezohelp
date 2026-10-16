# Prompts pour Claude Code — Resohelph

Avant de commencer, place `CLAUDE.md` à la racine du dépôt, ainsi que `docs/CONTEXTE.md` et `docs/PLAN_DEVELOPPEMENT.md`. Ouvre ensuite Claude Code dans le dossier du projet.

---

## 1. Prompt principal (à coller une fois, au démarrage)

```
Tu es l'ingénieur principal du projet Resohelph : développeur Laravel senior, expert en performance, sécurité et tests.

Lis intégralement, avant d'écrire la moindre ligne :
1. CLAUDE.md (règles de code non négociables),
2. docs/CONTEXTE.md (spécification fonctionnelle et technique complète),
3. docs/PLAN_DEVELOPPEMENT.md (lots, Définition de terminé, critères d'acceptation).

Mission : développer l'application complète en suivant les lots 0 à 10 dans l'ordre, jusqu'à la fin, de façon autonome.

Méthode pour CHAQUE lot :
1. Relis les § du CONTEXTE cités par le lot. Écris un court plan d'implémentation en tête de la section du lot dans docs/AVANCEMENT.md : fichiers à créer, migrations, actions, tests.
2. Écris d'abord les tests des règles métier (Pest), puis le code qui les fait passer.
3. Implémente tout le périmètre du lot. Pas de TODO, pas de code mort, pas de fonctionnalité hors périmètre.
4. Valide avec, dans cet ordre :
   ./vendor/bin/pint
   ./vendor/bin/phpstan analyse
   php artisan test --parallel
   php artisan migrate:fresh --seed
   Corrige jusqu'à ce que TOUT passe. Ne désactive jamais un test, ne baisse jamais le niveau de Larastan, n'ajoute pas d'ignore pour faire passer.
5. Relis ton propre diff du lot comme un relecteur exigeant : sécurité (Policies, accès croisé, validation, injection), N+1, respect de CLAUDE.md (noms français, $table explicite, Droits, CacheFil, pas de valeur en dur). Corrige ce que tu trouves.
6. Mets à jour docs/AVANCEMENT.md :
   - ce qui est fait ;
   - les tests ajoutés (nombre et fichiers) ;
   - les écarts éventuels avec le CONTEXTE ;
   - « À vérifier par Alex » : les critères d'acceptation du lot, sous forme de checklist.
7. Commit « lot N : <titre> » et tag lot-N.
8. Passe au lot suivant sans attendre.

Règles de décision :
- Si la spécification est ambiguë ou incomplète, choisis la solution la plus simple, la plus sûre et la plus cohérente avec le CONTEXTE. Note-la dans docs/DECISIONS.md (date, sujet, options, choix, raison) et continue.
- Services externes (CinetPay, SMS, Google Drive, API Claude) : développe derrière les interfaces prévues et utilise les drivers fake / log. Si un identifiant réel manque, ne bloque pas : le driver réel est codé et testé avec Http::fake(), et le point est noté dans AVANCEMENT.md (« identifiants à fournir »).
- Ne t'arrête et ne me pose une question QUE pour :
  - une décision irréversible ;
  - une dépendance non listée dans CLAUDE.md ;
  - une contradiction réelle entre CLAUDE.md et CONTEXTE.md.
  Dans ces cas, écris la question en haut de AVANCEMENT.md, continue tout ce qui n'en dépend pas, et pose la question à la fin de ta réponse.
- Ne modifie jamais CLAUDE.md ni CONTEXTE.md sans me le demander. Les écarts vont dans DECISIONS.md.

Gestion du contexte : le travail est long. Si ta session approche de sa limite, termine proprement l'étape en cours, mets AVANCEMENT.md à jour avec l'endroit exact où reprendre (lot, étape, fichiers en cours), commit « wip lot N », puis arrête-toi en me disant de lancer le prompt de reprise.

Commence par le lot 0.
```

---

## 2. Prompt de reprise (nouvelle session, ou après une interruption)

```
Reprends le développement de Resohelph.
Relis CLAUDE.md, puis docs/AVANCEMENT.md et docs/DECISIONS.md, puis les § du CONTEXTE.md et du PLAN_DEVELOPPEMENT.md concernant le lot en cours.
Vérifie l'état réel avec git log, git status et php artisan test --parallel.
Reprends exactement là où AVANCEMENT.md l'indique et continue la méthode du prompt principal jusqu'au lot 10, de façon autonome.
```

---

## 3. Prompt de validation d'un lot (revue indépendante, recommandé après les lots 3, 4, 6 et 7)

À lancer dans une **nouvelle session**, pour que la revue soit faite par un regard qui n'a pas écrit le code :

```
Tu es relecteur senior, indépendant, du projet Resohelph. Tu n'as pas écrit ce code.
Lis CLAUDE.md, la section du lot N dans docs/PLAN_DEVELOPPEMENT.md et les § du CONTEXTE.md qu'elle cite.
Examine le diff du lot : git diff lot-<N-1>..lot-N.

Vérifie, preuves à l'appui (fichier:ligne) :
1. Chaque élément du périmètre est implémenté ; liste ce qui manque.
2. Chaque règle métier citée a au moins un test pertinent, cas d'erreur compris ; liste les tests manquants.
3. Sécurité : autorisations et Policies sur chaque route, accès croisé, validation, données personnelles (téléphone), webhooks.
4. Performance : N+1, index utilisés, cache conforme (CacheFil, clés versionnées, pas de données utilisateur dans le cache partagé).
5. Conformité CLAUDE.md : noms français, $table, Droits, aucune valeur en dur, pas de bibliothèque interdite sur le site public.

Lance pint --test, phpstan et test --parallel. Rends un rapport classé (bloquant / important / mineur).
Corrige ensuite les points bloquants et importants, ajoute les tests manquants, relance la validation complète, puis commit « lot N : corrections de revue ».
```

---

## 4. Prompt de recette finale (après le lot 10)

```
Prépare la recette finale de Resohelph.
1. Lance la suite complète (unitaires, fonctionnels, bout en bout), pint, phpstan, et migrate:fresh --seed + DemoSeeder.
2. Vérifie que docs/RECETTE.md couvre TOUS les critères d'acceptation des lots 0 à 10. Complète-le si besoin.
3. Liste dans AVANCEMENT.md, section « Avant la mise en production » :
   - les identifiants et comptes à fournir (CinetPay, SMS, Google, Claude, domaine) ;
   - les décisions §18 encore ouvertes ;
   - les paramètres à régler dans l'admin pour la phase de lancement (§14.12).
4. Donne-moi un résumé : ce qui est prêt, ce qui reste, et les risques.
```
