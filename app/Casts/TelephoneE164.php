<?php

namespace App\Casts;

use App\Services\TelephoneService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stocke un numéro de téléphone au format E.164.
 *
 * Le pays sert à interpréter une saisie nationale : la colonne indiquée en
 * paramètre (ex. « pays_code ») si elle est renseignée, sinon la Côte d'Ivoire.
 *
 * @implements CastsAttributes<string, string>
 */
class TelephoneE164 implements CastsAttributes
{
    public function __construct(private readonly ?string $colonnePays = null) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $pays = $this->colonnePays !== null ? ($attributes[$this->colonnePays] ?? null) : null;
        $e164 = app(TelephoneService::class)->normaliser((string) $value, is_string($pays) ? $pays : null);

        if ($e164 === null) {
            throw new InvalidArgumentException("Numéro de téléphone invalide pour {$key}.");
        }

        return $e164;
    }
}
