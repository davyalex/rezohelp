<?php

namespace App\Actions\Commande;

use App\Contracts\PasserellePaiement;
use App\Donnees\DemandePaiement;
use App\Enums\TypeArticle;
use App\Models\Commande;
use App\Models\Plan;
use App\Models\Produit;

/**
 * Ouvre le paiement d'une commande auprès de la passerelle et renvoie l'URL du guichet.
 */
class InitierPaiement
{
    public function __construct(private readonly PasserellePaiement $passerelle) {}

    public function executer(Commande $commande): string
    {
        $commande->loadMissing('utilisateur');
        $article = $commande->article_type === TypeArticle::Plan
            ? Plan::query()->find($commande->article_id)
            : Produit::query()->find($commande->article_id);

        $initialisation = $this->passerelle->initialiser(new DemandePaiement(
            reference: $commande->numero,
            montantFcfa: $commande->montant_ttc_fcfa,
            description: 'Resohelph — '.($article->nom ?? 'commande').' ('.$commande->numero.')',
            urlRetour: route('paiements.retour', $commande->id),
            urlNotification: route('paiements.notification', $this->passerelle->nom()),
            telephoneClient: (string) $commande->utilisateur?->telephone,
            nomClient: (string) $commande->utilisateur?->nomAffiche(),
        ));

        $commande->forceFill([
            'reference_passerelle' => $initialisation->reference,
            'donnees_passerelle' => ['initialisation' => $initialisation->donnees],
        ])->save();

        return $initialisation->urlPaiement;
    }
}
