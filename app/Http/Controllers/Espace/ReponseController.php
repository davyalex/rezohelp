<?php

namespace App\Http\Controllers\Espace;

use App\Actions\Publication\ChangerStatutReponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Espace\ChangerStatutReponseRequest;
use App\Models\Publication;
use App\Models\Reponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Réponses reçues par l'auteur d'une publication (CONTEXTE §11.8).
 */
class ReponseController extends Controller
{
    public function index(Publication $publication): View
    {
        $this->authorize('voirReponses', $publication);

        $reponses = $publication->reponses()
            ->with('utilisateur:id,nom,prenoms,telephone,pays_code')
            ->latest('cree_le')
            ->get();

        return view('espace.reponses.index', ['publication' => $publication, 'reponses' => $reponses]);
    }

    public function update(ChangerStatutReponseRequest $request, Reponse $reponse, ChangerStatutReponse $changer): RedirectResponse
    {
        $changer->executer($reponse, $request->statut());

        return back()->with('statut', 'Réponse marquée « '.$request->statut()->libelle().' ».');
    }
}
