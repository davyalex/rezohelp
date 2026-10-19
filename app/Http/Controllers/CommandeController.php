<?php

namespace App\Http\Controllers;

use App\Actions\Abonnement\DemarrerEssai;
use App\Actions\Commande\CreerCommande;
use App\Actions\Commande\InitierPaiement;
use App\Exceptions\OperationImpossibleException;
use App\Http\Requests\Abonnement\CommanderRequest;
use App\Http\Requests\Abonnement\DemarrerEssaiRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Achats (plan ou produit) et essais gratuits (CONTEXTE §14.6, §14.7).
 * Le paiement s'ouvre sur le guichet de la passerelle ; l'activation n'a lieu qu'au webhook.
 */
class CommandeController extends Controller
{
    public function store(CommanderRequest $requete, CreerCommande $creer, InitierPaiement $initier): RedirectResponse
    {
        try {
            $commande = $creer->executer($requete->membre(), $requete->titulaire(), $requete->article(), $requete->publication());
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->away($initier->executer($commande));
    }

    public function essai(DemarrerEssaiRequest $requete, DemarrerEssai $demarrer): RedirectResponse
    {
        try {
            $abonnement = $demarrer->executer($requete->membre(), $requete->titulaire(), $requete->plan());
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('espace.abonnement')->with('statut',
            'Votre essai gratuit « '.$requete->plan()->nom.' » est actif jusqu’au '.$abonnement->fin_le?->translatedFormat('j F Y').'.');
    }
}
