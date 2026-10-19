<?php

namespace App\Http\Controllers\Espace;

use App\Actions\Compte\AnonymiserUtilisateur;
use App\Actions\Compte\ChangerNumero;
use App\Actions\Compte\ModifierProfil;
use App\Actions\Otp\EnvoyerCodeOtp;
use App\Enums\ResultatOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Espace\ChangerNumeroRequest;
use App\Http\Requests\Espace\ProfilRequest;
use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Profil, changement de numéro et suppression du compte.
 */
class ProfilController extends Controller
{
    private const string CLE_NOUVEAU_NUMERO = 'changement_numero.telephone';

    public function edit(Request $request): View
    {
        return view('espace.profil', ['membre' => $this->membre($request)]);
    }

    public function update(ProfilRequest $request, ModifierProfil $modifier): RedirectResponse
    {
        $modifier->executer($this->membre($request), $request->validated(), $request->file('photo'));

        return back()->with('statut', 'Profil enregistré.');
    }

    public function numero(Request $request): View
    {
        return view('espace.numero', ['enAttente' => $request->session()->get(self::CLE_NOUVEAU_NUMERO)]);
    }

    public function envoyerCode(ChangerNumeroRequest $request, ChangerNumero $changer): RedirectResponse
    {
        $nouveau = $request->telephoneE164();
        $changer->demander($this->membre($request), $nouveau);
        $request->session()->put(self::CLE_NOUVEAU_NUMERO, $nouveau);

        return redirect()->route('espace.numero')->with('statut', 'Un code vient d’être envoyé par SMS à votre nouveau numéro.');
    }

    public function confirmerNumero(Request $request, ChangerNumero $changer): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:'.EnvoyerCodeOtp::LONGUEUR]]);
        $nouveau = $request->session()->get(self::CLE_NOUVEAU_NUMERO);

        if (! is_string($nouveau)) {
            return redirect()->route('espace.numero');
        }

        $resultat = $changer->confirmer($this->membre($request), $nouveau, $request->string('code')->toString());

        if ($resultat !== ResultatOtp::Valide) {
            return back()->withErrors(['code' => $resultat->libelle()]);
        }

        $request->session()->forget(self::CLE_NOUVEAU_NUMERO);

        return redirect()->route('espace.profil')->with('statut', 'Votre nouveau numéro est enregistré. Utilisez-le désormais pour vous connecter.');
    }

    public function supprimer(Request $request, AnonymiserUtilisateur $anonymiser): RedirectResponse
    {
        $request->validate(['mot_de_passe_actuel' => ['required', 'string', 'current_password:web']], [], ['mot_de_passe_actuel' => 'mot de passe']);

        $membre = $this->membre($request);
        Auth::guard('web')->logout();
        $anonymiser->executer($membre);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('fil.index')->with('statut', 'Votre compte a été supprimé et vos données personnelles effacées.');
    }

    private function membre(Request $request): Utilisateur
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 401);

        return $utilisateur;
    }
}
