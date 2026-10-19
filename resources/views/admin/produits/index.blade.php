@extends('admin.layout')

@section('titre', 'Produits')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Produits ponctuels</h1>
        <x-bouton :href="route('admin.produits.create')">+ Nouveau produit</x-bouton>
    </div>

    <x-admin.table libelle="Produits" :table="$table" :url="route('admin.produits.index')" />
@endsection
