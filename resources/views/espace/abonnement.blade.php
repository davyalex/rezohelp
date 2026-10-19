@extends('layouts.public')

@section('titre', 'Mon abonnement')

@section('contenu')
    <div class="mx-auto max-w-3xl">
        <x-menu-espace />
        <h1 class="text-2xl font-bold">Mon abonnement</h1>
        <p class="mt-1 text-slate-600">
            Les formules sont prépayées : rien n’est jamais débité automatiquement.
            <a href="{{ route('tarifs') }}" class="font-semibold text-brand underline">Voir tous les tarifs</a>
        </p>

        @foreach ($situations as $situation)
            @php($entrepriseId = $situation['titulaire'] instanceof \App\Models\Entreprise ? $situation['titulaire']->id : null)
            <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70" aria-labelledby="titulaire-{{ $loop->index }}">
                <h2 id="titulaire-{{ $loop->index }}" class="text-lg font-bold text-slate-900">{{ $situation['nom'] }}</h2>

                {{-- Formules en cours --}}
                @forelse ($situation['abonnements'] as $abonnement)
                    <div @class([
                        'mt-3 rounded-lg px-4 py-3 text-sm',
                        'bg-amber-50 text-amber-900' => $abonnement->statut === \App\Enums\StatutAbonnement::Grace,
                        'bg-brand/5 text-slate-800' => $abonnement->statut !== \App\Enums\StatutAbonnement::Grace,
                    ])>
                        <p>
                            <strong>{{ $abonnement->plan->nom }}</strong> · {{ $abonnement->statut->libelle() }}
                            @if ($abonnement->debut_le?->isFuture())
                                — commence le {{ $abonnement->debut_le->translatedFormat('j F Y') }}
                            @elseif ($abonnement->statut === \App\Enums\StatutAbonnement::Grace)
                                — terminée le {{ $abonnement->fin_le?->translatedFormat('j F Y') }}. Vos avantages sont maintenus encore quelques jours : renouvelez pour ne pas les perdre.
                            @else
                                — jusqu’au {{ $abonnement->fin_le?->translatedFormat('j F Y') }}
                            @endif
                        </p>
                    </div>
                @empty
                    <p class="mt-2 text-sm text-slate-600">Formule gratuite.</p>
                @endforelse

                {{-- Compteurs restants --}}
                @if ($situation['compteurs'] !== [])
                    <h3 class="mt-5 text-sm font-semibold uppercase tracking-wide text-slate-500">Utilisation</h3>
                    <dl class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($situation['compteurs'] as $compteur)
                            <div class="rounded-lg bg-fond px-4 py-3">
                                <dt class="text-sm text-slate-600">{{ $compteur['cle']->libelle() }}</dt>
                                <dd class="font-semibold text-slate-900">
                                    @if ($compteur['limite'] === \App\Enums\CleDroit::ILLIMITE)
                                        {{ $compteur['consomme'] }} · illimité
                                    @else
                                        {{ $compteur['consomme'] }} / {{ $compteur['limite'] }}
                                    @endif
                                    @if ($compteur['credits'] > 0)
                                        <span class="text-sm font-normal text-slate-600">+ {{ $compteur['credits'] }} supplémentaire(s)</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                {{-- Formules proposées --}}
                @if ($situation['plans']->isNotEmpty())
                    <h3 class="mt-5 text-sm font-semibold uppercase tracking-wide text-slate-500">Formules disponibles</h3>
                    <ul class="mt-2 space-y-3">
                        @foreach ($situation['plans'] as ['plan' => $plan, 'essai' => $essai])
                            @php($souscrit = $situation['abonnements']->contains('plan_id', $plan->id))
                            <li class="rounded-lg border border-slate-200 p-4">
                                <p class="font-semibold text-slate-900">{{ $plan->nom }}</p>
                                <p class="text-sm text-slate-600">{{ \App\Support\Montant::fcfa($plan->prix_fcfa) }} pour {{ $plan->duree_jours }} jours</p>
                                @if ($plan->description)
                                    <p class="mt-1 text-sm text-slate-700">{{ $plan->description }}</p>
                                @endif
                                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                    @if ($essai && ! $souscrit)
                                        <x-formulaire-essai :plan="$plan->code" :entreprise="$entrepriseId">
                                            Essayer {{ $plan->duree_essai_jours }} jours gratuits
                                        </x-formulaire-essai>
                                    @endif
                                    <x-formulaire-achat :code="$plan->code" :entreprise="$entrepriseId" :variante="$essai && ! $souscrit ? 'secondaire' : 'primaire'">
                                        {{ $souscrit ? 'Renouveler' : 'Choisir' }} · {{ \App\Support\Montant::fcfa($plan->prix_fcfa) }}
                                    </x-formulaire-achat>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        {{-- Historique --}}
        <section class="mt-6" aria-labelledby="titre-commandes">
            <h2 id="titre-commandes" class="text-lg font-bold">Mes commandes et reçus</h2>
            @if ($commandes->isEmpty())
                <p class="mt-2 text-slate-600">Aucune commande pour le moment.</p>
            @else
                <ul class="mt-3 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
                    @foreach ($commandes as $commande)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $commande->article()?->nom ?? 'Commande' }}</p>
                                <p class="text-sm text-slate-600">
                                    {{ $commande->numero }} · {{ $commande->cree_le?->translatedFormat('j F Y') }} ·
                                    {{ \App\Support\Montant::fcfa($commande->montant_ttc_fcfa) }} · {{ $commande->statut->libelle() }}
                                </p>
                            </div>
                            @can('voirRecu', $commande)
                                <x-bouton :href="route('espace.recu', $commande)" variante="secondaire" class="min-h-10 text-sm">Reçu</x-bouton>
                            @elseif ($commande->statut === \App\Enums\StatutCommande::EnAttente)
                                <x-bouton :href="route('paiements.retour', $commande)" variante="lien" class="min-h-10 text-sm">Suivre le paiement</x-bouton>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
