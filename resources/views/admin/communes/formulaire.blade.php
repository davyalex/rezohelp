@extends('admin.layout')

@section('titre', $commune->exists ? 'Modifier '.$commune->nom : 'Nouvelle commune')

@section('contenu')
    <a href="{{ route('admin.communes.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Communes</a>
    <h1 class="text-2xl font-bold">{{ $commune->exists ? 'Modifier la commune' : 'Nouvelle commune' }}</h1>

    <form method="POST" action="{{ $commune->exists ? route('admin.communes.update', $commune) : route('admin.communes.store') }}"
          class="mt-4 max-w-xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @if ($commune->exists) @method('PUT') @endif
        <x-liste name="pays_code" label="Pays" :options="$pays" :valeur="$commune->pays_code" />
        <x-champ name="ville" label="Ville" :valeur="$commune->ville" maxlength="80" required />
        <x-champ name="nom" label="Commune" :valeur="$commune->nom" maxlength="80" required />
        <x-case name="actif" label="Active (proposée dans les filtres et les formulaires)" :coche="$commune->actif" />
        <x-bouton>Enregistrer</x-bouton>
    </form>
@endsection
