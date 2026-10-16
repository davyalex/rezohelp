<?php

namespace App\Exceptions;

use App\Enums\CleDroit;
use RuntimeException;

/**
 * Une action dépasserait la limite d'un droit (§14.5). Affichée par l'écran « Quota atteint ».
 */
class QuotaAtteintException extends RuntimeException
{
    public function __construct(public readonly CleDroit $cle, public readonly int $limite)
    {
        parent::__construct("Limite atteinte pour {$cle->value} ({$limite}).");
    }
}
