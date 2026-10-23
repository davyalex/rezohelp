<?php

namespace Database\Factories;

use App\Models\Administrateur;
use App\Support\PermissionsAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Administrateur>
 */
class AdministrateurFactory extends Factory
{
    protected $model = Administrateur::class;

    protected static ?string $motDePasse = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'nom_utilisateur' => fake()->unique()->userName(),
            'email' => null,
            'mot_de_passe' => static::$motDePasse ??= Hash::make('Admin123'),
            'doit_changer_mot_de_passe' => false,
        ];
    }

    public function superAdministrateur(): static
    {
        return $this->afterCreating(fn (Administrateur $admin) => $admin->assignRole(PermissionsAdmin::ROLE_SUPER));
    }

    public function administrateur(): static
    {
        return $this->afterCreating(fn (Administrateur $admin) => $admin->assignRole(PermissionsAdmin::ROLE_ADMIN));
    }

    public function moderateur(): static
    {
        return $this->afterCreating(fn (Administrateur $admin) => $admin->assignRole(PermissionsAdmin::ROLE_MODERATEUR));
    }

    public function suspendu(): static
    {
        return $this->state(['suspendu_le' => now()]);
    }

    public function motDePasseAChanger(): static
    {
        return $this->state(['doit_changer_mot_de_passe' => true]);
    }
}
