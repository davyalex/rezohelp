<?php

use App\Donnees\DemandeEnrichissement;
use App\Services\IA\FakeClientIA;

it('donne un score élevé à une offre sans signal de risque', function () {
    $resultat = (new FakeClientIA)->enrichir(new DemandeEnrichissement(
        type: 'emploi',
        titre: 'Comptable confirmé',
        contenu: 'Poste de comptable au Plateau, 5 ans d’expérience.',
        categoriesPossibles: ['Finance'],
    ));

    expect($resultat->scoreFiabilite)->toBe(85)
        ->and($resultat->signauxRisque)->toBe([])
        ->and($resultat->categorie)->toBe('Finance')
        ->and($resultat->modele)->toBe('fake');
});

it('détecte les signaux d’arnaque et baisse le score', function () {
    $resultat = (new FakeClientIA)->enrichir(new DemandeEnrichissement(
        type: 'emploi',
        titre: 'Recrutement urgent',
        contenu: 'Frais de dossier 10 000 FCFA à envoyer par Western Union.',
    ));

    expect($resultat->signauxRisque)->toBe(['frais_demandes', 'transfert_argent'])
        ->and($resultat->scoreFiabilite)->toBe(25);
});

it('est déterministe et limite le texte de la carte', function () {
    $demande = new DemandeEnrichissement(type: 'service', titre: 'Plombier', contenu: str_repeat('Plomberie générale. ', 40));

    $premier = (new FakeClientIA)->enrichir($demande);
    $second = (new FakeClientIA)->enrichir($demande);

    expect($premier)->toEqual($second)
        ->and(mb_strlen($premier->texteCarte))->toBeLessThanOrEqual(223);
});
