<?php

namespace App\Notifications\Canaux;

use App\Enums\MotifSms;

/**
 * Contenu d'une notification envoyée par SMS.
 */
final readonly class MessageSms
{
    public function __construct(
        public string $texte,
        public MotifSms $motif,
    ) {}
}
