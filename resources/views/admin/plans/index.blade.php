@extends('admin.layout')

@section('titre', 'Plans')

@section('contenu')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Plans</h1>
            <p class="text-sm text-slate-600">Prix, durées, essais et limites : tout se règle ici, sans toucher au code. Quotas appliqués : <strong>{{ \App\Models\Parametre::booleen('quotas_actifs') ? 'oui' : 'non (lancement)' }}</strong>.</p>
        </div>
        <x-bouton :href="route('admin.plans.create')">+ Nouveau plan</x-bouton>
    </div>

    <x-admin.table libelle="Plans" :table="$table" :url="route('admin.plans.index')" />
@endsection
