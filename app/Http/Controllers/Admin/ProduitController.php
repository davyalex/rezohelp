<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\EnregistrerModele;
use App\DataTables\ProduitsDataTable;
use App\Enums\CibleOffre;
use App\Enums\TypeProduit;
use App\Http\Requests\Admin\ProduitRequest;
use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * K — Produits ponctuels : offre supplémentaire, remontée, « À la une », « Sponsorisé » (CONTEXTE §14.10).
 */
class ProduitController extends ControleurAdmin
{
    public function __construct(private readonly EnregistrerModele $enregistrer) {}

    public function index(Request $requete, ProduitsDataTable $table): View|JsonResponse
    {
        return $requete->ajax() ? $table->reponse($requete) : view('admin.produits.index', ['table' => $table]);
    }

    public function create(): View
    {
        return view('admin.produits.formulaire', ['produit' => new Produit(['cible' => CibleOffre::Artisan, 'type' => TypeProduit::Remontee, 'actif' => false, 'ordre' => 10])]);
    }

    public function store(ProduitRequest $requete): RedirectResponse
    {
        $this->enregistrer->executer(new Produit, $requete->donnees(), $this->admin($requete), 'produit');

        return redirect()->route('admin.produits.index')->with('statut', 'Produit créé.');
    }

    public function edit(Produit $produit): View
    {
        return view('admin.produits.formulaire', ['produit' => $produit]);
    }

    public function update(ProduitRequest $requete, Produit $produit): RedirectResponse
    {
        $this->enregistrer->executer($produit, $requete->donnees(), $this->admin($requete), 'produit');

        return redirect()->route('admin.produits.index')->with('statut', 'Produit enregistré.');
    }
}
