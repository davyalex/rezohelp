<?php

namespace App\Http\Requests\Admin;

use App\Models\Pays;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pays : code ISO 3166-1 alpha-2 (libphonenumber le reconnaît pour valider les numéros).
 */
class PaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('referentiels.gerer') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => mb_strtoupper(trim($this->string('code')->toString()))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pays = $this->route('pays');

        return [
            'code' => ['required', 'string', 'size:2', 'alpha', Rule::unique('pays', 'code')->ignore($pays instanceof Pays ? $pays->id : null)],
            'nom' => ['required', 'string', 'max:80'],
            'indicatif' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'actif' => ['boolean'],
        ];
    }

    /**
     * @return array{code: string, nom: string, indicatif: string, actif: bool}
     */
    public function donnees(): array
    {
        return [
            'code' => $this->string('code')->toString(),
            'nom' => trim($this->string('nom')->toString()),
            'indicatif' => $this->string('indicatif')->toString(),
            'actif' => $this->boolean('actif'),
        ];
    }
}
