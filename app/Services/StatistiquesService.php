<?php

namespace App\Services;

use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Enums\StatutCommande;
use App\Enums\StatutImportVeille;
use App\Enums\StatutPublication;
use App\Enums\TypeArticle;
use App\Enums\TypePublication;
use App\Models\Abonnement;
use App\Models\Alerte;
use App\Models\AppelIa;
use App\Models\Commande;
use App\Models\ImportVeille;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\SmsEnvoye;
use App\Models\Utilisateur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats du back-office (CONTEXTE §13.3.A, §14.10). Les compteurs du tableau de bord sont
 * mis en cache 5 min (admin:*) ; ceux de l'en-tête restent exacts à chaque page.
 */
class StatistiquesService
{
    /** Au-delà, l'absence d'import réussi est signalée en rouge (imports toutes les 8 h). */
    public const int HEURES_ALERTE_IMPORT = 9;

    public function __construct(private readonly CacheFil $cache) {}

    public function enAttente(): int
    {
        return Publication::query()->where('statut', StatutPublication::EnAttente->value)->count();
    }

    /**
     * Publications ayant au moins un signalement non traité.
     */
    public function signalementsOuverts(): int
    {
        return Signalement::query()->whereNull('traite_le')->distinct()->count('publication_id');
    }

    /**
     * @return array{publiees_aujourdhui: array<string, int>, en_attente: int, signalements: int, inscrits_jour: int, inscrits_7j: int, alertes_actives: int, notifications_jour: int}
     */
    public function compteurs(): array
    {
        return $this->cache->admin('compteurs', function (): array {
            $parType = Publication::query()
                ->where('statut', StatutPublication::Publiee->value)
                ->where('publiee_le', '>=', Carbon::today())
                ->selectRaw('type, count(*) as nombre')
                ->groupBy('type')
                ->pluck('nombre', 'type');

            return [
                'publiees_aujourdhui' => collect(TypePublication::cases())
                    ->mapWithKeys(fn (TypePublication $type): array => [$type->value => (int) ($parType[$type->value] ?? 0)])
                    ->all(),
                'en_attente' => $this->enAttente(),
                'signalements' => $this->signalementsOuverts(),
                'inscrits_jour' => Utilisateur::query()->where('cree_le', '>=', Carbon::today())->count(),
                'inscrits_7j' => Utilisateur::query()->where('cree_le', '>=', Carbon::today()->subDays(6))->count(),
                'alertes_actives' => Alerte::query()->where('active', true)->count(),
                'notifications_jour' => DB::table('notifications')->where('created_at', '>=', Carbon::today())->count(),
            ];
        });
    }

    public function dernierImport(): ?ImportVeille
    {
        return ImportVeille::query()->latest('cree_le')->latest('id')->first();
    }

    /**
     * Aucun import réussi depuis HEURES_ALERTE_IMPORT heures (bandeau rouge).
     */
    public function importEnRetard(): bool
    {
        return ! ImportVeille::query()
            ->where('statut', StatutImportVeille::Termine->value)
            ->where('traite_le', '>=', now()->subHours(self::HEURES_ALERTE_IMPORT))
            ->exists();
    }

    /**
     * Publications mises en ligne et inscriptions par jour, sur 30 jours.
     *
     * @return list<array{jour: string, publications: int, inscriptions: int}>
     */
    public function serie30Jours(): array
    {
        return $this->cache->admin('serie-30-jours', function (): array {
            $debut = Carbon::today()->subDays(29);
            $publications = Publication::query()->where('publiee_le', '>=', $debut)
                ->selectRaw('date(publiee_le) as jour, count(*) as nombre')->groupBy('jour')->pluck('nombre', 'jour');
            $inscriptions = Utilisateur::query()->where('cree_le', '>=', $debut)
                ->selectRaw('date(cree_le) as jour, count(*) as nombre')->groupBy('jour')->pluck('nombre', 'jour');

            return collect(range(0, 29))->map(function (int $decalage) use ($debut, $publications, $inscriptions): array {
                $jour = $debut->copy()->addDays($decalage)->toDateString();

                return ['jour' => $jour, 'publications' => (int) ($publications[$jour] ?? 0), 'inscriptions' => (int) ($inscriptions[$jour] ?? 0)];
            })->all();
        });
    }

    /**
     * Revenus et abonnements (§14.10) : chiffre d'affaires du mois par source, abonnements actifs
     * par plan, essais en cours et conversion, échéances à 7 jours, coûts SMS et IA du mois.
     *
     * @return array{ca_mois: int, ca_plans: int, ca_produits: int, actifs_par_plan: array<string, int>, essais_en_cours: int, essais_termines: int, essais_convertis: int, cout_sms: int, cout_ia: int}
     */
    public function revenus(): array
    {
        $debutMois = Carbon::now()->startOfMonth();
        $ca = Commande::query()
            ->where('statut', StatutCommande::Payee->value)
            ->where('payee_le', '>=', $debutMois)
            ->selectRaw('article_type, sum(montant_ttc_fcfa) as total')
            ->groupBy('article_type')
            ->pluck('total', 'article_type');

        $essaisTermines = Abonnement::query()
            ->where('origine', OrigineAbonnement::Essai->value)
            ->whereIn('statut', [StatutAbonnement::Expire->value, StatutAbonnement::Resilie->value]);

        // Un essai est converti quand le même titulaire a ensuite payé un plan de la même famille.
        $convertis = (clone $essaisTermines)
            ->whereExists(fn ($requete) => $requete->select(DB::raw(1))
                ->from('abonnements as payes')
                ->join('plans as plans_payes', 'plans_payes.id', '=', 'payes.plan_id')
                ->join('plans as plans_essai', 'plans_essai.id', '=', 'abonnements.plan_id')
                ->whereColumn('payes.titulaire_type', 'abonnements.titulaire_type')
                ->whereColumn('payes.titulaire_id', 'abonnements.titulaire_id')
                ->whereColumn('plans_payes.famille', 'plans_essai.famille')
                ->where('payes.origine', OrigineAbonnement::Paiement->value))
            ->count();

        return [
            'ca_mois' => (int) $ca->sum(),
            'ca_plans' => (int) ($ca[TypeArticle::Plan->value] ?? 0),
            'ca_produits' => (int) ($ca[TypeArticle::Produit->value] ?? 0),
            'actifs_par_plan' => Abonnement::query()
                ->whereIn('abonnements.statut', [StatutAbonnement::Actif->value, StatutAbonnement::Grace->value])
                ->join('plans', 'plans.id', '=', 'abonnements.plan_id')
                ->selectRaw('plans.nom, count(*) as nombre')
                ->groupBy('plans.nom')
                ->pluck('nombre', 'plans.nom')
                ->map(fn ($nombre): int => (int) $nombre)
                ->all(),
            'essais_en_cours' => Abonnement::query()->where('statut', StatutAbonnement::Essai->value)->count(),
            'essais_termines' => $essaisTermines->count(),
            'essais_convertis' => $convertis,
            'cout_sms' => (int) SmsEnvoye::query()->where('cree_le', '>=', $debutMois)->sum('cout_fcfa'),
            'cout_ia' => (int) AppelIa::query()->where('cree_le', '>=', $debutMois)->sum('cout_estime_fcfa'),
        ];
    }
}
