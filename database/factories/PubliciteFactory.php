<?php

namespace Database\Factories;

use App\Enums\EmplacementPublicite;
use App\Models\Publicite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Publicite>
 */
class PubliciteFactory extends Factory
{
    protected $model = Publicite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $annonceur = fake()->company();

        return [
            'titre' => 'Campagne '.$annonceur,
            'annonceur' => $annonceur,
            'lien' => 'https://'.fake()->domainName(),
            'texte_alternatif' => 'Publicité '.$annonceur,
            'emplacement' => EmplacementPublicite::MargeGauche,
            'debut_le' => null,
            'fin_le' => null,
            'active' => true,
            'poids' => 1,
        ];
    }

    public function emplacement(EmplacementPublicite $emplacement): static
    {
        return $this->state(['emplacement' => $emplacement]);
    }

    /**
     * Avec une image principale de démonstration (fichier PNG généré).
     */
    public function avecImage(): static
    {
        return $this->afterCreating(function (Publicite $publicite): void {
            $format = $publicite->emplacement->formatPrincipal();
            $chemin = tempnam(sys_get_temp_dir(), 'pub').'.png';
            $image = imagecreatetruecolor($format['largeur'], $format['hauteur']);
            imagefill($image, 0, 0, (int) imagecolorallocate($image, 14, 94, 111));
            imagepng($image, $chemin);
            imagedestroy($image);

            $publicite->addMedia($chemin)->toMediaCollection(Publicite::IMAGE_PRINCIPALE);
            $publicite->touch();
        });
    }
}
