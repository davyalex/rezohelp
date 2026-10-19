<?php

namespace App\Console\Commands;

use App\Enums\CleDroit;
use App\Enums\StatutAbonnement;
use App\Models\Abonnement;
use App\Models\Entreprise;
use App\Models\Parametre;
use App\Models\Utilisateur;
use App\Notifications\FinAbonnementProche;
use App\Services\Droits;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Cycle de vie des abonnements (CONTEXTE §14.6, §14.7), planifié chaque heure :
 * relances J-3 / J-1 / J0, passage en grâce, expiration, et mise en pause des alertes
 * au-delà de la limite revenue au plan par défaut (les plus anciennes restent actives).
 */
class TraiterAbonnements extends Command
{
    protected $signature = 'abonnements:traiter';

    protected $description = 'Relances de fin, délai de grâce et expiration des abonnements';

    /** jours restants => code de relance */
    private const array RELANCES = [3 => 'j3', 1 => 'j1', 0 => 'j0'];

    public function handle(): int
    {
        $relances = $this->relancer();
        $titulairesExpires = $this->expirer();

        foreach ($titulairesExpires as [$type, $id]) {
            $titulaire = $type === 'utilisateur' ? Utilisateur::query()->find($id) : Entreprise::query()->find($id);

            if ($titulaire instanceof Utilisateur) {
                $this->mettreEnPauseAlertesEnTrop($titulaire);
            }
        }

        $this->info("{$relances} relance(s), ".count($titulairesExpires).' titulaire(s) revenu(s) à leur plan par défaut.');

        return self::SUCCESS;
    }

    private function relancer(): int
    {
        $envois = 0;

        Abonnement::query()
            ->whereIn('statut', [StatutAbonnement::Essai->value, StatutAbonnement::Actif->value])
            ->whereBetween('fin_le', [now(), now()->addDays(3)->endOfDay()])
            ->with(['plan', 'titulaire'])
            ->lazyById(200)
            ->each(function (Abonnement $abonnement) use (&$envois): void {
                if ($this->estRenouvele($abonnement)) {
                    return;
                }

                $jours = (int) max(0, Carbon::today()->diffInDays($abonnement->fin_le?->copy()->startOfDay(), false));
                $dejaEnvoyees = $abonnement->relances_envoyees ?? [];
                $aEnvoyer = null;

                foreach (self::RELANCES as $seuil => $code) {
                    if ($jours <= $seuil && ! in_array($code, $dejaEnvoyees, true)) {
                        $aEnvoyer = $code;
                        $dejaEnvoyees[] = $code;
                    }
                }

                if ($aEnvoyer === null) {
                    return;
                }

                $abonnement->forceFill(['relances_envoyees' => array_values(array_unique($dejaEnvoyees))])->saveQuietly();
                $this->destinataire($abonnement)?->notify(new FinAbonnementProche($abonnement, $jours));
                $envois++;
            });

        return $envois;
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function expirer(): array
    {
        $grace = Parametre::entier('jours_grace');
        $titulaires = [];

        $echus = Abonnement::query()
            ->where(fn ($requete) => $requete
                ->where(fn ($r) => $r->whereIn('statut', [StatutAbonnement::Essai->value, StatutAbonnement::Actif->value])->where('fin_le', '<=', now()))
                ->orWhere(fn ($r) => $r->where('statut', StatutAbonnement::Grace->value)->where('fin_le', '<=', now()->subDays($grace))))
            ->get();

        foreach ($echus as $abonnement) {
            // Pass payé non renouvelé : délai de grâce (droits maintenus, bandeau de relance). Un essai expire directement.
            $statut = $abonnement->statut === StatutAbonnement::Actif && $grace > 0 && ! $this->estRenouvele($abonnement)
                && $abonnement->fin_le?->gt(now()->subDays($grace))
                ? StatutAbonnement::Grace
                : StatutAbonnement::Expire;

            $abonnement->statut = $statut;
            $abonnement->save();

            if ($statut === StatutAbonnement::Expire) {
                $titulaires[$abonnement->titulaire_type.':'.$abonnement->titulaire_id] = [$abonnement->titulaire_type, (int) $abonnement->titulaire_id];
            }
        }

        return array_values($titulaires);
    }

    /**
     * Une période payée suivante est déjà prévue (renouvellement anticipé).
     */
    private function estRenouvele(Abonnement $abonnement): bool
    {
        return Abonnement::query()
            ->where('titulaire_type', $abonnement->titulaire_type)
            ->where('titulaire_id', $abonnement->titulaire_id)
            ->where('plan_id', $abonnement->plan_id)
            ->where('statut', StatutAbonnement::Actif->value)
            ->whereKeyNot($abonnement->id)
            ->where('debut_le', '>=', $abonnement->fin_le ?? now())
            ->exists();
    }

    private function destinataire(Abonnement $abonnement): ?Utilisateur
    {
        $titulaire = $abonnement->titulaire;

        return match (true) {
            $titulaire instanceof Utilisateur => $titulaire,
            $titulaire instanceof Entreprise => $titulaire->proprietaire,
            default => null,
        };
    }

    private function mettreEnPauseAlertesEnTrop(Utilisateur $membre): void
    {
        $limite = Droits::pour($membre)->limite(CleDroit::AlertesMax);

        if ($limite === CleDroit::ILLIMITE) {
            return;
        }

        $gardees = $membre->alertes()->where('active', true)
            ->orderBy('cree_le')->orderBy('id')
            ->limit($limite)
            ->pluck('id');

        $membre->alertes()->where('active', true)->whereNotIn('id', $gardees)->update(['active' => false]);
    }
}
