<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse;

/**
 * Après l'inscription, le membre est invité à vérifier son numéro (code SMS).
 * La page d'origine (redirect()->intended()) reste en session pour la suite.
 */
class ReponseInscription implements RegisterResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        return redirect()->route('telephone.verification');
    }
}
