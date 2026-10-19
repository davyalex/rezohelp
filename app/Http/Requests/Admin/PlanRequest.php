<?php

namespace App\Http\Requests\Admin;

use App\Enums\CibleOffre;
use App\Enums\CleDroit;
use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Plan et éditeur de limites (CONTEXTE §14.10) : case à cocher pour un booléen, nombre ou
 * « illimité » pour un entier ; un champ vide laisse la clé non fixée par ce plan.
 */
class PlanRequest extends FormRequest
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
        $plan = $this->route('plan');

        $regles = [
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('plans', 'code')->ignore($plan instanceof Plan ? $plan->id : null)],
            'nom' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'cible' => ['required', Rule::enum(CibleOffre::class)],
            'famille' => ['nullable', 'string', 'max:50', 'alpha_dash:ascii'],
            'prix_fcfa' => ['required', 'integer', 'min:0', 'max:10000000'],
            'duree_jours' => ['required', 'integer', 'min:0', 'max:3660'],
            'duree_essai_jours' => ['required', 'integer', 'min:0', 'max:365'],
            'est_defaut' => ['boolean'],
            'actif' => ['boolean'],
            'visible' => ['boolean'],
            'ordre' => ['required', 'integer', 'min:0', 'max:1000'],
            'limites' => ['array'],
            'illimite' => ['array'],
        ];

        foreach (CleDroit::cases() as $cle) {
            $regles['limites.'.$this->champ($cle)] = $cle->estBooleen() ? ['nullable', Rule::in(['', '0', '1'])] : ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        return $regles;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('est_defaut') && $this->integer('prix_fcfa') > 0) {
                $validator->errors()->add('prix_fcfa', 'Un plan par défaut est toujours gratuit.');
            }

            if ($this->integer('duree_essai_jours') > 0 && ! $this->filled('famille')) {
                $validator->errors()->add('famille', 'Indiquez une famille : l’essai est unique par famille de plans.');
            }

            if (! $this->boolean('est_defaut') && $this->integer('prix_fcfa') > 0 && $this->integer('duree_jours') === 0) {
                $validator->errors()->add('duree_jours', 'Un plan payant a une durée.');
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributsPlan(): array
    {
        return [
            'code' => $this->string('code')->toString(),
            'nom' => trim($this->string('nom')->toString()),
            'description' => $this->filled('description') ? trim($this->string('description')->toString()) : null,
            'cible' => $this->enum('cible', CibleOffre::class),
            'famille' => $this->filled('famille') ? $this->string('famille')->toString() : null,
            'prix_fcfa' => $this->integer('prix_fcfa'),
            'duree_jours' => $this->integer('duree_jours'),
            'duree_essai_jours' => $this->integer('duree_essai_jours'),
            'est_defaut' => $this->boolean('est_defaut'),
            'actif' => $this->boolean('actif'),
            'visible' => $this->boolean('visible'),
            'ordre' => $this->integer('ordre'),
        ];
    }

    /**
     * @return array<string, string> clé => valeur ('-1' = illimité)
     */
    public function limites(): array
    {
        $limites = [];

        foreach (CleDroit::cases() as $cle) {
            $champ = $this->champ($cle);

            if (! $cle->estBooleen() && $this->boolean('illimite.'.$champ)) {
                $limites[$cle->value] = (string) CleDroit::ILLIMITE;

                continue;
            }

            $valeur = $this->input('limites.'.$champ);

            if ($valeur !== null && $valeur !== '') {
                $limites[$cle->value] = (string) (int) $valeur;
            }
        }

        return $limites;
    }

    /**
     * Les points des clés (« alertes.max ») sont remplacés dans les noms de champs.
     */
    public static function champ(CleDroit $cle): string
    {
        return str_replace('.', '__', $cle->value);
    }
}
