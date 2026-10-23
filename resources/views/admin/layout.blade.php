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
    /** @var \App\Models\Administrateur $administrateur */
    $administrateur = auth('admin')->user();
    $statistiques = app(\App\Services\StatistiquesService::class);
    $nbEnAttente = $administrateur->can('publications.moderer') ? $statistiques->enAttente() : 0;
    $nbSignalements = $administrateur->can('signalements.traiter') ? $statistiques->signalementsOuverts() : 0;

    // Préférences d'affichage (cookies non chiffrés écrits par admin.js) : rendues côté serveur, sans clignotement.
    $reduit = request()->cookie('admin_menu_reduit') === '1';
    $groupesFermes = array_values(array_filter(explode('-', (string) request()->cookie('admin_menu_groupes'))));

    $groupes = [
        ['cle' => 'pilotage', 'titre' => null, 'entrees' => [
            ['route' => 'admin.tableau-de-bord', 'actif' => 'admin.tableau-de-bord', 'libelle' => 'Tableau de bord', 'icone' => 'accueil', 'permission' => 'tableau_de_bord.voir'],
        ]],
        ['cle' => 'contenu', 'titre' => 'Contenu', 'entrees' => [
            ['route' => 'admin.moderation.index', 'actif' => 'admin.moderation.*', 'libelle' => 'Modération', 'icone' => 'bouclier', 'permission' => 'publications.moderer', 'compteur' => $nbEnAttente, 'ton' => 'bg-amber-500'],
            ['route' => 'admin.publications.index', 'actif' => 'admin.publications.*', 'libelle' => 'Publications', 'icone' => 'document', 'permission' => 'publications.gerer'],
            ['route' => 'admin.signalements.index', 'actif' => 'admin.signalements.*', 'libelle' => 'Signalements', 'icone' => 'alerte', 'permission' => 'signalements.traiter', 'compteur' => $nbSignalements, 'ton' => 'bg-red-600'],
        ]],
        ['cle' => 'communaute', 'titre' => 'Membres du site', 'entrees' => [
            ['route' => 'admin.utilisateurs.index', 'actif' => 'admin.utilisateurs.*', 'libelle' => 'Membres', 'icone' => 'utilisateurs', 'permission' => 'utilisateurs.voir'],
            ['route' => 'admin.entreprises.index', 'actif' => 'admin.entreprises.*', 'libelle' => 'Entreprises', 'icone' => 'immeuble', 'permission' => 'utilisateurs.voir'],
        ]],
        ['cle' => 'veille', 'titre' => 'Veille IA', 'entrees' => [
            ['route' => 'admin.veille.imports.index', 'actif' => 'admin.veille.imports.*', 'libelle' => 'Imports', 'icone' => 'import', 'permission' => 'veille.gerer'],
            ['route' => 'admin.veille.sources.index', 'actif' => 'admin.veille.sources.*', 'libelle' => 'Sources', 'icone' => 'globe', 'permission' => 'veille.gerer'],
        ]],
        ['cle' => 'monetisation', 'titre' => 'Monétisation', 'entrees' => [
            ['route' => 'admin.revenus', 'actif' => 'admin.revenus', 'libelle' => 'Revenus', 'icone' => 'graphique', 'permission' => 'paiements.voir'],
            ['route' => 'admin.commandes.index', 'actif' => 'admin.commandes.*', 'libelle' => 'Commandes', 'icone' => 'panier', 'permission' => 'paiements.voir'],
            ['route' => 'admin.abonnements.index', 'actif' => 'admin.abonnements.*', 'libelle' => 'Abonnements', 'icone' => 'carte', 'permission' => 'abonnements.gerer'],
            ['route' => 'admin.plans.index', 'actif' => 'admin.plans.*', 'libelle' => 'Plans', 'icone' => 'pile', 'permission' => 'abonnements.gerer'],
            ['route' => 'admin.produits.index', 'actif' => 'admin.produits.*', 'libelle' => 'Produits', 'icone' => 'cube', 'permission' => 'abonnements.gerer'],
        ]],
        ['cle' => 'configuration', 'titre' => 'Configuration', 'entrees' => [
            ['route' => 'admin.categories.index', 'actif' => 'admin.categories.*', 'libelle' => 'Catégories', 'icone' => 'etiquette', 'permission' => 'referentiels.gerer'],
            ['route' => 'admin.communes.index', 'actif' => 'admin.communes.*', 'libelle' => 'Communes', 'icone' => 'repere', 'permission' => 'referentiels.gerer'],
            ['route' => 'admin.pays.index', 'actif' => 'admin.pays.*', 'libelle' => 'Pays', 'icone' => 'drapeau', 'permission' => 'referentiels.gerer'],
            ['route' => 'admin.parametres.edit', 'actif' => 'admin.parametres.*', 'libelle' => 'Paramètres', 'icone' => 'reglages', 'permission' => 'parametres.gerer'],
        ]],
        ['cle' => 'equipe', 'titre' => 'Équipe', 'entrees' => [
            ['route' => 'admin.administrateurs.index', 'actif' => 'admin.administrateurs.*', 'libelle' => 'Administrateurs', 'icone' => 'compte', 'permission' => 'administrateurs.gerer'],
            ['route' => 'admin.roles.index', 'actif' => 'admin.roles.*', 'libelle' => 'Rôles et permissions', 'icone' => 'cadenas', 'permission' => 'roles.gerer'],
        ]],
    ];

    // Seules les entrées autorisées sont gardées ; un groupe vide disparaît.
    $groupes = array_values(array_filter(array_map(function (array $groupe) use ($administrateur): array {
        $groupe['entrees'] = array_values(array_filter($groupe['entrees'], fn (array $entree): bool => $administrateur->can($entree['permission'])));

        return $groupe;
    }, $groupes), fn (array $groupe): bool => $groupe['entrees'] !== []));
