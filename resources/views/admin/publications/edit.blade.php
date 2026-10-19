@extends('admin.layout')

@section('titre', 'Modifier « '.$publication->titre.' »')

@section('contenu')
    <a href="{{ route('admin.publications.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Publications</a>
    <h1 class="text-2xl font-bold">Modifier la publication n° {{ $publication->id }}</h1>
    <p class="mt-1 text-sm text-slate-600">
        {{ $publication->type->libelle() }} · <x-admin.badge-statut :statut="$publication->statut" />
        · {{ $publication->source === \App\Enums\SourcePublication::VeilleIa ? 'veille IA ('.$publication->source_nom.')' : $publication->auteur_nom_affiche }}
        · créée le {{ $publication->cree_le?->format('d/m/Y H:i') }}
    </p>

    <form method="POST" action="{{ route('admin.publications.update', $publication) }}" class="mt-4 max-w-3xl space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @method('PUT')
        <x-champ name="titre" label="Titre" :valeur="$publication->titre" maxlength="100" required />
        <x-zone-texte name="contenu" label="Texte" :valeur="$publication->contenu" :min="80" :max="3000" :lignes="10" required />
        <div class="grid gap-4 sm:grid-cols-3">
            <x-liste name="categorie_id" label="Catégorie" :options="$categories" :valeur="$publication->categorie_id" required />
            <x-liste name="commune_id" label="Commune" :options="$communes" :valeur="$publication->commune_id" vide="Aucune" />
            <x-champ name="expire_le" label="Expire le" type="date" :valeur="$publication->expire_le?->toDateString()" />
        </div>
        <p class="text-sm text-slate-600">Le statut se change avec les actions de la liste (modérer, dépublier, prolonger).</p>
        <x-bouton>Enregistrer</x-bouton>
    </form>
@endsection
