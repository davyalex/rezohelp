<?php

namespace App\Http\Requests;

use App\Models\Commentaire;
use App\Models\Publication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommentaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $publication = $this->route('publication');

        return $publication instanceof Publication && ($this->user()?->can('interagir', $publication) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $publication = $this->route('publication');

        return [
            'contenu' => ['required', 'string', 'min:2', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('commentaires', 'id')
                    ->where('publication_id', $publication instanceof Publication ? $publication->id : 0)
                    ->whereNull('supprime_le'),
            ],
        ];
    }

    public function parent(): ?Commentaire
    {
        return $this->filled('parent_id') ? Commentaire::query()->find($this->integer('parent_id')) : null;
    }
}
