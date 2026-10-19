<?php

namespace App\Http\Requests\Admin;

use App\Enums\TypePublication;
use App\Models\Categorie;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Catégorie : deux niveaux (catégorie → sous-catégorie / métier), du même type que son parent.
 */
class CategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('referentiels.gerer') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->string('type')->toString().' '.$this->string('nom')->toString())]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categorie = $this->route('categorie');
        $id = $categorie instanceof Categorie ? $categorie->id : null;

        return [
            'type' => ['required', Rule::enum(TypePublication::class)],
            'nom' => ['required', 'string', 'max:80'],
            'slug' => ['required', Rule::unique('categories', 'slug')->ignore($id)],
            'icone' => ['nullable', 'string', 'max:10'],
            'parent_id' => ['nullable', 'integer', Rule::notIn(array_filter([$id])),
                Rule::exists('categories', 'id')->whereNull('parent_id')->where('type', $this->string('type')->toString())],
            'actif' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Une catégorie de ce nom existe déjà pour ce type.',
            'parent_id.exists' => 'La catégorie parente doit être une catégorie principale du même type.',
        ];
    }

    /**
     * @return array{type: TypePublication, nom: string, slug: string, icone: ?string, parent_id: ?int, actif: bool}
     */
    public function donnees(): array
    {
        return [
            'type' => $this->enum('type', TypePublication::class) ?? TypePublication::Emploi,
            'nom' => trim($this->string('nom')->toString()),
            'slug' => $this->string('slug')->toString(),
            'icone' => $this->filled('icone') ? trim($this->string('icone')->toString()) : null,
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'actif' => $this->boolean('actif'),
        ];
    }
}
