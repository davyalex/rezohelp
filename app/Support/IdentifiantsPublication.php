<?php

namespace App\Support;

use App\Models\Publication;
use Illuminate\Support\Str;

/**
 * Identifiants publics d'une publication : slug lisible et code court des liens SMS (/p/{code}).
 */
final class IdentifiantsPublication
{
    public const int LONGUEUR_CODE_COURT = 6;

    public static function slug(string $titre): string
    {
        $base = Str::limit(Str::slug($titre), 150, '') ?: 'publication';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Publication::withTrashed()->where('slug', $slug)->exists());

        return $slug;
    }

    public static function codeCourt(): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';

        do {
            $code = '';
            for ($i = 0; $i < self::LONGUEUR_CODE_COURT; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Publication::withTrashed()->where('code_court', $code)->exists());

        return $code;
    }

    public static function extrait(string $contenu): string
    {
        return Str::limit(Str::squish($contenu), 220);
    }
}
