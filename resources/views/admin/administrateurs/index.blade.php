@extends('admin.layout')

@section('titre', 'Administrateurs')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Administrateurs</h1>
            <p class="text-sm text-slate-600">Comptes de l’équipe, distincts des membres du site. Connexion sur <code class="rounded bg-slate-100 px-1">/admin/connexion</code>.</p>
        </div>
        <x-bouton :href="route('admin.administrateurs.create')">+ Nouvel administrateur</x-bouton>
    </div>

    @include('admin.administrateurs._mot-de-passe-genere')

    <x-admin.filtres id="filtres-administrateurs">
        <x-liste name="role" label="Rôle" vide="Tous" :options="$roles" />
        <x-liste name="etat" label="État" vide="Tous" :options="['actif' => 'Actif', 'suspendu' => 'Suspendu']" />
    </x-admin.filtres>

    <x-admin.table libelle="Administrateurs" :table="$table" :url="route('admin.administrateurs.index')" filtres="#filtres-administrateurs" />
@endsection