@endphp
<body class="group/admin min-h-screen" @if ($reduit) data-reduit @endif
      x-data="layoutAdmin(@js($reduit))" :data-reduit="reduit || null"
      @keydown.escape.window="menu = false">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Aller au contenu</a>

    <header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-slate-200 bg-white/95 px-3 backdrop-blur sm:px-4">
        <button type="button" class="inline-flex size-11 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 lg:hidden"
                @click="menu = !menu" :aria-expanded="menu.toString()" aria-expanded="false" aria-controls="menu-admin">
            <x-admin.icone nom="menu" class="size-6" /><span class="sr-only">Ouvrir le menu</span>
        </button>
        <button type="button" class="hidden size-10 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 lg:inline-flex"
                @click="basculerReduit()" :aria-pressed="reduit.toString()" aria-pressed="{{ $reduit ? 'true' : 'false' }}" aria-controls="menu-admin">
            <x-admin.icone nom="reduire" class="transition-transform duration-300 group-data-[reduit]/admin:rotate-180 motion-reduce:transition-none" />
            <span class="sr-only">Réduire le menu latéral</span>
        </button>
        <a href="{{ route('admin.tableau-de-bord') }}" class="flex items-center gap-2 font-bold text-brand">
            <span aria-hidden="true" class="inline-flex size-8 items-center justify-center rounded-lg bg-brand text-sm text-white">R</span>
            <span class="hidden sm:inline">{{ config('app.name') }}</span>
            <span class="rounded-md bg-brand-clair px-1.5 py-0.5 text-xs font-semibold text-brand-fonce">admin</span>
        </a>

        <div class="ml-auto flex items-center gap-1 text-sm sm:gap-2">
            @can('publications.moderer')
                <a href="{{ route('admin.moderation.index') }}" title="Publications en attente de modération"
                   class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-amber-100 px-3 font-semibold text-amber-900 hover:bg-amber-200">
                    <x-admin.icone nom="bouclier" class="size-4" /><span class="hidden md:inline">En attente</span>
                    <span>{{ $nbEnAttente }}</span><span class="sr-only md:hidden">en attente</span>
                </a>
            @endcan
            @can('signalements.traiter')
                <a href="{{ route('admin.signalements.index') }}" title="Signalements ouverts"
                   class="hidden min-h-10 items-center gap-1.5 rounded-full bg-red-100 px-3 font-semibold text-red-900 hover:bg-red-200 sm:inline-flex">
                    <x-admin.icone nom="alerte" class="size-4" /><span class="hidden md:inline">Signalements</span>
                    <span>{{ $nbSignalements }}</span><span class="sr-only md:hidden">signalements</span>
                </a>
            @endcan
            <x-admin.notifications />
            <span aria-hidden="true" class="mx-1 hidden h-6 w-px bg-slate-200 sm:block"></span>
            <x-admin.menu-compte />
        </div>
    </header>

    <div class="flex">
        {{-- Voile derrière le menu ouvert sur mobile --}}
        <div x-cloak x-show="menu" x-transition.opacity @click="menu = false" class="fixed inset-0 top-14 z-10 bg-slate-900/40 lg:hidden"></div>

        <nav id="menu-admin" aria-label="Administration"
             class="fixed inset-y-14 left-0 z-20 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[transform,width] duration-300 ease-out motion-reduce:transition-none lg:sticky lg:top-14 lg:h-[calc(100vh-3.5rem)] lg:translate-x-0 lg:group-data-[reduit]/admin:w-[4.5rem]"
             :class="menu && 'translate-x-0'">
            <div data-menu-defilant class="barre-fine relative flex-1 overflow-y-auto overflow-x-hidden overscroll-contain p-3">
                @foreach ($groupes as $groupe)
                    @php
                        $contientActif = collect($groupe['entrees'])->contains(fn (array $entree): bool => request()->routeIs($entree['actif']));
                        $ouvert = $groupe['titre'] === null || $contientActif || ! in_array($groupe['cle'], $groupesFermes, true);
                    @endphp
                    {{-- Menu réduit (ordinateur) : toutes les icônes restent visibles, groupes séparés par un trait. --}}
                    <div @class(['mt-2' => ! $loop->first]) x-data="{ ouvert: @js($ouvert) }">
                        @if ($groupe['titre'] !== null)
                            <button type="button" @click="ouvert = !ouvert; memoriserGroupe(@js($groupe['cle']), ouvert)"
                                    :aria-expanded="ouvert.toString()" aria-expanded="{{ $ouvert ? 'true' : 'false' }}" aria-controls="groupe-{{ $groupe['cle'] }}"
                                    class="flex min-h-9 w-full items-center gap-2 rounded-md px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-800 lg:group-data-[reduit]/admin:hidden">
                                <span class="flex-1 truncate text-left">{{ $groupe['titre'] }}</span>
                                @if ($contientActif)
                                    <span aria-hidden="true" class="size-1.5 rounded-full bg-brand transition-opacity {{ $ouvert ? 'opacity-0' : '' }}" x-bind:class="{ 'opacity-0': ouvert }"></span>
                                @endif
                                <x-admin.icone nom="chevron-bas" :class="'size-4 transition-transform duration-200 motion-reduce:transition-none'.($ouvert ? '' : ' -rotate-90')" x-bind:class="{ '-rotate-90': !ouvert }" />
                            </button>
                            <hr aria-hidden="true" class="mx-2 mb-2 hidden border-slate-200 lg:group-data-[reduit]/admin:block">
                        @endif
                        {{-- Ouverture animée (hauteur 0fr → 1fr) ; « invisible » retire les liens repliés du parcours au clavier. --}}
                        <div id="groupe-{{ $groupe['cle'] }}"
                             @class([
                                 'grid transition-[grid-template-rows,visibility] duration-200 ease-out motion-reduce:transition-none',
                                 'lg:group-data-[reduit]/admin:visible! lg:group-data-[reduit]/admin:grid-rows-[1fr]!',
                                 'grid-rows-[1fr] visible' => $ouvert,
                                 'grid-rows-[0fr] invisible' => ! $ouvert,
                             ])
                             x-bind:class="{ 'grid-rows-[1fr] visible': ouvert, 'grid-rows-[0fr] invisible': !ouvert }">
                            <ul class="min-h-0 space-y-0.5 overflow-hidden">
                                @foreach ($groupe['entrees'] as $entree)
                                    @php($actif = request()->routeIs($entree['actif']))
                                    <li>
                                        <a href="{{ route($entree['route']) }}" data-lien-menu @if ($actif) aria-current="page" @endif
                                           :title="reduit ? @js($entree['libelle']) : null"
                                           class="relative flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 aria-[current=page]:bg-brand aria-[current=page]:font-semibold aria-[current=page]:text-white aria-[current=page]:shadow-sm lg:group-data-[reduit]/admin:justify-center lg:group-data-[reduit]/admin:px-0">
                                            <x-admin.icone :nom="$entree['icone']" />
                                            <span class="flex-1 truncate whitespace-nowrap lg:group-data-[reduit]/admin:sr-only">{{ $entree['libelle'] }}</span>
                                            @if (($entree['compteur'] ?? 0) > 0)
                                                <span class="rounded-full px-2 text-xs font-bold leading-5 text-white {{ $entree['ton'] }} lg:group-data-[reduit]/admin:hidden">{{ $entree['compteur'] }}</span>
                                                <span aria-hidden="true" class="absolute right-3 top-2 hidden size-2.5 rounded-full ring-2 ring-white {{ $entree['ton'] }} lg:group-data-[reduit]/admin:block"></span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-200 p-3 lg:hidden">
                <a href="{{ route('fil.index') }}" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm text-slate-700 hover:bg-slate-100">
                    <x-admin.icone nom="site" />Voir le site
                </a>
            </div>
        </nav>

        <main id="contenu" class="min-w-0 flex-1 p-4 lg:p-6">
            <x-admin.flash />
            @yield('contenu')
        </main>
    </div>
</body>
</html>
