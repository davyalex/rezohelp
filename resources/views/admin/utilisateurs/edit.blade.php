@extends('admin.layout')

@section('titre', 'Modifier '.$utilisateur->nomAffiche())

@section('contenu')
    <a href="{{ route('admin.utilisateurs.show', $utilisateur) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Fiche</a>
    <h1 class="text-2xl font-bold">Modifier {{ $utilisateur->nomAffiche() }}</h1>

    <form method="POST" action="{{ route('admin.utilisateurs.update', $utilisateur) }}" class="mt-4 max-w-xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @method('PUT')
        <x-champ name="prenoms" label="Prénoms" :valeur="$utilisateur->prenoms" maxlength="120" />
        <x-champ name="nom" label="Nom" :valeur="$utilisateur->nom" maxlength="80" required />
        <x-champ name="email" label="E-mail" type="email" :valeur="$utilisateur->email" maxlength="150" />
        <x-liste name="commune_id" label="Commune" :options="$communes" :valeur="$utilisateur->commune_id" vide="Aucune" />
        <p class="text-sm text-slate-600">Le numéro de téléphone se change par le membre lui-même, avec un code SMS.</p>
        <x-bouton>Enregistrer</x-bouton>
    </form>
@endsection
