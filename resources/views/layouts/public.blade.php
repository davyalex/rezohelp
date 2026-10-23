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
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icones/apple-touch-icon.png">
    @stack('entete')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen pb-16 lg:pb-0">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Aller au contenu</a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4">
            <a href="{{ route('fil.index') }}" class="shrink-0 text-xl font-bold text-brand">{{ config('app.name') }}</a>

            {{-- Recherche : loupe dans le champ, largeur bornée, pour ne pas se confondre avec « Publier ». --}}
            <form method="GET" action="{{ route('fil.recherche') }}" role="search" class="relative min-w-0 flex-1 lg:max-w-xl">
                <label for="recherche-entete" class="sr-only">Rechercher une offre, un métier, une formation</label>
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-500">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.4 9.83l3.13 3.14a.75.75 0 1 0 1.06-1.06l-3.13-3.14A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"/>
                </svg>
                <input id="recherche-entete" type="search" name="q" value="{{ request('q') }}" placeholder="Emploi, métier, formation…"
                       class="h-10 w-full rounded-full border-0 bg-fond pl-10 pr-4 text-base placeholder:text-slate-600 focus:ring-2 focus:ring-brand/40" maxlength="100">
            </form>

            <nav aria-label="Compte" class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
                {{-- Lien à contour (et non x-bouton, dont les couleurs grises entreraient en conflit). --}}
                <a href="{{ route('publier.choix') }}" @if (request()->routeIs('publier.*')) aria-current="page" @endif
                   class="hidden min-h-11 items-center gap-1.5 rounded-lg border border-brand bg-white px-3 text-sm font-semibold text-brand transition-colors hover:bg-brand-clair aria-[current=page]:bg-brand-clair lg:inline-flex">
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                    Publier une annonce
                </a>
                <span aria-hidden="true" class="mx-1 hidden h-6 w-px bg-slate-200 lg:block"></span>
                @auth('web')
                    @php($nbNonLues = auth('web')->user()->unreadNotifications()->count())
                    <a href="{{ route('espace.notifications') }}" @if (request()->routeIs('espace.notifications*')) aria-current="page" @endif
                       class="relative inline-flex size-11 items-center justify-center rounded-full text-xl hover:bg-slate-100 aria-[current=page]:bg-brand-clair">
                        <span aria-hidden="true">🔔</span>
                        <span class="sr-only">Notifications{{ $nbNonLues > 0 ? ' : '.$nbNonLues.' non lue(s)' : '' }}</span>
                        @if ($nbNonLues > 0)
                            <span aria-hidden="true" class="absolute right-0.5 top-0.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-xs font-bold leading-5 text-white">{{ $nbNonLues > 9 ? '9+' : $nbNonLues }}</span>
                        @endif
                    </a>
                    <x-menu-utilisateur />
                @else
                    <x-menu-visiteur />
                @endauth
            </nav>
        </div>
    </header>

    <main id="contenu" class="mx-auto max-w-6xl px-4 py-4 lg:py-6">
        @if (session('statut'))
            <x-message class="mb-4">{{ session('statut') }}</x-message>
        @endif
        @if (session('erreur'))
            <x-message type="erreur" class="mb-4">{{ session('erreur') }}</x-message>
        @endif

        @yield('contenu')
    </main>

    {{-- Invitation à installer l'application (pwa.js), à partir de la 2e visite. --}}
    <aside id="invitation-installation" hidden aria-label="Installer l’application"
           class="fixed inset-x-3 bottom-20 z-50 mx-auto flex max-w-md items-center gap-3 rounded-2xl bg-white p-3 shadow-lg ring-1 ring-slate-200 lg:bottom-6">
        <img src="/icones/icone-192.png" alt="" width="40" height="40" class="size-10 rounded-lg">
        <p class="flex-1 text-sm text-slate-800">Installez Rezohelp sur votre téléphone : plus rapide, même avec une connexion faible.</p>
        <button type="button" data-plus-tard class="min-h-11 px-2 text-sm text-slate-600 hover:text-slate-900">Plus tard</button>
        <button type="button" data-installer class="min-h-11 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-fonce">Installer</button>
    </aside>

    {{-- Confirmation des actions destructives (resources/js/confirmation.js) : remplace la boîte du navigateur. --}}
    <dialog id="dialogue-confirmation" aria-labelledby="dialogue-confirmation-titre" aria-describedby="dialogue-confirmation-detail"
            class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl bg-white p-0 shadow-xl backdrop:bg-slate-900/50">
        <form method="dialog" class="p-5" data-sans-spinner>
            <p id="dialogue-confirmation-titre" data-titre class="text-lg font-bold text-slate-900"></p>
            <p id="dialogue-confirmation-detail" data-detail class="mt-2 text-sm text-slate-700"></p>
            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="submit" value="annuler" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 font-semibold text-slate-800 hover:bg-slate-50">Annuler</button>
                <button type="submit" value="confirmer" data-confirmer class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 font-semibold text-white hover:bg-brand-fonce">Confirmer</button>
            </div>
        </form>
    </dialog>

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
            <li>
                <a href="{{ route('alertes.index') }}" @if (request()->routeIs('alertes.*')) aria-current="page" @endif
                   class="flex min-h-14 min-w-16 flex-col items-center justify-center text-xs text-slate-600 aria-[current=page]:font-semibold aria-[current=page]:text-brand">
                    <span aria-hidden="true" class="text-lg">🔔</span>Alertes
                </a>
            </li>
            <li>
                <a href="{{ route('espace.index') }}" @if (request()->routeIs('espace.*')) aria-current="page" @endif
                   class="flex min-h-14 min-w-16 flex-col items-center justify-center text-xs text-slate-600 aria-[current=page]:font-semibold aria-[current=page]:text-brand">
                    <span aria-hidden="true" class="text-lg">👤</span>Mon espace
                </a>
            </li>
        </ul>
    </nav>
</body>
</html>
