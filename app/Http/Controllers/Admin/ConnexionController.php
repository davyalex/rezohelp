<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConnexionAdminRequest;
use App\Models\Administrateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Connexion au back-office (D-066) : nom d'utilisateur + mot de passe, garde « admin »,
 * indépendante de la connexion des membres. 5 essais par minute (limiteur connexion-admin).
 */
class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.connexion');
    }

    public function store(ConnexionAdminRequest $requete): RedirectResponse
    {
        $garde = Auth::guard(Administrateur::GARDE);
        $identifiants = [
            'nom_utilisateur' => mb_strtolower(trim($requete->string('nom_utilisateur')->toString())),
            'password' => $requete->string('mot_de_passe')->toString(),
        ];

        if (! $garde->attempt($identifiants, $requete->boolean('se_souvenir'))) {
            return back()->onlyInput('nom_utilisateur')
                ->withErrors(['nom_utilisateur' => 'Nom d’utilisateur ou mot de passe incorrect.']);
        }

        /** @var Administrateur $admin */
        $admin = $garde->user();

        if ($admin->estSuspendu()) {
            $garde->logout();

            return back()->onlyInput('nom_utilisateur')
                ->withErrors(['nom_utilisateur' => 'Ce compte est suspendu. Contactez un super administrateur.']);
        }

        $requete->session()->regenerate();
        $admin->forceFill(['derniere_connexion_le' => now()])->save();

        return $admin->doit_changer_mot_de_passe
            ? redirect()->route('admin.mot-de-passe.edit')
            : redirect()->intended(route('admin.tableau-de-bord'));
    }

    /**
     * Déconnexion de l'équipe uniquement : une éventuelle session membre dans le même navigateur est conservée.
     */
    public function destroy(Request $requete): RedirectResponse
    {
        Auth::guard(Administrateur::GARDE)->logout();
        $requete->session()->regenerate();
        $requete->session()->regenerateToken();

        return redirect()->route('admin.connexion')->with('statut', 'Vous êtes déconnecté.');
    }
}
