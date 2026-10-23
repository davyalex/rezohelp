<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titre') · {{ config('app.name') }} admin</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-fonce via-brand to-slate-900 p-4">
    <main id="contenu" class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-2 text-white">
            <span aria-hidden="true" class="inline-flex size-10 items-center justify-center rounded-xl bg-white/15 text-lg font-bold ring-1 ring-white/30">R</span>
            <span class="text-xl font-bold">{{ config('app.name') }}</span>
            <span class="rounded-md bg-white/15 px-1.5 py-0.5 text-xs font-semibold ring-1 ring-white/30">admin</span>
        </div>
        <div class="rounded-2xl bg-white p-6 shadow-2xl ring-1 ring-black/5 sm:p-8">
            @if (session('statut'))
                <div role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900">{{ session('statut') }}</div>
            @endif
            @yield('contenu')
        </div>
        <p class="mt-6 text-center text-xs text-white/70">Espace réservé à l’équipe {{ config('app.name') }}.</p>
    </main>
</body>
</html>
