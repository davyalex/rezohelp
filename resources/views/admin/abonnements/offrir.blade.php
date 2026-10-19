@extends('admin.layout')

@section('titre', 'Offrir un abonnement')

@section('contenu')
    <a href="{{ route('admin.abonnements.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Abonnements</a>
    <h1 class="text-2xl font-bold">Offrir un abonnement</h1>

    <form method="POST" action="{{ route('admin.abonnements.store') }}" class="mt-4 max-w-xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70"
          x-data="{ titulaire: @js(old('titulaire', 'utilisateur')) }">
        @csrf
        <fieldset>
            <legend class="text-sm font-medium">Titulaire</legend>
            <div class="mt-1 flex gap-4">
                <label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="titulaire" value="utilisateur" x-model="titulaire"> Un membre (artisan, alertes SMS…)</label>
                <label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="titulaire" value="entreprise" x-model="titulaire"> Une entreprise</label>
            </div>
        </fieldset>
        <div x-show="titulaire === 'utilisateur'" class="space-y-1.5">
            <label for="utilisateur_id" class="block text-sm font-medium">Membre</label>
            <select id="utilisateur_id" name="utilisateur_id" data-select2 data-ajax="{{ route('admin.recherche.utilisateurs') }}" data-placeholder="Nom ou téléphone"></select>
        </div>
        <div x-show="titulaire === 'entreprise'" x-cloak class="space-y-1.5">
            <label for="entreprise_id" class="block text-sm font-medium">Entreprise</label>
            <select id="entreprise_id" name="entreprise_id" data-select2 data-ajax="{{ route('admin.recherche.entreprises') }}" data-placeholder="Nom de l’entreprise"></select>
        </div>
        <x-liste name="plan_id" label="Plan" :groupes="$plans->groupBy(fn ($p) => $p->cible->libelle())->map(fn ($g) => $g->pluck('nom', 'id')->all())->all()" />
        <x-champ name="jours" label="Durée offerte (jours)" type="number" min="1" max="3660" :valeur="30" required />
        <x-champ name="motif" label="Motif" maxlength="255" required aide="Exemple : partenaire de lancement, geste commercial." />
        <x-bouton>Offrir</x-bouton>
    </form>
@endsection
