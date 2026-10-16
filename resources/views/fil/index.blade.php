@extends('layouts.public')

@php
    use App\Enums\TypeContrat;
    use App\Enums\TypePublication;

    $referentiels = app(\App\Services\Referentiels::class);
    $onglets = [
        ['type' => null, 'libelle' => 'Tout'],
        ['type' => TypePublication::Emploi, 'libelle' => 'Emplois'],
        ['type' => TypePublication::Service, 'libelle' => 'Home service'],
        ['type' => TypePublication::Formation, 'libelle' => 'Formations'],
    ];
    $titrePage = $filtres->recherche !== null
        ? 'Résultats pour « '.$filtres->recherche.' »'
        : match ($filtres->type) {
            TypePublication::Emploi => 'Offres d’emploi à Abidjan',
            TypePublication::Service => 'Home service et petits métiers à Abidjan',
            TypePublication::Formation => 'Formations à Abidjan',
            null => 'Le fil',
        };
    $premiere = $salve->publications->first();
@endphp

@section('titre', $titrePage)

@section('contenu')
    {{-- Onglets de type --}}
    <nav aria-label="Catégories" class="-mx-4 overflow-x-auto px-4">
        <ul class="flex gap-2">
            @foreach ($onglets as $onglet)
                @php($actif = $filtres->type === $onglet['type'])
                <li>
                    <a href="{{ $filtres->url([], $onglet['type'], true) }}" @if ($actif) aria-current="page" @endif
                       class="inline-flex min-h-11 items-center whitespace-nowrap rounded-full px-4 text-sm font-semibold {{ $actif ? 'bg-brand text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                        {{ $onglet['libelle'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Puces de filtre --}}
    <div class="-mx-4 mt-3 overflow-x-auto px-4" role="group" aria-label="Filtres">
        <ul class="flex gap-2">
            <li>
                <a href="{{ $filtres->url(['aujourdhui' => $filtres->aujourdhui ? null : '1']) }}" aria-pressed="{{ $filtres->aujourdhui ? 'true' : 'false' }}"
                   class="inline-flex min-h-11 items-center whitespace-nowrap rounded-full px-3 text-sm {{ $filtres->aujourdhui ? 'bg-brand-clair font-semibold text-brand-fonce ring-1 ring-brand' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">
                    Aujourd’hui
                </a>
            </li>
            @if (in_array($filtres->type, [null, TypePublication::Emploi], true))
                @foreach (TypeContrat::cases() as $contrat)
                    @php($actif = $filtres->typeContrat === $contrat)
                    <li>
                        <a href="{{ $filtres->url(['contrat' => $actif ? null : $contrat->value]) }}" aria-pressed="{{ $actif ? 'true' : 'false' }}"
                           class="inline-flex min-h-11 items-center whitespace-nowrap rounded-full px-3 text-sm {{ $actif ? 'bg-emploi-clair font-semibold text-emploi ring-1 ring-emploi' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">
                            {{ $contrat->libelle() }}
                        </a>
                    </li>
                @endforeach
            @endif
            @foreach ($referentiels->communes() as $commune)
                @php($actif = $filtres->communeId === $commune['id'])
                <li>
                    <a href="{{ $filtres->url(['commune' => $actif ? null : $commune['slug']]) }}" aria-pressed="{{ $actif ? 'true' : 'false' }}"
                       class="inline-flex min-h-11 items-center whitespace-nowrap rounded-full px-3 text-sm {{ $actif ? 'bg-brand-clair font-semibold text-brand-fonce ring-1 ring-brand' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">
                        {{ $commune['nom'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="mt-4 grid gap-6 lg:grid-cols-[220px_minmax(0,640px)_260px] lg:justify-center">
        {{-- Colonne gauche (desktop) : catégories --}}
        <aside class="hidden lg:block" aria-label="Catégories détaillées">
            <div class="sticky top-20 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Catégories</h2>
                <ul class="mt-2 space-y-1">
                    @foreach ($referentiels->categoriesPrincipales($filtres->type) as $categorie)
                        @php($actif = $filtres->categorieSlug === $categorie['slug'])
                        <li>
                            <a href="{{ $filtres->url(['categorie' => $actif ? null : $categorie['slug']], \App\Enums\TypePublication::from($categorie['type']), true) }}"
                               @if ($actif) aria-current="true" @endif
                               class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm {{ $actif ? 'bg-brand-clair font-semibold text-brand-fonce' : 'text-slate-700 hover:bg-slate-50' }}">
                                <span aria-hidden="true">{{ $categorie['icone'] }}</span>{{ $categorie['nom'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Fil --}}
        <section aria-labelledby="titre-fil" class="min-w-0">
            <h1 id="titre-fil" class="{{ $filtres->recherche !== null || $filtres->type !== null ? 'mb-3 text-xl font-bold' : 'sr-only' }}">{{ $titrePage }}</h1>

            @if (request()->routeIs('fil.recherche') && $filtres->recherche === null)
                <form method="GET" action="{{ route('fil.recherche') }}" role="search" class="mb-4 flex gap-2">
                    <label for="recherche-page" class="sr-only">Que cherchez-vous ?</label>
                    <input id="recherche-page" type="search" name="q" maxlength="100" placeholder="Plombier, comptable, stage…" autofocus
                           class="min-h-11 w-full flex-1 rounded-lg border border-slate-300 bg-white px-3 text-base">
                    <x-bouton>Rechercher</x-bouton>
                </form>
            @endif

            <div id="bandeau-nouvelles" hidden class="sticky top-16 z-30 mb-3 text-center">
                <button type="button" class="min-h-11 rounded-full bg-brand px-4 text-sm font-semibold text-white shadow-lg" data-nouvelles-bouton>
                    <span data-nouvelles-nombre></span> ↑
                </button>
            </div>

            @if ($salve->publications->isEmpty())
                <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200/70">
                    <p class="text-lg font-semibold">Aucune publication ne correspond pour le moment.</p>
                    <p class="mt-1 text-slate-600">Essayez une autre commune ou une autre catégorie.</p>
                    @if ($filtres->estFiltre())
                        <x-bouton :href="$filtres->url(['commune' => null, 'categorie' => null, 'contrat' => null, 'aujourdhui' => null, 'q' => null])" variante="secondaire" class="mt-4">Retirer les filtres</x-bouton>
                    @endif
                </div>
            @else
                <ol id="fil" class="space-y-4" aria-live="polite" aria-busy="false"
                    data-suite="{{ route('fil.suite', array_merge($filtres->parametres(), ['type' => $filtres->type?->value])) }}"
                    data-nouvelles="{{ route('fil.nouvelles', array_merge($filtres->parametres(), ['type' => $filtres->type?->value])) }}"
                    data-depuis="{{ $premiere?->tri_le?->toIso8601String() }}"
                    data-curseur="{{ $salve->curseurSuivant }}">
                    @include('fil._cartes')
                </ol>

                <template id="squelette-carte">
                    <li aria-hidden="true" class="animate-pulse rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70" data-squelette>
                        <div class="flex gap-3"><div class="size-10 rounded-full bg-slate-200"></div><div class="flex-1 space-y-2"><div class="h-3 w-1/2 rounded bg-slate-200"></div><div class="h-3 w-1/3 rounded bg-slate-200"></div></div></div>
                        <div class="mt-4 h-4 w-3/4 rounded bg-slate-200"></div>
                        <div class="mt-2 h-3 w-full rounded bg-slate-200"></div>
                        <div class="mt-2 h-3 w-5/6 rounded bg-slate-200"></div>
                    </li>
                </template>

                <div id="fin-fil" class="py-6 text-center">
                    @if ($salve->curseurSuivant)
                        <a href="{{ $filtres->url(['curseur' => $salve->curseurSuivant]) }}" data-charger-plus
                           class="inline-flex min-h-11 items-center rounded-full bg-white px-5 font-semibold text-brand ring-1 ring-slate-300 hover:bg-slate-50">
                            Charger plus
                        </a>
                    @endif
                    <p data-fin @if ($salve->curseurSuivant) hidden @endif class="text-slate-600">Vous êtes à jour 🎉</p>
                </div>
            @endif
        </section>

        {{-- Colonne droite (desktop) --}}
        <aside class="hidden space-y-4 lg:block" aria-label="À découvrir">
            @if ($communesPopulaires !== [])
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Communes populaires</h2>
                    <ul class="mt-2 space-y-1">
                        @foreach ($communesPopulaires as $commune)
                            <li>
                                <a href="{{ $filtres->url(['commune' => $commune['slug']]) }}" class="flex min-h-11 items-center justify-between rounded-lg px-2 text-sm text-slate-700 hover:bg-slate-50">
                                    <span>{{ $commune['nom'] }}</span><span class="text-slate-500">{{ $commune['nombre'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($formationsRecentes !== [])
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Formations récentes</h2>
                    <ul class="mt-2 space-y-1">
                        @foreach ($formationsRecentes as $formation)
                            <li><a href="{{ route('publications.show', $formation['slug']) }}" class="flex min-h-11 items-center rounded-lg px-2 text-sm text-formation hover:bg-slate-50">{{ $formation['titre'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
@endsection
