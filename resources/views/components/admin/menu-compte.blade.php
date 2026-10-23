{{-- Menu de l'administrateur connecté (en-tête du back-office, garde admin). Alpine : menuDeroulant. --}}
@php
    /** @var \App\Models\Administrateur $admin */
    $admin = auth('admin')->user();
    $role = $admin->getRoleNames()->first();
@endphp
<div x-data="menuDeroulant" class="relative" @keydown.escape.window="fermer()" @click.outside="fermer(false)"
     @focusout="$event.relatedTarget && !$el.contains($event.relatedTarget) && fermer(false)">
    <button type="button" x-ref="bouton" @click="basculer()" @keydown.arrow-down.prevent="ouvrir()"
            :aria-expanded="ouvert.toString()" aria-expanded="false" aria-haspopup="true" aria-controls="menu-compte-admin"
            class="flex min-h-11 items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 aria-expanded:bg-slate-100">
        <span aria-hidden="true" class="inline-flex size-9 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{{ $admin->initiales() }}</span>
        <span class="hidden text-left leading-tight md:block">
            <span class="block max-w-36 truncate">{{ $admin->nomComplet() }}</span>
            <span class="block text-xs font-normal text-slate-500">{{ $role ? \App\Support\PermissionsAdmin::libelleRole($role) : 'Sans rôle' }}</span>
        </span>
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 text-slate-500 transition-transform motion-reduce:transition-none" :class="ouvert && 'rotate-180'">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
        </svg>
        <span class="sr-only">Menu du compte</span>
    </button>

    <div id="menu-compte-admin" x-ref="panneau" x-cloak x-show="ouvert"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
         class="absolute right-0 top-full z-50 mt-2 w-64 origin-top-right overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
        <div class="border-b border-slate-100 px-4 py-3">
            <p class="truncate font-semibold text-slate-900">{{ $admin->nomComplet() }}</p>
            <p class="truncate text-xs text-slate-500">{{ '@'.$admin->nom_utilisateur }}</p>
        </div>
        <ul class="border-b border-slate-100 py-1">
            <li>
                <a href="{{ route('admin.mot-de-passe.edit') }}" class="flex min-h-11 items-center gap-3 px-4 text-sm text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50">
                    <x-admin.icone nom="cle" class="size-4 text-slate-500" />Mon mot de passe
                </a>
            </li>
            <li>
                <a href="{{ route('fil.index') }}" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-3 px-4 text-sm text-slate-700 hover:bg-slate-50 focus-visible:bg-slate-50">
                    <x-admin.icone nom="site" class="size-4 text-slate-500" />Voir le site
                </a>
            </li>
        </ul>
        <form method="POST" action="{{ route('admin.deconnexion') }}" class="py-1">
            @csrf
            <button type="submit" class="flex min-h-11 w-full items-center gap-3 px-4 text-left text-sm text-red-700 hover:bg-red-50 focus-visible:bg-red-50">
                <x-admin.icone nom="sortie" class="size-4" />Se déconnecter
            </button>
        </form>
    </div>
</div>
