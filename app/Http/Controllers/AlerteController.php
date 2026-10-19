<?php

namespace App\Http\Controllers;

use App\Actions\Alerte\BasculerAlerte;
use App\Actions\Alerte\EnregistrerAlerte;
use App\Enums\CleDroit;
use App\Enums\TypeContrat;
use App\Enums\TypePublication;
use App\Exceptions\QuotaAtteintException;
use App\Http\Requests\AlerteRequest;
use App\Models\Alerte;
use App\Models\Utilisateur;
use App\Services\CorrespondanceAlertes;
use App\Services\Offres;
use App\Services\Referentiels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * « Mon espace → Alertes » (CONTEXTE §10) et création depuis les filtres du fil.
 */
class AlerteController extends Controller
{
    public function index(Request $request, CorrespondanceAlertes $correspondance, Offres $offres): View
    {
        $membre = $this->membre($request);
        $alertes = $membre->alertes()->latest('cree_le')->latest('id')->get();

        return view('alertes.index', [
            'alertes' => $alertes,
            'correspondance' => $correspondance,
            'autorisation' => Gate::inspect('create', Alerte::class),
            // Juste après la création d'une alerte : « Recevez-la aussi par SMS » (§14.11).
            'essaiSms' => $request->session()->has('alerte_creee')
                ? $offres->essaiPour($membre, $membre, CleDroit::AlertesCanalSms)
                : null,
        ]);
    }

    /**
     * Formulaire pré-rempli avec les filtres du fil (type, commune, catégorie, contrat, recherche).
     */
    public function create(Request $request, Referentiels $referentiels): View
    {
        $autorisation = Gate::inspect('create', Alerte::class);

        if ($autorisation->denied()) {
            return view('alertes.limite', ['message' => $autorisation->message()]);
        }

        $categorie = $request->filled('categorie') ? $referentiels->categorieParSlug($request->string('categorie')->toString()) : null;
        $commune = $request->filled('commune') ? $referentiels->communeParSlug($request->string('commune')->toString()) : null;

        return view('alertes.formulaire', [
            'alerte' => null,
            'valeurs' => [
                'type' => TypePublication::tryFrom($request->string('type')->toString())->value ?? $categorie['type'] ?? null,
                'categorie_id' => $categorie['id'] ?? null,
                'commune_id' => $commune['id'] ?? null,
                'type_contrat' => TypeContrat::tryFrom($request->string('contrat')->toString())?->value,
                'mots_cles' => $request->filled('q') ? mb_substr($request->string('q')->toString(), 0, 150) : null,
                'frequence' => 'instantanee',
            ],
        ]);
    }

    public function store(AlerteRequest $request, EnregistrerAlerte $enregistrer): RedirectResponse
    {
        $alerte = $enregistrer->executer($this->membre($request), $request->validated());

        return redirect()->route('alertes.index')
            ->with('statut', 'Alerte « '.$alerte->nom.' » créée. Vous serez prévenu des nouvelles publications.')
            ->with('alerte_creee', true);
    }

    public function edit(Alerte $alerte): View
    {
        $this->authorize('update', $alerte);

        return view('alertes.formulaire', [
            'alerte' => $alerte,
            'valeurs' => [
                'nom' => $alerte->nom,
                'type' => $alerte->type?->value,
                'categorie_id' => $alerte->categorie_id,
                'commune_id' => $alerte->commune_id,
                'type_contrat' => $alerte->type_contrat?->value,
                'mots_cles' => $alerte->mots_cles,
                'frequence' => $alerte->frequence->value,
            ],
        ]);
    }

    public function update(AlerteRequest $request, Alerte $alerte, EnregistrerAlerte $enregistrer): RedirectResponse
    {
        $enregistrer->executer($this->membre($request), $request->validated(), $alerte);

        return redirect()->route('alertes.index')->with('statut', 'Alerte modifiée.');
    }

    public function basculer(Alerte $alerte, BasculerAlerte $basculer): RedirectResponse
    {
        $this->authorize('update', $alerte);

        try {
            $basculer->executer($alerte);
        } catch (QuotaAtteintException $exception) {
            return back()->withErrors(['alerte' => 'Vous avez atteint le nombre maximal d’alertes actives ('.$exception->limite.').']);
        }

        return back()->with('statut', $alerte->active ? 'Alerte réactivée.' : 'Alerte en pause.');
    }

    public function destroy(Alerte $alerte): RedirectResponse
    {
        $this->authorize('delete', $alerte);

        $alerte->delete();

        return back()->with('statut', 'Alerte supprimée.');
    }

    /**
     * « Créer une alerte » depuis le fil : un visiteur crée d'abord son compte (§4.2),
     * puis arrive sur le formulaire pré-rempli avec ses filtres.
     */
    public function depuisFil(Request $request): RedirectResponse
    {
        $cible = route('alertes.create', $request->only(['type', 'commune', 'categorie', 'contrat', 'q']));

        if ($request->user() !== null) {
            return redirect()->to($cible);
        }

        $request->session()->put('url.intended', $cible);

        return redirect()->route('register');
    }

    private function membre(Request $request): Utilisateur
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 401);

        return $utilisateur;
    }
}
