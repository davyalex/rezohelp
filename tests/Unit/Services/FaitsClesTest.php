<?php

use App\Enums\Modalite;
use App\Enums\TypeContrat;
use App\Enums\UniteTarif;
use App\Models\DetailEmploi;
use App\Models\DetailFormation;
use App\Models\DetailService;
use App\Models\Publication;
use App\Services\FaitsCles;
use App\Support\Montant;

$e = Montant::ESPACE;

it('formate les montants en FCFA', function () use ($e) {
    expect(Montant::fcfa(150000))->toBe("150{$e}000{$e}FCFA")
        ->and(Montant::fcfa(500))->toBe("500{$e}FCFA")
        ->and(Montant::fourchette(150000, 200000))->toBe("150{$e}000 – 200{$e}000{$e}FCFA")
        ->and(Montant::fourchette(150000, null))->toBe("150{$e}000{$e}FCFA")
        ->and(Montant::fourchette(null, null))->toBeNull();
});

it('résume une offre d’emploi : contrat, salaire, postes, date limite', function () use ($e) {
    $publication = Publication::factory()->emploi()->make();
    $detail = new DetailEmploi([
        'type_contrat' => TypeContrat::Cdi, 'salaire_min' => 350000, 'salaire_max' => 400000,
        'salaire_a_negocier' => false, 'nb_postes' => 10, 'date_limite' => '2026-10-30',
    ]);

    expect(app(FaitsCles::class)->calculer($publication, $detail))
        ->toBe(['CDI', "350{$e}000 – 400{$e}000{$e}FCFA", '10 postes', 'Jusqu’au 30 oct.']);
});

it('affiche « Salaire à négocier » et ignore un seul poste', function () {
    $faits = app(FaitsCles::class)->calculer(
        Publication::factory()->emploi()->make(),
        new DetailEmploi(['type_contrat' => TypeContrat::Stage, 'salaire_a_negocier' => true, 'nb_postes' => 1]),
    );

    expect($faits)->toBe(['Stage', 'Salaire à négocier']);
});

it('résume une offre de service et une demande', function () use ($e) {
    $offre = app(FaitsCles::class)->calculer(
        Publication::factory()->serviceOffre()->make(),
        new DetailService(['metier' => 'Électricien', 'tarif_indicatif' => 5000, 'unite_tarif' => UniteTarif::Prestation, 'disponibilite' => '7 j / 7']),
    );
    $devis = app(FaitsCles::class)->calculer(
        Publication::factory()->serviceOffre()->make(),
        new DetailService(['metier' => 'Menuisier', 'unite_tarif' => UniteTarif::Devis]),
    );
    $demande = app(FaitsCles::class)->calculer(
        Publication::factory()->serviceDemande()->make(),
        new DetailService(['metier' => 'Plomberie', 'budget' => 25000]),
    );

    expect($offre)->toBe(['Électricien', "5{$e}000{$e}FCFA la prestation", '7 j / 7'])
        ->and($devis)->toBe(['Menuisier', 'Sur devis'])
        ->and($demande)->toBe(['Plomberie', "Budget : 25{$e}000{$e}FCFA", 'Dès que possible']);
});

it('résume une formation : prix ou gratuit, durée, modalité, certifiante', function () {
    $faits = app(FaitsCles::class)->calculer(
        Publication::factory()->formation()->make(),
        new DetailFormation(['prix' => 0, 'duree' => '3 mois', 'modalite' => Modalite::EnLigne, 'certifiante' => true]),
    );

    expect($faits)->toBe(['Gratuit', '3 mois', 'En ligne', 'Certifiante']);
});

it('copie le type de contrat sur la publication pour le filtre du fil', function () {
    $detail = DetailEmploi::factory()->create(['type_contrat' => TypeContrat::Cdd]);
    $publication = $detail->publication()->firstOrFail()->load('detailEmploi');

    app(FaitsCles::class)->appliquer($publication);

    expect($publication->type_contrat)->toBe(TypeContrat::Cdd)
        ->and($publication->faits_cles)->toContain('CDD');
});
