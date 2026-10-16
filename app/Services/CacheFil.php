<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * Point d'accès unique au cache applicatif (Memcached) — CLAUDE.md, CONTEXTE §8.
 *
 * Clés versionnées, jamais de tags. Aucune donnée propre à un utilisateur
 * n'est stockée ici. L'invalidation est déclenchée par les observers.
 */
class CacheFil
{
    public const int DUREE_REFERENTIEL = 86400;

    public function __construct(private readonly Repository $cache) {}

    /**
     * Référentiel mis en cache 24 h sous la clé « ref:{nom} ».
     *
     * @template T
     *
     * @param  Closure(): T  $calcul
     * @return T
     */
    public function referentiel(string $nom, Closure $calcul): mixed
    {
        return $this->cache->remember("ref:{$nom}", self::DUREE_REFERENTIEL, $calcul);
    }

    public function oublierReferentiel(string $nom): void
    {
        $this->cache->forget("ref:{$nom}");
    }
}
