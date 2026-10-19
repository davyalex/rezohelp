@extends('admin.layout')

@section('titre', $categorie->exists ? 'Modifier '.$categorie->nom : 'Nouvelle catégorie')

@section('contenu')
    <a href="{{ route('admin.categories.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Catégories</a>
    <h1 class="text-2xl font-bold">{{ $categorie->exists ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}</h1>

    <form method="POST" action="{{ $categorie->exists ? route('admin.categories.update', $categorie) : route('admin.categories.store') }}"
          class="mt-4 max-w-xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @if ($categorie->exists) @method('PUT') @endif
        <x-liste name="type" label="Type" :options="\App\Enums\TypePublication::options()" :valeur="$categorie->type?->value" />
        <x-champ name="nom" label="Nom" :valeur="$categorie->nom" maxlength="80" required />
        <x-champ name="icone" label="Icône (emoji)" :valeur="$categorie->icone" maxlength="10" />
        <x-liste name="parent_id" label="Catégorie parente" :groupes="$parents" :valeur="$categorie->parent_id" vide="Aucune (catégorie principale)" />
        <x-case name="actif" label="Active (visible dans le fil et les formulaires)" :coche="$categorie->actif" />
        <x-bouton>Enregistrer</x-bouton>
    </form>
@endsection
