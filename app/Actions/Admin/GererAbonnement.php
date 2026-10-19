<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Enums\OrigineAbonnement;
use App\Enums\StatutAbonnement;
use App\Exceptions\OperationImpossibleException;
use App\Models\Abonnement;
use App\Models\Entreprise;
use App\Models\Plan;
use App\Models\Utilisateur;
use App\Services\Droits;
use Illuminate\Support\Facades\DB;

/**
 * K — Abonnements gérés par l'équipe (CONTEXTE §14.10) : offrir (origine offert_admin, avec un
 * motif), prolonger, résilier. AbonnementObserver recalcule les droits et les badges.
 */
class GererAbonnement
{
    use JournaliseActivite;

    public function offrir(Utilisateur|Entreprise $titulaire, Plan $plan, int $jours, string $motif, Utilisateur $admin): Abonnement
    {
        if (! in_array($plan->cible, Droits::cibles($titulaire), true)) {
            throw new OperationImpossibleException('Ce plan ne correspond pas à ce titulaire (cible '.$plan->cible->libelle().').');
        }

        return DB::transaction(function () use ($titulaire, $plan, $jours, $motif, $admin): Abonnement {
            $abonnement = Abonnement::query()->create([
                'titulaire_type' => $titulaire->getMorphClass(),
                'titulaire_id' => $titulaire->getKey(),
                'plan_id' => $plan->id,
                'statut' => StatutAbonnement::Actif,
                'origine' => OrigineAbonnement::OffertAdmin,
                'debut_le' => now(),
                'fin_le' => now()->addDays($jours),
                'motif' => $motif,
            ]);

            $this->journaliser($admin, 'abonnement.offert', $abonnement, ['plan' => $plan->code, 'jours' => $jours, 'motif' => $motif]);

            return $abonnement;
        });
    }

    public function prolonger(Abonnement $abonnement, int $jours, Utilisateur $admin): void
    {
        if (! in_array($abonnement->statut, Droits::STATUTS_EN_COURS, true)) {
            throw new OperationImpossibleException('Seul un abonnement en cours peut être prolongé.');
        }

        DB::transaction(function () use ($abonnement, $jours, $admin): void {
            $depart = $abonnement->fin_le !== null && $abonnement->fin_le->isFuture() ? $abonnement->fin_le : now();
            $abonnement->fin_le = $depart->copy()->addDays($jours);

            // Une période en grâce prolongée redevient active ; les relances repartent de zéro.
            if ($abonnement->statut === StatutAbonnement::Grace) {
                $abonnement->statut = StatutAbonnement::Actif;
            }
            $abonnement->relances_envoyees = null;

            $differences = $this->differences($abonnement);
            $abonnement->save();
            $this->journaliser($admin, 'abonnement.prolonge', $abonnement, [...$differences, 'jours' => $jours]);
        });
    }

    public function resilier(Abonnement $abonnement, Utilisateur $admin, ?string $motif = null): void
    {
        if (! in_array($abonnement->statut, Droits::STATUTS_EN_COURS, true)) {
            throw new OperationImpossibleException('Cet abonnement n’est plus en cours.');
        }

        DB::transaction(function () use ($abonnement, $admin, $motif): void {
            $abonnement->statut = StatutAbonnement::Resilie;
            $abonnement->fin_le = now();
            $abonnement->save();
            $this->journaliser($admin, 'abonnement.resilie', $abonnement, ['motif' => $motif]);
        });
    }
}
