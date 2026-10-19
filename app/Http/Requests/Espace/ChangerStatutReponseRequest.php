<?php

namespace App\Http\Requests\Espace;

use App\Enums\StatutReponse;
use App\Models\Reponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangerStatutReponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reponse = $this->route('reponse');

        return $reponse instanceof Reponse && ($this->user()?->can('update', $reponse) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::enum(StatutReponse::class)->except([StatutReponse::Envoyee])],
        ];
    }

    public function statut(): StatutReponse
    {
        return StatutReponse::from($this->string('statut')->toString());
    }
}
