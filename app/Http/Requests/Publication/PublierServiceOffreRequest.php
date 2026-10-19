<?php

namespace App\Http\Requests\Publication;

use App\Enums\ParcoursPublication;
use App\Enums\UniteTarif;
use Illuminate\Validation\Rule;

/**
 * « Je propose mes services » (CONTEXTE §11.4). La catégorie est le métier (sous-catégorie).
 */
class PublierServiceOffreRequest extends PublierPublicationRequest
{
    public function parcours(): ParcoursPublication
    {
        return ParcoursPublication::ServiceOffre;
    }

    protected function reglesDuParcours(): array
    {
        $pays = $this->membre()->pays_code;

        return [
            'categorie_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('type', 'service')->whereNotNull('parent_id')->where('actif', true)],
            'zones_intervention' => ['nullable', 'array', 'max:13'],
            'zones_intervention.*' => ['integer', Rule::exists('communes', 'id')->where('actif', true)],
            'tarif_indicatif' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'unite_tarif' => ['required', Rule::enum(UniteTarif::class)],
            'disponibilite' => ['nullable', 'string', 'max:150'],
            'telephone_contact' => ['required', 'string', 'max:30', "phone:{$pays},INTERNATIONAL"],
            'afficher_telephone' => ['nullable', 'boolean'],
            'whatsapp_meme_numero' => ['nullable', 'boolean'],
            'whatsapp' => ['nullable', 'string', 'max:30', "phone:{$pays},INTERNATIONAL"],
        ];
    }
}
