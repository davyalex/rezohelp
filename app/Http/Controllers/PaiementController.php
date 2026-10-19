<?php

namespace App\Http\Controllers;

use App\Actions\Commande\TraiterNotificationPaiement;
use App\Contracts\PasserellePaiement;
use App\Enums\StatutCommande;
use App\Enums\StatutPaiementPasserelle;
use App\Models\Commande;
use App\Models\NotificationPaiement;
use App\Services\Paiement\FakePasserelle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Webhook et page de retour des paiements (CONTEXTE §14.7).
 *
 * - Le webhook journalise chaque appel, refuse une signature invalide, puis revérifie le statut
 *   auprès de l'API de la passerelle : seul ce chemin active une commande.
 * - La page de retour ne fait que lire le statut et s'actualise toutes les 3 s pendant 60 s.
 */
class PaiementController extends Controller
{
    /** 20 actualisations × 3 s = 60 s. */
    public const int ACTUALISATIONS_MAX = 20;

    public function notification(Request $request, string $passerelle, PasserellePaiement $driver, TraiterNotificationPaiement $traiter): Response
    {
        abort_unless($passerelle === $driver->nom(), 404);

        // Appel de contrôle de disponibilité de l'URL (sans charge utile) : rien à traiter.
        if ($request->isMethod('GET')) {
            return response('OK');
        }

        $journal = NotificationPaiement::query()->create([
            'passerelle' => $passerelle,
            'reference' => $driver->referenceNotifiee($request),
            'charge_utile' => $request->all(),
            'signature_valide' => $driver->signatureValide($request),
        ]);

        if (! $journal->signature_valide) {
            $journal->forceFill(['erreur' => 'Signature invalide.'])->save();

            return response('Signature invalide', 403);
        }

        if ($journal->reference === null) {
            $journal->forceFill(['erreur' => 'Référence absente.'])->save();

            return response('Référence absente', 422);
        }

        try {
            $commande = $traiter->executer($journal->reference);
        } catch (Throwable $exception) {
            $journal->forceFill(['erreur' => mb_substr($exception->getMessage(), 0, 1000)])->save();

            // La passerelle renverra la notification : rien n'a été activé.
            throw $exception;
        }

        $journal->forceFill([
            'traitee_le' => now(),
            'erreur' => $commande === null ? 'Commande inconnue.' : null,
        ])->save();

        return response('OK');
    }

    public function retour(Request $request, Commande $commande, PasserellePaiement $passerelle): View
    {
        $this->authorize('view', $commande);
        $commande->loadMissing(['plan', 'produit']);

        $actualisation = min(self::ACTUALISATIONS_MAX, max(0, $request->integer('n')));

        return view('paiements.retour', [
            'commande' => $commande,
            'article' => $commande->article(),
            'actualiser' => $commande->statut === StatutCommande::EnAttente && $actualisation < self::ACTUALISATIONS_MAX,
            'actualisationSuivante' => $actualisation + 1,
            'guichetSimule' => $this->guichetSimule($commande, $passerelle) !== null,
        ]);
    }

    /**
     * Guichet du driver « fake » (local uniquement) : simule l'issue du paiement puis la
     * notification de la passerelle, qui passe par le même traitement que le vrai webhook.
     */
    public function simuler(Request $request, Commande $commande, PasserellePaiement $passerelle, TraiterNotificationPaiement $traiter): RedirectResponse
    {
        $this->authorize('view', $commande);
        $guichet = $this->guichetSimule($commande, $passerelle);
        abort_if($guichet === null, 404);

        $issue = $request->validate(['issue' => ['required', Rule::in(['accepte', 'refuse'])]])['issue'];
        $reference = $commande->reference_passerelle ?? $commande->numero;

        $guichet->simuler($reference, $issue === 'accepte' ? StatutPaiementPasserelle::Accepte : StatutPaiementPasserelle::Refuse);
        $traiter->executer($reference);

        return redirect()->route('paiements.retour', $commande);
    }

    private function guichetSimule(Commande $commande, PasserellePaiement $passerelle): ?FakePasserelle
    {
        $disponible = ! app()->isProduction() && $commande->statut === StatutCommande::EnAttente;

        return $disponible && $passerelle instanceof FakePasserelle ? $passerelle : null;
    }
}
