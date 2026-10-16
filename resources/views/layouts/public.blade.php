<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E5E6F">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('titre')@yield('titre') · {{ config('app.name') }}@else{{ config('app.name') }}@endif</title>
    <meta name="description" content="@yield('description', 'Emplois, home service et formations à Abidjan, dans un seul fil.')">
    @stack('entete')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Aller au contenu</a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between gap-3 px-4">
            <a href="{{ route('accueil') }}" class="text-xl font-bold text-brand">{{ config('app.name') }}</a>

            <nav aria-label="Compte" class="flex items-center gap-2">
                @auth
                    <span class="hidden text-sm text-slate-700 sm:inline">{{ auth()->user()->nomAffiche() }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-bouton variante="secondaire" class="min-h-10 px-3 text-sm">Se déconnecter</x-bouton>
                    </form>
                @else
                    <x-bouton :href="route('login')" variante="secondaire" class="min-h-10 px-3 text-sm">Se connecter</x-bouton>
                @endauth
            </nav>
        </div>
    </header>

    <main id="contenu" class="mx-auto max-w-6xl px-4 py-6">
        @if (session('statut'))
            <x-message class="mb-4">{{ session('statut') }}</x-message>
        @endif

        @yield('contenu')
    </main>
</body>
</html>
