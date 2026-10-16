<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E5E6F">
    <title>@yield('titre', config('app.name'))</title>
    <meta name="description" content="@yield('description', 'Emplois, home service et formations à Abidjan, dans un seul fil.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Aller au contenu</a>

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
        <div class="mx-auto flex h-14 max-w-6xl items-center px-4">
            <a href="{{ url('/') }}" class="text-xl font-bold text-brand">{{ config('app.name') }}</a>
        </div>
    </header>

    <main id="contenu" class="mx-auto max-w-6xl px-4 py-6">
        @yield('contenu')
    </main>
</body>
</html>
