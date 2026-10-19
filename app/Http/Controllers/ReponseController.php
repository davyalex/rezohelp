<?php

namespace App\Http\Controllers;

use App\Actions\Publication\RepondrePublication;
use App\Exceptions\QuotaAtteintException;
use App\Http\Requests\RepondreRequest;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;

/**
 * Candidature, inscription, proposition ou demande de contact depuis la page d'une publication.
 */
class ReponseController extends Controller
{
    public function store(RepondreRequest $request, Publication $publication, RepondrePublication $repondre): RedirectResponse
    {
        $membre = $request->user();
        abort_unless($membre instanceof Utilisateur, 401);
        $retour = route('publications.show', $publication->slug).'#repondre';

        try {
            $repondre->executer($membre, $publication, $request->input('message'), $request->boolean('partager_telephone'));
        } catch (QuotaAtteintException) {
            return redirect()->to($retour)->withErrors(['message' => 'Vous avez atteint votre nombre de réponses aux demandes pour ce mois-ci.']);
        }

        return redirect()->to($retour)->with('statut', 'Votre réponse a été envoyée. L’auteur a été prévenu.');
    }
}
