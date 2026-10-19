<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\EnregistrerModele;
use App\DataTables\SourcesVeilleDataTable;
use App\Http\Requests\Admin\SourceVeilleRequest;
use App\Models\SourceVeille;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * E — Sources de la veille (CONTEXTE §13.3.E).
 */
class SourceVeilleController extends ControleurAdmin
{
    public function __construct(private readonly EnregistrerModele $enregistrer) {}

    public function index(Request $requete, SourcesVeilleDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.veille.sources.index', ['table' => $table]);
    }

    public function create(): View
    {
        return view('admin.veille.sources.formulaire', ['source' => new SourceVeille(['actif' => true])]);
    }

    public function store(SourceVeilleRequest $requete): RedirectResponse
    {
        $this->enregistrer->executer(new SourceVeille, $requete->donnees(), $this->admin($requete), 'source_veille');

        return redirect()->route('admin.veille.sources.index')->with('statut', 'Source ajoutée.');
    }

    public function edit(SourceVeille $source): View
    {
        return view('admin.veille.sources.formulaire', ['source' => $source]);
    }

    public function update(SourceVeilleRequest $requete, SourceVeille $source): RedirectResponse
    {
        $this->enregistrer->executer($source, $requete->donnees(), $this->admin($requete), 'source_veille');

        return redirect()->route('admin.veille.sources.index')->with('statut', 'Source modifiée.');
    }
}
