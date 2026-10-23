<?php

namespace App\Http\Controllers\Espace;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Services\RecuCommande;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Reçu d'une commande payée (CONTEXTE §14.7) : page imprimable et PDF, avec les mentions
 * légales des paramètres (raison sociale, RCCM, NCC). Le back-office a ses propres routes.
 */
class RecuController extends Controller
{
    public function __construct(private readonly RecuCommande $recus) {}

    public function show(Commande $commande): View
    {
        $this->authorize('voirRecu', $commande);

        return view('espace.recus.show', $this->recus->donnees($commande));
    }

    public function pdf(Commande $commande): Response
    {
        $this->authorize('voirRecu', $commande);

        return $this->recus->pdf($commande);
    }
}
