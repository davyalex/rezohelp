<?php

namespace App\Http\Requests\Publication;

use App\Enums\ParcoursPublication;
use Illuminate\Validation\Rule;

/**
 * « Je cherche un prestataire » (CONTEXTE §11.4). Aucun numéro n'est saisi :
 * le demandeur reçoit les coordonnées des artisans qui répondent.
 */
class PublierServiceDemandeRequest extends PublierPublicationRequest
{
    public function parcours(): ParcoursPublication
    {
        return ParcoursPublication::ServiceDemande;
    }

    protected function reglesDuParcours(): array
    {
        return [
            'categorie_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('type', 'service')->whereNotNull('parent_id')->where('actif', true)],
            'date_souhaitee_choix' => ['required', Rule::in(['aujourdhui', 'semaine', 'date', 'flexible'])],
            'date_souhaitee' => ['required_if:date_souhaitee_choix,date', 'nullable', 'date', 'after_or_equal:today'],
            'budget' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
