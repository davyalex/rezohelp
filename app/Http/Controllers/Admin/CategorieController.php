<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeplacerReferentiel;
use App\Actions\Admin\EnregistrerModele;
use App\Enums\TypePublication;
use App\Http\Requests\Admin\CategorieRequest;
use App\Models\Categorie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * G — Catégories : arbre type → catégorie → sous-catégorie, icône, ordre, activation (CONTEXTE §13.3.G).
 * L'arbre, borné et hiérarchique, est rendu par le serveur plutôt qu'en liste DataTables (D-057).
 */
class CategorieController extends ControleurAdmin
{
    public function __construct(private readonly EnregistrerModele $enregistrer) {}

    public function index(): View
    {
        $categories = Categorie::query()->orderBy('ordre')->orderBy('nom')->get(['id', 'type', 'nom', 'icone', 'parent_id', 'ordre', 'actif']);

        return view('admin.categories.index', [
            'arbre' => collect(TypePublication::cases())->mapWithKeys(fn (TypePublication $type): array => [$type->value => [
                'type' => $type,
                'principales' => $categories->where('type', $type)->whereNull('parent_id')->values(),
            ]]),
            'enfants' => $categories->whereNotNull('parent_id')->groupBy('parent_id'),
        ]);
    }

    public function create(Request $requete): View
    {
        return $this->formulaire(new Categorie([
            'type' => TypePublication::tryFrom($requete->string('type')->toString()) ?? TypePublication::Emploi,
            'parent_id' => $requete->integer('parent_id') ?: null,
            'actif' => true,
        ]));
    }

    public function store(CategorieRequest $requete): RedirectResponse
    {
        $donnees = $requete->donnees();
        $donnees['ordre'] = (int) Categorie::query()->where('type', $donnees['type']->value)->where('parent_id', $donnees['parent_id'])->max('ordre') + 1;

        $this->enregistrer->executer(new Categorie, $donnees, $this->admin($requete), 'categorie');

        return redirect()->route('admin.categories.index')->with('statut', 'Catégorie ajoutée.');
    }

    public function edit(Categorie $categorie): View
    {
        return $this->formulaire($categorie);
    }

    public function update(CategorieRequest $requete, Categorie $categorie): RedirectResponse
    {
        $this->enregistrer->executer($categorie, $requete->donnees(), $this->admin($requete), 'categorie');

        return redirect()->route('admin.categories.index')->with('statut', 'Catégorie modifiée.');
    }

    public function deplacer(Request $requete, Categorie $categorie, DeplacerReferentiel $deplacer): RedirectResponse
    {
        $sens = $requete->validate(['sens' => ['required', Rule::in(['haut', 'bas'])]])['sens'];
        $deplacer->executer($categorie, $sens, $this->admin($requete));

        return back();
    }

    private function formulaire(Categorie $categorie): View
    {
        return view('admin.categories.formulaire', [
            'categorie' => $categorie,
            'parents' => Categorie::query()->whereNull('parent_id')->whereKeyNot($categorie->id ?? 0)->orderBy('type')->orderBy('ordre')
                ->get(['id', 'type', 'nom'])
                ->groupBy(fn (Categorie $c): string => $c->type->libelle())
                ->map(fn ($groupe) => $groupe->pluck('nom', 'id')->all())
                ->all(),
        ]);
    }
}
