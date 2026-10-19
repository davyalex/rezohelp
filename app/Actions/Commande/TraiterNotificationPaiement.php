<?php

namespace App\Actions\Commande;

use App\Contracts\PasserellePaiement;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiementPasserelle;
use App\Models\Commande;
use App\Services\CacheFil;

/**
 * Traite une notification de paiement (CONTEXTE §14.7) : le statut est toujours revérifié
 * auprès de l'API de la passerelle, sous verrou, et une commande payée n'est jamais
 * activée deux fois (webhook rejoué, double clic, page de retour).
 */
class TraiterNotificationPaiement
{
    public function __construct(
        private readonly PasserellePaiement $passerelle,
        private readonly ActiverCommande $activer,
        private readonly CacheFil $cache,
    ) {}

    public function executer(string $reference): ?Commande
    {
        $commande = Commande::query()
            ->where('reference_passerelle', $reference)
            ->orWhere('numero', $reference)
            ->first();

        if ($commande === null) {
            return null;
        }

        return $this->cache->sousVerrou('commande:'.$commande->id, function () use ($commande): Commande {
            $commande->refresh();

            if ($commande->statut !== StatutCommande::EnAttente) {
                return $commande;
            }

            $verification = $this->passerelle->verifier($commande->reference_passerelle ?? $commande->numero);
            $donnees = array_merge($commande->donnees_passerelle ?? [], ['verification' => $verification->donnees]);

            if ($verification->statut === StatutPaiementPasserelle::Refuse) {
                $commande->forceFill(['statut' => StatutCommande::Echouee, 'donnees_passerelle' => $donnees])->save();

                return $commande;
            }

            if (! $verification->estAccepte()) {
                return $commande;
            }

            // Montant payé différent du montant figé : falsification ou erreur, jamais d'activation.
            if ($verification->montantFcfa !== $commande->montant_ttc_fcfa) {
                $commande->forceFill([
                    'statut' => StatutCommande::Echouee,
                    'donnees_passerelle' => array_merge($donnees, ['anomalie' => 'Montant payé ('.$verification->montantFcfa.') différent du montant de la commande.']),
                ])->save();

                return $commande;
            }

            $this->activer->executer($commande, $verification->moyenPaiement, $donnees);

            return $commande->refresh();
        });
    }
}
