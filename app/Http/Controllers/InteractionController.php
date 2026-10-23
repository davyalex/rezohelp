<?php

namespace App\Http\Controllers;

use App\Actions\Publication\BasculerEnregistrement;
use App\Actions\Publication\BasculerJaime;
use App\Actions\Publication\PublierCommentaire;
use App\Actions\Publication\SignalerPublication;
use App\Actions\Publication\SupprimerCommentaire;
use App\Http\Requests\CommentaireRequest;
use App\Http\Requests\SignalementRequest;
use App\Models\Administrateur;
use App\Models\Commentaire;
use App\Models\Publication;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * J'aime, enregistrement, commentaires et signalement d'une publication.
 * Réponses JSON pour l'interface optimiste (Alpine), redirection sans JavaScript.
 */
class InteractionController extends Controller
{
    public function jaime(Request $request, Publication $publication, BasculerJaime $basculer): JsonResponse|RedirectResponse
    {
        $this->authorize('interagir', $publication);

        $resultat = $basculer->executer($this->membre($request), $publication);

        return $request->expectsJson() ? response()->json($resultat) : back();
    }

    public function enregistrer(Request $request, Publication $publication, BasculerEnregistrement $basculer): JsonResponse|RedirectResponse
    {
        $this->authorize('interagir', $publication);

        $enregistree = $basculer->executer($this->membre($request), $publication);

        return $request->expectsJson()
            ? response()->json(['actif' => $enregistree])
            : back()->with('statut', $enregistree ? 'Publication enregistrée.' : 'Publication retirée de vos enregistrements.');
    }

    public function commenter(CommentaireRequest $request, Publication $publication, PublierCommentaire $publier): RedirectResponse
    {
        $commentaire = $publier->executer($this->membre($request), $publication, $request->string('contenu')->toString(), $request->parent());

        return redirect()->to(route('publications.show', $publication->slug).'#commentaire-'.$commentaire->id);
    }

    public function supprimerCommentaire(Request $request, Commentaire $commentaire, SupprimerCommentaire $supprimer): RedirectResponse
    {
        // L'auteur (session membre) ou l'équipe (session back-office, D-066).
        abort_unless(
            (bool) $request->user('web')?->can('delete', $commentaire) || (bool) $request->user(Administrateur::GARDE)?->can('delete', $commentaire),
            403,
        );

        $supprimer->executer($commentaire);

        return back()->with('statut', 'Commentaire supprimé.');
    }

    public function signaler(SignalementRequest $request, Publication $publication, SignalerPublication $signaler): RedirectResponse
    {
        $signaler->executer($this->membre($request), $publication, $request->motif(), $request->input('commentaire'));

        return redirect()->route('publications.show', $publication->slug)
            ->with('statut', 'Merci, votre signalement a été transmis à l’équipe de modération.');
    }

    private function membre(Request $request): Utilisateur
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 401);

        return $utilisateur;
    }
}
