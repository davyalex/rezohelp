<?php

namespace App\Http\Controllers;

use App\Enums\CibleOffre;
use App\Services\Offres;
use Illuminate\View\View;

/**
 * Page publique /tarifs (CONTEXTE §14.11), générée à partir des plans visibles et actifs.
 */
class TarifController extends Controller
{
    public function index(Offres $offres): View
    {
        return view('tarifs', [
            'grille' => $offres->grillePublique(),
            'cibles' => CibleOffre::cases(),
            'produits' => $offres->produitsActifs(),
        ]);
    }
}
