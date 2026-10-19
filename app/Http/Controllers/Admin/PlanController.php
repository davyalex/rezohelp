<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\EnregistrerPlan;
use App\DataTables\PlansDataTable;
use App\Enums\CibleOffre;
use App\Exceptions\OperationImpossibleException;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * K — Plans et éditeur de limites (CONTEXTE §14.10). Un plan souscrit se désactive, il ne se supprime pas.
 */
class PlanController extends ControleurAdmin
{
    public function __construct(private readonly EnregistrerPlan $enregistrer) {}

    public function index(Request $requete, PlansDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.plans.index', ['table' => $table]);
    }

    public function create(): View
    {
        return $this->formulaire(new Plan(['cible' => CibleOffre::Membre, 'actif' => false, 'visible' => true, 'duree_jours' => 30, 'duree_essai_jours' => 0, 'prix_fcfa' => 0, 'ordre' => 10]));
    }

    public function store(PlanRequest $requete): RedirectResponse
    {
        $plan = $this->enregistrer->executer(new Plan, $requete->attributsPlan(), $requete->limites(), $this->admin($requete));

        return redirect()->route('admin.plans.edit', $plan)->with('statut', 'Plan créé.');
    }

    public function edit(Plan $plan): View
    {
        return $this->formulaire($plan->load('limites'));
    }

    public function update(PlanRequest $requete, Plan $plan): RedirectResponse
    {
        $this->enregistrer->executer($plan, $requete->attributsPlan(), $requete->limites(), $this->admin($requete));

        return redirect()->route('admin.plans.edit', $plan)->with('statut', 'Plan enregistré : les droits des abonnés sont recalculés.');
    }

    public function destroy(Request $requete, Plan $plan): RedirectResponse
    {
        try {
            $this->enregistrer->supprimer($plan, $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.plans.index')->with('statut', 'Plan supprimé.');
    }

    private function formulaire(Plan $plan): View
    {
        return view('admin.plans.formulaire', [
            'plan' => $plan,
            'limites' => $plan->exists ? $plan->limites->mapWithKeys(fn ($l): array => [$l->cle->value => (string) $l->valeur])->all() : [],
        ]);
    }
}
