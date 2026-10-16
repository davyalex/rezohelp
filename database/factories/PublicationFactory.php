<?php

namespace Database\Factories;

use App\Enums\SourcePublication;
use App\Enums\SousTypeService;
use App\Enums\StatutPublication;
use App\Enums\TypePublication;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Publication>
 */
class PublicationFactory extends Factory
{
    protected $model = Publication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titre = Str::ucfirst(fake()->words(fake()->numberBetween(3, 6), true));
        $contenu = fake()->paragraphs(3, true);

        return [
            'uuid' => (string) Str::uuid(),
            'type' => TypePublication::Emploi,
            'sous_type' => null,
            'statut' => StatutPublication::Brouillon,
            'titre' => $titre,
            'slug' => Str::slug($titre).'-'.Str::lower(Str::random(6)),
            'contenu' => $contenu,
            'extrait' => Str::limit($contenu, 220),
            'categorie_id' => null,
            'commune_id' => null,
            'auteur_id' => Utilisateur::factory(),
            'entreprise_id' => null,
            'auteur_nom_affiche' => fake()->name(),
            'auteur_avatar_url' => null,
            'source' => SourcePublication::Utilisateur,
            'expire_le' => now()->addDays(30),
            'code_court' => fake()->unique()->regexify('[a-z0-9]{6}'),
        ];
    }

    public function emploi(): static
    {
        return $this->state(fn (): array => ['type' => TypePublication::Emploi, 'sous_type' => null]);
    }

    public function serviceOffre(): static
    {
        return $this->state(fn (): array => ['type' => TypePublication::Service, 'sous_type' => SousTypeService::Offre]);
    }

    public function serviceDemande(): static
    {
        return $this->state(fn (): array => ['type' => TypePublication::Service, 'sous_type' => SousTypeService::Demande]);
    }

    public function formation(): static
    {
        return $this->state(fn (): array => ['type' => TypePublication::Formation, 'sous_type' => null]);
    }

    public function publiee(?\DateTimeInterface $le = null): static
    {
        return $this->state(function () use ($le): array {
            $date = $le ?? now()->subMinutes(fake()->numberBetween(1, 60 * 24 * 10));

            return [
                'statut' => StatutPublication::Publiee,
                'publiee_le' => $date,
                'tri_le' => $date,
            ];
        });
    }

    public function enAttente(): static
    {
        return $this->state(fn (): array => ['statut' => StatutPublication::EnAttente]);
    }

    public function veille(): static
    {
        return $this->state(function (): array {
            $url = 'https://www.emploi.ci/offre/'.fake()->unique()->numerify('######');

            return [
                'auteur_id' => null,
                'source' => SourcePublication::VeilleIa,
                'source_nom' => 'Emploi.ci',
                'source_url' => $url,
                'source_empreinte' => hash('sha256', $url),
                'score_fiabilite' => fake()->numberBetween(40, 95),
            ];
        });
    }
}
