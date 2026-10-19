@extends('admin.layout')

@section('titre', $produit->exists ? 'Produit '.$produit->nom : 'Nouveau produit')

@section('contenu')
    <a href="{{ route('admin.produits.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Produits</a>
    <h1 class="text-2xl font-bold">{{ $produit->exists ? 'Produit « '.$produit->nom.' »' : 'Nouveau produit' }}</h1>

    <form method="POST" action="{{ $produit->exists ? route('admin.produits.update', $produit) : route('admin.produits.store') }}"
          class="mt-4 max-w-2xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @if ($produit->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-champ name="code" label="Code" :valeur="$produit->code" maxlength="50" required />
            <x-champ name="nom" label="Nom" :valeur="$produit->nom" maxlength="80" required />
            <x-liste name="type" label="Type" :options="\App\Enums\TypeProduit::options()" :valeur="$produit->type?->value" />
            <x-liste name="cible" label="Cible" :options="\App\Enums\CibleOffre::options()" :valeur="$produit->cible?->value" />
            <x-champ name="prix_fcfa" label="Prix TTC (FCFA)" type="number" min="1" :valeur="$produit->prix_fcfa" required />
            <x-champ name="duree_jours" label="Durée (jours, vide pour une remontée)" type="number" min="1" :valeur="$produit->duree_jours" />
            <x-champ name="ordre" label="Ordre" type="number" min="0" :valeur="$produit->ordre" required />
        </div>
        <x-zone-texte name="description" label="Description" :valeur="$produit->description" :max="500" :lignes="3" />
        <x-case name="actif" label="Actif (proposé à l’achat)" :coche="$produit->actif" />
        <x-bouton>Enregistrer</x-bouton>
    </form>
@endsection
