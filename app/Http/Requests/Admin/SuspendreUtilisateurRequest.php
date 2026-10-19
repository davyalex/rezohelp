<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Suspension d'un compte : motif obligatoire, date de fin facultative (vide = définitive).
 */
class SuspendreUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('utilisateurs.suspendre') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motif_suspension' => ['required', 'string', 'max:255'],
            'suspendu_jusqu_au' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['motif_suspension' => 'motif', 'suspendu_jusqu_au' => 'date de fin'];
    }

    public function jusquau(): ?Carbon
    {
        return $this->filled('suspendu_jusqu_au') ? Carbon::parse($this->string('suspendu_jusqu_au')->toString())->endOfDay() : null;
    }
}
