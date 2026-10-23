<?php

namespace App\Http\Controllers;

use App\Models\Publicite;
use App\Services\Publicites;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Suivi des publicités (D-074) : clic compté puis redirection vers l'annonceur ;
 * impressions signalées par le navigateur quand la bannière devient visible.
 */
class PubliciteController extends Controller
{
    public function __construct(private readonly Publicites $publicites) {}

    public function clic(Publicite $publicite): RedirectResponse
    {
        $this->publicites->compterClic($publicite);

        // Le lien est saisi par l'équipe et validé (https) : redirection externe volontaire.
        return redirect()->away($publicite->lien);
    }

    public function vues(Request $requete): Response
    {
        $ids = array_slice(array_values(array_filter(array_map('intval', (array) $requete->input('ids', [])))), 0, 10);
        $this->publicites->compterImpressions($ids);

        return response()->noContent();
    }
}
