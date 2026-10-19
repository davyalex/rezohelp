<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeplacerReferentiel;
use App\Actions\Admin\EnregistrerModele;
use App\DataTables\PaysDataTable;
use App\Http\Requests\Admin\PaysRequest;
use App\Models\Pays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * G — Pays ouverts à l'inscription : activation, ordre, ajout (CONTEXTE §13.3.G).
 */
class PaysController extends ControleurAdmin
{
    public function __construct(private readonly EnregistrerModele $enregistrer) {}

    public function index(Request $requete, PaysDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.pays.index', ['table' => $table]);
    }

    public function create(): View
    {
        return view('admin.pays.formulaire', ['pays' => new Pays(['actif' => true])]);
    }

    public function store(PaysRequest $requete): RedirectResponse
    {
        $this->enregistrer->executer(new Pays, [...$requete->donnees(), 'ordre' => (int) Pays::query()->max('ordre') + 1], $this->admin($requete), 'pays');

        return redirect()->route('admin.pays.index')->with('statut', 'Pays ajouté.');
    }

    public function edit(Pays $pays): View
    {
        return view('admin.pays.formulaire', ['pays' => $pays]);
    }

    public function update(PaysRequest $requete, Pays $pays): RedirectResponse
    {
        $this->enregistrer->executer($pays, $requete->donnees(), $this->admin($requete), 'pays');

        return redirect()->route('admin.pays.index')->with('statut', 'Pays modifié.');
    }

    public function deplacer(Request $requete, Pays $pays, DeplacerReferentiel $deplacer): RedirectResponse
    {
        $deplacer->executer($pays, $requete->validate(['sens' => ['required', Rule::in(['haut', 'bas'])]])['sens'], $this->admin($requete));

        return back();
    }
}
