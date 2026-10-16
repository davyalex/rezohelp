<?php

namespace App\Actions\Publication;

use App\Enums\SousTypeService;
use App\Enums\TypePublication;
use App\Models\Publication;
use App\Models\Utilisateur;

/**
 * Le rôle se déduit de la première publication de chaque type (CONTEXTE §11.1) :
 * emploi → recruteur, service proposé → artisan, formation → organisme.
 */
class AttribuerRoleAuteur
{
    public function executer(Utilisateur $auteur, Publication $publication): void
    {
        $role = match (true) {
            $publication->type === TypePublication::Emploi => 'recruteur',
            $publication->type === TypePublication::Formation => 'organisme',
            $publication->sous_type === SousTypeService::Offre => 'artisan',
            default => null,
        };

        if ($role !== null && ! $auteur->hasRole($role)) {
            $auteur->assignRole($role);
        }
    }
}
