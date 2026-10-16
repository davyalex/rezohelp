<?php

namespace App\Donnees;

use Carbon\CarbonImmutable;

/**
 * Un fichier déposé par la veille IA (Google Sheet ou CSV).
 */
final readonly class FichierVeille
{
    public function __construct(
        public string $id,
        public string $nom,
        public ?CarbonImmutable $modifieLe = null,
        public ?string $url = null,
    ) {}
}
