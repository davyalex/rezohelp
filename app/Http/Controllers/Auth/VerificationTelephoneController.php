<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Otp\EnvoyerCodeOtp;
use App\Actions\Otp\VerifierCodeOtp;
use App\Enums\ObjetOtp;
use App\Enums\ResultatOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifierTelephoneRequest;
use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vérification du numéro par code SMS (telephone_verifie_le).
 */
class VerificationTelephoneController extends Controller
{
    public function afficher(Request $request): View|RedirectResponse
    {
        $utilisateur = $this->utilisateur($request);

        if ($utilisateur->telephone_verifie_le !== null) {
            return redirect()->intended(route('fil.index'));
        }

        return view('auth.verification-telephone', ['telephone' => (string) $utilisateur->telephone]);
    }

    public function envoyer(Request $request, EnvoyerCodeOtp $envoyerCodeOtp): RedirectResponse
    {
        $utilisateur = $this->utilisateur($request);

        if ($utilisateur->telephone_verifie_le === null && $utilisateur->telephone !== null) {
            $envoyerCodeOtp->executer($utilisateur->telephone, ObjetOtp::Verification, $utilisateur);
        }

        return back()->with('statut', 'Un nouveau code vient de vous être envoyé par SMS.');
    }

    public function verifier(VerifierTelephoneRequest $request, VerifierCodeOtp $verifierCodeOtp): RedirectResponse
    {
        $utilisateur = $this->utilisateur($request);

        $resultat = $verifierCodeOtp->executer((string) $utilisateur->telephone, ObjetOtp::Verification, $request->string('code')->toString());

        if ($resultat !== ResultatOtp::Valide) {
            return back()->withErrors(['code' => $resultat->libelle()]);
        }

        $utilisateur->forceFill(['telephone_verifie_le' => now()])->save();

        return redirect()->intended(route('fil.index'))->with('statut', 'Votre numéro est vérifié. Merci !');
    }

    private function utilisateur(Request $request): Utilisateur
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 403);

        return $utilisateur;
    }
}
