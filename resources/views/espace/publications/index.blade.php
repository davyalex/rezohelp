@extends('layouts.public')

@php
    use App\Enums\StatutPublication;
    use App\Http\Requests\Espace\CloturerPublicationRequest;

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

                        @if ($publication->statut === StatutPublication::Publiee)
                            @can('mettreEnAvant', $publication)
                                <div class="mt-3 flex flex-wrap gap-2" aria-label="Visibilité">
                                    @if ($droitsTitulaire['remontees'] !== 0)
                                        <form method="POST" action="{{ route('espace.publications.remonter', $publication->id) }}">
                                            @csrf
                                            <x-bouton variante="secondaire" class="min-h-11 text-sm">
                                                ⬆ Remonter en tête du fil{{ $droitsTitulaire['remontees'] > 0 ? ' (incluse, '.$droitsTitulaire['remontees'].' restante(s))' : '' }}
                                            </x-bouton>
                                        </form>
                                    @elseif ($produits->has('remontee'))
                                        <x-formulaire-achat type="produit" :code="$produits['remontee']->code" :publication="$publication->id" :entreprise="$publication->entreprise_id" variante="secondaire">
                                            ⬆ Remonter en tête du fil · {{ \App\Support\Montant::fcfa($produits['remontee']->prix_fcfa) }}
                                        </x-formulaire-achat>
                                    @endif
                                    @foreach (['une_commune' => '⭐ À la une', 'sponsorise_fil' => '📣 Sponsoriser'] as $type => $libelle)
                                        @if ($produits->has($type))
                                            <x-formulaire-achat type="produit" :code="$produits[$type]->code" :publication="$publication->id" :entreprise="$publication->entreprise_id" variante="secondaire">
                                                {{ $libelle }} {{ $produits[$type]->duree_jours }} j · {{ \App\Support\Montant::fcfa($produits[$type]->prix_fcfa) }}
                                            </x-formulaire-achat>
                                        @endif
                                    @endforeach
                                </div>
                            @endcan
                        @endif

                        <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                            @can('update', $publication)
                                <x-bouton :href="route('espace.publications.edit', $publication->id)" variante="secondaire" class="min-h-11 text-sm">
                                    {{ match ($publication->statut) { StatutPublication::Brouillon => 'Compléter et publier', StatutPublication::Rejetee => 'Corriger et renvoyer', default => 'Modifier' } }}
                                </x-bouton>
                            @endcan
                            @if ($publication->nb_reponses > 0 || $publication->statut === StatutPublication::Publiee)
                                <x-bouton :href="route('espace.reponses.index', $publication->id)" variante="secondaire" class="min-h-11 text-sm">Réponses</x-bouton>
                            @endif
                            @if ($publication->statut === StatutPublication::Publiee)
                                <x-bouton :href="route('publications.partager', $publication->slug)" target="_blank" rel="noopener" variante="secondaire" class="min-h-11 text-sm">Partager</x-bouton>
                                @if ($publication->nb_prolongations < $maxProlongations)
                                    <form method="POST" action="{{ route('espace.publications.prolonger', $publication->id) }}">
                                        @csrf
                                        <x-bouton variante="secondaire" class="min-h-11 text-sm">Prolonger</x-bouton>
                                    </form>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('espace.publications.dupliquer', $publication->id) }}">
                                @csrf
                                <x-bouton variante="secondaire" class="min-h-11 text-sm">Dupliquer</x-bouton>
                            </form>
                            @if (in_array($publication->statut, [StatutPublication::Publiee, StatutPublication::EnAttente], true))
                                <details class="w-full sm:w-auto">
                                    <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold">Clôturer</summary>
                                    <form method="POST" action="{{ route('espace.publications.cloturer', $publication->id) }}" class="mt-2 flex flex-wrap gap-2">
                                        @csrf
                                        <label for="motif-{{ $publication->id }}" class="sr-only">Motif de clôture</label>
                                        <select id="motif-{{ $publication->id }}" name="motif_cloture" class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm">
                                            @foreach (CloturerPublicationRequest::MOTIFS as $motif)
                                                <option value="{{ $motif }}">{{ $motif }}</option>
                                            @endforeach
                                        </select>
                                        <x-bouton class="min-h-11 text-sm">Confirmer</x-bouton>
                                    </form>
                                </details>
                            @endif
                            <form method="POST" action="{{ route('espace.publications.destroy', $publication->id) }}"
                                  data-confirmer="Supprimer cette publication ?" data-detail="Elle disparaîtra du fil et de vos publications. Les réponses reçues ne seront plus accessibles." data-bouton="Supprimer" data-danger>
                                @csrf
                                @method('DELETE')
                                <x-bouton variante="lien" class="min-h-11 text-sm text-red-700">Supprimer</x-bouton>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $publications->links() }}</div>
        @endif
    </div>
@endsection
