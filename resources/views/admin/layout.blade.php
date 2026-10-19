<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titre', 'Administration') · {{ config('app.name') }} admin</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
@php
    $statistiques = app(\App\Services\StatistiquesService::class);
    $nbEnAttente = auth()->user()?->can('publications.moderer') ? $statistiques->enAttente() : 0;
    $nbSignalements = auth()->user()?->can('signalements.traiter') ? $statistiques->signalementsOuverts() : 0;
    $menu = [
        ['route' => 'admin.tableau-de-bord', 'actif' => 'admin.tableau-de-bord', 'libelle' => 'Tableau de bord', 'permission' => 'tableau_de_bord.voir'],
        ['route' => 'admin.moderation.index', 'actif' => 'admin.moderation.*', 'libelle' => 'Modération', 'permission' => 'publications.moderer', 'compteur' => $nbEnAttente],
        ['route' => 'admin.publications.index', 'actif' => 'admin.publications.*', 'libelle' => 'Publications', 'permission' => 'publications.gerer'],
        ['route' => 'admin.signalements.index', 'actif' => 'admin.signalements.*', 'libelle' => 'Signalements', 'permission' => 'signalements.traiter', 'compteur' => $nbSignalements],
        ['route' => 'admin.utilisateurs.index', 'actif' => 'admin.utilisateurs.*', 'libelle' => 'Utilisateurs', 'permission' => 'utilisateurs.voir'],
        ['route' => 'admin.entreprises.index', 'actif' => 'admin.entreprises.*', 'libelle' => 'Entreprises', 'permission' => 'utilisateurs.voir'],
        ['route' => 'admin.veille.imports.index', 'actif' => 'admin.veille.*', 'libelle' => 'Veille IA', 'permission' => 'veille.gerer'],
        ['route' => 'admin.categories.index', 'actif' => 'admin.categories.*', 'libelle' => 'Catégories', 'permission' => 'referentiels.gerer'],
        ['route' => 'admin.communes.index', 'actif' => 'admin.communes.*', 'libelle' => 'Communes', 'permission' => 'referentiels.gerer'],
        ['route' => 'admin.pays.index', 'actif' => 'admin.pays.*', 'libelle' => 'Pays', 'permission' => 'referentiels.gerer'],
        ['route' => 'admin.parametres.edit', 'actif' => 'admin.parametres.*', 'libelle' => 'Paramètres', 'permission' => 'parametres.gerer'],
        ['route' => 'admin.plans.index', 'actif' => 'admin.plans.*', 'libelle' => 'Plans', 'permission' => 'abonnements.gerer'],
        ['route' => 'admin.produits.index', 'actif' => 'admin.produits.*', 'libelle' => 'Produits', 'permission' => 'abonnements.gerer'],
        ['route' => 'admin.abonnements.index', 'actif' => 'admin.abonnements.*', 'libelle' => 'Abonnements', 'permission' => 'abonnements.gerer'],
        ['route' => 'admin.commandes.index', 'actif' => 'admin.commandes.*', 'libelle' => 'Commandes', 'permission' => 'paiements.voir'],
        ['route' => 'admin.revenus', 'actif' => 'admin.revenus', 'libelle' => 'Revenus', 'permission' => 'paiements.voir'],
    ];
@endphp
<body class="min-h-screen" x-data="{ menu: false }">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Aller au contenu</a>

    <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4">
        <button type="button" class="inline-flex size-11 items-center justify-center rounded-lg text-xl lg:hidden" @click="menu = !menu" :aria-expanded="menu.toString()" aria-controls="menu-admin">
            <span aria-hidden="true">☰</span><span class="sr-only">Menu</span>
        </button>
        <a href="{{ route('admin.tableau-de-bord') }}" class="font-bold text-brand">{{ config('app.name') }} · admin</a>
        <div class="ml-auto flex items-center gap-2 text-sm">
            @can('publications.moderer')
                <a href="{{ route('admin.moderation.index') }}" class="inline-flex min-h-10 items-center rounded-full bg-amber-100 px-3 font-semibold text-amber-900">En attente : {{ $nbEnAttente }}</a>
            @endcan
            @can('signalements.traiter')
                <a href="{{ route('admin.signalements.index') }}" class="inline-flex min-h-10 items-center rounded-full bg-red-100 px-3 font-semibold text-red-900">Signalements : {{ $nbSignalements }}</a>
            @endcan
            <a href="{{ route('fil.index') }}" class="hidden min-h-10 items-center px-2 text-slate-600 hover:text-brand sm:inline-flex">Voir le site</a>
        </div>
    </header>

    <div class="flex">
        <nav id="menu-admin" aria-label="Administration"
             class="fixed inset-y-14 left-0 z-20 w-60 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white p-3 transition-transform lg:sticky lg:top-14 lg:h-[calc(100vh-3.5rem)] lg:translate-x-0 motion-reduce:transition-none"
             :class="menu && 'translate-x-0'">
            <ul class="space-y-1">
                @foreach ($menu as $entree)
                    @can($entree['permission'])
                        @php($actif = request()->routeIs($entree['actif']))
                        <li>
                            <a href="{{ route($entree['route']) }}" @if ($actif) aria-current="page" @endif
                               class="flex min-h-11 items-center justify-between rounded-lg px-3 text-sm {{ $actif ? 'bg-brand text-white' : 'text-slate-700 hover:bg-slate-100' }}">
                                <span>{{ $entree['libelle'] }}</span>
                                @if (($entree['compteur'] ?? 0) > 0)
                                    <span class="rounded-full bg-red-600 px-2 text-xs font-bold leading-5 text-white">{{ $entree['compteur'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endcan
                @endforeach
            </ul>
        </nav>

        <main id="contenu" class="min-w-0 flex-1 p-4 lg:p-6">
            <x-admin.flash />
            @yield('contenu')
        </main>
    </div>
</body>
</html>
