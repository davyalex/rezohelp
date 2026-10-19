<?php

namespace App\Http\Requests\Abonnement;

use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Démarrage d'un essai gratuit en un clic (CONTEXTE §14.6).
 */
class DemarrerEssaiRequest extends FormRequest
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
            'plan' => ['required', 'string', 'max:50', Rule::exists('plans', 'code')],
            'entreprise_id' => ['nullable', 'integer', $this->regleEntreprise()],
        ];
    }

    public function plan(): Plan
    {
        return Plan::query()->where('code', $this->string('plan')->toString())->firstOrFail();
    }
}
