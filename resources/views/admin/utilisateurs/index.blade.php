@extends('admin.layout')

@section('titre', 'Utilisateurs')

@section('contenu')
    <h1 class="text-2xl font-bold">Utilisateurs</h1>
    <p class="text-sm text-slate-600">Recherchez par nom ou par numéro, sous n’importe quelle forme (0708091011, 07 08 09 10 11, +225…).</p>

    <x-admin.filtres id="filtres-utilisateurs" class="mt-4">
        <x-liste name="role" label="Rôle" vide="Tous" :options="$roles" />
        <x-liste name="confiance" label="Confiance" vide="Toutes" :options="\App\Enums\ConfianceUtilisateur::options()" />
        <x-liste name="etat" label="État" vide="Tous" :options="['suspendu' => 'Suspendus']" />
    </x-admin.filtres>

    <x-admin.table libelle="Utilisateurs" :table="$table" :url="route('admin.utilisateurs.index')" filtres="#filtres-utilisateurs" />
@endsection
