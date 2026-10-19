<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererPublication;
use App\DataTables\PublicationsDataTable;
use App\Http\Requests\Admin\PublicationRequest;
use App\Models\Publication;
use App\Services\Referentiels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * C — Publications (CONTEXTE §13.3.C).
 */
class PublicationController extends ControleurAdmin
{
    public function __construct(private readonly GererPublication $gerer) {}

    public function index(Request $requete, PublicationsDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.publications.index', ['table' => $table]);
    }

    public function edit(Publication $publication, Referentiels $referentiels): View
    {
        return view('admin.publications.edit', [
            'publication' => $publication,
            'categories' => $referentiels->optionsCategories($publication->type),
            'communes' => collect($referentiels->communes())->mapWithKeys(fn (array $commune): array => [$commune['id'] => $commune['nom']])->all(),
        ]);
    }

    public function update(PublicationRequest $requete, Publication $publication): RedirectResponse
    {
        $this->gerer->modifier($publication, $this->admin($requete), $requete->donnees());

        return redirect()->route('admin.publications.index')->with('statut', 'Publication modifiée.');
    }

    public function depublier(Request $requete, Publication $publication): RedirectResponse
    {
        return $this->appliquer(fn () => $this->gerer->depublier($publication, $this->admin($requete)), 'Publication retirée du fil : elle est de nouveau en modération.');
    }

    public function prolonger(Request $requete, Publication $publication): RedirectResponse
    {
        return $this->appliquer(fn () => $this->gerer->prolonger($publication, $this->admin($requete)), 'Publication prolongée.');
    }

    public function destroy(Request $requete, Publication $publication): RedirectResponse
    {
        $this->gerer->supprimer($publication, $this->admin($requete));

        return redirect()->route('admin.publications.index')->with('statut', 'Publication supprimée.');
    }
}
