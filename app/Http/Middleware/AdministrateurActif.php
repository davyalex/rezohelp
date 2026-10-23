<?php

namespace App\Http\Middleware;

use App\Models\Administrateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office (D-066) : un compte suspendu est déconnecté au clic suivant ; un mot de passe généré
 * doit être changé avant toute autre page.
 */
class AdministrateurActif
{
    /** Pages accessibles tant que le mot de passe provisoire n'est pas changé. */
    private const array ROUTES_AUTORISEES = ['admin.mot-de-passe.edit', 'admin.mot-de-passe.update', 'admin.deconnexion'];

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user(Administrateur::GARDE);

        if ($admin instanceof Administrateur && $admin->estSuspendu()) {
            Auth::guard(Administrateur::GARDE)->logout();
            $request->session()->regenerate();

            return redirect()->route('admin.connexion')
                ->withErrors(['nom_utilisateur' => 'Ce compte est suspendu. Contactez un super administrateur.']);
        }

        if ($admin instanceof Administrateur && $admin->doit_changer_mot_de_passe && ! $request->routeIs(...self::ROUTES_AUTORISEES)) {
            return redirect()->route('admin.mot-de-passe.edit');
        }

        return $next($request);
    }
}
