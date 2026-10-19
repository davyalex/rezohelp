@extends('admin.layout')

@section('titre', 'Entreprises')

@section('contenu')
    <h1 class="text-2xl font-bold">Entreprises</h1>

    <x-admin.filtres id="filtres-entreprises" class="mt-4">
        <x-liste name="verifiee" label="Vérification" vide="Toutes" :options="['1' => 'Vérifiées', '0' => 'Non vérifiées']" />
    </x-admin.filtres>

    <x-admin.table libelle="Entreprises" :table="$table" :url="route('admin.entreprises.index')" filtres="#filtres-entreprises" />
@endsection
