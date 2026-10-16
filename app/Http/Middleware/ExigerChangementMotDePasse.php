<?php

namespace App\Http\Middleware;

use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Après une réinitialisation par l'administrateur (mot de passe temporaire),
 * le membre doit choisir un nouveau mot de passe avant toute autre action.
 */
class ExigerChangementMotDePasse
{
    private const array ROUTES_AUTORISEES = ['mot-de-passe.changement', 'mot-de-passe.changer', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur instanceof Utilisateur
            && $utilisateur->doit_changer_mot_de_passe
            && ! $request->routeIs(...self::ROUTES_AUTORISEES)) {
            return redirect()->route('mot-de-passe.changement');
        }

        return $next($request);
    }
}
