@extends('layouts.public')

@section('titre', 'Tarifs')
@section('description', 'Consulter, créer des alertes, postuler et publier restent gratuits. Découvrez les formules pour aller plus loin.')

@section('contenu')
    <div class="mx-auto max-w-4xl">
        <h1 class="text-2xl font-bold">Tarifs</h1>
        <p class="mt-1 text-slate-700">
            L’essentiel est gratuit : consulter, créer un compte et des alertes, postuler, publier dans les limites de la formule gratuite.
            Les formules sont prépayées : rien n’est jamais débité automatiquement. Les montants sont TTC.
        </p>

        @foreach ($cibles as $cible)
            @continue(empty($grille[$cible->value]))
            <section class="mt-8" aria-labelledby="cible-{{ $cible->value }}">
                <h2 id="cible-{{ $cible->value }}" class="text-xl font-bold">{{ $cible->libelle() }}</h2>
                <ul class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($grille[$cible->value] as $plan)
                        <li class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
                            <h3 class="text-lg font-bold text-slate-900">{{ $plan->nom }}</h3>
                            <p class="mt-1 text-2xl font-bold text-brand">
                                @if ($plan->prix_fcfa === 0)
                                    Gratuit
                                @else
                                    {{ \App\Support\Montant::fcfa($plan->prix_fcfa) }}
                                    <span class="text-sm font-normal text-slate-600">/ {{ $plan->duree_jours }} jours</span>
                                @endif
                            </p>
                            @if ($plan->duree_essai_jours > 0)
                                <p class="mt-1 text-sm font-semibold text-green-800">{{ $plan->duree_essai_jours }} jours d’essai gratuit</p>
                            @endif
                            @if ($plan->description)
                                <p class="mt-2 text-sm text-slate-700">{{ $plan->description }}</p>
                            @endif
                            <ul class="mt-3 flex-1 space-y-1 text-sm text-slate-700">
                                @foreach ($plan->limites as $limite)
                                    @continue((int) $limite->valeur === 0)
                                    <li>
                                        <span aria-hidden="true">✓</span>
                                        {{ $limite->cle->libelle() }}@unless ($limite->cle->estBooleen()) :
                                            {{ (int) $limite->valeur === \App\Enums\CleDroit::ILLIMITE ? 'illimité' : $limite->valeur }}@endunless
                                    </li>
                                @endforeach
                            </ul>
                            @if ($plan->prix_fcfa > 0)
                                <x-bouton :href="auth()->check() ? route('espace.abonnement') : route('login')" class="mt-4">Choisir</x-bouton>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if ($produits->isNotEmpty())
            <section class="mt-8" aria-labelledby="titre-produits">
                <h2 id="titre-produits" class="text-xl font-bold">À l’unité</h2>
                <p class="mt-1 text-sm text-slate-600">Depuis « Mes publications », pour une publication déjà en ligne. Toute publication mise en avant porte la mention « Annonce sponsorisée » ou « À la une ».</p>
                <ul class="mt-3 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
                    @foreach ($produits as $produit)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3">
                            <span>
                                <span class="font-semibold text-slate-900">{{ $produit->nom }}</span>
                                @if ($produit->description)
                                    <span class="block text-sm text-slate-600">{{ $produit->description }}</span>
                                @endif
                            </span>
                            <span class="font-semibold text-brand">
                                {{ \App\Support\Montant::fcfa($produit->prix_fcfa) }}@if ($produit->duree_jours) <span class="text-sm font-normal text-slate-600">/ {{ $produit->duree_jours }} jours</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
