@extends('admin.layout')

@section('titre', 'Publications')

@section('contenu')
    <h1 class="text-2xl font-bold">Publications</h1>

    @php($referentiels = app(\App\Services\Referentiels::class))
    <x-admin.filtres id="filtres-publications" class="mt-4">
        <x-liste name="type" label="Type" vide="Tous" :options="\App\Enums\TypePublication::options()" />
        <x-liste name="statut" label="Statut" vide="Tous" :options="\App\Enums\StatutPublication::options()" />
        <x-liste name="source" label="Source" vide="Toutes" :options="\App\Enums\SourcePublication::options()" />
        <x-liste name="commune_id" label="Commune" vide="Toutes" :options="collect($referentiels->communes())->mapWithKeys(fn ($c) => [$c['id'] => $c['nom']])->all()" />
        <x-liste name="categorie_id" label="Catégorie" vide="Toutes" :options="$referentiels->optionsCategories(null)" />
        <div class="space-y-1.5">
            <label for="filtre-auteur" class="block text-sm font-medium text-slate-800">Auteur</label>
            <select id="filtre-auteur" name="auteur_id" data-select2 data-ajax="{{ route('admin.recherche.utilisateurs') }}" data-placeholder="Nom ou téléphone"></select>
        </div>
        <x-champ name="du" label="Créées depuis le" type="date" />
        <x-champ name="au" label="Jusqu’au" type="date" />
    </x-admin.filtres>

    <x-admin.table libelle="Publications" :table="$table" :url="route('admin.publications.index')" filtres="#filtres-publications" />
@endsection
