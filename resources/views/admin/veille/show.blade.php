@extends('admin.layout')

@section('titre', 'Import '.$import->nom_fichier)

@section('contenu')
    <a href="{{ route('admin.veille.imports.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Imports</a>
    <h1 class="text-2xl font-bold">{{ $import->nom_fichier }}</h1>
    <p class="mt-1 text-sm text-slate-600">
        <x-admin.badge-statut :statut="$import->statut" /> · reçu le {{ $import->cree_le?->format('d/m/Y H:i') }}
        @if ($import->traite_le) · traité le {{ $import->traite_le->format('d/m/Y H:i') }}@endif
        · <a href="https://drive.google.com/file/d/{{ rawurlencode($import->fichier_drive_id) }}/view" target="_blank" rel="noopener noreferrer" class="text-brand underline">ouvrir dans Drive</a>
    </p>

    <div class="mt-4 grid gap-4 sm:grid-cols-4">
        <x-admin.carte-stat libelle="Lignes" :valeur="$import->nb_lignes" />
        <x-admin.carte-stat libelle="Importées" :valeur="$import->nb_importees" />
        <x-admin.carte-stat libelle="Doublons" :valeur="$import->nb_doublons" />
        <x-admin.carte-stat libelle="Rejetées" :valeur="$import->nb_rejetees" />
    </div>

    @if ($import->erreur)
        <x-message type="erreur" class="mt-4">{{ $import->erreur }}</x-message>
    @endif

    <section aria-labelledby="titre-publications" class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
        <h2 id="titre-publications" class="font-semibold">Publications créées par cet import</h2>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
            @forelse ($publications as $publication)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span>
                        <a href="{{ $publication->statut === \App\Enums\StatutPublication::EnAttente ? route('admin.moderation.show', $publication) : route('admin.publications.edit', $publication) }}" class="text-brand hover:underline">{{ $publication->titre }}</a>
                        · {{ $publication->source_nom }}
                    </span>
                    <span class="flex items-center gap-2">@include('admin.moderation._score', ['score' => $publication->score_fiabilite]) <x-admin.badge-statut :statut="$publication->statut" /></span>
                </li>
            @empty
                <li class="py-2 text-slate-600">Aucune publication.</li>
            @endforelse
        </ul>
    </section>
@endsection
