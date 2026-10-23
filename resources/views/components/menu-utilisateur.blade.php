{{-- Menu du membre connecté (en-tête public). L'équipe a son propre menu (x-admin.menu-compte, D-066). Alpine : menuDeroulant. --}}
@php
    $contexte = 'public';
    /** @var \App\Models\Utilisateur $utilisateur */
    $utilisateur = auth('web')->user();
    $initiales = mb_strtoupper(mb_substr(trim((string) $utilisateur->prenoms) ?: $utilisateur->nom, 0, 1).mb_substr($utilisateur->nom, 0, 1));
    $telephone = $utilisateur->telephone !== null
        ? app(\App\Services\TelephoneService::class)->afficher($utilisateur->telephone, $utilisateur->pays_code)
        : null;

    $groupes = [
            [
                ['route' => 'espace.index', 'libelle' => 'Mon espace', 'icone' => '🏠', 'actif' => request()->routeIs('espace.index')],
                ['route' => 'espace.publications.index', 'libelle' => 'Mes publications', 'icone' => '📝', 'actif' => request()->routeIs('espace.publications.*', 'espace.reponses.*')],
                ['route' => 'alertes.index', 'libelle' => 'Mes alertes', 'icone' => '🔔', 'actif' => request()->routeIs('alertes.*')],
                ['route' => 'espace.enregistres', 'libelle' => 'Enregistrés', 'icone' => '🔖', 'actif' => request()->routeIs('espace.enregistres')],
            ],
            [
                ['route' => 'espace.abonnement', 'libelle' => 'Mon abonnement', 'icone' => '⭐', 'actif' => request()->routeIs('espace.abonnement', 'espace.recu*')],
                ['route' => 'espace.profil', 'libelle' => 'Mon profil', 'icone' => '👤', 'actif' => request()->routeIs('espace.profil', 'espace.numero')],
            ],
        ];
@endphp
<div x-data="menuDeroulant" class="relative" @keydown.escape.window="fermer()" @click.outside="fermer(false)" @focusout="$event.relatedTarget && !$el.contains($event.relatedTarget) && fermer(false)">
    <button type="button" x-ref="bouton" @click="basculer()" @keydown.arrow-down.prevent="ouvrir()"
            :aria-expanded="ouvert.toString()" aria-expanded="false" aria-haspopup="true" aria-controls="menu-compte-{{ $contexte }}"
            class="flex min-h-11 items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 aria-expanded:bg-slate-100">
        <span aria-hidden="true" class="inline-flex size-9 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{{ $initiales }}</span>
        <span class="hidden max-w-32 truncate md:inline">{{ $utilisateur->nomAffiche() }}</span>
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 text-slate-500 transition-transform motion-reduce:transition-none" :class="ouvert && 'rotate-180'">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
        </svg>
        <span class="sr-only">Menu du compte</span>
    </button>

    <div id="menu-compte-{{ $contexte }}" x-ref="panneau" x-cloak x-show="ouvert"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
         class="absolute right-0 top-full z-50 mt-2 w-64 origin-top-right overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
            <span aria-hidden="true" class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-clair font-bold text-brand-fonce">{{ $initiales }}</span>
            <div class="min-w-0">
                <p class="truncate font-semibold text-slate-900">{{ trim($utilisateur->prenoms.' '.$utilisateur->nom) }}</p>
                @if ($telephone)
                    <p class="truncate text-xs text-slate-500">{{ $telephone }}</p>
                @endif
            </div>
        </div>

        @foreach ($groupes as $groupe)
            <ul class="border-b border-slate-100 py-1">
                @foreach ($groupe as $entree)
                    <li>
                        <a href="{{ route($entree['route']) }}" @if ($entree['actif']) aria-current="page" @endif
                           class="flex min-h-11 items-center gap-3 px-4 text-sm text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50 aria-[current=page]:bg-brand-clair aria-[current=page]:font-semibold aria-[current=page]:text-brand-fonce">
                            <span aria-hidden="true" class="w-5 text-center">{{ $entree['icone'] }}</span>{{ $entree['libelle'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach

        <form method="POST" action="{{ route('logout') }}" class="py-1">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm text-red-700 hover:bg-red-50 focus-visible:bg-red-50">
                <span aria-hidden="true" class="w-5 text-center">↪</span>Se déconnecter
            </button>
        </form>
    </div>
</div>
