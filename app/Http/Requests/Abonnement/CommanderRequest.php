<?php

namespace App\Http\Requests\Abonnement;

use App\Enums\TypeArticle;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Publication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Achat d'un plan ou d'un produit (CONTEXTE §14.7). Seul le code de l'article est transmis :
 * le prix vient toujours de la base.
 */
class CommanderRequest extends FormRequest
{
    use ChoisitTitulaire;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'article_type' => ['required', Rule::enum(TypeArticle::class)],
            'code' => ['required', 'string', 'max:50'],
            'entreprise_id' => ['nullable', 'integer', $this->regleEntreprise()],
            'publication_id' => ['nullable', 'integer'],
        ];
    }

    public function article(): Plan|Produit
    {
        $code = $this->string('code')->toString();

        return $this->enum('article_type', TypeArticle::class) === TypeArticle::Plan
            ? Plan::query()->where('code', $code)->firstOrFail()
            : Produit::query()->where('code', $code)->firstOrFail();
    }

    public function publication(): ?Publication
    {
        $id = $this->integer('publication_id');

        return $id > 0 ? Publication::query()->find($id) : null;
    }
}
