<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GererAdministrateur;
use App\Http\Requests\Admin\MotDePasseAdminRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Changement du mot de passe de l'administrateur connecté : obligatoire après un mot de passe généré.
 */
class MotDePasseController extends ControleurAdmin
{
    public function edit(Request $requete): View
    {
        return view('admin.auth.mot-de-passe', ['obligatoire' => $this->admin($requete)->doit_changer_mot_de_passe]);
    }

    public function update(MotDePasseAdminRequest $requete, GererAdministrateur $gerer): RedirectResponse
    {
        $gerer->changerSonMotDePasse($this->admin($requete), $requete->string('mot_de_passe')->toString());
        $requete->session()->regenerate();

        return redirect()->route('admin.tableau-de-bord')->with('statut', 'Mot de passe modifié.');
    }
}
