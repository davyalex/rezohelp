@extends('admin.layout')

@section('titre', 'Sources de la veille')

@section('contenu')
    <a href="{{ route('admin.veille.imports.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Imports</a>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Sources de la veille</h1>
        <x-bouton :href="route('admin.veille.sources.create')">+ Ajouter une source</x-bouton>
    </div>
    <p class="mb-4 text-sm text-slate-600">Publication automatique seulement pour une source de confiance haute dont l’annonce obtient un score ≥ {{ \App\Models\Parametre::entier('seuil_publication_auto') }}.</p>

    <x-admin.table libelle="Sources de la veille" :table="$table" :url="route('admin.veille.sources.index')" />
@endsection
