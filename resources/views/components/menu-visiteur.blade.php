{{-- Menu du visiteur non connecté : connexion et inscription derrière une icône. Alpine : menuDeroulant. --}}
<div x-data="menuDeroulant" class="relative" @keydown.escape.window="fermer()" @click.outside="fermer(false)"
     @focusout="$event.relatedTarget && !$el.contains($event.relatedTarget) && fermer(false)">
    {{-- Sans JavaScript, le bouton mène simplement à la page de connexion. --}}
    <a href="{{ route('login') }}" x-ref="bouton" @click.prevent="basculer()" @keydown.space.prevent="basculer()" @keydown.arrow-down.prevent="ouvrir()"
       role="button" :aria-expanded="ouvert.toString()" aria-haspopup="true" aria-controls="menu-visiteur"
       class="flex min-h-11 items-center gap-1.5 rounded-full border border-slate-300 py-1 pl-1 pr-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 aria-expanded:bg-slate-100">
        <span aria-hidden="true" class="inline-flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-600">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                <path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
        </span>
        <span class="hidden sm:inline">Compte</span>
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-4 text-slate-500 transition-transform motion-reduce:transition-none" :class="ouvert && 'rotate-180'">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
        </svg>
        <span class="sr-only">Se connecter ou créer un compte</span>
    </a>

    <div id="menu-visiteur" x-ref="panneau" x-cloak x-show="ouvert"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
         class="absolute right-0 top-full z-50 mt-2 w-72 origin-top-right overflow-hidden rounded-2xl bg-white p-2 shadow-xl ring-1 ring-slate-200">
        <p class="px-3 pb-2 pt-1 text-sm text-slate-600">Créez des alertes, postulez et publiez gratuitement.</p>
        <a href="{{ route('login') }}" class="flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:bg-slate-50">
            <span aria-hidden="true" class="inline-flex size-9 items-center justify-center rounded-full bg-brand-clair text-brand">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                    <path d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>
                </svg>
            </span>
            <span><span class="block">Se connecter</span><span class="block text-xs font-normal text-slate-500">J’ai déjà un compte</span></span>
        </a>
        <a href="{{ route('register') }}" class="flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold text-slate-800 hover:bg-slate-50 focus-visible:bg-slate-50">
            <span aria-hidden="true" class="inline-flex size-9 items-center justify-center rounded-full bg-brand text-white">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                    <path d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/>
                </svg>
            </span>
            <span><span class="block">Créer un compte</span><span class="block text-xs font-normal text-slate-500">Gratuit, avec votre numéro</span></span>
        </a>
    </div>
</div>
