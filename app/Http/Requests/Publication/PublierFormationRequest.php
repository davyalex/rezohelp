<?php

namespace App\Http\Requests\Publication;

use App\Enums\Modalite;
use App\Enums\ModeInscription;
use App\Enums\ParcoursPublication;
use Illuminate\Validation\Rule;

/**
 * « Je propose une formation » (CONTEXTE §11.4). La commune n'est pas exigée pour une formation en ligne.
 */
class PublierFormationRequest extends PublierPublicationRequest
{
    public function parcours(): ParcoursPublication
    {
        return ParcoursPublication::Formation;
    }

    protected function reglesDuParcours(): array
    {
        return array_merge($this->reglesEntreprise('organisateur', ['entreprise', 'nouvelle', 'texte']), [
            'organisme' => ['required_if:organisateur,texte', 'nullable', 'string', 'max:150'],
            'modalite' => ['required', Rule::enum(Modalite::class)],
            'commune_id' => ['required_unless:modalite,en_ligne', 'nullable', 'integer', Rule::exists('communes', 'id')->where('actif', true)],
            'date_debut' => ['nullable', 'date', 'after_or_equal:today'],
            'duree' => ['nullable', 'string', 'max:100'],
            'prix' => ['required', 'integer', 'min:0', 'max:100000000'],
            'certifiante' => ['nullable', 'boolean'],
            'places' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mode_inscription' => ['required', Rule::enum(ModeInscription::class)],
            'contact_inscription' => $this->reglesContact('mode_inscription'),
        ]);
    }
}
