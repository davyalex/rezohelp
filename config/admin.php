<?php

/*
|--------------------------------------------------------------------------
| Back-office : équipe, rôles et permissions (CONTEXTE §13.2, D-066)
|--------------------------------------------------------------------------
| Les comptes de l'équipe sont séparés des membres du site (table administrateurs,
| garde « admin »). Ce fichier fixe :
|  - le super administrateur créé par le seeder (valeurs lues dans .env) ;
|  - le libellé des rôles livrés ;
|  - le catalogue des permissions : clé utilisée par le code (middleware can:, @can),
|    groupe et libellé de l'écran « Rôles et permissions », rôles qui la reçoivent
|    à leur création par le seeder.
| Ajouter une permission ici puis lancer « php artisan db:seed --class=RolesEtPermissionsSeeder ».
| Le super administrateur a toujours toutes les permissions. Ensuite, les permissions des
| rôles se règlent dans l'admin : le seeder ne les écrase pas.
*/

return [

    'super_administrateur' => [
        'nom_utilisateur' => env('ADMIN_NOM_UTILISATEUR', 'superadmin'),
        'nom' => env('ADMIN_NOM', 'Administrateur'),
        'prenoms' => env('ADMIN_PRENOMS'),
        'email' => env('ADMIN_EMAIL'),
        // Vide : un mot de passe de 8 caractères est généré et affiché une seule fois dans la console.
        // Dans tous les cas, il doit être changé à la première connexion.
        'mot_de_passe' => env('ADMIN_MOT_DE_PASSE'),
    ],

    'roles' => [
        'super_administrateur' => 'Super administrateur',
        'administrateur' => 'Administrateur',
        'moderateur' => 'Modérateur',
    ],

    'permissions' => [
        'tableau_de_bord.voir' => ['groupe' => 'Pilotage', 'libelle' => 'Voir le tableau de bord', 'roles' => ['administrateur', 'moderateur']],
        'statistiques.voir' => ['groupe' => 'Pilotage', 'libelle' => 'Voir les statistiques', 'roles' => ['administrateur', 'moderateur']],
        'systeme.voir' => ['groupe' => 'Pilotage', 'libelle' => 'Voir la santé du système', 'roles' => ['administrateur']],

        'publications.moderer' => ['groupe' => 'Contenu', 'libelle' => 'Modérer les publications', 'roles' => ['administrateur', 'moderateur']],
        'publications.gerer' => ['groupe' => 'Contenu', 'libelle' => 'Modifier, dépublier, supprimer les publications', 'roles' => ['administrateur', 'moderateur']],
        'signalements.traiter' => ['groupe' => 'Contenu', 'libelle' => 'Traiter les signalements', 'roles' => ['administrateur', 'moderateur']],
        'commentaires.moderer' => ['groupe' => 'Contenu', 'libelle' => 'Modérer les commentaires', 'roles' => ['administrateur', 'moderateur']],

        'utilisateurs.voir' => ['groupe' => 'Membres du site', 'libelle' => 'Voir les membres et les entreprises', 'roles' => ['administrateur', 'moderateur']],
        'utilisateurs.suspendre' => ['groupe' => 'Membres du site', 'libelle' => 'Suspendre et réactiver un membre', 'roles' => ['administrateur', 'moderateur']],
        'utilisateurs.gerer' => ['groupe' => 'Membres du site', 'libelle' => 'Modifier, réinitialiser, supprimer un membre', 'roles' => ['administrateur']],
        'entreprises.verifier' => ['groupe' => 'Membres du site', 'libelle' => 'Vérifier et fusionner les entreprises', 'roles' => ['administrateur']],

        'veille.gerer' => ['groupe' => 'Veille IA', 'libelle' => 'Gérer les imports et les sources', 'roles' => ['administrateur']],

        'abonnements.gerer' => ['groupe' => 'Monétisation', 'libelle' => 'Gérer plans, produits et abonnements', 'roles' => ['administrateur']],
        'paiements.voir' => ['groupe' => 'Monétisation', 'libelle' => 'Voir les commandes et les revenus', 'roles' => ['administrateur']],

        'referentiels.gerer' => ['groupe' => 'Configuration', 'libelle' => 'Gérer catégories, communes et pays', 'roles' => ['administrateur']],
        'parametres.gerer' => ['groupe' => 'Configuration', 'libelle' => 'Modifier les paramètres et vider le cache', 'roles' => ['administrateur']],

        // Gestion de l'équipe : super administrateur seulement, par défaut.
        'administrateurs.gerer' => ['groupe' => 'Équipe', 'libelle' => 'Gérer les comptes administrateurs', 'roles' => []],
        'roles.gerer' => ['groupe' => 'Équipe', 'libelle' => 'Gérer les rôles et leurs permissions', 'roles' => []],
    ],

];
