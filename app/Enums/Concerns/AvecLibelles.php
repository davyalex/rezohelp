<?php

namespace App\Enums\Concerns;

/**
 * Fournit la liste « valeur => libellé » d'un enum backed string,
 * pour les listes déroulantes et la validation.
 */
trait AvecLibelles
{
    abstract public function libelle(): string;

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $cas) {
            $options[$cas->value] = $cas->libelle();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
