<?php

use App\Contracts\SourceVeille;
use App\Enums\StatutImportVeille;
use App\Jobs\ImporterFichierVeille;
use App\Jobs\LancerImportVeilleEnFile;
use App\Models\ImportVeille;
use App\Models\JournalActivite;
use App\Models\Utilisateur;
use App\Services\Veille\FakeSourceVeille;
use Database\Seeders\SourcesVeilleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    referentielsPublication();
    $this->seed(SourcesVeilleSeeder::class);
    $this->admin = Utilisateur::factory()->create();
    $this->admin->assignRole('administrateur');
});

it('lance la recherche de nouveaux fichiers en file, sans attendre Drive dans la requête', function () {
    Queue::fake();

    $this->actingAs($this->admin)->post(route('admin.veille.importer-maintenant'))->assertSessionHas('statut');

    Queue::assertPushed(LancerImportVeilleEnFile::class);
    expect(JournalActivite::query()->where('action', 'veille.import_lance')->exists())->toBeTrue();
});

it('relance un import en remettant ses compteurs à zéro', function () {
    Queue::fake();
    $import = ImportVeille::factory()->create(['nb_lignes' => 3, 'nb_importees' => 1, 'nb_rejetees' => 2]);
    $import->forceFill(['statut' => StatutImportVeille::Erreur, 'erreur' => 'Délai dépassé'])->save();

    $this->actingAs($this->admin)->post(route('admin.veille.imports.relancer', $import))->assertSessionHas('statut');

    $import->refresh();
    expect($import->statut)->toBe(StatutImportVeille::EnCours)
        ->and([$import->nb_lignes, $import->nb_importees, $import->erreur])->toBe([0, 0, null])
        ->and(JournalActivite::query()->where('action', 'veille.import_relance')->exists())->toBeTrue();
    Queue::assertPushed(ImporterFichierVeille::class, fn (ImporterFichierVeille $job): bool => $job->importId === $import->id);
});

it('montre le sort de chaque ligne dans le détail d’un import', function () {
    app()->instance(SourceVeille::class, new FakeSourceVeille(base_path('tests/Fixtures/veille')));
    $this->artisan('veille:importer');
    $import = ImportVeille::query()->sole();

    $this->actingAs($this->admin)->get(route('admin.veille.imports.show', $import))
        ->assertOk()
        ->assertSee('Lignes du fichier')
        ->assertSee('Comptable confirmé')
        ->assertSee('Importée')
        ->assertSee('Relancer l’import');
});

it('réserve la veille à l’administrateur', function () {
    $moderateur = Utilisateur::factory()->create();
    $moderateur->assignRole('moderateur');

    $this->actingAs($moderateur)->post(route('admin.veille.importer-maintenant'))->assertForbidden();
});
