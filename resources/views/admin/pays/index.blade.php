@extends('admin.layout')

@section('titre', 'Pays')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Pays</h1>
        <x-bouton :href="route('admin.pays.create')">+ Ajouter un pays</x-bouton>
    </div>
    <p class="mb-4 text-sm text-slate-600">Un pays inactif disparaît du sélecteur d’indicatif à l’inscription.</p>

    <x-admin.table libelle="Pays" :table="$table" :url="route('admin.pays.index')" />
@endsection
