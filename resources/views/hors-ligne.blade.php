<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0E5E6F">
    <meta name="robots" content="noindex">
    <title>Hors connexion · {{ config('app.name') }}</title>
    {{-- Page mise en cache à l'installation de la PWA : aucune donnée de compte ni jeton ici. --}}
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center p-6">
    <main class="max-w-sm rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200/70">
        <img src="/icones/icone-192.png" alt="" width="64" height="64" class="mx-auto size-16 rounded-xl">
        <h1 class="mt-4 text-xl font-bold text-slate-900">Vous êtes hors connexion</h1>
        <p class="mt-2 text-slate-700">Les pages déjà consultées restent disponibles. Vérifiez votre connexion, puis réessayez.</p>
        <a href="/" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 font-semibold text-white hover:bg-brand-fonce">Réessayer</a>
    </main>
</body>
</html>
