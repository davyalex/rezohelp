@extends('admin.layout')

@section('titre', $source->exists ? 'Modifier '.$source->nom : 'Nouvelle source')

@section('contenu')
    <a href="{{ route('admin.veille.sources.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Sources</a>
    <h1 class="text-2xl font-bold">{{ $source->exists ? 'Modifier la source' : 'Nouvelle source' }}</h1>

    <form method="POST" action="{{ $source->exists ? route('admin.veille.sources.update', $source) : route('admin.veille.sources.store') }}"
          class="mt-4 max-w-xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @if ($source->exists) @method('PUT') @endif
        <x-champ name="nom" label="Nom affiché (« via … »)" :valeur="$source->nom" maxlength="100" required />
        <x-champ name="domaine" label="Domaine" :valeur="$source->domaine" aide="Exemple : emploi.ci. Vide uniquement pour la source « Inconnue »." maxlength="150" />
        <x-liste name="niveau_confiance" label="Niveau de confiance" :options="\App\Enums\NiveauConfiance::options()" :valeur="$source->niveau_confiance?->value ?? 'bas'" />
        <x-case name="publication_auto" label="Publication automatique autorisée" :coche="$source->publication_auto" />
        <x-case name="actif" label="Source active" :coche="$source->actif" />
        <x-bouton>Enregistrer</x-bouton>
    </form>
@endsection
