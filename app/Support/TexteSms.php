<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Texte de SMS économique (CONTEXTE §14.8) : alphabet GSM-7 sans accents (un accent
 * force l'UCS-2 et réduit un SMS de 160 à 70 caractères) et un seul SMS de 160 caractères.
 */
final class TexteSms
{
    public const int LONGUEUR_MAX = 160;

    private const string SUITE = '...';

    /** Caractères de l'alphabet GSM-7 de base que l'on garde tels quels. */
    private const string GSM7 = "@£\$¥\n\r !\"#%&'()*+,-./0123456789:;<=>?ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_";

    public static function preparer(string $texte): string
    {
        // Guillemets français : « dispo » → "dispo" (les espaces intérieures disparaissent).
        $texte = (string) preg_replace(['/«\s*/u', '/\s*»/u'], '"', $texte);
        $texte = str_replace(['’', '‘', '“', '”', '–', '—', '…', "\u{00A0}", "\u{202F}"], ["'", "'", '"', '"', '-', '-', '...', ' ', ' '], $texte);
        $texte = Str::ascii($texte);

        $propre = '';
        foreach (mb_str_split($texte) as $caractere) {
            $propre .= str_contains(self::GSM7, $caractere) ? $caractere : ' ';
        }

        return self::tronquer(trim((string) preg_replace('/[ ]{2,}/', ' ', $propre)), self::LONGUEUR_MAX);
    }

    /**
     * Coupe au dernier espace avant la limite et ajoute « ... ».
     */
    public static function tronquer(string $texte, int $longueur): string
    {
        if (strlen($texte) <= $longueur) {
            return $texte;
        }

        $coupe = substr($texte, 0, $longueur - strlen(self::SUITE));
        $dernierEspace = strrpos($coupe, ' ');

        if ($dernierEspace !== false && $dernierEspace > $longueur / 2) {
            $coupe = substr($coupe, 0, $dernierEspace);
        }

        return rtrim($coupe, ' ,.;:-').self::SUITE;
    }

    /**
     * Message d'alerte : « Rezohelp: {titre}, {commune}. Voir: rezohelp.ci/p/ab12cd ».
     * Le titre est raccourci pour que le lien tienne toujours dans le SMS.
     */
    public static function alerte(string $titre, ?string $commune, string $lienCourt): string
    {
        $lien = ' Voir: '.preg_replace('#^https?://#', '', $lienCourt);
        $suffixe = ($commune !== null ? ', '.self::preparer($commune) : '').'.'.$lien;
        $place = self::LONGUEUR_MAX - strlen('Rezohelp: ') - strlen($suffixe);

        return 'Rezohelp: '.self::tronquer(rtrim(self::preparer($titre), '.'), max(10, $place)).$suffixe;
    }
}
