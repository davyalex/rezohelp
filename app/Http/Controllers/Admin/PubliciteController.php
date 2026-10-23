<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererPublicite;
use App\Actions\Admin\ModifierParametres;
use App\DataTables\PublicitesDataTable;
use App\Enums\EmplacementPublicite;
use App\Http\Requests\Admin\PubliciteRequest;
use App\Models\Parametre;
use App\Models\Publicite;
use App\Services\Publicites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Monétisation — Publicités (D-074), permission publicites.gerer.
 */
class PubliciteController extends ControleurAdmin
{
    public function __construct(private readonly GererPublicite $gerer) {}

    public function index(Request $requete, PublicitesDataTable $table): View|JsonResponse
    {
        if ($requete->ajax()) {
            return $table->reponse($requete);
        }

        // Vue d'ensemble par emplacement : annonces en cours, impressions et clics cumulés.
        $aujourdhui = Carbon::today()->toDateString();
        $statistiques = Publicite::query()
            ->selectRaw('emplacement, sum(nb_impressions) as impressions, sum(nb_clics) as clics')
            ->selectRaw('sum(case when active = 1 and (debut_le is null or debut_le <= ?) and (fin_le is null or fin_le >= ?) then 1 else 0 end) as en_cours', [$aujourdhui, $aujourdhui])
            ->groupBy('emplacement')
            ->toBase()->get()->keyBy('emplacement');

        return view('admin.publicites.index', [
            'table' => $table,
            'actives' => Parametre::booleen('publicites_actives'),
            'contact' => Parametre::texte('publicite_contact'),
            'emplacements' => EmplacementPublicite::cases(),
            'statistiques' => $statistiques,
        ]);
    }

    public function create(Request $requete): View
    {
        $emplacement = EmplacementPublicite::tryFrom((string) $requete->query('emplacement')) ?? EmplacementPublicite::MargeGauche;

        return $this->formulaire(new Publicite(['emplacement' => $emplacement, 'active' => true, 'poids' => 1]));
    }

    public function store(PubliciteRequest $requete): RedirectResponse
    {
        $publicite = $this->gerer->enregistrer(
            new Publicite,
            $requete->donnees(),
            $this->admin($requete),
            $this->fichier($requete, 'image_principale'),
            $this->fichier($requete, 'image_secondaire'),
        );

        return redirect()->route('admin.publicites.edit', $publicite)->with('statut', 'Publicité créée.');
    }

    public function edit(Publicite $publicite): View
    {
        return $this->formulaire($publicite->load('media'));
    }

    public function update(PubliciteRequest $requete, Publicite $publicite): RedirectResponse
    {
        $this->gerer->enregistrer(
            $publicite,
            $requete->donnees(),
            $this->admin($requete),
            $this->fichier($requete, 'image_principale'),
            $this->fichier($requete, 'image_secondaire'),
            $requete->boolean('retirer_image_secondaire'),
        );

        return redirect()->route('admin.publicites.edit', $publicite)->with('statut', 'Publicité enregistrée.');
    }

    /**
     * Réglages généraux des publicités (paramètres publicites_actives et publicite_contact),
     * aussi modifiables depuis la page Paramètres.
     */
    public function reglages(Request $requete, ModifierParametres $modifier): RedirectResponse
    {
        $valeurs = $requete->validate([
            'publicites_actives' => ['required', 'boolean'],
            'publicite_contact' => ['nullable', 'string', 'max:500', 'regex:'.Publicites::LIEN_CONTACT_VALIDE],
        ], [
            'publicite_contact.regex' => 'Indiquez une adresse « mailto:… », un numéro « tel:… », une adresse « https://… » ou un chemin du site commençant par « / ».',
        ], ['publicite_contact' => 'lien « Annoncez ici »']);

        $modifier->executer([
            'publicites_actives' => $valeurs['publicites_actives'],
            'publicite_contact' => (string) ($valeurs['publicite_contact'] ?? ''),
        ], $this->admin($requete));

        return back()->with('statut', 'Réglages des publicités enregistrés.');
    }

    public function basculer(Request $requete, Publicite $publicite): RedirectResponse
    {
        return $this->appliquer(
            fn () => $this->gerer->basculer($publicite, $this->admin($requete)),
            $publicite->active ? 'Publicité désactivée.' : 'Publicité activée.',
        );
    }

    public function destroy(Request $requete, Publicite $publicite): RedirectResponse
    {
        $this->gerer->supprimer($publicite, $this->admin($requete));

        return redirect()->route('admin.publicites.index')->with('statut', 'Publicité supprimée.');
    }

    private function formulaire(Publicite $publicite): View
    {
        return view('admin.publicites.formulaire', [
            'publicite' => $publicite,
            'emplacements' => EmplacementPublicite::cases(),
        ]);
    }

    private function fichier(Request $requete, string $champ): ?UploadedFile
    {
        $fichier = $requete->file($champ);

        return $fichier instanceof UploadedFile ? $fichier : null;
    }
}
