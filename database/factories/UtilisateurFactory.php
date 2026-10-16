<?php

namespace Database\Factories;

use App\Enums\ConfianceUtilisateur;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Utilisateur>
 */
class UtilisateurFactory extends Factory
{
    protected $model = Utilisateur::class;

    protected static ?string $motDePasse = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'pays_code' => fn (): string => PaysFactory::coteDivoire()->code,
            // Mobile ivoirien valide (10 chiffres depuis 2021), au format E.164
            'telephone' => '+22507'.fake()->unique()->numerify('########'),
            'telephone_verifie_le' => now(),
            'email' => null,
            'mot_de_passe' => static::$motDePasse ??= Hash::make('mot-de-passe-test'),
            'confiance' => ConfianceUtilisateur::Nouveau,
            'remember_token' => Str::random(10),
        ];
    }

    public function nonVerifie(): static
    {
        return $this->state(fn (): array => ['telephone_verifie_le' => null]);
    }

    public function fiable(): static
    {
        return $this->state(fn (): array => ['confiance' => ConfianceUtilisateur::Fiable]);
    }

    public function restreint(): static
    {
        return $this->state(fn (): array => ['confiance' => ConfianceUtilisateur::Restreint]);
    }

    public function suspendu(): static
    {
        return $this->state(fn (): array => [
            'suspendu_le' => now(),
            'motif_suspension' => 'Annonces frauduleuses',
        ]);
    }
}
