<?php

use App\Enums\ModeCandidature;
use App\Enums\StatutPublication;
use App\Enums\TypeContrat;
use App\Models\Publication;

beforeEach(fn () => referentielsPublication());

function offreVeille(array $detail = [], array $attributs = []): Publication
{
    $publication = Publication::factory()->emploi()->veille()->publiee()->create(array_replace([
        'contenu' => 'Traitement d’appels clients dans un centre d’appels à Angré.',
        'note_veille' => 'Détail non ouvert ce passage, à vérifier.',
    ], $attributs));
    $publication->detailEmploi()->create(array_replace([
        'type_contrat' => TypeContrat::Cdd,
        'mode_candidature' => ModeCandidature::Lien,
        'contact_candidature' => $publication->source_url,
        'date_limite' => now()->addDays(10)->toDateString(),
    ], $detail));

    return $publication;
}

it('met en tête la date limite, le contrat et le lien pour postuler', function () {
    $publication = offreVeille();

    $this->get(route('publications.show', $publication->slug))
        ->assertOk()
        ->assertSeeInOrder(['Date limite de candidature', now()->addDays(10)->translatedFormat('l j F Y'), '(encore 10 jours)', 'Contrat', 'CDD', 'Postuler sur Emploi.ci', 'Description', 'centre d’appels à Angré'])
        // Origine en lien direct vers l'annonce source.
        ->assertSee('href="'.e($publication->source_url).'" target="_blank"', false);
});

it('n’affiche jamais les notes de la veille ni des informations vides', function () {
    $publication = offreVeille();

    $this->get(route('publications.show', $publication->slug))
        ->assertDontSee('à vérifier')
        ->assertDontSee('Postes')
        ->assertDontSee('Non précisé');
});

it('signale le dernier jour pour postuler', function () {
    $publication = offreVeille(['date_limite' => now()->toDateString()]);

    $this->get(route('publications.show', $publication->slug))->assertSee('(dernier jour)');
});

it('n’invite plus à postuler à une annonce expirée', function () {
    $publication = offreVeille(['date_limite' => now()->subDay()->toDateString()]);
    $publication->forceFill(['statut' => StatutPublication::Expiree])->save();

    $this->get(route('publications.show', $publication->slug))
        ->assertSee('(clôturée)')
        ->assertSee('Cette annonce n’accepte plus de candidatures.')
        ->assertDontSee('Postuler sur Emploi.ci');
});

it('distingue le bouton « Publier une annonce » de la recherche dans l’en-tête', function () {
    $this->get(route('fil.index'))
        ->assertSee('Publier une annonce')
        ->assertSee('placeholder="Emploi, métier, formation…"', false);
});
