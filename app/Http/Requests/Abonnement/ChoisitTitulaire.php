<?php

namespace App\Http\Requests\Abonnement;

use App\Models\Entreprise;
use App\Models\Utilisateur;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Titulaire des droits achetés (CONTEXTE §14.2) : le membre lui-même ou une de ses entreprises.
 * Champ facultatif « entreprise_id » ; vide = le membre.
 */
trait ChoisitTitulaire
{
    protected function regleEntreprise(): Exists
    {
        return Rule::exists('entreprises', 'id')
            ->where('proprietaire_id', $this->user()?->getKey())
            ->whereNull('supprime_le');
    }

    public function titulaire(): Utilisateur|Entreprise
    {
        $membre = $this->user();
        assert($membre instanceof Utilisateur);

        $entrepriseId = $this->integer('entreprise_id');

        return $entrepriseId > 0
            ? Entreprise::query()->where('proprietaire_id', $membre->id)->findOrFail($entrepriseId)
            : $membre;
    }

    public function membre(): Utilisateur
    {
        $membre = $this->user();
        assert($membre instanceof Utilisateur);

        return $membre;
    }
}
