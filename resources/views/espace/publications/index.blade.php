@extends('layouts.public')

@php
    use App\Enums\StatutPublication;

    $maxProlongations = \App\Models\Parametre::entier('max_prolongations');
@endphp

@section('titre', 'Mes publications')

@section('contenu')
    <div class="mx-auto max-w-3xl">
        <x-menu-espace />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Mes publications</h1>
            <x-bouton :href="route('publier.choix')">+ Publier</x-bouton>
        </div>

        @error('prolongation')<x-message type="erreur" class="mt-4">{{ $message }}</x-message>@enderror
        @error('motif_cloture')<x-message type="erreur" class="mt-4">{{ $message }}</x-message>@enderror

        @if ($publications->isEmpty())
            <div class="mt-6 rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200/70">
                <p class="text-lg font-semibold">Vous n’avez encore rien publié.</p>
                <p class="mt-1 text-slate-600">Offre d’emploi, service, demande ou formation : c’est gratuit.</p>
            </div>
        @else
            <ul class="mt-6 space-y-4">
                @foreach ($publications as $publication)
                    <li class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge-type :type="$publication->type" :sous-type="$publication->sous_type" />
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-green-100 text-green-900' => $publication->statut === StatutPublication::Publiee,
                                        'bg-amber-100 text-amber-900' => $publication->statut === StatutPublication::EnAttente,
                                        'bg-red-100 text-red-900' => $publication->statut === StatutPublication::Rejetee,
                                        'bg-slate-100 text-slate-700' => in_array($publication->statut, [StatutPublication::Brouillon, StatutPublication::Expiree, StatutPublication::Cloturee], true),
                                    ])>{{ $publication->statut->libelle() }}</span>
                                </div>
                                <h2 class="mt-2 font-bold text-slate-900">
                                    @if (in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::Cloturee, StatutPublication::Expiree, StatutPublication::EnAttente], true))
                                        <a href="{{ route('publications.show', $publication->slug) }}" class="hover:underline">{{ $publication->titre }}</a>
                                    @else
                                        {{ $publication->titre }}
                                    @endif
                                </h2>
                                <p class="text-sm text-slate-600">
                                    Créée {{ $publication->cree_le?->diffForHumans() }}
                                    @if ($publication->expire_le && $publication->statut === StatutPublication::Publiee) · visible jusqu’au {{ $publication->expire_le->translatedFormat('j F') }}@endif
                                </p>
                            </div>
                        </div>

                        @if ($publication->statut === StatutPublication::Rejetee)
                            <x-message type="erreur" class="mt-3">
                                Refusée : {{ $publication->motif_rejet?->libelle() ?? 'motif non précisé' }}{{ $publication->motif_rejet_detail ? ' — '.$publication->motif_rejet_detail : '' }}.
                                Corrigez-la puis renvoyez-la.
                            </x-message>
                        @elseif ($publication->statut === StatutPublication::Cloturee && $publication->motif_cloture)
                            <p class="mt-2 text-sm text-slate-600">Clôturée : {{ $publication->motif_cloture }}.</p>
                        @endif

                        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-700" aria-label="Statistiques">
                            <li>👁 {{ $publication->nb_vues }} vues</li>
                            <li>👍 {{ $publication->nb_jaime }} j’aime</li>
                            <li>↗ {{ $publication->nb_partages }} partages</li>
                            <li>✉ {{ $publication->nb_reponses }} réponses</li>
                        </ul>

                        @php
                            $titulaire = $publication->entreprise_id ? 'entreprise:'.$publication->entreprise_id : 'utilisateur:'.$publication->auteur_id;
                            $droitsTitulaire = $droits[$titulaire] ?? ['remontees' => 0, 'statistiques' => false];
                        @endphp

                        @if ($droitsTitulaire['statistiques'])
                            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-700" aria-label="Statistiques détaillées">
                                <li>💬 {{ $publication->nb_commentaires }} commentaires</li>
                                <li>Taux de réponse : {{ $publication->nb_vues > 0 ? round($publication->nb_reponses * 100 / $publication->nb_vues, 1) : 0 }} %</li>
                                <li>J’aime pour 100 vues : {{ $publication->nb_vues > 0 ? round($publication->nb_jaime * 100 / $publication->nb_vues, 1) : 0 }}</li>
                            </ul>
                        @endif

                        @foreach ($publication->misesEnAvant as $miseEnAvant)
                            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                <strong>{{ $miseEnAvant->type === \App\Enums\TypeMiseEnAvant::UneCommune ? 'À la une' : 'Sponsorisé' }}</strong>
                                @if ($miseEnAvant->suspendue_le)
                                    · en pause tant que la publication n’est pas en ligne
                                @elseif ($miseEnAvant->fin_le?->isFuture())
                                    · jusqu’au {{ $miseEnAvant->fin_le->translatedFormat('j F à H\hi') }}
                                @else
                                    · terminé le {{ $miseEnAvant->fin_le?->translatedFormat('j F') }}
                                @endif
                                · {{ $miseEnAvant->nb_impressions }} affichages · {{ $miseEnAvant->nb_clics }} clics
                            </p>
                        @endforeach

                        @include('espace.publications._actions')
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $publications->links() }}</div>
        @endif
    </div>
@endsection
