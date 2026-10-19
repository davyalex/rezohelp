<?php

namespace App\Http\Requests\Admin;

use App\Enums\CibleOffre;
use App\Enums\TypeProduit;
use App\Models\Produit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Produit ponctuel : une durée pour les mises en avant et l'offre supplémentaire, aucune pour la remontée.
 */
class ProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('abonnements.gerer') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $produit = $this->route('produit');

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('produits', 'code')->ignore($produit instanceof Produit ? $produit->id : null)],
            'nom' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'cible' => ['required', Rule::enum(CibleOffre::class)],
            'type' => ['required', Rule::enum(TypeProduit::class)],
            'prix_fcfa' => ['required', 'integer', 'min:1', 'max:10000000'],
            'duree_jours' => [Rule::requiredIf($this->input('type') !== TypeProduit::Remontee->value), 'nullable', 'integer', 'min:1', 'max:365'],
            'actif' => ['boolean'],
            'ordre' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function donnees(): array
    {
        $type = $this->enum('type', TypeProduit::class);

        return [
            'code' => $this->string('code')->toString(),
            'nom' => trim($this->string('nom')->toString()),
            'description' => $this->filled('description') ? trim($this->string('description')->toString()) : null,
            'cible' => $this->enum('cible', CibleOffre::class),
            'type' => $type,
            'prix_fcfa' => $this->integer('prix_fcfa'),
            'duree_jours' => $type === TypeProduit::Remontee || ! $this->filled('duree_jours') ? null : $this->integer('duree_jours'),
            'actif' => $this->boolean('actif'),
            'ordre' => $this->integer('ordre'),
        ];
    }
}
