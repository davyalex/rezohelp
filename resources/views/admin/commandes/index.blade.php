@extends('admin.layout')

@section('titre', 'Commandes')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Commandes et paiements</h1>
        <x-bouton :href="route('admin.revenus')" variante="secondaire">Revenus</x-bouton>
    </div>

    <x-admin.filtres id="filtres-commandes">
        <x-liste name="statut" label="Statut" vide="Tous" :options="\App\Enums\StatutCommande::options()" />
        <x-liste name="passerelle" label="Passerelle" vide="Toutes" :options="\App\Enums\PasserelleCommande::options()" />
        <x-champ name="du" label="Depuis le" type="date" />
        <x-champ name="au" label="Jusqu’au" type="date" />
    </x-admin.filtres>

    <p class="mb-3">
        {{-- L'export reprend les filtres choisis. --}}
        <button type="submit" form="filtres-commandes" formaction="{{ route('admin.commandes.export') }}" formmethod="GET"
                class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold hover:bg-slate-50">Exporter en CSV</button>
    </p>

    <x-admin.table libelle="Commandes" :table="$table" :url="route('admin.commandes.index')" filtres="#filtres-commandes" />
@endsection
