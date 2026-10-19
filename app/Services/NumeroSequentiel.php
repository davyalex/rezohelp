<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numéros séquentiels sans trou, par préfixe et par année (CMD-2026-000123, REC-2026-000123).
 * La ligne du compteur est verrouillée dans la transaction : deux paiements simultanés
 * ne reçoivent jamais le même numéro.
 */
class NumeroSequentiel
{
    public const string COMMANDE = 'CMD';

    public const string RECU = 'REC';

    public function suivant(string $prefixe): string
    {
        $annee = now()->year;
        $cle = "{$prefixe}-{$annee}";

        $valeur = DB::transaction(function () use ($cle): int {
            DB::table('sequences')->insertOrIgnore(['cle' => $cle, 'valeur' => 0]);
            $actuelle = (int) DB::table('sequences')->where('cle', $cle)->lockForUpdate()->value('valeur');
            DB::table('sequences')->where('cle', $cle)->update(['valeur' => $actuelle + 1]);

            return $actuelle + 1;
        });

        return sprintf('%s-%d-%06d', $prefixe, $annee, $valeur);
    }
}
