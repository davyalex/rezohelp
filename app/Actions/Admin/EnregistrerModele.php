<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Models\Administrateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Création ou modification d'un élément de référentiel du back-office (source de veille, catégorie,
 * commune, pays, produit…), journalisée « {prefixe}.cree » ou « {prefixe}.modifie » avec l'avant / après.
 * L'invalidation des caches reste dans les observers des modèles.
 */
class EnregistrerModele
{
    use JournaliseActivite;

    /**
     * @template T of Model
     *
     * @param  T  $modele
     * @param  array<string, mixed>  $donnees
     * @return T
     */
    public function executer(Model $modele, array $donnees, Administrateur $admin, string $prefixe): Model
    {
        $creation = ! $modele->exists;
        $modele->fill($donnees);

        if (! $creation && ! $modele->isDirty()) {
            return $modele;
        }

        return DB::transaction(function () use ($modele, $admin, $prefixe, $creation): Model {
            $differences = $this->differences($modele);
            $modele->save();
            $this->journaliser($admin, $prefixe.($creation ? '.cree' : '.modifie'), $modele, $creation ? ['apres' => $differences['apres']] : $differences);

            return $modele;
        });
    }
}
