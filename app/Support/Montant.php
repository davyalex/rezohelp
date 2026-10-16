<?php

namespace App\Support;

/**
 * Affichage des montants en francs CFA : « 150 000 FCFA ».
 * Espaces insécables : le montant ne se coupe jamais en fin de ligne.
 */
final class Montant
{
    public const string ESPACE = "\u{00A0}";

    public static function fcfa(int $montant): string
    {
        return self::nombre($montant).self::ESPACE.'FCFA';
    }

    public static function nombre(int $montant): string
    {
        return number_format($montant, 0, ',', self::ESPACE);
    }

    /**
     * « 150 000 – 200 000 FCFA », « À partir de 150 000 FCFA »…
     */
    public static function fourchette(?int $minimum, ?int $maximum): ?string
    {
        return match (true) {
            $minimum !== null && $maximum !== null && $maximum > $minimum => self::nombre($minimum).' – '.self::fcfa($maximum),
            $minimum !== null => self::fcfa($minimum),
            $maximum !== null => 'Jusqu’à '.self::fcfa($maximum),
            default => null,
        };
    }
}
