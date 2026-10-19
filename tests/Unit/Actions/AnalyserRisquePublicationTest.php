<?php

use App\Actions\Publication\AnalyserRisquePublication;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use Database\Seeders\ParametresSeeder;

beforeEach(function () {
    $this->seed(ParametresSeeder::class);
    $this->analyser = app(AnalyserRisquePublication::class);
});

$propre = 'Nous recrutons un comptable confirmé pour notre agence du Plateau. Maîtrise de SAGE et de la fiscalité ivoirienne exigée.';

it('ne signale rien pour une offre propre', function () use ($propre) {
    expect($this->analyser->executer(TypePublication::Emploi, 'Comptable confirmé', $propre, TypeContrat::Cdi, 400000))->toBe([]);
});

it('détecte les expressions à risque, accents et apostrophes compris', function (string $texte) use ($propre) {
    expect($this->analyser->executer(TypePublication::Emploi, 'Offre urgente', $propre.' '.$texte))->toContain('expression_a_risque');
})->with([
    'frais de dossier' => ['Des frais de dossier de 10 000 FCFA sont demandés.'],
    'frais d’inscription typographique' => ['Frais d’inscription obligatoires.'],
    'majuscules et accents' => ['WESTERN UNION accepté.'],
    'payer' => ['Il faut payer avant la prise de poste.'],
    'mandat' => ['Envoyez un mandat.'],
]);

it('ne confond pas un mot qui contient une expression à risque', function () use ($propre) {
    // Les expressions sont cherchées en mots entiers : « payerons » ne déclenche pas « payer ».
    expect($this->analyser->executer(TypePublication::Emploi, 'Assistant de direction', $propre.' Nous payerons…'))->not->toContain('expression_a_risque');
});

it('signale un numéro de téléphone dans le texte d’une offre d’emploi', function (string $numero) use ($propre) {
    expect($this->analyser->executer(TypePublication::Emploi, 'Comptable confirmé', $propre.' Appelez le '.$numero))->toContain('telephone_dans_texte');
})->with(['07 08 09 10 11', '0708091011', '+225 07 08 09 10 11', '00225 0708091011', '07.08.09.10.11']);

it('ne prend ni une date ni un salaire pour un numéro', function () use ($propre) {
    expect($this->analyser->executer(TypePublication::Emploi, 'Comptable confirmé', $propre.' Début le 16.10.2026, salaire 350 000 FCFA, 2 500 000 par an.'))
        ->not->toContain('telephone_dans_texte');
});

it('accepte un numéro dans le texte d’une annonce de service', function () use ($propre) {
    expect($this->analyser->executer(TypePublication::Service, 'Électricien disponible', $propre.' Appelez le 07 08 09 10 11'))->toBe([]);
});

it('signale un lien WhatsApp dans le texte d’une offre d’emploi', function () use ($propre) {
    expect($this->analyser->executer(TypePublication::Emploi, 'Comptable confirmé', $propre.' Écrivez sur https://wa.me/2250708091011'))->toContain('whatsapp_dans_texte');
});

it('signale un salaire au-dessus du plafond du type de contrat', function () use ($propre) {
    expect($this->analyser->executer(TypePublication::Emploi, 'Stagiaire comptable', $propre, TypeContrat::Stage, 800000))->toContain('salaire_hors_norme')
        ->and($this->analyser->executer(TypePublication::Emploi, 'Stagiaire comptable', $propre, TypeContrat::Stage, 100000))->not->toContain('salaire_hors_norme');
});

it('suit les plafonds réglés dans les paramètres', function () use ($propre) {
    parametre('plafonds_salaire', json_encode(['cdi' => 200000, 'autre' => 200000]));

    expect($this->analyser->executer(TypePublication::Emploi, 'Comptable confirmé', $propre, TypeContrat::Cdi, 250000))->toContain('salaire_hors_norme');
});

it('signale un texte majoritairement en majuscules', function () {
    $texte = 'URGENT RECRUTEMENT DE COMMERCIAUX POUR NOTRE AGENCE, VENEZ NOMBREUX AVEC VOTRE CV DÈS DEMAIN MATIN.';

    expect($this->analyser->executer(TypePublication::Emploi, 'URGENT RECRUTEMENT', $texte))->toContain('majuscules');
});

it('signale un lien raccourci', function () use ($propre) {
    expect($this->analyser->executer(TypePublication::Formation, 'Bootcamp web', $propre.' Inscription : https://bit.ly/abc123'))->toContain('lien_raccourci');
});

it('suit la liste des mots à risque réglée dans les paramètres', function () use ($propre) {
    parametre('mots_a_risque', json_encode(['caution remboursable']));

    expect($this->analyser->executer(TypePublication::Emploi, 'Comptable confirmé', $propre.' Une caution remboursable est exigée.'))->toContain('expression_a_risque')
        ->and($this->analyser->executer(TypePublication::Emploi, 'Comptable confirmé', $propre.' Frais de dossier.'))->not->toContain('expression_a_risque');
});
