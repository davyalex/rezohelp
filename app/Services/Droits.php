<?php

namespace App\Services;

use App\Enums\CibleOffre;
use App\Enums\CleDroit;
use App\Enums\MotifSms;
use App\Enums\SousTypeService;
use App\Enums\StatutAbonnement;
use App\Enums\StatutPublication;
use App\Enums\TypeMiseEnAvant;
use App\Enums\TypePublication;
use App\Enums\TypeReponse;
use App\Exceptions\QuotaAtteintException;
use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\Credit;
use App\Models\Entreprise;
use App\Models\MiseEnAvant;
use App\Models\Parametre;
use App\Models\PlanLimite;
use App\Models\Publication;
use App\Models\Reponse;
use App\Models\SmsEnvoye;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Droits effectifs d'un titulaire (CONTEXTE §14.5) : seul point de vérification
 * des capacités et des quotas. Aucun prix ni aucune limite n'est écrit dans le code.
 *
 * Droits effectifs = plans par défaut des cibles du titulaire + plans de tous ses
 * abonnements essai / actif / grace en cours (maximum des entiers, -1 l'emporte, OU des
 * booléens) + crédits ponctuels non consommés.
 *
 * Droits::pour($titulaire)->peut(CleDroit::AlertesCanalSms);
 * Droits::pour($titulaire)->restant(CleDroit::EmploiOffresMois);
 */
final class Droits
{
    /** Statuts d'abonnement qui ouvrent des droits. */
    public const array STATUTS_EN_COURS = [StatutAbonnement::Essai, StatutAbonnement::Actif, StatutAbonnement::Grace];

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
        $limites = $titulaire->exists
            ? app(CacheFil::class)->droits($titulaire->getMorphClass(), (int) $titulaire->getKey(), fn (): array => self::calculerLimites($titulaire))
            : self::calculerLimites($titulaire);

        return new self($titulaire, $limites, Parametre::booleen('quotas_actifs'));
    }

    /**
     * Cibles dont le plan par défaut s'applique au titulaire (D-033).
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
     * Valeur effective des plans : -1 = illimité. Quotas désactivés ⇒ illimité pour les clés de quota.
     */
    public function limite(CleDroit $cle): int
    {
        if ($cle->estQuota() && ! $this->quotasActifs) {
            return CleDroit::ILLIMITE;
        }

        return $this->limites[$cle->value] ?? 0;
    }

    /**
     * Ce qu'il reste à consommer (-1 = illimité) : reste du plan + crédits ponctuels.
     */
    public function restant(CleDroit $cle): int
    {
        $limite = $this->limite($cle);

        if ($limite === CleDroit::ILLIMITE) {
            return CleDroit::ILLIMITE;
        }

        return max(0, $limite - $this->consommation($cle)) + $this->creditsRestants($cle);
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
     * Après une consommation (publication créée…) : si le plan est dépassé, un crédit est utilisé.
     */
    public function imputer(CleDroit $cle): void
    {
        $limite = $this->limite($cle);

        if ($limite === CleDroit::ILLIMITE || $this->consommation($cle) <= $limite) {
            return;
        }

        DB::transaction(function () use ($cle): void {
            $credit = $this->creditsValides($cle)
                ->whereColumn('consommee', '<', 'quantite')
                ->orderBy('expire_le')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $credit?->increment('consommee');
        });
    }

    public function creditsRestants(CleDroit $cle): int
    {
        return (int) $this->creditsValides($cle)->sum(DB::raw('quantite - consommee'));
    }

    /**
     * Consommation calculée par requête, jamais stockée (§14.5).
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
            CleDroit::AlertesSmsParJour => $this->titulaire instanceof Utilisateur
                ? SmsEnvoye::query()
                    ->where('utilisateur_id', $this->titulaire->id)
                    ->where('motif', MotifSms::Alerte->value)
                    ->where('cree_le', '>=', Carbon::now()->startOfDay())
                    ->count()
                : 0,
            CleDroit::PublicationRemonteesMois => MiseEnAvant::query()
                ->where('type', TypeMiseEnAvant::Remontee->value)
                ->whereNull('commande_id')
                ->where('cree_le', '>=', Carbon::now()->startOfMonth())
                ->whereIn('publication_id', $this->publicationsDuTitulaire(null)->select('id'))
                ->count(),
            default => 0,
        };
    }

    /**
     * Publications comptées pour le titulaire : celles de l'entreprise, ou celles
     * publiées en son nom propre par l'utilisateur (particulier, artisan).
     *
     * @return Builder<Publication>
     */
    private function publicationsDuTitulaire(?TypePublication $type): Builder
    {
        $requete = Publication::query()->when($type !== null, fn (Builder $r) => $r->where('type', $type?->value));

        return $this->titulaire instanceof Entreprise
            ? $requete->where('entreprise_id', $this->titulaire->id)
            : $requete->where('auteur_id', $this->titulaire->id)->whereNull('entreprise_id');
    }

    /**
     * @return Builder<Credit>
     */
    private function creditsValides(CleDroit $cle): Builder
    {
        return Credit::query()
            ->where('titulaire_type', $this->titulaire->getMorphClass())
            ->where('titulaire_id', $this->titulaire->getKey() ?? 0)
            ->where('cle', $cle->value)
            ->where(fn (Builder $r) => $r->whereNull('expire_le')->orWhere('expire_le', '>', now()));
    }

    /**
     * @return array<string, int>
     */
    private static function calculerLimites(Utilisateur|Entreprise $titulaire): array
    {
        $plansAbonnes = $titulaire->exists
            ? Abonnement::query()
                ->where('titulaire_type', $titulaire->getMorphClass())
                ->where('titulaire_id', $titulaire->getKey())
                ->whereIn('statut', array_map(fn (StatutAbonnement $s): string => $s->value, self::STATUTS_EN_COURS))
                ->where(fn (Builder $r) => $r->whereNull('debut_le')->orWhere('debut_le', '<=', now()))
                ->pluck('plan_id')
                ->all()
            : [];

        $lignes = PlanLimite::query()
            ->whereHas('plan', fn (Builder $plan) => $plan
                ->where(fn (Builder $r) => $r
                    ->where(fn (Builder $defaut) => $defaut
                        ->where('est_defaut', true)
                        ->whereIn('cible', array_map(fn (CibleOffre $cible): string => $cible->value, self::cibles($titulaire))))
                    ->orWhereIn('id', $plansAbonnes)))
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
