<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StatistiquesService;
use Illuminate\View\View;

/**
 * A — Tableau de bord (CONTEXTE §13.3.A).
 */
class TableauDeBordController extends Controller
{
    public function index(StatistiquesService $statistiques): View
    {
        return view('admin.tableau-de-bord', [
            'compteurs' => $statistiques->compteurs(),
            'dernierImport' => $statistiques->dernierImport(),
            'importEnRetard' => $statistiques->importEnRetard(),
            'serie' => $statistiques->serie30Jours(),
        ]);
    }
}
