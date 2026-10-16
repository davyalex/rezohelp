<?php

namespace App\Services;

use App\Enums\CibleOffre;
use App\Enums\CleDroit;
use App\Enums\SousTypeService;
use App\Enums\StatutPublication;
use App\Enums\TypePublication;
use App\Enums\TypeReponse;
use App\Exceptions\QuotaAtteintException;
use App\Models\Alerte;
use App\Models\Entreprise;
use App\Models\Parametre;
use App\Models\PlanLimite;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Droits effectifs d'un titulaire (CONTEXTE §14.5) : seul point de vérification
 * des capacités et des quotas. Aucun prix ni aucune limite n'est écrit dans le code.
 *
 * Droits::pour($titulaire)->peut(CleDroit::AlertesCanalSms);
 * Droits::pour($titulaire)->restant(CleDroit::EmploiOffresMois);
 */
final class Droits
{
    /**
     * @param  array<string, int>  $limites  clé de droit => valeur (-1 = illimité, 0/1 pour un booléen)
     */
    private function __construct(
        private readonly Utilisateur|Entreprise $titulaire,
        private readonly array $limites,
        private readonly bool $quotasActifs,
    ) {}

    public static function pour(Utilisateur|Entreprise $titulaire): self
    {
        return new self($titulaire, self::calculerLimites($titulaire), Parametre::booleen('quotas_actifs'));
    }

    /**
     * Cibles dont le plan par défaut s'applique au titulaire.
     *
     * @return list<CibleOffre>
     */
    public static function cibles(Utilisateur|Entreprise $titulaire): array
    {
        return $titulaire instanceof Entreprise
            ? [CibleOffre::Recruteur, CibleOffre::Organisme]
            : [CibleOffre::Membre, CibleOffre::Artisan];
    }

    public function peut(CleDroit $cle): bool
    {
        return $this->limite($cle) !== 0;
    }

    /**
     * Valeur effective : -1 = illimité. Quotas désactivés ⇒ illimité pour les clés de quota.
     */
    public function limite(CleDroit $cle): int
    {
        if ($cle->estQuota() && ! $this->quotasActifs) {
            return CleDroit::ILLIMITE;
        }

        return $this->limites[$cle->value] ?? 0;
    }

    /**
     * Ce qu'il reste à consommer (-1 = illimité).
     */
    public function restant(CleDroit $cle): int
    {
        $limite = $this->limite($cle);

        if ($limite === CleDroit::ILLIMITE) {
            return CleDroit::ILLIMITE;
        }

        return max(0, $limite - $this->consommation($cle));
    }

    /**
     * @throws QuotaAtteintException
     */
    public function verifierOuEchouer(CleDroit $cle): void
    {
        if ($this->restant($cle) === 0) {
            throw new QuotaAtteintException($cle, $this->limite($cle));
        }
    }

    /**
     * Consommation calculée par requête, jamais stockée.
     */
    public function consommation(CleDroit $cle): int
    {
        return match ($cle) {
            CleDroit::EmploiOffresMois => $this->publicationsDuTitulaire(TypePublication::Emploi)
                ->where('cree_le', '>=', Carbon::now()->startOfMonth())
                ->whereNotIn('statut', [StatutPublication::Rejetee->value, StatutPublication::Brouillon->value])
                ->count(),
            CleDroit::ServiceOffresActives => $this->publicationsDuTitulaire(TypePublication::Service)
                ->where('sous_type', SousTypeService::Offre->value)
                ->whereIn('statut', [StatutPublication::EnAttente->value, StatutPublication::Publiee->value])
                ->count(),
            CleDroit::FormationOffresActives => $this->publicationsDuTitulaire(TypePublication::Formation)
                ->whereIn('statut', [StatutPublication::EnAttente->value, StatutPublication::Publiee->value])
                ->count(),
            CleDroit::ReponsesDemandesMois => $this->titulaire instanceof Utilisateur
                ? Reponse::query()
                    ->where('utilisateur_id', $this->titulaire->id)
                    ->where('type', TypeReponse::Proposition->value)
                    ->where('cree_le', '>=', Carbon::now()->startOfMonth())
                    ->count()
                : 0,
            CleDroit::AlertesMax => $this->titulaire instanceof Utilisateur
                ? Alerte::query()->where('utilisateur_id', $this->titulaire->id)->where('active', true)->count()
                : 0,
            default => 0,
        };
    }

    /**
     * Publications comptées pour le titulaire : celles de l'entreprise, ou celles
     * publiées en son nom propre par l'utilisateur (particulier, artisan).
     *
     * @return Builder<Publication>
     */
    private function publicationsDuTitulaire(TypePublication $type): Builder
    {
        $requete = Publication::query()->where('type', $type->value);

        return $this->titulaire instanceof Entreprise
            ? $requete->where('entreprise_id', $this->titulaire->id)
            : $requete->where('auteur_id', $this->titulaire->id)->whereNull('entreprise_id');
    }

    /**
     * Plans par défaut des cibles du titulaire, combinés : maximum pour un entier
     * (-1 l'emporte), OU pour un booléen.
     *
     * @return array<string, int>
     */
    private static function calculerLimites(Utilisateur|Entreprise $titulaire): array
    {
        $lignes = PlanLimite::query()
            ->whereHas('plan', fn (Builder $plan) => $plan
                ->where('est_defaut', true)
                ->whereIn('cible', array_map(fn (CibleOffre $cible): string => $cible->value, self::cibles($titulaire))))
            ->get(['cle', 'valeur']);

        $limites = [];

        foreach ($lignes as $ligne) {
            $limites[$ligne->cle->value] = self::combiner($limites[$ligne->cle->value] ?? null, (int) $ligne->valeur);
        }

        return $limites;
    }

    private static function combiner(?int $actuelle, int $nouvelle): int
    {
        if ($actuelle === null) {
            return $nouvelle;
        }

        if ($actuelle === CleDroit::ILLIMITE || $nouvelle === CleDroit::ILLIMITE) {
            return CleDroit::ILLIMITE;
        }

        return max($actuelle, $nouvelle);
    }
}
