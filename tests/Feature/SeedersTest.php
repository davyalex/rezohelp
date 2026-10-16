<?php

use App\Enums\CibleOffre;
use App\Enums\CleDroit;
use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Parametre;
use App\Models\Pays;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use App\Models\SourceVeille;
use App\Models\Utilisateur;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesEtPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config([
        'resohelph.admin.telephone' => '07 08 09 10 11',
        'resohelph.admin.pays_code' => 'CI',
        'resohelph.admin.mot_de_passe' => 'Admin-Resohelph-2026',
    ]);
});

it('crée les référentiels de base', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Pays::query()->pluck('code')->all())->toEqualCanonicalizing(['CI', 'SN', 'ML', 'BF', 'BJ', 'TG', 'GN', 'NE', 'CM', 'FR'])
        ->and(Pays::query()->actifs()->first()?->code)->toBe('CI')
        ->and(Commune::query()->count())->toBe(13)
        ->and(Commune::query()->where('nom', 'Port-Bouët')->exists())->toBeTrue()
        ->and(Categorie::query()->where('type', 'emploi')->count())->toBeGreaterThan(5)
        ->and(Categorie::query()->where('type', 'formation')->count())->toBeGreaterThan(5)
        ->and(Categorie::query()->where('type', 'service')->whereNotNull('parent_id')->where('nom', 'Plomberie')->exists())->toBeTrue()
        ->and(SourceVeille::query()->whereNull('domaine')->value('nom'))->toBe('Inconnue');
});

it('crée les rôles et les permissions du §13.2', function () {
    $this->seed(DatabaseSeeder::class);

    $moderateur = Role::findByName('moderateur', 'web');

    expect(Permission::query()->count())->toBe(count(RolesEtPermissionsSeeder::PERMISSIONS))
        ->and(Role::findByName('administrateur', 'web')->permissions)->toHaveCount(count(RolesEtPermissionsSeeder::PERMISSIONS))
        ->and($moderateur->hasPermissionTo('publications.moderer'))->toBeTrue()
        ->and($moderateur->hasPermissionTo('utilisateurs.gerer'))->toBeFalse()
        ->and($moderateur->hasPermissionTo('abonnements.gerer'))->toBeFalse()
        ->and(Role::query()->pluck('name')->all())->toContain('membre', 'recruteur', 'artisan', 'organisme');
});

it('crée tous les paramètres cités aux §11, §13 et §14', function () {
    $this->seed(DatabaseSeeder::class);

    foreach ([
        'seuil_publication_auto', 'jours_expiration_defaut', 'seuil_masquage_signalements', 'max_publications_par_jour',
        'plafonds_salaire', 'mots_a_risque', 'quotas_actifs', 'jours_grace', 'taux_tva', 'frequence_sponsorise',
        'mentions_legales_recu', 'texte_bandeau_prevention', 'contenu_cgu', 'contenu_confidentialite',
    ] as $cle) {
        expect(Parametre::query()->whereKey($cle)->exists())->toBeTrue("Paramètre manquant : {$cle}");
    }

    expect(Parametre::booleen('quotas_actifs'))->toBeFalse()
        ->and(Parametre::entier('seuil_publication_auto'))->toBe(70)
        ->and(Parametre::liste('plafonds_salaire'))->toHaveKey('cdi');
});

it('crée la grille des plans du §14.4 réglée pour le lancement', function () {
    $this->seed(DatabaseSeeder::class);

    $parDefaut = Plan::query()->parDefaut()->get();

    expect(Plan::query()->count())->toBe(9)
        ->and($parDefaut)->toHaveCount(4)
        ->and($parDefaut->pluck('cible')->all())->toEqualCanonicalizing(CibleOffre::cases())
        ->and($parDefaut->every(fn (Plan $plan): bool => $plan->prix_fcfa === 0))->toBeTrue()
        ->and(Plan::query()->where('code', 'alertes_sms_7j')->value('actif'))->toBeTrue()
        ->and(Plan::query()->where('code', 'artisan_pro_30j')->value('actif'))->toBeFalse()
        ->and(Produit::query()->where('code', 'remontee')->value('actif'))->toBeTrue()
        ->and(Produit::query()->where('code', 'sponsorise_fil_7j')->value('actif'))->toBeFalse();

    $entreprise = Plan::query()->where('code', 'entreprise_30j')->with('limites')->firstOrFail();

    expect($entreprise->limites->firstWhere('cle', CleDroit::EmploiOffresMois)?->valeur)->toBe('-1')
        ->and($entreprise->prix_fcfa)->toBe(50000)
        ->and($entreprise->duree_essai_jours)->toBe(14);
});

it('crée le compte administrateur avec un téléphone normalisé', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = Utilisateur::query()->where('telephone', '+2250708091011')->firstOrFail();

    expect($admin->hasRole('administrateur'))->toBeTrue()
        ->and($admin->telephone_verifie_le)->not->toBeNull()
        ->and(Hash::check('Admin-Resohelph-2026', $admin->mot_de_passe))->toBeTrue();
});

it('ne crée pas d’administrateur sans identifiants dans .env', function () {
    config(['resohelph.admin.telephone' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(Utilisateur::query()->count())->toBe(0);
});

it('peut être relancé sans doublon ni perte des réglages', function () {
    $this->seed(DatabaseSeeder::class);
    Parametre::query()->whereKey('seuil_publication_auto')->update(['valeur' => '80']);
    Plan::query()->where('code', 'alertes_sms_30j')->update(['prix_fcfa' => 1500]);

    $this->seed(DatabaseSeeder::class);

    expect(Commune::query()->count())->toBe(13)
        ->and(Plan::query()->count())->toBe(9)
        ->and(Utilisateur::query()->count())->toBe(1)
        ->and(Parametre::query()->whereKey('seuil_publication_auto')->value('valeur'))->toBe('80')
        ->and(Plan::query()->where('code', 'alertes_sms_30j')->value('prix_fcfa'))->toBe(1500);
});

it('génère des données de démonstration cohérentes', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    $publications = Publication::query()->get();

    expect($publications)->toHaveCount(60)
        ->and($publications->pluck('type')->unique())->toHaveCount(3)
        ->and($publications->pluck('commune_id')->filter()->unique()->count())->toBeGreaterThan(5)
        ->and(Utilisateur::query()->count())->toBe(21)
        ->and(Publication::query()->publiees()->whereNull('tri_le')->count())->toBe(0)
        ->and(Publication::query()->where('type', 'emploi')->doesntHave('detailEmploi')->count())->toBe(0)
        ->and(Publication::query()->where('type', 'service')->doesntHave('detailService')->count())->toBe(0)
        ->and(Publication::query()->where('type', 'formation')->doesntHave('detailFormation')->count())->toBe(0);
});
