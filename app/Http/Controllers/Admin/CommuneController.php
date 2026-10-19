<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeplacerReferentiel;
use App\Actions\Admin\EnregistrerModele;
use App\DataTables\CommunesDataTable;
use App\Http\Requests\Admin\CommuneRequest;
use App\Models\Commune;
use App\Models\Pays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * G — Communes : activation, ordre, ajout (CONTEXTE §13.3.G).
 */
class CommuneController extends ControleurAdmin
{
    public function __construct(private readonly EnregistrerModele $enregistrer) {}

    public function index(Request $requete, CommunesDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.communes.index', ['table' => $table]);
    }

    public function create(): View
    {
        return $this->formulaire(new Commune(['pays_code' => 'CI', 'ville' => 'Abidjan', 'actif' => true]));
    }

    public function store(CommuneRequest $requete): RedirectResponse
    {
        $donnees = $requete->donnees();
        $donnees['ordre'] = (int) Commune::query()->where('pays_code', $donnees['pays_code'])->where('ville', $donnees['ville'])->max('ordre') + 1;
        $this->enregistrer->executer(new Commune, $donnees, $this->admin($requete), 'commune');

        return redirect()->route('admin.communes.index')->with('statut', 'Commune ajoutée.');
    }

    public function edit(Commune $commune): View
    {
        return $this->formulaire($commune);
    }

    public function update(CommuneRequest $requete, Commune $commune): RedirectResponse
    {
        $this->enregistrer->executer($commune, $requete->donnees(), $this->admin($requete), 'commune');

        return redirect()->route('admin.communes.index')->with('statut', 'Commune modifiée.');
    }

    public function deplacer(Request $requete, Commune $commune, DeplacerReferentiel $deplacer): RedirectResponse
    {
        $deplacer->executer($commune, $requete->validate(['sens' => ['required', Rule::in(['haut', 'bas'])]])['sens'], $this->admin($requete));

        return back();
    }

    private function formulaire(Commune $commune): View
    {
        return view('admin.communes.formulaire', [
            'commune' => $commune,
            'pays' => Pays::query()->orderBy('ordre')->pluck('nom', 'code')->all(),
        ]);
    }
}
