@extends('admin.layout')

@section('titre', 'Signalements de « '.$publication->titre.' »')

@section('contenu')
    <a href="{{ route('admin.signalements.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Signalements</a>
    <h1 class="text-2xl font-bold">{{ $publication->titre }}</h1>
    <p class="mt-1 text-sm text-slate-600">
        <x-admin.badge-statut :statut="$publication->statut" />
        @if ($publication->trashed()) · <strong class="text-red-700">supprimée</strong>@endif
        · {{ $publication->auteur_nom_affiche }}
        @can('utilisateurs.voir')
            @if ($publication->auteur_id) · <a href="{{ route('admin.utilisateurs.show', $publication->auteur_id) }}" class="text-brand underline">fiche de l’auteur</a>@endif
        @endcan
    </p>

    <div class="mt-4 grid gap-6 xl:grid-cols-[minmax(0,640px)_minmax(0,1fr)]">
        <section aria-label="Aperçu">
            @if ($carte)
                <div class="pointer-events-none" inert>{!! $carte !!}</div>
            @else
                <x-message type="info">La publication a été supprimée.</x-message>
            @endif
        </section>

        <div class="space-y-4">
            @if ($signalements->whereNull('traite_le')->isNotEmpty())
                <section aria-labelledby="titre-decision" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 id="titre-decision" class="font-semibold">Décision</h2>
                    <div class="mt-3 flex flex-col gap-2">
                        @foreach ([
                            'retablir' => ['Rétablir (signalements non fondés)', 'secondaire', 'Classer les signalements et laisser la publication en ligne ?'],
                            'supprimer' => ['Supprimer la publication', 'danger', 'Supprimer la publication et retenir les signalements ?'],
                            'supprimer_et_suspendre' => ['Supprimer et suspendre l’auteur', 'danger', 'Supprimer la publication et suspendre définitivement son auteur ?'],
                        ] as $decision => [$libelle, $variante, $question])
                            <form method="POST" action="{{ route('admin.signalements.decider', $publication->id) }}" data-confirmer="{{ $question }}" @if ($variante === 'danger') data-danger="1" @endif>
                                @csrf
                                <input type="hidden" name="decision" value="{{ $decision }}">
                                <x-bouton :variante="$variante" class="w-full">{{ $libelle }}</x-bouton>
                            </form>
                        @endforeach
                    </div>
                </section>
            @endif

            <section aria-labelledby="titre-liste" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                <h2 id="titre-liste" class="font-semibold">Signalements ({{ $signalements->count() }})</h2>
                <ul class="mt-2 divide-y divide-slate-100">
                    @foreach ($signalements as $signalement)
                        <li class="py-2 text-sm">
                            <p><strong>{{ $signalement->motif->libelle() }}</strong> · {{ $signalement->cree_le?->format('d/m/Y H:i') }} · {{ $signalement->utilisateur?->nomAffiche() ?? 'Visiteur' }}</p>
                            @if ($signalement->commentaire)
                                <p class="mt-1 text-slate-700">{{ $signalement->commentaire }}</p>
                            @endif
                            @if ($signalement->traite_le)
                                <p class="mt-1 text-xs text-slate-500">Traité le {{ $signalement->traite_le->format('d/m/Y') }} par {{ $signalement->traitePar?->nomAffiche() ?? '—' }} · {{ $signalement->retenu ? 'retenu' : 'non retenu' }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
@endsection
