<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\DemanderReinitialisationMotDePasse;
use App\Actions\Auth\ReinitialiserMotDePasse;
use App\Enums\ResultatOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DemanderReinitialisationRequest;
use App\Http\Requests\Auth\ReinitialiserMotDePasseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mot de passe oublié : code SMS puis nouveau mot de passe (CONTEXTE §9).
 * La réponse est identique, que le numéro corresponde à un compte ou non.
 */
class MotDePasseOublieController extends Controller
{
    public const string CLE_SESSION = 'reinitialisation.telephone';

    public function demande(): View
    {
        return view('auth.mot-de-passe-oublie');
    }

    public function envoyerCode(DemanderReinitialisationRequest $request, DemanderReinitialisationMotDePasse $demander): RedirectResponse
    {
        $telephone = $request->telephoneE164();
        $demander->executer($telephone);

        $request->session()->put(self::CLE_SESSION, $telephone);

        return redirect()->route('mot-de-passe.reinitialisation')
            ->with('statut', 'Si un compte correspond à ce numéro, un code vient de vous être envoyé par SMS.');
    }

    public function formulaire(Request $request): View|RedirectResponse
    {
        $telephone = $request->session()->get(self::CLE_SESSION);

        if (! is_string($telephone)) {
            return redirect()->route('mot-de-passe.oublie');
        }

        return view('auth.reinitialiser-mot-de-passe', ['telephone' => $telephone]);
    }

    public function reinitialiser(ReinitialiserMotDePasseRequest $request, ReinitialiserMotDePasse $reinitialiser): RedirectResponse
    {
        $resultat = $reinitialiser->executer(
            (string) $request->session()->get(self::CLE_SESSION),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        if ($resultat !== ResultatOtp::Valide) {
            return back()->withErrors(['code' => $resultat->libelle()]);
        }

        $request->session()->forget(self::CLE_SESSION);

        return redirect()->route('login')->with('statut', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
    }
}
