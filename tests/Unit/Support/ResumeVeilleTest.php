<?php

use App\Support\ResumeVeille;
use Carbon\CarbonImmutable;

function lireResume(string $resume, string $conditions = '', string $date = '19/10/2026'): ResumeVeille
{
    return ResumeVeille::lire($resume, $conditions, $date, CarbonImmutable::create(2026, 10, 19));
}

it('lit la date de clôture sous ses différentes formes, l’année venant de la publication', function (string $resume, string $attendue) {
    expect(lireResume($resume)->dateLimite?->toDateString())->toBe($attendue);
})->with([
    ['Poste de comptable. Clôture le 30/10.', '2026-10-30'],
    ['Traitement d’appels clients à Angré. Clôture 01/11.', '2026-11-01'],
    ['Vente de quincaillerie. Jusqu’au 25/10.', '2026-10-25'],
    ['Graphiste. Candidatures ouvertes jusqu\'au 30/11.', '2026-11-30'],
    ['Gestion financière. Candidature par e-mail avant le 02/11.', '2026-11-02'],
    ['Traiteur pour une mission. Clôture immédiate (21/10).', '2026-10-21'],
    ['Développeur web. Clôture le 30-31/10.', '2026-10-31'],
    ['Chauffeur. Expire aujourd’hui.', '2026-10-19'],
    ['Agent de sûreté. Places limitées, inscriptions jusqu\'au 02/11.', '2026-11-02'],
    ['Comptable. Clôture le 15/11/2026.', '2026-11-15'],
]);

it('passe à l’année suivante pour une clôture en janvier publiée en décembre', function () {
    expect(lireResume('Caissier. Clôture le 15/01.', date: '20/12/2026')->dateLimite?->toDateString())->toBe('2027-01-15');
});

it('ignore une date incohérente, antérieure à la publication ou trop lointaine', function (string $resume) {
    expect(lireResume($resume)->dateLimite)->toBeNull();
})->with([
    'signalée incohérente' => ['Assistant. Date limite affichée (14/09) incohérente : à vérifier.'],
    'antérieure' => ['Assistant. Clôture le 14/09.'],
    'trop lointaine' => ['Assistant. Clôture le 14/09/2027.'],
    'sans date' => ['Assistant. Clôture imminente.'],
    'date qui n’est pas une clôture' => ['Mission ponctuelle pour un événement le 22/10.'],
]);

it('lit la date dans « Contrat/Durée/Prix » si le résumé n’en a pas', function () {
    expect(lireResume('Vendeur en boutique.', 'CDD ; jusqu’au 05/11')->dateLimite?->toDateString())->toBe('2026-11-05');
});

it('retire du texte public les notes de la veille et la phrase de clôture, et garde les notes', function () {
    $lecture = lireResume('Traitement d’appels clients dans un centre d’appels à Angré. Clôture 01/11. Détail non ouvert ce passage, à vérifier.');

    expect($lecture->texte)->toBe('Traitement d’appels clients dans un centre d’appels à Angré.')
        ->and($lecture->notes)->toBe('Détail non ouvert ce passage, à vérifier.')
        ->and($lecture->dateLimite?->toDateString())->toBe('2026-11-01');
});

it('ne retire qu’une proposition ou une parenthèse de note, et garde une phrase utile qui cite la date', function () {
    $lecture = lireResume('Promotion terrain de l’application ; la fiche affiche le logo d’une autre société, à vérifier. Vente de quincaillerie (aussi sur Projobivoire). Candidature par formulaire avant le 30/10.');

    expect($lecture->texte)->toBe('Promotion terrain de l’application. Vente de quincaillerie. Candidature par formulaire avant le 30/10.')
        ->and($lecture->notes)->toContain('La fiche affiche le logo d’une autre société, à vérifier.')
        ->and($lecture->notes)->toContain('(aussi sur Projobivoire)');
});

it('laisse un texte vide quand le résumé n’est qu’une note', function () {
    $lecture = lireResume('Détail non consulté (accès Emploi.ci limité pendant ce passage) : repéré dans la liste du site, à vérifier.');

    expect($lecture->texte)->toBe('')
        ->and($lecture->dateLimite)->toBeNull();
});
