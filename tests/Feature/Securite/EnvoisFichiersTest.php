<?php

use App\Http\Requests\Publication\PublierPublicationRequest;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Lot 9 : envois de fichiers (CONTEXTE §11.4, §16) : images JPG, PNG ou WebP seulement,
 * taille bornée, jamais de SVG (script possible) ni de fichier déguisé en image.
 */
beforeEach(function () {
    referentielsPublication();
    Storage::fake('public');
    $this->membre = Utilisateur::factory()->create();
});

/**
 * Fichier réellement écrit sur le disque, envoyé sous un nom choisi : comme un envoi du navigateur.
 */
function fichierReel(string $nom, string $contenu): UploadedFile
{
    $chemin = tempnam(sys_get_temp_dir(), 'envoi');
    file_put_contents($chemin, $contenu);

    return new UploadedFile($chemin, $nom, null, null, true);
}

it('refuse une photo de publication trop lourde, un SVG ou un fichier déguisé en image', function (Closure $fichier) {
    $this->actingAs($this->membre)
        ->post('/publier/service-offre', donneesServiceOffre(['photos' => [$fichier()]]))
        ->assertSessionHasErrors('photos.0');

    expect(Publication::query()->count())->toBe(0);
})->with([
    'plus de 5 Mo' => [fn () => UploadedFile::fake()->image('lourde.jpg', 1200, 900)->size(PublierPublicationRequest::TAILLE_PHOTO_KO + 1)],
    'SVG' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    // Vrai fichier (et non un faux de test, dont le type vient du nom) : le type est lu dans le contenu.
    'PHP renommé en .jpg' => [fn () => fichierReel('photo.jpg', '<?php echo "pirate"; ?>')],
    'GIF' => [fn () => UploadedFile::fake()->image('anime.gif', 200, 200)],
]);

it('accepte une photo JPG, PNG ou WebP dans la limite de taille', function (string $nom) {
    $this->actingAs($this->membre)
        ->post('/publier/service-offre', donneesServiceOffre(['photos' => [UploadedFile::fake()->image($nom, 800, 600)->size(400)]]))
        ->assertSessionHasNoErrors();

    expect(Publication::query()->sole()->getMedia('photos'))->toHaveCount(1);
})->with(['chantier.jpg', 'chantier.png', 'chantier.webp']);

it('refuse un logo d’entreprise SVG ou de plus de 2 Mo', function (Closure $fichier) {
    $this->actingAs($this->membre)
        ->post('/publier/emploi', donneesEmploi([
            'employeur' => 'nouvelle',
            'entreprise_nom' => 'Société Test',
            'entreprise_logo' => $fichier(),
        ]))
        ->assertSessionHasErrors('entreprise_logo');
})->with([
    'SVG' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')],
    'plus de 2 Mo' => [fn () => UploadedFile::fake()->image('logo.png', 400, 400)->size(2049)],
]);

it('refuse une photo de profil SVG ou de plus de 2 Mo', function (Closure $fichier) {
    $this->actingAs($this->membre)
        ->put(route('espace.profil.update'), ['prenoms' => 'Awa', 'nom' => 'Koné', 'photo' => $fichier()])
        ->assertSessionHasErrors('photo');

    expect($this->membre->refresh()->getMedia('photo'))->toHaveCount(0);
})->with([
    'SVG' => [fn () => UploadedFile::fake()->createWithContent('moi.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    'plus de 2 Mo' => [fn () => UploadedFile::fake()->image('moi.jpg', 400, 400)->size(2049)],
]);
