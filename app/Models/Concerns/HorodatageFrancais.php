<?php

namespace App\Models\Concerns;

/**
 * Horodatages en français : cree_le, modifie_le et supprime_le (SoftDeletes).
 *
 * Les constantes CREATED_AT / UPDATED_AT de Model ne peuvent pas être
 * redéfinies par un trait : on surcharge donc leurs accesseurs.
 */
trait HorodatageFrancais
{
    public const string DELETED_AT = 'supprime_le';

    public function getCreatedAtColumn(): ?string
    {
        return 'cree_le';
    }

    public function getUpdatedAtColumn(): ?string
    {
        return 'modifie_le';
    }
}
