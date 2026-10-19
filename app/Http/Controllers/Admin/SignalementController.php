<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\TraiterSignalements;
use App\DataTables\SignalementsDataTable;
use App\Models\Publication;
use App\Services\RenduCarte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * D — Signalements (CONTEXTE §13.3.D).
 */
class SignalementController extends ControleurAdmin
{
    public function index(Request $requete, SignalementsDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.signalements.index', ['table' => $table]);
    }

    public function show(int $publication, RenduCarte $rendu): View
    {
        $publication = Publication::withTrashed()->findOrFail($publication);

        return view('admin.signalements.show', [
            'publication' => $publication,
            'carte' => $publication->trashed() ? null : $rendu->chargerEtat(null, [])->rendre($publication),
            'signalements' => $publication->signalements()
                ->with(['utilisateur:id,nom,prenoms', 'traitePar:id,nom,prenoms'])
                ->orderByRaw('traite_le is not null')->latest('cree_le')
                ->get(),
        ]);
    }

    public function decider(Request $requete, int $publication, TraiterSignalements $traiter): RedirectResponse
    {
        $publication = Publication::withTrashed()->findOrFail($publication);
        $decision = $requete->validate(['decision' => ['required', Rule::in(TraiterSignalements::DECISIONS)]])['decision'];

        $nombre = $traiter->executer($publication, $this->admin($requete), $decision);

        return redirect()->route('admin.signalements.index')->with('statut', match ($decision) {
            'retablir' => "Publication rétablie, {$nombre} signalement(s) classé(s).",
            'supprimer' => "Publication supprimée, {$nombre} signalement(s) retenu(s).",
            default => "Publication supprimée et auteur suspendu, {$nombre} signalement(s) retenu(s).",
        });
    }
}
