<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ModifierParametres;
use App\Enums\TypeParametre;
use App\Models\Parametre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * G — Paramètres de la plateforme (CONTEXTE §13.3.G), typés : entier, booléen, texte, HTML, JSON.
 */
class ParametreController extends ControleurAdmin
{
    public function edit(): View
    {
        return view('admin.parametres', [
            'parametres' => Parametre::query()->with('modifiePar:id,nom,prenoms')->orderBy('cle')->get(),
        ]);
    }

    public function update(Request $requete, ModifierParametres $modifier): RedirectResponse
    {
        $parametres = Parametre::query()->get(['cle', 'type']);

        $regles = $parametres->mapWithKeys(fn (Parametre $parametre): array => ['parametres.'.$parametre->cle => match ($parametre->type) {
            TypeParametre::Entier => ['required', 'integer', 'min:0', 'max:100000000'],
            TypeParametre::Booleen => ['required', 'boolean'],
            TypeParametre::Json => ['required', 'json'],
            TypeParametre::Texte => ['nullable', 'string', 'max:2000'],
            TypeParametre::Html => ['nullable', 'string', 'max:50000'],
        }])->all();

        $valeurs = $requete->validate($regles, [], $parametres->mapWithKeys(fn (Parametre $p): array => ['parametres.'.$p->cle => $p->cle])->all());

        $nombre = $modifier->executer((array) ($valeurs['parametres'] ?? []), $this->admin($requete));

        return redirect()->route('admin.parametres.edit')->with('statut', $nombre === 0 ? 'Aucun changement.' : $nombre.' paramètre(s) modifié(s).');
    }
}
