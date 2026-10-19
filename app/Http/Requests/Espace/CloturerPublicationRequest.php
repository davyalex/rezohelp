<?php

namespace App\Http\Requests\Espace;

use App\Models\Publication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloturerPublicationRequest extends FormRequest
{
    /**
     * Motifs proposés à l'écran (CONTEXTE §11.7) ; « Autre » laisse saisir le sien.
     */
    public const array MOTIFS = ['Poste pourvu', 'Plus disponible', 'Complet', 'Autre'];

    public function authorize(): bool
    {
        $publication = $this->route('publication');

        return $publication instanceof Publication && ($this->user()?->can('cloturer', $publication) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motif_cloture' => ['required', 'string', 'max:150'],
        ];
    }
}
