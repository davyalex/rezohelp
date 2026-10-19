@php
    $entrees = [
        ['route' => 'espace.index', 'libelle' => 'Accueil', 'icone' => '🏠', 'actif' => request()->routeIs('espace.index')],
        ['route' => 'espace.publications.index', 'libelle' => 'Mes publications', 'icone' => '📝', 'actif' => request()->routeIs('espace.publications.*', 'espace.reponses.*')],
        ['route' => 'alertes.index', 'libelle' => 'Alertes', 'icone' => '🔔', 'actif' => request()->routeIs('alertes.*')],
        ['route' => 'espace.notifications', 'libelle' => 'Notifications', 'icone' => '📬', 'actif' => request()->routeIs('espace.notifications*')],
        ['route' => 'espace.enregistres', 'libelle' => 'Enregistrés', 'icone' => '🔖', 'actif' => request()->routeIs('espace.enregistres')],
        ['route' => 'espace.abonnement', 'libelle' => 'Abonnement', 'icone' => '⭐', 'actif' => request()->routeIs('espace.abonnement', 'espace.recu*')],
        ['route' => 'espace.profil', 'libelle' => 'Profil', 'icone' => '👤', 'actif' => request()->routeIs('espace.profil', 'espace.numero')],
    ];
@endphp
<nav aria-label="Mon espace" class="-mx-4 mb-4 overflow-x-auto px-4">
    <ul class="flex gap-2">
        @foreach ($entrees as $entree)
            <li>
                <a href="{{ route($entree['route']) }}" @if ($entree['actif']) aria-current="page" @endif
                   class="inline-flex min-h-11 items-center gap-1 whitespace-nowrap rounded-full px-4 text-sm font-semibold {{ $entree['actif'] ? 'bg-brand text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                    <span aria-hidden="true">{{ $entree['icone'] }}</span>{{ $entree['libelle'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
