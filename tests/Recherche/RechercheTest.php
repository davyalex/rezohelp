<?php

use App\Jobs\EnregistrerRecherche;
use App\Models\Publication;
use App\Models\Recherche;
use Database\Seeders\ParametresSeeder;
use Illuminate\Support\Facades\Queue;

/*
 * Recherche FULLTEXT : InnoDB n'indexe qu'au COMMIT, ces tests tournent donc sans
 * transaction (DatabaseTruncation, voir tests/Pest.php).
 */

beforeEach(fn () => $this->seed(ParametresSeeder::class));

it('trouve les publications par mot du titre ou du contenu, préfixes et accents compris', function () {
    Publication::factory()->publiee()->create(['titre' => 'Plombier disponible à Cocody', 'contenu' => 'Dépannage et installation sanitaire.']);
    Publication::factory()->publiee()->create(['titre' => 'Comptable confirmé', 'contenu' => 'Maîtrise de la comptabilité SYSCOHADA.']);
    Publication::factory()->enAttente()->create(['titre' => 'Plombier en attente', 'contenu' => 'Ne doit pas apparaître.']);

    $this->get('/recherche?q=plomb')->assertOk()->assertSee('Plombier disponible à Cocody')->assertDontSee('Comptable confirmé')->assertDontSee('Plombier en attente');
    $this->get('/recherche?q=sanitaire')->assertSee('Plombier disponible à Cocody');
    $this->get('/recherche?q=comptabilite')->assertSee('Comptable confirmé');
    $this->get('/recherche?q=PLOMBIER%20cocody')->assertSee('Plombier disponible à Cocody');
});

it('exige tous les mots de la recherche', function () {
    Publication::factory()->publiee()->create(['titre' => 'Chauffeur à Yopougon', 'contenu' => 'Permis B exigé.']);
    Publication::factory()->publiee()->create(['titre' => 'Chauffeur à Cocody', 'contenu' => 'Permis C.']);

    $html = (string) $this->get('/recherche?q=chauffeur%20yopougon')->getContent();

    expect($html)->toContain('Chauffeur à Yopougon')->not->toContain('Chauffeur à Cocody');
});

it('se replie sur le titre pour un mot trop court', function () {
    Publication::factory()->publiee()->create(['titre' => 'Agent BTP', 'contenu' => 'Chantier.']);

    $this->get('/recherche?q=BT')->assertSee('Agent BTP');
});

it('journalise la recherche en file d’attente, avec le nombre de résultats', function () {
    Publication::factory()->count(2)->publiee()->create(['titre' => 'Nounou à domicile']);

    $this->get('/recherche?q=Nounou')->assertOk();

    $recherche = Recherche::query()->sole();
    expect($recherche->terme)->toBe('nounou')
        ->and($recherche->nb_resultats)->toBe(2);
});

it('passe par la file d’attente sans bloquer la page', function () {
    Queue::fake();

    $this->get('/recherche?q=electricien')->assertOk();

    Queue::assertPushed(EnregistrerRecherche::class, fn (EnregistrerRecherche $job): bool => $job->filtres->recherche === 'electricien');
    expect(Recherche::query()->count())->toBe(0);
});

it('ne journalise pas une simple visite de la page de recherche', function () {
    Queue::fake();

    $this->get('/recherche')->assertOk()->assertSee('Que cherchez-vous ?');

    Queue::assertNothingPushed();
});
