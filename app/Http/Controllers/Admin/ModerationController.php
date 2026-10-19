<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\MarquerDoublon;
use App\Actions\Admin\ModererLot;
use App\Actions\Admin\RejeterPublication;
use App\Actions\Admin\ValiderPublication;
use App\DataTables\ModerationDataTable;
use App\Enums\StatutPublication;
use App\Exceptions\OperationImpossibleException;
use App\Http\Requests\Admin\ModererLotRequest;
use App\Http\Requests\Admin\RejeterPublicationRequest;
use App\Models\Publication;
use App\Services\RenduCarte;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * B — Modération (CONTEXTE §13.3.B) : file des publications en attente, détail avec la carte
 * exacte du fil, validation, rejet, doublon et actions groupées ; après chaque décision,
 * passage automatique à la publication suivante.
 */
class ModerationController extends ControleurAdmin
{
    public function index(Request $requete, ModerationDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.moderation.index', ['table' => $table]);
    }

    public function show(Publication $publication, RenduCarte $rendu): View
    {
        $publication->loadMissing(['auteur:id,nom,prenoms,confiance,cree_le,suspendu_le,suspendu_jusqu_au', 'entreprise:id,nom,verifiee', 'categorie:id,nom']);

        $historique = null;

        if ($publication->auteur_id !== null) {
            $publicationsAuteur = Publication::withTrashed()->where('auteur_id', $publication->auteur_id)->whereKeyNot($publication->id);
            $historique = [
                'total' => (clone $publicationsAuteur)->count(),
                'validees' => (clone $publicationsAuteur)->whereNotNull('publiee_le')->count(),
                'rejetees' => (clone $publicationsAuteur)->where('statut', StatutPublication::Rejetee->value)->count(),
            ];
        }

        return view('admin.moderation.show', [
            'publication' => $publication,
            'carte' => $rendu->chargerEtat(null, [])->rendre($publication),
            'historique' => $historique,
            'similaires' => $this->similaires($publication),
            'suivante' => $this->suivante($publication),
        ]);
    }

    public function valider(Request $requete, Publication $publication, ValiderPublication $valider): RedirectResponse
    {
        try {
            $valider->executer($publication, $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return $this->apres($publication, 'Publication validée : elle est en ligne.');
    }

    public function rejeter(RejeterPublicationRequest $requete, Publication $publication, RejeterPublication $rejeter): RedirectResponse
    {
        try {
            $rejeter->executer($publication, $this->admin($requete), $requete->motif(), $requete->detail());
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return $this->apres($publication, 'Publication refusée.');
    }

    public function doublon(Request $requete, Publication $publication, MarquerDoublon $marquer): RedirectResponse
    {
        $donnees = $requete->validate(['publication_originale_id' => ['required', 'integer', 'exists:publications,id']]);

        try {
            $marquer->executer($publication, Publication::query()->findOrFail($donnees['publication_originale_id']), $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return $this->apres($publication, 'Publication refusée comme doublon.');
    }

    public function lot(ModererLotRequest $requete, ModererLot $lot): RedirectResponse
    {
        $action = $requete->string('action')->toString();
        $nombre = $lot->executer($requete->ids(), $action, $this->admin($requete), $requete->motif(), $requete->detail());

        return redirect()->route('admin.moderation.index')
            ->with('statut', $nombre.' publication(s) '.($action === 'valider' ? 'validée(s)' : 'refusée(s)').'.');
    }

    /**
     * Après une décision : la prochaine publication de la file, ou la liste si elle est vide.
     */
    private function apres(Publication $traitee, string $message): RedirectResponse
    {
        $suivante = $this->suivante($traitee);

        return $suivante !== null
            ? redirect()->route('admin.moderation.show', $suivante)->with('statut', $message)
            : redirect()->route('admin.moderation.index')->with('statut', $message.' La file de modération est vide.');
    }

    private function suivante(Publication $courante): ?Publication
    {
        return ModerationDataTable::fileDAttente()
            ->whereKeyNot($courante->id)
            ->orderBy('score_fiabilite')
            ->orderBy('id')
            ->first(['id']);
    }

    /**
     * Même annonce d'origine, ou même titre chez le même auteur ou la même entreprise.
     *
     * @return Collection<int, Publication>
     */
    private function similaires(Publication $publication): Collection
    {
        if ($publication->source_empreinte === null && $publication->auteur_id === null && $publication->entreprise_id === null) {
            return new Collection;
        }

        return Publication::query()
            ->whereKeyNot($publication->id)
            ->where(function ($requete) use ($publication): void {
                if ($publication->source_empreinte !== null) {
                    $requete->orWhere('source_empreinte', $publication->source_empreinte);
                }

                $requete->orWhere(fn ($meme) => $meme->where('titre', $publication->titre)
                    ->where(fn ($proprietaire) => $proprietaire
                        ->when($publication->entreprise_id !== null, fn ($r) => $r->orWhere('entreprise_id', $publication->entreprise_id))
                        ->when($publication->auteur_id !== null, fn ($r) => $r->orWhere('auteur_id', $publication->auteur_id))));
            })
            ->latest('cree_le')
            ->limit(5)
            ->get(['id', 'titre', 'statut', 'slug', 'cree_le']);
    }
}
