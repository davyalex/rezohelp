<?php

namespace App\Models\Concerns;

/**
 * Tables de journal : seule la date de création (cree_le) est tenue.
 */
trait HorodatageCreation
{
    public function getCreatedAtColumn(): ?string
    {
        return 'cree_le';
    }

    public function getUpdatedAtColumn(): ?string
    {
        return null;
    }
}
