@extends('layouts.public')

@php
    use App\Enums\TypeContrat;
    use App\Enums\TypePublication;

    $referentiels = app(\App\Services\Referentiels::class);
    $onglets = [
        ['type' => null, 'libelle' => 'Tout', 'icone' => '✨'],
        ['type' => TypePublication::Emploi, 'libelle' => 'Emplois', 'icone' => '💼'],
        ['type' => TypePublication::Service, 'libelle' => 'Home service', 'icone' => '🔧'],
        ['type' => TypePublication::Formation, 'libelle' => 'Formations', 'icone' => '🎓'],
    ];
    // Couleur de chaque type (tokens @theme) : classes écrites en entier pour que Tailwind les détecte.
    $couleurs = [
        'emploi' => ['puce' => 'bg-emploi', 'actif' => 'bg-emploi-clair font-semibold text-emploi', 'pastille' => 'bg-emploi-clair text-emploi'],
        'service' => ['puce' => 'bg-service', 'actif' => 'bg-service-clair font-semibold text-service', 'pastille' => 'bg-service-clair text-service'],
        'formation' => ['puce' => 'bg-formation', 'actif' => 'bg-formation-clair font-semibold text-formation', 'pastille' => 'bg-formation-clair text-formation'],
    ];
    $puce = 'inline-flex min-h-11 shrink-0 snap-start items-center gap-1 whitespace-nowrap rounded-full px-3 text-sm transition-colors';
    $categoriesDuType = $filtres->type !== null ? $referentiels->categoriesPrincipales($filtres->type) : [];
    $groupesCategories = collect($referentiels->categoriesPrincipales(null))->groupBy('type');
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
    <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,640px)_260px] lg:justify-center">
        {{-- Colonne gauche (desktop) : catégories --}}
        <aside class="hidden lg:block" aria-labelledby="titre-categories">
            <div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200/70">
                <h2 id="titre-categories" class="px-2 pt-1 text-sm font-semibold uppercase tracking-wide text-slate-600">Catégories</h2>

                @if ($filtres->type === null)
                    {{-- Vue « Tout » : catégories regroupées par type, chaque groupe se déroule. --}}
                    <div class="mt-2 space-y-1">
                        @foreach ($onglets as $onglet)
                            @continue($onglet['type'] === null)
                            @php($cle = $onglet['type']->value)
                            @php($categories = $groupesCategories->get($cle, collect()))
                            @php($total = $categories->sum(fn (array $c): int => $nombresParCategorie[$c['id']] ?? 0))
                            @php($contientActif = $categories->contains('slug', $filtres->categorieSlug))
                            @continue($categories->isEmpty())
                            <details class="group/groupe rounded-xl" @if ($contientActif) open @endif>
                                <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg px-2 text-sm font-semibold text-slate-800 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                                    <span aria-hidden="true" class="size-2 shrink-0 rounded-full {{ $couleurs[$cle]['puce'] }}"></span>
                                    <span aria-hidden="true">{{ $onglet['icone'] }}</span>
                                    <span class="flex-1">{{ $onglet['libelle'] }}</span>
                                    @if ($total > 0)
                                        <span class="rounded-full px-2 text-xs font-semibold leading-5 {{ $couleurs[$cle]['pastille'] }}">{{ $total }}</span>
                                    @endif
                                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 shrink-0 text-slate-400 transition-transform group-open/groupe:rotate-180 motion-reduce:transition-none">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                                    </svg>
                                </summary>
                                <ul class="mb-1 ml-3 mt-0.5 space-y-0.5 border-l border-slate-200 pl-2">
                                    <li>
                                        <a href="{{ $filtres->url([], $onglet['type'], true) }}" class="flex min-h-10 items-center rounded-lg px-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                                            Tout voir
                                        </a>
                                    </li>
                                    @foreach ($categories as $categorie)
                                        <li>
                                            <a href="{{ $filtres->url(['categorie' => $categorie['slug']], $onglet['type'], true) }}"
                                               class="flex min-h-10 items-center gap-2 rounded-lg px-2 text-sm text-slate-700 hover:bg-slate-50">
                                                <span aria-hidden="true">{{ $categorie['icone'] }}</span>
                                                <span class="flex-1 truncate">{{ $categorie['nom'] }}</span>
                                                @if (($nombresParCategorie[$categorie['id']] ?? 0) > 0)
                                                    <span class="text-xs text-slate-500">{{ $nombresParCategorie[$categorie['id']] }}</span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endforeach
                    </div>
                @else
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($categoriesDuType as $categorie)
                            @php($actif = $filtres->categorieSlug === $categorie['slug'])
                            <li>
                                <a href="{{ $filtres->url(['categorie' => $actif ? null : $categorie['slug']]) }}"
                                   @if ($actif) aria-current="true" @endif
                                   class="flex min-h-11 items-center gap-2 rounded-lg px-2 text-sm transition-colors {{ $actif ? $couleurs[$categorie['type']]['actif'] : 'text-slate-700 hover:bg-slate-50' }}">
                                    <span aria-hidden="true">{{ $categorie['icone'] }}</span>
                                    <span class="flex-1 truncate">{{ $categorie['nom'] }}</span>
                                    @if ($actif)
                                        <span aria-hidden="true" class="text-xs">✕</span><span class="sr-only">(retirer ce filtre)</span>
                                    @elseif (($nombresParCategorie[$categorie['id']] ?? 0) > 0)
                                        <span class="text-xs text-slate-500">{{ $nombresParCategorie[$categorie['id']] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>

        {{-- Fil --}}
        <section aria-labelledby="titre-fil" class="min-w-0">
            {{-- Onglets et filtres, alignés sur les cartes du fil --}}
            <div class="mb-4 space-y-2">
                <nav aria-label="Types de publication" class="-mx-4 px-4 sm:mx-0 sm:px-0">
                    <ul class="sans-barre flex snap-x gap-2 overflow-x-auto py-1">
                        @foreach ($onglets as $onglet)
                            @php($actif = $filtres->type === $onglet['type'])
                            <li class="shrink-0 snap-start">
                                <a href="{{ $filtres->url([], $onglet['type'], true) }}" @if ($actif) aria-current="page" @endif
                                   class="inline-flex min-h-11 items-center gap-1.5 whitespace-nowrap rounded-full px-4 text-sm font-semibold transition-colors {{ $actif ? 'bg-brand text-white shadow-sm' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                                    <span aria-hidden="true">{{ $onglet['icone'] }}</span>{{ $onglet['libelle'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                <div class="flex items-center gap-2">
                    {{-- Commune : liste déroulante compacte plutôt qu'une rangée de 13 puces. --}}
                    @php($communeActive = $filtres->communeId !== null ? $referentiels->nomCommune($filtres->communeId) : null)
                    <div x-data="menuDeroulant" class="relative shrink-0" @keydown.escape.window="fermer()" @click.outside="fermer(false)"
                         @focusout="$event.relatedTarget && !$el.contains($event.relatedTarget) && fermer(false)">
                        <button type="button" x-ref="bouton" @click="basculer()" @keydown.arrow-down.prevent="ouvrir()"
                                :aria-expanded="ouvert.toString()" aria-expanded="false" aria-haspopup="true" aria-controls="choix-commune"
                                class="{{ $puce }} {{ $communeActive ? 'bg-brand-clair font-semibold text-brand-fonce ring-1 ring-brand' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                            <span aria-hidden="true">📍</span>
                            <span class="max-w-28 truncate">{{ $communeActive ?? 'Commune' }}</span>
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 opacity-60"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                            <span class="sr-only">: choisir une commune</span>
                        </button>
                        <div id="choix-commune" x-ref="panneau" x-cloak x-show="ouvert" x-transition.opacity.duration.100ms
                             @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
                             class="absolute left-0 top-full z-40 mt-2 max-h-80 w-60 overflow-y-auto overscroll-contain rounded-2xl bg-white p-1 shadow-xl ring-1 ring-slate-200">
                            <a href="{{ $filtres->url(['commune' => null]) }}" @if ($communeActive === null) aria-current="true" @endif
                               class="flex min-h-11 items-center justify-between rounded-xl px-3 text-sm text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50 aria-[current=true]:font-semibold aria-[current=true]:text-brand-fonce">
                                Toutes les communes @if ($communeActive === null)<span aria-hidden="true">✓</span>@endif
                            </a>
                            <hr class="my-1 border-slate-100">
                            @foreach ($referentiels->communes() as $commune)
                                @php($actif = $filtres->communeId === $commune['id'])
                                <a href="{{ $filtres->url(['commune' => $commune['slug']]) }}" @if ($actif) aria-current="true" @endif
                                   class="flex min-h-11 items-center justify-between rounded-xl px-3 text-sm text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50 aria-[current=true]:bg-brand-clair aria-[current=true]:font-semibold aria-[current=true]:text-brand-fonce">
                                    {{ $commune['nom'] }} @if ($actif)<span aria-hidden="true">✓</span>@endif
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <x-rangee-defilante libelle="Filtrer par date et type de contrat" class="min-w-0 flex-1">
                        <li class="shrink-0 snap-start">
                            <a href="{{ $filtres->url(['aujourdhui' => $filtres->aujourdhui ? null : '1']) }}" @if ($filtres->aujourdhui) aria-current="true" @endif
                               class="{{ $puce }} {{ $filtres->aujourdhui ? 'bg-brand-clair font-semibold text-brand-fonce ring-1 ring-brand' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                                <span aria-hidden="true">🕑</span>Aujourd’hui
                            </a>
                        </li>
                        @if (in_array($filtres->type, [null, TypePublication::Emploi], true))
                            @foreach (TypeContrat::cases() as $contrat)
                                @php($actif = $filtres->typeContrat === $contrat)
                                <li class="shrink-0 snap-start">
                                    <a href="{{ $filtres->url(['contrat' => $actif ? null : $contrat->value]) }}" @if ($actif) aria-current="true" @endif
                                       class="{{ $puce }} {{ $actif ? 'bg-emploi-clair font-semibold text-emploi ring-1 ring-emploi' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                                        {{ $contrat->libelle() }}@if ($actif)<span aria-hidden="true" class="ml-0.5">✕</span>@endif
                                    </a>
                                </li>
                            @endforeach
                        @endif
                    </x-rangee-defilante>
                </div>

                @if ($categoriesDuType !== [])
                    {{-- Sur mobile, la colonne des catégories n'existe pas : rangée dédiée. --}}
                    <x-rangee-defilante libelle="Filtrer par catégorie" class="-mx-4 px-4 sm:mx-0 sm:px-0 lg:hidden">
                        @foreach ($categoriesDuType as $categorie)
                            @php($actif = $filtres->categorieSlug === $categorie['slug'])
                            <li class="shrink-0 snap-start">
                                <a href="{{ $filtres->url(['categorie' => $actif ? null : $categorie['slug']]) }}" @if ($actif) aria-current="true" @endif
                                   class="{{ $puce }} {{ $actif ? $couleurs[$categorie['type']]['actif'].' ring-1 ring-current' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                                    <span aria-hidden="true">{{ $categorie['icone'] }}</span>{{ $categorie['nom'] }}@if ($actif)<span aria-hidden="true" class="ml-0.5">✕</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </x-rangee-defilante>
                @endif
            </div>
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

            @if ($aLaUne->isNotEmpty())
                <section aria-label="À la une" class="mb-4">
                    <ul class="space-y-4">
                        @foreach ($aLaUne as $miseEnAvant)
                            {!! $rendu->rendreMiseEnAvant($miseEnAvant) !!}
                        @endforeach
                    </ul>
                </section>
            @endif

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
                    data-curseur="{{ $salve->curseurSuivant }}"
                    data-deja="{{ $deja + $salve->publications->count() }}">
                    @foreach ($salve->publications as $publication)
                        @isset($sponsorisees[$loop->index])
                            {!! $rendu->rendreMiseEnAvant($sponsorisees[$loop->index]) !!}
                        @endisset
                        <li data-carte>{!! $rendu->rendre($publication) !!}</li>
                        @if ($loop->iteration === min(5, $loop->count))
                            <li>@include('fil._bloc-alerte')</li>
                        @endif
                    @endforeach
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
                        <a href="{{ $filtres->url(['curseur' => $salve->curseurSuivant, 'deja' => (string) ($deja + $salve->publications->count())]) }}" data-charger-plus
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
            @include('fil._bloc-alerte')
            @if ($offresParContrat !== [])
                @php($maxContrat = max(array_column($offresParContrat, 'nombre')))
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
                    <h2 class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-600">
                        <span aria-hidden="true">💼</span>Emplois par contrat
                    </h2>
                    <ul class="mt-2 space-y-0.5">
                        @foreach ($offresParContrat as $ligne)
                            @php($actif = $filtres->typeContrat?->value === $ligne['contrat'])
                            <li>
                                <a href="{{ $filtres->url(['contrat' => $actif ? null : $ligne['contrat']], TypePublication::Emploi, true) }}"
                                   @if ($actif) aria-current="true" @endif
                                   class="group block rounded-lg px-2 py-2 text-sm transition-colors {{ $actif ? 'bg-emploi-clair' : 'hover:bg-slate-50' }}">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="{{ $actif ? 'font-semibold text-emploi' : 'text-slate-700' }}">{{ $ligne['libelle'] }}</span>
                                        <span class="rounded-full bg-emploi-clair px-2 text-xs font-semibold leading-5 text-emploi">
                                            {{ $ligne['nombre'] }}<span class="sr-only"> {{ $ligne['nombre'] > 1 ? 'offres disponibles' : 'offre disponible' }}</span>
                                        </span>
                                    </span>
                                    <span aria-hidden="true" class="mt-1.5 block h-1 overflow-hidden rounded-full bg-slate-100">
                                        <span class="block h-full rounded-full bg-emploi/70" style="width: {{ max(6, (int) round($ligne['nombre'] / $maxContrat * 100)) }}%"></span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('fil.emplois') }}" class="mt-2 inline-flex min-h-11 items-center px-2 text-sm font-semibold text-emploi hover:underline">
                        Voir toutes les offres d’emploi →
                    </a>
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
