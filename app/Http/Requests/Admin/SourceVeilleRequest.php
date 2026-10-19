<?php

namespace App\Http\Requests\Admin;

use App\Enums\NiveauConfiance;
use App\Models\SourceVeille;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Source de la veille : le domaine est ramené à son hôte, sans « www. ».
 */
class SourceVeilleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('veille.gerer') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $domaine = mb_strtolower(trim($this->string('domaine')->toString()));
        $domaine = (string) (parse_url(str_contains($domaine, '://') ? $domaine : 'https://'.$domaine, PHP_URL_HOST) ?? '');

        $this->merge(['domaine' => $domaine !== '' ? (string) preg_replace('/^www\./', '', $domaine) : null]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $source = $this->route('source');

        return [
            'nom' => ['required', 'string', 'max:100'],
            'domaine' => ['nullable', 'string', 'max:150', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/',
                Rule::unique('sources_veille', 'domaine')->ignore($source instanceof SourceVeille ? $source->id : null)],
            'niveau_confiance' => ['required', Rule::enum(NiveauConfiance::class)],
            'publication_auto' => ['boolean'],
            'actif' => ['boolean'],
        ];
    }

    /**
     * @return array{nom: string, domaine: ?string, niveau_confiance: NiveauConfiance, publication_auto: bool, actif: bool}
     */
    public function donnees(): array
    {
        return [
            'nom' => trim($this->string('nom')->toString()),
            'domaine' => $this->filled('domaine') ? $this->string('domaine')->toString() : null,
            'niveau_confiance' => $this->enum('niveau_confiance', NiveauConfiance::class) ?? NiveauConfiance::Bas,
            'publication_auto' => $this->boolean('publication_auto'),
            'actif' => $this->boolean('actif'),
        ];
    }
}
