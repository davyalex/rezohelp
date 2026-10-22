@extends('admin.layout')

@section('titre', 'Import '.$import->nom_fichier)

@section('contenu')
    <a href="{{ route('admin.veille.imports.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Imports</a>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">{{ $import->nom_fichier }}</h1>
        <form method="POST" action="{{ route('admin.veille.imports.relancer', $import) }}" data-confirmer="Relancer cet import ? Les annonces déjà importées ne seront pas dupliquées.">
            @csrf
            <x-bouton variante="secondaire">Relancer l’import</x-bouton>
        </form>
    </div>
    <p class="mt-1 text-sm text-slate-600">
        <x-admin.badge-statut :statut="$import->statut" /> · reçu le {{ $import->cree_le?->format('d/m/Y H:i') }}
        @if ($import->traite_le) · traité le {{ $import->traite_le->format('d/m/Y H:i') }}@endif
        @unless (str_starts_with($import->fichier_drive_id, 'fake-'))
            · <a href="https://drive.google.com/file/d/{{ rawurlencode($import->fichier_drive_id) }}/view" target="_blank" rel="noopener noreferrer" class="text-brand underline">ouvrir dans Drive</a>
        @endunless
    </p>

    <div class="mt-4 grid gap-4 sm:grid-cols-4">
        <x-admin.carte-stat libelle="Lignes" :valeur="$import->nb_lignes" />
        <x-admin.carte-stat libelle="Importées" :valeur="$import->nb_importees" />
        <x-admin.carte-stat libelle="Doublons" :valeur="$import->nb_doublons" />
        <x-admin.carte-stat libelle="Rejetées" :valeur="$import->nb_rejetees" :ton="$import->nb_rejetees > 0 ? 'attention' : 'neutre'" />
    </div>

    @if ($import->erreur)
        <x-message type="erreur" class="mt-4">{{ $import->erreur }}</x-message>
    @endif

    <section aria-labelledby="titre-lignes" class="mt-6 overflow-x-auto rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
        <h2 id="titre-lignes" class="font-semibold">Lignes du fichier</h2>
        @if ($lignes->isEmpty())
            <p class="mt-2 text-sm text-slate-600">Aucune ligne traitée pour le moment.</p>
        @else
            <table class="mt-2 w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-600">
                    <tr><th scope="col" class="py-1">N°</th><th scope="col">Intitulé</th><th scope="col">Sort</th><th scope="col">Publication ou raison</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($lignes as $ligne)
                        <tr>
                            <td class="py-2 align-top">{{ $ligne->numero }}</td>
                            <td class="py-2 align-top">
                                {{ $ligne->intitule ?? '—' }}
                                @if ($ligne->lien && preg_match('#^https?://#i', $ligne->lien))
                                    <a href="{{ $ligne->lien }}" target="_blank" rel="noopener noreferrer nofollow" class="block truncate text-xs text-brand underline">{{ \Illuminate\Support\Str::limit($ligne->lien, 70) }}</a>
                                @elseif ($ligne->lien)
                                    <span class="block truncate text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($ligne->lien, 70) }}</span>
                                @endif
                            </td>
                            <td class="py-2 align-top">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-green-100 text-green-900' => $ligne->statut === \App\Enums\StatutLigneVeille::Importee,
                                    'bg-slate-100 text-slate-700' => $ligne->statut === \App\Enums\StatutLigneVeille::Doublon,
                                    'bg-red-100 text-red-900' => $ligne->statut === \App\Enums\StatutLigneVeille::Rejetee,
                                ])>{{ $ligne->statut->libelle() }}</span>
                            </td>
                            <td class="py-2 align-top">
                                @if ($ligne->publication)
                                    <a href="{{ $ligne->publication->statut === \App\Enums\StatutPublication::EnAttente ? route('admin.moderation.show', $ligne->publication) : route('admin.publications.edit', $ligne->publication) }}" class="text-brand hover:underline">
                                        {{ $ligne->statut === \App\Enums\StatutLigneVeille::Doublon ? 'Existante : ' : '' }}{{ $ligne->publication->titre }}
                                    </a>
                                    <span class="ml-1 inline-flex items-center gap-1">@include('admin.moderation._score', ['score' => $ligne->publication->score_fiabilite]) <x-admin.badge-statut :statut="$ligne->publication->statut" /></span>
                                @else
                                    <span class="text-slate-700">{{ $ligne->raison ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
