<?php

namespace App\Observers;

use App\Models\Categorie;
use App\Models\Commune;
use App\Models\Parametre;
use App\Models\Pays;
use App\Services\CacheFil;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalide la clé « ref:* » d'un référentiel à chaque modification.
 */
class ReferentielObserver
{
    public function __construct(private readonly CacheFil $cache) {}

    public function saved(Model $modele): void
    {
        $this->invalider($modele);
    }

    public function deleted(Model $modele): void
    {
        $this->invalider($modele);
    }

    private function invalider(Model $modele): void
    {
        $nom = match ($modele::class) {
            Pays::class => 'pays',
            Commune::class => 'communes',
            Categorie::class => 'categories',
            Parametre::class => 'parametres',
            default => null,
        };

        if ($nom !== null) {
            $this->cache->oublierReferentiel($nom);
        }
    }
}
