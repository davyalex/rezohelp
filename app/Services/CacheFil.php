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
 *  - droits:v{version}:{type}:{id}     droits effectifs d'un titulaire, 10 min (CONTEXTE §14.5) ;
 *  - differe:{nom}:{id}                impressions / clics des mises en avant, vidés toutes les 5 min ;
 *  - jeton:{nom}                       jetons d'accès des API externes ;
 *  - admin:{nom}                       agrégats non sensibles du tableau de bord, 5 min ;
 *  - verrou:{clé}                      verrous Cache::lock contre l'effet ruée.
 * Aucune donnée affichée propre à un utilisateur (j'aime, enregistré, notifications) n'est stockée ici :
 * les droits ne servent qu'aux contrôles côté serveur.
 */
class CacheFil
{
    public const int DUREE_REFERENTIEL = 86400;

    public const int DUREE_SALVE = 300;

    public const int DUREE_CARTE = 86400;

    public const int DUREE_COMPTEURS = 600;

    public const int DUREE_DROITS = 600;

    /** Les compteurs différés survivent largement à l'intervalle de vidage (5 min). */
    public const int DUREE_DIFFERE = 3600;

    /** Agrégats du tableau de bord admin (seule exception à « pas de cache admin », §13.3.A). */
    public const int DUREE_ADMIN = 300;

    public const string COMPTEUR_IMPRESSIONS = 'impressions';

    public const string COMPTEUR_CLICS = 'clics';

    private const string CLE_VERSION = 'fil:version';

    private const string CLE_VERSION_DROITS = 'droits:version';

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
     * Droits effectifs d'un titulaire (§14.5), 10 min : « droits:v{version}:{type}:{id} ».
     *
     * @template T
     *
     * @param  Closure(): T  $calcul
     * @return T
     */
    public function droits(string $type, int $id, Closure $calcul): mixed
    {
        return $this->cache->remember($this->cleDroits($type, $id), self::DUREE_DROITS, $calcul);
    }

    public function oublierDroits(string $type, int $id): void
    {
        $this->cache->forget($this->cleDroits($type, $id));
    }

    /**
     * Un plan ou une limite a changé : tous les droits sont recalculés.
     */
    public function invaliderDroits(): void
    {
        $this->cache->add(self::CLE_VERSION_DROITS, 1, null);
        $this->cache->increment(self::CLE_VERSION_DROITS);
    }

    /**
     * Exécute $traitement sous un verrou exclusif (activation idempotente d'une commande…).
     *
     * @template T
     *
     * @param  Closure(): T  $traitement
     * @return T
     */
    public function sousVerrou(string $nom, Closure $traitement): mixed
    {
        $magasin = $this->cache->getStore();

        if (! $magasin instanceof LockProvider) {
            return $traitement();
        }

        return $magasin->lock("verrou:{$nom}", self::DUREE_VERROU)->block(self::DUREE_VERROU, $traitement);
    }

    /**
     * Jeton d'accès à une API externe (OAuth du fournisseur SMS…), conservé $duree secondes.
     *
     * @param  Closure(): string  $obtention
     */
    public function jeton(string $nom, int $duree, Closure $obtention): string
    {
        return (string) $this->cache->remember("jeton:{$nom}", $duree, $obtention);
    }

    /**
     * Agrégat du tableau de bord admin sous la clé « admin:{nom} », 5 min.
     *
     * @template T
     *
     * @param  Closure(): T  $calcul
     * @return T
     */
    public function admin(string $nom, Closure $calcul): mixed
    {
        return $this->memoriser("admin:{$nom}", self::DUREE_ADMIN, $calcul);
    }

    /**
     * Les référentiels mis en cache (bouton « Vider les référentiels » du back-office).
     *
     * @param  list<string>  $noms
     */
    public function oublierReferentiels(array $noms): void
    {
        foreach ($noms as $nom) {
            $this->oublierReferentiel($nom);
        }
    }

    /**
     * Compteur cumulé en cache (impressions, clics), vidé en base périodiquement.
     */
    public function incrementerCompteurDiffere(string $nom, int $id): void
    {
        $cle = "differe:{$nom}:{$id}";
        $this->cache->add($cle, 0, self::DUREE_DIFFERE);
        $this->cache->increment($cle);

        $index = "differe:{$nom}:index";
        $ids = $this->cache->get($index, []);

        if (is_array($ids) && in_array($id, $ids, true)) {
            return;
        }

        // L'index est une lecture-écriture : sous verrou pour ne perdre aucun id.
        $this->sousVerrou($index, function () use ($index, $id): void {
            $ids = $this->cache->get($index, []);
            $ids = is_array($ids) ? $ids : [];

            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
                $this->cache->put($index, $ids, self::DUREE_DIFFERE);
            }
        });
    }

    /**
     * Valeurs cumulées depuis le dernier vidage, remises à zéro.
     *
     * @return array<int, int> id => valeur
     */
    public function viderCompteursDifferes(string $nom): array
    {
        $ids = $this->cache->pull("differe:{$nom}:index");
        $valeurs = [];

        foreach (is_array($ids) ? $ids : [] as $id) {
            $valeur = $this->cache->pull("differe:{$nom}:{$id}");
            $valeur = is_numeric($valeur) ? (int) $valeur : 0;

            if ($valeur > 0) {
                $valeurs[(int) $id] = $valeur;
            }
        }

        return $valeurs;
    }

    private function cleDroits(string $type, int $id): string
    {
        $version = $this->cache->get(self::CLE_VERSION_DROITS, 1);

        return 'droits:v'.(is_numeric($version) ? (int) $version : 1).":{$type}:{$id}";
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
