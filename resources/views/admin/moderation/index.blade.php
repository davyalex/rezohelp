@extends('admin.layout')

@section('titre', 'Modération')

@section('contenu')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Modération</h1>
            <p class="text-sm text-slate-600">Publications en attente, les plus risquées d’abord. Ouvrez-en une pour la traiter au clavier.</p>
        </div>
    </div>

    <x-admin.filtres id="filtres-moderation" class="mt-4">
        <x-liste name="type" label="Type" vide="Tous" :options="\App\Enums\TypePublication::options()" />
        <x-liste name="source" label="Source" vide="Toutes" :options="\App\Enums\SourcePublication::options()" />
    </x-admin.filtres>

    <div class="mb-3 flex flex-wrap items-end gap-2">
        <form method="POST" action="{{ route('admin.moderation.lot') }}" data-confirmer="Valider les publications cochées ?" data-selection="#table-moderation">
            @csrf
            <input type="hidden" name="action" value="valider">
            <x-bouton class="text-sm">Valider la sélection</x-bouton>
        </form>
        <form method="POST" action="{{ route('admin.moderation.lot') }}" class="flex flex-wrap items-end gap-2"
              data-confirmer="Refuser les publications cochées ?" data-danger="1" data-selection="#table-moderation">
            @csrf
            <input type="hidden" name="action" value="rejeter">
            <div>
                <label for="motif-lot" class="block text-xs font-medium text-slate-700">Motif du refus</label>
                <select id="motif-lot" name="motif_rejet" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                    @foreach (\App\Enums\MotifRejet::options() as $valeur => $libelle)
                        <option value="{{ $valeur }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <x-bouton variante="danger" class="text-sm">Refuser la sélection</x-bouton>
        </form>
    </div>

    <x-admin.table id="table-moderation" libelle="Publications en attente" :table="$table" :url="route('admin.moderation.index')" filtres="#filtres-moderation" />
@endsection
