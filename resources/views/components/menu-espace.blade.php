@php
    $nbNonLues = auth('web')->user()?->unreadNotifications()->count() ?? 0;
    $entrees = [
        ['route' => 'espace.index', 'libelle' => 'Accueil', 'icone' => '🏠', 'actif' => request()->routeIs('espace.index')],
        ['route' => 'espace.publications.index', 'libelle' => 'Mes publications', 'icone' => '📝', 'actif' => request()->routeIs('espace.publications.*', 'espace.reponses.*')],
        ['route' => 'alertes.index', 'libelle' => 'Alertes', 'icone' => '🔔', 'actif' => request()->routeIs('alertes.*')],
        ['route' => 'espace.notifications', 'libelle' => 'Notifications', 'icone' => '📬', 'actif' => request()->routeIs('espace.notifications*'), 'compteur' => $nbNonLues],
        ['route' => 'espace.enregistres', 'libelle' => 'Enregistrés', 'icone' => '🔖', 'actif' => request()->routeIs('espace.enregistres')],
        ['route' => 'espace.abonnement', 'libelle' => 'Abonnement', 'icone' => '⭐', 'actif' => request()->routeIs('espace.abonnement', 'espace.recu*')],
        ['route' => 'espace.profil', 'libelle' => 'Profil', 'icone' => '👤', 'actif' => request()->routeIs('espace.profil', 'espace.numero')],
    ];
@endphp
{{-- Onglets de « Mon espace » : barre collée sous l'en-tête, défilante, onglet actif centré au chargement. --}}
<nav aria-label="Mon espace" class="sticky top-14 z-30 -mx-4 mb-5 border-b border-slate-200 bg-white/95 px-2 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:shadow-sm">
    <x-rangee-defilante libelle="Rubriques de Mon espace">
        @foreach ($entrees as $entree)
            <li class="shrink-0 snap-start">
                <a href="{{ route($entree['route']) }}" @if ($entree['actif']) aria-current="page" @endif
                   class="relative inline-flex min-h-12 items-center gap-1.5 whitespace-nowrap px-3 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-900 aria-[current=page]:text-brand after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:rounded-full after:bg-transparent aria-[current=page]:after:bg-brand">
                    <span aria-hidden="true">{{ $entree['icone'] }}</span>{{ $entree['libelle'] }}
                    @if (($entree['compteur'] ?? 0) > 0)
                        <span class="min-w-5 rounded-full bg-red-600 px-1.5 text-center text-xs font-bold leading-5 text-white">{{ $entree['compteur'] > 9 ? '9+' : $entree['compteur'] }}<span class="sr-only"> non lue(s)</span></span>
                    @endif
                </a>
            </li>
        @endforeach
    </x-rangee-defilante>
</nav>
