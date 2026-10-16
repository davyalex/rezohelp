<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ChangerMotDePasse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangerMotDePasseRequest;
use App\Models\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChangementMotDePasseController extends Controller
{
    public function formulaire(): View
    {
        return view('auth.changer-mot-de-passe');
    }

    public function changer(ChangerMotDePasseRequest $request, ChangerMotDePasse $changer): RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof Utilisateur, 403);

        $changer->executer($utilisateur, $request->string('password')->toString());
        $request->session()->regenerate();

        return redirect()->intended(route('accueil'))->with('statut', 'Votre nouveau mot de passe est enregistré.');
    }
}
