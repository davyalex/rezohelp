<?php

namespace App\Http\Middleware;

use App\Models\Utilisateur;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte immédiatement un membre suspendu pendant sa session.
 */
class VerifierCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        // Le back-office a ses propres comptes (D-066) : la session membre n'y intervient pas.
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $utilisateur = $request->user('web');

        if ($utilisateur instanceof Utilisateur && $utilisateur->estSuspendu()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['telephone' => $utilisateur->messageSuspension()]);
        }

        return $next($request);
    }
}
