<?php

namespace App\Http\Requests\Publication;

use App\Enums\ModeCandidature;
use App\Enums\ParcoursPublication;
use App\Enums\TypeContrat;
use Illuminate\Validation\Rule;

/**
 * « Je recrute » (CONTEXTE §11.4).
 */
class PublierEmploiRequest extends PublierPublicationRequest
{
    public function parcours(): ParcoursPublication
    {
        return ParcoursPublication::Emploi;
    }

    protected function reglesDuParcours(): array
    {
        return array_merge($this->reglesEntreprise('employeur', ['entreprise', 'nouvelle', 'particulier']), [
            'type_contrat' => ['required', Rule::enum(TypeContrat::class)],
            'nb_postes' => ['nullable', 'integer', 'min:1', 'max:500'],
            'salaire_a_negocier' => ['nullable', 'boolean'],
            'salaire_min' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'salaire_max' => ['nullable', 'integer', 'min:0', 'max:100000000', 'gte:salaire_min'],
            'experience_requise' => ['nullable', 'string', 'max:100'],
            'niveau_etude' => ['nullable', 'string', 'max:100'],
            'date_limite' => ['nullable', 'date', 'after_or_equal:today'],
            'mode_candidature' => ['required', Rule::enum(ModeCandidature::class)],
            'contact_candidature' => $this->reglesContact('mode_candidature'),
        ]);
    }
}
