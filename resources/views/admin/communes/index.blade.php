@extends('admin.layout')

@section('titre', 'Communes')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Communes</h1>
        <x-bouton :href="route('admin.communes.create')">+ Ajouter une commune</x-bouton>
    </div>

    <x-admin.table libelle="Communes" :table="$table" :url="route('admin.communes.index')" />
@endsection
