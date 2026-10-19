<?php

namespace App\Http\Controllers;

use App\Donnees\SalveFil;
use App\Enums\TypePublication;
use App\Http\Requests\FilRequest;
use App\Jobs\EnregistrerRecherche;
use App\Models\MiseEnAvant;
use App\Models\Utilisateur;
use App\Services\EmplacementsSponsorises;
use App\Services\FilService;
use App\Services\RenduCarte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Fil public façon réseau social : première salve rendue par le serveur,
 * suites en fragments HTML par curseur (CONTEXTE §8). Les cartes payées sont
 * ajoutées hors curseur par EmplacementsSponsorises (§14.9).
 */
class FilController extends Controller
{
    public function __construct(
        private readonly FilService $fil,
        private readonly RenduCarte $rendu,
        private readonly EmplacementsSponsorises $emplacements,
    ) {}

    public function index(FilRequest $request, ?string $type = null): View
    {
        $filtres = $request->filtres(TypePublication::tryFrom((string) $type));
        $salve = $this->fil->salve($filtres, $request->curseur());

        if ($filtres->recherche !== null && $request->curseur() === null) {
            EnregistrerRecherche::dispatch($filtres, $this->visiteur($request)?->id);
        }

        // « À la une » au-dessus d'un fil filtré ; ces cartes ne sont pas répétées dans la salve.
        $aLaUne = $request->curseur() === null ? $this->emplacements->aLaUne($filtres) : collect();
        $salve = new SalveFil(
            $salve->publications->whereNotIn('id', $aLaUne->pluck('publication_id'))->values(),
            $salve->curseurSuivant,
        );
        $sponsorisees = $this->emplacements->pourSalve($filtres, $salve->publications, $request->deja());

        return view('fil.index', [
            'filtres' => $filtres,
            'salve' => $salve,
            'aLaUne' => $aLaUne,
            'sponsorisees' => $sponsorisees,
            'deja' => $request->deja(),
            'rendu' => $this->chargerEtat($request, $salve, [...$aLaUne->all(), ...$sponsorisees]),
            'communesPopulaires' => $this->fil->communesPopulaires(),
            'formationsRecentes' => $this->fil->formationsRecentes(),
        ]);
    }

    /**
     * Salve suivante : fragment HTML + en-tête X-Curseur-Suivant (vide à la fin du fil).
     */
    public function suite(FilRequest $request): Response
    {
        $filtres = $request->filtres();
        $salve = $this->fil->salve($filtres, $request->curseur());
        $sponsorisees = $this->emplacements->pourSalve($filtres, $salve->publications, $request->deja());
        $visiteur = $this->visiteur($request);

        return response()
            ->view('fil._cartes', [
                'salve' => $salve,
                'sponsorisees' => $sponsorisees,
                'rendu' => $this->chargerEtat($request, $salve, $sponsorisees),
            ])
            ->header('X-Curseur-Suivant', $salve->curseurSuivant ?? '')
            // Les cartes sponsorisées tournent : le fragment n'est gardé que peu de temps.
            ->header('Cache-Control', $visiteur === null && $sponsorisees === [] ? 'public, max-age=60' : 'private, no-store');
    }

    /**
     * Nombre de publications parues depuis la première carte affichée.
     */
    public function nouvelles(FilRequest $request): JsonResponse
    {
        $depuis = $request->date('depuis') ?? Carbon::now();

        return response()->json(['nombre' => $this->fil->nouvelles($request->filtres(), $depuis)]);
    }

    /**
     * Clic sur une carte mise en avant (envoyé par fil.js avant d'ouvrir la publication).
     */
    public function clic(MiseEnAvant $miseEnAvant): Response
    {
        $this->emplacements->compterClic($miseEnAvant);

        return response()->noContent();
    }

    /**
     * État du visiteur (J'aime, enregistré) chargé en une requête pour toutes les cartes affichées.
     *
     * @param  array<int, MiseEnAvant>  $misesEnAvant
     */
    private function chargerEtat(FilRequest $request, SalveFil $salve, array $misesEnAvant): RenduCarte
    {
        $publications = $salve->publications->concat(array_map(fn (MiseEnAvant $mise) => $mise->publication, $misesEnAvant));

        return $this->rendu->chargerEtat($this->visiteur($request), $publications);
    }

    private function visiteur(FilRequest $request): ?Utilisateur
    {
        $utilisateur = $request->user();

        return $utilisateur instanceof Utilisateur ? $utilisateur : null;
    }
}
