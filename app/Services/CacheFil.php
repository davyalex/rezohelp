<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;

/**
 * Point d'accès unique au cache applicatif (Memcached) — CLAUDE.md, CONTEXTE §8.
 *
 * Clés versionnées, jamais de tags :
 *  - fil:version                       numéro de version du fil (incrémenté par PublicationObserver) ;
 *  - fil:v{version}:{type}:{commune}:{empreinte}:{curseur}   ids d'une salve + curseur suivant, 5 min ;
 *  - carte:{id}:{modifie_le_ts}        gabarit HTML d'une carte, 24 h ;
 *  - ref:{nom}                         référentiels, 24 h ;
 *  - compteurs:{nom}                   agrégats non sensibles, 10 min (expiration seule) ;
 *  - verrou:{clé}                      verrous Cache::lock contre l'effet ruée.
 * Aucune donnée propre à un utilisateur n'est stockée ici.
 */
class CacheFil
{
    public const int DUREE_REFERENTIEL = 86400;

    public const int DUREE_SALVE = 300;

    public const int DUREE_CARTE = 86400;

    public const int DUREE_COMPTEURS = 600;

    private const string CLE_VERSION = 'fil:version';

    /** Durée maximale du verrou anti-ruée et attente maximale des autres requêtes. */
    private const int DUREE_VERROU = 10;

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
        return $this->memoriser("ref:{$nom}", self::DUREE_REFERENTIEL, $calcul);
    }

    public function oublierReferentiel(string $nom): void
    {
        $this->cache->forget("ref:{$nom}");
    }

    public function versionFil(): int
    {
        $version = $this->cache->get(self::CLE_VERSION);

        if (is_numeric($version)) {
            return (int) $version;
        }

        $this->cache->add(self::CLE_VERSION, 1, null);

        return (int) $this->cache->get(self::CLE_VERSION, 1);
    }

    /**
     * Invalide toutes les salves du fil en changeant de version (les anciennes clés expirent seules).
     */
    public function invaliderFil(): void
    {
        $this->versionFil();
        $this->cache->increment(self::CLE_VERSION);
    }

    /**
     * Salve du fil (ids + curseur suivant), 5 min.
     *
     * @template T
     *
     * @param  Closure(): T  $calcul
     * @return T
     */
    public function salve(string $type, string $commune, string $empreinteFiltres, ?string $curseur, Closure $calcul): mixed
    {
        $cle = sprintf('fil:v%d:%s:%s:%s:%s', $this->versionFil(), $type, $commune, $empreinteFiltres, $curseur ?? 'debut');

        return $this->memoriser($cle, self::DUREE_SALVE, $calcul);
    }

    /**
     * Gabarit HTML d'une carte ; la clé change avec modifie_le, l'invalidation est donc automatique.
     *
     * @param  Closure(): string  $rendu
     */
    public function carte(int $id, int $modifieLeTimestamp, Closure $rendu): string
    {
        return (string) $this->cache->remember("carte:{$id}:{$modifieLeTimestamp}", self::DUREE_CARTE, $rendu);
    }

    /**
     * Agrégat non sensible (compteurs par type, communes populaires…), 10 min.
     *
     * @template T
     *
     * @param  Closure(): T  $calcul
     * @return T
     */
    public function compteurs(string $nom, Closure $calcul): mixed
    {
        return $this->memoriser("compteurs:{$nom}", self::DUREE_COMPTEURS, $calcul);
    }

    /**
     * remember() protégé contre l'effet ruée : une seule requête recalcule une clé expirée,
     * les autres attendent son résultat.
     *
     * @template T
     *
     * @param  Closure(): T  $calcul
     * @return T
     */
    private function memoriser(string $cle, int $duree, Closure $calcul): mixed
    {
        if ($this->cache->has($cle)) {
            return $this->cache->get($cle);
        }

        $magasin = $this->cache->getStore();

        if (! $magasin instanceof LockProvider) {
            return $this->cache->remember($cle, $duree, $calcul);
        }

        return $magasin->lock("verrou:{$cle}", self::DUREE_VERROU)->block(self::DUREE_VERROU, function () use ($cle, $duree, $calcul) {
            return $this->cache->remember($cle, $duree, $calcul);
        });
    }
}
