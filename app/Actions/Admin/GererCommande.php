<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\JournaliseActivite;
use App\Actions\Commande\TraiterNotificationPaiement;
use App\Enums\StatutAbonnement;
use App\Enums\StatutCommande;
use App\Exceptions\OperationImpossibleException;
use App\Models\Abonnement;
use App\Models\Commande;
use App\Models\Credit;
use App\Models\MiseEnAvant;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * K — Commandes (CONTEXTE §14.7, §14.10) : revérification du statut auprès de la passerelle
 * (même traitement idempotent que le webhook) et remboursement manuel.
 */
class GererCommande
{
    use JournaliseActivite;

    public function __construct(private readonly TraiterNotificationPaiement $traiter) {}

    public function reverifier(Commande $commande, Utilisateur $admin): Commande
    {
        $avant = $commande->statut;
        $resultat = $this->traiter->executer($commande->reference_passerelle ?? $commande->numero) ?? $commande;

        $this->journaliser($admin, 'commande.reverifiee', $commande, ['avant' => $avant->value, 'apres' => $resultat->statut->value]);

        return $resultat;
    }

    /**
     * Remboursement manuel (fait hors plateforme auprès de l'opérateur) : la commande passe en
     * « remboursee », l'abonnement est résilié, les crédits restants et les mises en avant s'arrêtent.
     */
    public function rembourser(Commande $commande, Utilisateur $admin, string $motif): void
    {
        if ($commande->statut !== StatutCommande::Payee) {
            throw new OperationImpossibleException('Seule une commande payée peut être remboursée.');
        }

        DB::transaction(function () use ($commande, $admin, $motif): void {
            $commande->statut = StatutCommande::Remboursee;
            $commande->save();

            Abonnement::query()->where('commande_id', $commande->id)
                ->whereIn('statut', [StatutAbonnement::Essai->value, StatutAbonnement::Actif->value, StatutAbonnement::Grace->value])
                ->get()
                ->each(fn (Abonnement $abonnement) => $abonnement->forceFill(['statut' => StatutAbonnement::Resilie, 'fin_le' => now()])->save());

            Credit::query()->where('commande_id', $commande->id)->update(['consommee' => DB::raw('quantite')]);

            MiseEnAvant::query()->where('commande_id', $commande->id)
                ->where(fn ($r) => $r->whereNull('fin_le')->orWhere('fin_le', '>', now()))
                ->update(['fin_le' => now()]);

            $this->journaliser($admin, 'commande.remboursee', $commande, ['motif' => $motif, 'montant' => $commande->montant_ttc_fcfa]);
        });
    }
}
