@extends('admin.layout')

@section('titre', 'Abonnements')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Abonnements</h1>
        <x-bouton :href="route('admin.abonnements.create')">Offrir un abonnement</x-bouton>
    </div>

    <x-admin.filtres id="filtres-abonnements">
        <x-liste name="plan_id" label="Plan" vide="Tous" :options="$plans" />
        <x-liste name="statut" label="Statut" vide="Tous" :options="\App\Enums\StatutAbonnement::options()" />
        <x-liste name="echeance" label="Échéance" vide="Toutes" :options="['7' => 'Dans les 7 jours', '30' => 'Dans les 30 jours']" />
    </x-admin.filtres>

    <x-admin.table libelle="Abonnements" :table="$table" :url="route('admin.abonnements.index')" filtres="#filtres-abonnements" />
@endsection
