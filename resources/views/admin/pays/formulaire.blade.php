@extends('admin.layout')

@section('titre', $pays->exists ? 'Modifier '.$pays->nom : 'Nouveau pays')

@section('contenu')
    <a href="{{ route('admin.pays.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Pays</a>
    <h1 class="text-2xl font-bold">{{ $pays->exists ? 'Modifier le pays' : 'Nouveau pays' }}</h1>

    <form method="POST" action="{{ $pays->exists ? route('admin.pays.update', $pays) : route('admin.pays.store') }}"
          class="mt-4 max-w-xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @if ($pays->exists) @method('PUT') @endif
        <x-champ name="code" label="Code ISO (2 lettres)" :valeur="$pays->code" maxlength="2" required />
        <x-champ name="nom" label="Nom" :valeur="$pays->nom" maxlength="80" required />
        <x-champ name="indicatif" label="Indicatif" :valeur="$pays->indicatif" aide="Exemple : +225" maxlength="5" required />
        <x-case name="actif" label="Ouvert à l’inscription" :coche="$pays->actif" />
        <x-bouton>Enregistrer</x-bouton>
    </form>
@endsection
