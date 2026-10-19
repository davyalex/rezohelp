<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E5E6F">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="url-connexion" content="{{ route('login') }}">
    <title>@hasSection('titre')@yield('titre') · {{ config('app.name') }}@else{{ config('app.name') }} — Emplois, home service et formations à Abidjan @endif</title>
    <meta name="description" content="@yield('description', 'Emplois, home service et formations à Abidjan, dans un seul fil.')">
    @stack('entete')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen pb-16 lg:pb-0">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Aller au contenu</a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4">
            <a href="{{ route('fil.index') }}" class="shrink-0 text-xl font-bold text-brand">{{ config('app.name') }}</a>

            <form method="GET" action="{{ route('fil.recherche') }}" role="search" class="min-w-0 flex-1">
                <label for="recherche-entete" class="sr-only">Rechercher une offre, un métier, une formation</label>
                <input id="recherche-entete" type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher…"
                       class="h-10 w-full rounded-full border-0 bg-fond px-4 text-base focus:ring-2 focus:ring-brand/40" maxlength="100">
            </form>

            <nav aria-label="Compte" class="flex shrink-0 items-center gap-2">
                <x-bouton :href="route('publier.choix')" class="hidden min-h-10 px-3 text-sm lg:inline-flex">+ Publier</x-bouton>
                @auth
                    <a href="{{ route('espace.publications.index') }}" class="hidden min-h-10 items-center text-sm font-semibold text-slate-700 hover:text-brand md:inline-flex">Mes publications</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-bouton variante="secondaire" class="min-h-10 px-3 text-sm">Se déconnecter</x-bouton>
                    </form>
                @else
                    <x-bouton :href="route('login')" variante="primaire" class="min-h-10 px-3 text-sm">Se connecter</x-bouton>
                @endauth
            </nav>
        </div>
    </header>

    <main id="contenu" class="mx-auto max-w-6xl px-4 py-4 lg:py-6">
        @if (session('statut'))
            <x-message class="mb-4">{{ session('statut') }}</x-message>
        @endif

        @yield('contenu')
    </main>

    {{-- Navigation basse (mobile) --}}
    <nav aria-label="Navigation principale" class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white lg:hidden">
        <ul class="mx-auto flex max-w-md justify-around">
            <li>
                <a href="{{ route('fil.index') }}" @if (request()->routeIs('fil.index', 'fil.emplois', 'fil.services', 'fil.formations')) aria-current="page" @endif
                   class="flex min-h-14 min-w-16 flex-col items-center justify-center text-xs text-slate-600 aria-[current=page]:font-semibold aria-[current=page]:text-brand">
                    <span aria-hidden="true" class="text-lg">🏠</span>Fil
                </a>
            </li>
            <li>
                <a href="{{ route('fil.recherche') }}" @if (request()->routeIs('fil.recherche')) aria-current="page" @endif
                   class="flex min-h-14 min-w-16 flex-col items-center justify-center text-xs text-slate-600 aria-[current=page]:font-semibold aria-[current=page]:text-brand">
                    <span aria-hidden="true" class="text-lg">🔍</span>Recherche
                </a>
            </li>
            <li>
                <a href="{{ route('publier.choix') }}" @if (request()->routeIs('publier.*')) aria-current="page" @endif
                   class="flex min-h-14 min-w-16 flex-col items-center justify-center text-xs text-slate-600 aria-[current=page]:font-semibold aria-[current=page]:text-brand">
                    <span aria-hidden="true" class="text-lg">➕</span>Publier
                </a>
            </li>
        </ul>
    </nav>
</body>
</html>
