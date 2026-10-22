<?php

namespace App\Support;

/**
 * URL d'une annonce de la veille, normalisée pour détecter les doublons (CLAUDE.md, CONTEXTE §12) :
 * https, hôte en minuscules sans « www. », paramètres de suivi retirés, paramètres restants triés,
 * sans fragment ni slash final. L'empreinte est le SHA-256 de cette forme.
 */
final class UrlSource
{
    /** Paramètres qui ne changent pas l'annonce visée. */
    private const array PARAMETRES_DE_SUIVI = ['fbclid', 'gclid', 'dclid', 'msclkid', 'igshid', 'mc_cid', 'mc_eid', 'ref_src', '_ga', '_gl', 'yclid'];

    public static function normaliser(string $url): ?string
    {
        $url = trim($url);
        $parties = parse_url($url);

        if ($parties === false || ! isset($parties['host']) || ! in_array(strtolower($parties['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $hote = (string) preg_replace('/^www\./', '', strtolower($parties['host']));
        $chemin = rtrim($parties['path'] ?? '', '/');

        $parametres = [];
        parse_str($parties['query'] ?? '', $parametres);
        $parametres = array_filter(
            $parametres,
            fn (string|int $cle): bool => ! str_starts_with(strtolower((string) $cle), 'utm_') && ! in_array(strtolower((string) $cle), self::PARAMETRES_DE_SUIVI, true),
            ARRAY_FILTER_USE_KEY,
        );
        ksort($parametres);
        $requete = http_build_query($parametres, '', '&', PHP_QUERY_RFC3986);

        return 'https://'.$hote.(isset($parties['port']) ? ':'.$parties['port'] : '').$chemin.($requete !== '' ? '?'.$requete : '');
    }

    public static function empreinte(string $urlNormalisee): string
    {
        return hash('sha256', $urlNormalisee);
    }

    /**
     * Hôte sans « www. » d'une URL normalisée (rattachement à une source de veille).
     */
    public static function hote(string $urlNormalisee): string
    {
        return (string) parse_url($urlNormalisee, PHP_URL_HOST);
    }
}
