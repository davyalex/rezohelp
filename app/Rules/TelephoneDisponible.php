<?php

namespace App\Rules;

use App\Models\Utilisateur;
use App\Services\TelephoneService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Le numéro, une fois normalisé en E.164 avec le pays saisi, n'appartient à aucun compte.
 * « 0708091011 » et « +225 07 08 09 10 11 » sont donc le même numéro.
 */
class TelephoneDisponible implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $donnees = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->donnees = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pays = $this->donnees['pays_code'] ?? null;
        $e164 = app(TelephoneService::class)->normaliser(is_string($value) ? $value : null, is_string($pays) ? $pays : null);

        if ($e164 === null) {
            return; // la règle « phone » signale déjà le format invalide
        }

        if (Utilisateur::withTrashed()->where('telephone', $e164)->exists()) {
            $fail('Ce numéro de téléphone est déjà utilisé par un compte.');
        }
    }
}
