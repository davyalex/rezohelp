@extends('admin.layout')

@section('titre', 'Modérer « '.$publication->titre.' »')

@section('contenu')
    @php($enAttente = $publication->statut === \App\Enums\StatutPublication::EnAttente)
    <div x-data="raccourcisModeration" @keydown.window="touche($event)">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.moderation.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← File de modération</a>
            @if ($suivante)
                <a href="{{ route('admin.moderation.show', $suivante) }}" x-ref="suivante" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">Suivante → <kbd class="ml-1 rounded border px-1 text-xs">→</kbd></a>
            @endif
        </div>

        <div class="mt-2 grid gap-6 xl:grid-cols-[minmax(0,640px)_minmax(0,1fr)]">
            <section aria-label="Aperçu de la carte dans le fil">
                <p class="mb-2 text-sm text-slate-600">Aperçu exact dans le fil · <x-admin.badge-statut :statut="$publication->statut" /></p>
                <div class="pointer-events-none" inert>{!! $carte !!}</div>
                <details class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <summary class="cursor-pointer font-semibold">Texte complet</summary>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ $publication->contenu }}</p>
                </details>
            </section>

            <div class="space-y-4">
                @if ($enAttente)
                    <section aria-labelledby="titre-decision" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                        <h2 id="titre-decision" class="font-semibold">Décision</h2>
                        <p class="mt-1 text-xs text-slate-500">Raccourcis : <kbd class="rounded border px-1">V</kbd> valider · <kbd class="rounded border px-1">R</kbd> refuser · <kbd class="rounded border px-1">D</kbd> doublon · <kbd class="rounded border px-1">→</kbd> suivante</p>

                        <form method="POST" action="{{ route('admin.moderation.valider', $publication) }}" x-ref="valider" class="mt-3">
                            @csrf
                            <x-bouton class="w-full">Valider <kbd class="rounded border border-white/40 px-1 text-xs">V</kbd></x-bouton>
                        </form>

                        <details class="mt-3" x-ref="rejeter" @toggle="$event.target.open && $refs.motif.focus()">
                            <summary class="inline-flex min-h-11 w-full cursor-pointer items-center justify-center rounded-lg border border-red-300 font-semibold text-red-800">Refuser <kbd class="ml-1 rounded border px-1 text-xs">R</kbd></summary>
                            <form method="POST" action="{{ route('admin.moderation.rejeter', $publication) }}" class="mt-3 space-y-3">
                                @csrf
                                <x-liste name="motif_rejet" label="Motif" :options="\App\Enums\MotifRejet::options()" x-ref="motif" />
                                <x-zone-texte name="motif_rejet_detail" label="Précision pour l’auteur (obligatoire pour « Autre »)" :lignes="3" :max="500" />
                                <x-bouton variante="danger" class="w-full">Confirmer le refus</x-bouton>
                            </form>
                        </details>

                        <details class="mt-3" x-ref="doublon">
                            <summary class="inline-flex min-h-11 w-full cursor-pointer items-center justify-center rounded-lg border border-slate-300 font-semibold">Doublon <kbd class="ml-1 rounded border px-1 text-xs">D</kbd></summary>
                            <form method="POST" action="{{ route('admin.moderation.doublon', $publication) }}" class="mt-3 space-y-3">
                                @csrf
                                <label for="publication_originale_id" class="block text-sm font-medium">Publication d’origine</label>
                                <select id="publication_originale_id" name="publication_originale_id" required data-select2 data-ajax="{{ route('admin.recherche.publications') }}" data-placeholder="Titre ou numéro">
                                    @foreach ($similaires as $similaire)
                                        <option value="{{ $similaire->id }}">{{ $similaire->titre }} (n° {{ $similaire->id }})</option>
                                    @endforeach
                                </select>
                                <x-bouton variante="secondaire" class="w-full">Refuser comme doublon</x-bouton>
                            </form>
                        </details>
                    </section>
                @else
                    <x-message type="info">Cette publication n’est plus en attente ({{ $publication->statut->libelle() }}).</x-message>
                @endif

                <section aria-labelledby="titre-signaux" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 id="titre-signaux" class="font-semibold">Signaux de risque</h2>
                    <p class="mt-1 text-sm">Score de fiabilité : @include('admin.moderation._score', ['score' => $publication->score_fiabilite])</p>
                    @if (($publication->signaux_risque ?? []) === [])
                        <p class="mt-2 text-sm text-slate-600">Aucun signal détecté.</p>
                    @else
                        <ul class="mt-2 space-y-1">
                            @foreach ($publication->signaux_risque as $signal)
                                <li class="rounded-lg bg-red-50 px-3 py-2 text-sm font-semibold text-red-900">
                                    ⚠ {{ \App\Enums\SignalRisque::tryFrom((string) $signal)?->libelle() ?? str_replace('_', ' ', (string) $signal) }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($publication->source_url)
                        <p class="mt-3 text-sm">Annonce d’origine : <a href="{{ $publication->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="font-semibold text-brand underline">{{ $publication->source_nom ?? parse_url($publication->source_url, PHP_URL_HOST) }}</a></p>
                    @endif
                </section>

                <section aria-labelledby="titre-auteur" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 id="titre-auteur" class="font-semibold">Auteur</h2>
                    @if ($publication->auteur)
                        <p class="mt-1 text-sm">
                            @can('utilisateurs.voir')
                                <a href="{{ route('admin.utilisateurs.show', $publication->auteur) }}" class="font-semibold text-brand hover:underline">{{ $publication->auteur->nomAffiche() }}</a>
                            @else
                                {{ $publication->auteur->nomAffiche() }}
                            @endcan
                            · confiance {{ $publication->auteur->confiance->libelle() }} · inscrit {{ $publication->auteur->cree_le?->diffForHumans() }}
                            @if ($publication->auteur->estSuspendu()) · <strong class="text-red-700">suspendu</strong>@endif
                        </p>
                        @if ($publication->entreprise)
                            <p class="text-sm text-slate-600">Pour {{ $publication->entreprise->nom }}{{ $publication->entreprise->verifiee ? ' (vérifiée)' : '' }}</p>
                        @endif
                        @if ($historique)
                            <p class="mt-1 text-sm text-slate-700">Autres publications : {{ $historique['total'] }} · validées : {{ $historique['validees'] }} · refusées : {{ $historique['rejetees'] }}</p>
                        @endif
                    @else
                        <p class="mt-1 text-sm text-slate-600">Import de la veille{{ $publication->source_nom ? ' · '.$publication->source_nom : '' }}.</p>
                    @endif
                </section>

                <section aria-labelledby="titre-similaires" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 id="titre-similaires" class="font-semibold">Publications similaires</h2>
                    @forelse ($similaires as $similaire)
                        <p class="mt-2 text-sm">
                            <a href="{{ route('admin.moderation.show', $similaire) }}" class="text-brand hover:underline">{{ $similaire->titre }}</a>
                            · n° {{ $similaire->id }} · <x-admin.badge-statut :statut="$similaire->statut" /> · {{ $similaire->cree_le?->format('d/m/Y') }}
                        </p>
                    @empty
                        <p class="mt-1 text-sm text-slate-600">Aucune.</p>
                    @endforelse
                </section>
            </div>
        </div>
    </div>
@endsection
