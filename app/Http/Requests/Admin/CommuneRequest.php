<?php

namespace App\Http\Requests\Admin;

use App\Models\Commune;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Commune : le slug (filtre du fil) est dérivé du nom.
 */
class CommuneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('referentiels.gerer') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->string('nom')->toString())]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $commune = $this->route('commune');

        return [
            'pays_code' => ['required', 'string', Rule::exists('pays', 'code')],
            'ville' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'slug' => ['required', Rule::unique('communes', 'slug')->ignore($commune instanceof Commune ? $commune->id : null)],
            'actif' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.unique' => 'Une commune de ce nom existe déjà.'];
    }

    /**
     * @return array{pays_code: string, ville: string, nom: string, slug: string, actif: bool}
     */
    public function donnees(): array
    {
        return [
            'pays_code' => $this->string('pays_code')->toString(),
            'ville' => trim($this->string('ville')->toString()),
            'nom' => trim($this->string('nom')->toString()),
            'slug' => $this->string('slug')->toString(),
            'actif' => $this->boolean('actif'),
        ];
    }
}
