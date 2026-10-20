<?php

namespace App\Services;

use App\Enums\ModeCandidature;
use App\Models\Publication;

/**
 * Lien de contact externe d'une offre (candidature ou inscription hors Rezohelp) :
 * mailto:, tel: ou adresse web en http(s) uniquement.
 */
class LienContact
{
    public function pour(Publication $publication): ?string
    {
        $emploi = $publication->detailEmploi;
        $formation = $publication->detailFormation;

        [$mode, $contact] = match (true) {
            $emploi !== null => [$emploi->mode_candidature->value, $emploi->contact_candidature],
            $formation !== null => [$formation->mode_inscription->value, $formation->contact_inscription],
            default => [null, null],
        };

        if (! is_string($contact) || $contact === '') {
            return null;
        }

        return match ($mode) {
            ModeCandidature::Email->value => 'mailto:'.$contact.'?subject='.rawurlencode('Candidature : '.$publication->titre),
            // Les modes « telephone » et « lien » ont la même valeur pour une candidature et une inscription.
            ModeCandidature::Telephone->value => 'tel:'.$contact,
            ModeCandidature::Lien->value => preg_match('#^https?://#i', $contact) === 1 ? $contact : null,
            default => null,
        };
    }
}
