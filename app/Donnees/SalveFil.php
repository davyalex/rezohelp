<?php

namespace App\Donnees;

use App\Models\Publication;
use Illuminate\Support\Collection;

/**
 * Une salve du fil : les publications dans l'ordre et le curseur de la suivante.
 */
final readonly class SalveFil
{
    /**
     * @param  Collection<int, Publication>  $publications
     */
    public function __construct(
        public Collection $publications,
        public ?string $curseurSuivant,
    ) {}
}
