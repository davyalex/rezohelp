<?php

namespace App\Http\Requests;

use App\Enums\FrequenceAlerte;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Models\Alerte;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Critères d'une alerte (CONTEXTE §10) : au moins un critère, pour ne pas recevoir tout le fil.
 */
class AlerteRequest extends FormRequest
{
    private const array CRITERES = ['type', 'categorie_id', 'commune_id', 'type_contrat', 'mots_cles'];

    public function authorize(): bool
    {
        $alerte = $this->route('alerte');

        return $alerte instanceof Alerte
            ? ($this->user()?->can('update', $alerte) ?? false)
            : ($this->user()?->can('create', Alerte::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(TypePublication::class)],
            'categorie_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('actif', true)
                    ->when($this->filled('type'), fn ($regle) => $regle->where('type', $this->string('type')->toString())),
            ],
            'commune_id' => ['nullable', 'integer', Rule::exists('communes', 'id')->where('actif', true)],
            'type_contrat' => ['nullable', Rule::enum(TypeContrat::class)],
            'mots_cles' => ['nullable', 'string', 'max:150'],
            'frequence' => ['required', Rule::enum(FrequenceAlerte::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'categorie_id' => 'catégorie',
            'commune_id' => 'commune',
            'type_contrat' => 'type de contrat',
            'mots_cles' => 'mots-clés',
            'frequence' => 'fréquence',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validateur): void {
                if ($this->filled('type_contrat') && $this->filled('type') && $this->input('type') !== TypePublication::Emploi->value) {
                    $validateur->errors()->add('type_contrat', 'Le type de contrat ne concerne que les offres d’emploi.');
                }

                foreach (self::CRITERES as $critere) {
                    if ($this->filled($critere)) {
                        return;
                    }
                }

                $validateur->errors()->add('type', 'Choisissez au moins un critère (type, catégorie, commune, contrat ou mots-clés).');
            },
        ];
    }
}
