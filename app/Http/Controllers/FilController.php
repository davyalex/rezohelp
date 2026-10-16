<?php

namespace App\Http\Controllers;

use App\Enums\TypePublication;
use App\Http\Requests\FilRequest;
use App\Jobs\EnregistrerRecherche;
use App\Models\Utilisateur;
use App\Services\FilService;
use App\Services\RenduCarte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Fil public façon réseau social : première salve rendue par le serveur,
 * suites en fragments HTML par curseur (CONTEXTE §8).
 */
class FilController extends Controller
{
    public function __construct(
        private readonly FilService $fil,
        private readonly RenduCarte $rendu,
    ) {}

    public function index(FilRequest $request, ?string $type = null): View
    {
        $filtres = $request->filtres(TypePublication::tryFrom((string) $type));
        $salve = $this->fil->salve($filtres, $request->curseur());

        if ($filtres->recherche !== null && $request->curseur() === null) {
            EnregistrerRecherche::dispatch($filtres, $this->visiteur($request)?->id);
        }

        return view('fil.index', [
            'filtres' => $filtres,
            'salve' => $salve,
            'rendu' => $this->rendu->chargerEtat($this->visiteur($request), $salve->publications),
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
        $visiteur = $this->visiteur($request);

        return response()
            ->view('fil._cartes', [
                'salve' => $salve,
                'rendu' => $this->rendu->chargerEtat($visiteur, $salve->publications),
            ])
            ->header('X-Curseur-Suivant', $salve->curseurSuivant ?? '')
            ->header('Cache-Control', $visiteur === null ? 'public, max-age=60' : 'private, no-store');
    }

    /**
     * Nombre de publications parues depuis la première carte affichée.
     */
    public function nouvelles(FilRequest $request): JsonResponse
    {
        $depuis = $request->date('depuis') ?? Carbon::now();

        return response()->json(['nombre' => $this->fil->nouvelles($request->filtres(), $depuis)]);
    }

    private function visiteur(FilRequest $request): ?Utilisateur
    {
        $utilisateur = $request->user();

        return $utilisateur instanceof Utilisateur ? $utilisateur : null;
    }
}
