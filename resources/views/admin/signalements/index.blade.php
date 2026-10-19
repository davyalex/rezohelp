@extends('admin.layout')

@section('titre', 'Signalements')

@section('contenu')
    <h1 class="text-2xl font-bold">Signalements</h1>
    <p class="mb-4 text-sm text-slate-600">
        Signalements non traités, regroupés par publication. À {{ \App\Models\Parametre::entier('seuil_masquage_signalements') }} signalements « arnaque », la publication est retirée du fil en attendant votre décision.
    </p>

    <x-admin.table libelle="Signalements ouverts" :table="$table" :url="route('admin.signalements.index')" />
@endsection
