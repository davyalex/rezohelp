<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Publication\PublierPublicationRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modification d'une publication par l'équipe : mêmes limites que le formulaire public (§11.4).
 */
class PublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publications.gerer') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:'.PublierPublicationRequest::TITRE_MAX],
            'contenu' => ['required', 'string', 'max:'.PublierPublicationRequest::CONTENU_MAX],
            'categorie_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'commune_id' => ['nullable', 'integer', Rule::exists('communes', 'id')],
            'expire_le' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{titre: string, contenu: string, categorie_id: int, commune_id: ?int, expire_le: ?string}
     */
    public function donnees(): array
    {
        return [
            'titre' => $this->string('titre')->toString(),
            'contenu' => $this->string('contenu')->toString(),
            'categorie_id' => $this->integer('categorie_id'),
            'commune_id' => $this->filled('commune_id') ? $this->integer('commune_id') : null,
            'expire_le' => $this->filled('expire_le') ? $this->string('expire_le')->toString() : null,
        ];
    }
}
