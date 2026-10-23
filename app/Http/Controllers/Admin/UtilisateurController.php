<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererUtilisateur;
use App\DataTables\UtilisateursDataTable;
use App\Exceptions\OperationImpossibleException;
use App\Http\Requests\Admin\SuspendreUtilisateurRequest;
use App\Http\Requests\Admin\UtilisateurRequest;
use App\Models\JournalActivite;
use App\Models\Publication;
use App\Models\Signalement;
use App\Models\Utilisateur;
use App\Services\Droits;
use App\Services\Referentiels;
use App\Services\TelephoneService;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F — Utilisateurs (CONTEXTE §13.3.F).
 */
class UtilisateurController extends ControleurAdmin
{
    public function __construct(private readonly GererUtilisateur $gerer) {}

    public function index(Request $requete, UtilisateursDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.utilisateurs.index', [
            'table' => $table,
            'roles' => array_combine(RolesEtPermissionsSeeder::ROLES_MEMBRES, RolesEtPermissionsSeeder::ROLES_MEMBRES),
        ]);
    }

    public function show(Utilisateur $utilisateur, TelephoneService $telephones): View
    {
        $utilisateur->load(['roles:id,name', 'commune:id,nom', 'entreprises:id,nom,proprietaire_id,verifiee']);
        $publications = Publication::withTrashed()->where('auteur_id', $utilisateur->id);

        return view('admin.utilisateurs.show', [
            'utilisateur' => $utilisateur,
            'telephone' => $utilisateur->telephone !== null ? $telephones->afficher($utilisateur->telephone, 'CI') : null,
            'publications' => (clone $publications)->latest('cree_le')->limit(20)->get(['id', 'titre', 'statut', 'type', 'cree_le', 'supprime_le']),
            'nbPublications' => (clone $publications)->count(),
            'nbAlertes' => $utilisateur->alertes()->where('active', true)->count(),
            'signalementsRecus' => Signalement::query()
                ->whereIn('publication_id', (clone $publications)->select('id'))
                ->with('publication:id,titre')
                ->latest('cree_le')->limit(10)->get(),
            'sanctions' => JournalActivite::query()
                ->where('sujet_type', $utilisateur->getMorphClass())->where('sujet_id', $utilisateur->id)
                ->with('administrateur:id,nom,prenoms')
                ->latest('cree_le')->limit(20)->get(),
            'abonnements' => $utilisateur->abonnements()->whereIn('statut', array_map(fn ($s) => $s->value, Droits::STATUTS_EN_COURS))->with('plan:id,nom')->get(),
            'roles' => RolesEtPermissionsSeeder::ROLES_MEMBRES,
        ]);
    }

    public function edit(Utilisateur $utilisateur, Referentiels $referentiels): View
    {
        return view('admin.utilisateurs.edit', [
            'utilisateur' => $utilisateur,
            'communes' => collect($referentiels->communes())->mapWithKeys(fn (array $c): array => [$c['id'] => $c['nom']])->all(),
        ]);
    }

    public function update(UtilisateurRequest $requete, Utilisateur $utilisateur): RedirectResponse
    {
        $this->gerer->modifier($utilisateur, $this->admin($requete), $requete->donnees());

        return redirect()->route('admin.utilisateurs.show', $utilisateur)->with('statut', 'Compte modifié.');
    }

    public function suspendre(SuspendreUtilisateurRequest $requete, Utilisateur $utilisateur): RedirectResponse
    {
        return $this->appliquer(
            fn () => $this->gerer->suspendre($utilisateur, $this->admin($requete), $requete->string('motif_suspension')->toString(), $requete->jusquau()),
            'Compte suspendu : ses publications en ligne sont retirées du fil.',
        );
    }

    public function reactiver(Request $requete, Utilisateur $utilisateur): RedirectResponse
    {
        return $this->appliquer(fn () => $this->gerer->reactiver($utilisateur, $this->admin($requete)), 'Compte réactivé.');
    }

    public function reinitialiserMotDePasse(Request $requete, Utilisateur $utilisateur): RedirectResponse
    {
        $temporaire = $this->gerer->reinitialiserMotDePasse($utilisateur, $this->admin($requete));

        // Affiché une seule fois (donnée flash), jamais journalisé.
        return back()->with('mot_de_passe_temporaire', $temporaire);
    }

    public function roles(Request $requete, Utilisateur $utilisateur): RedirectResponse
    {
        $roles = $requete->validate(['roles' => ['array'], 'roles.*' => ['string', Rule::in(RolesEtPermissionsSeeder::ROLES_MEMBRES)]])['roles'] ?? [];

        return $this->appliquer(fn () => $this->gerer->roles($utilisateur, $this->admin($requete), array_values($roles)), 'Rôles mis à jour.');
    }

    public function leverRestriction(Request $requete, Utilisateur $utilisateur): RedirectResponse
    {
        return $this->appliquer(fn () => $this->gerer->leverRestriction($utilisateur, $this->admin($requete)), 'Restriction levée : le compte peut de nouveau publier.');
    }

    public function destroy(Request $requete, Utilisateur $utilisateur): RedirectResponse
    {
        try {
            $this->gerer->anonymiser($utilisateur, $this->admin($requete));
        } catch (OperationImpossibleException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }

        return redirect()->route('admin.utilisateurs.index')->with('statut', 'Compte supprimé et anonymisé.');
    }
}
