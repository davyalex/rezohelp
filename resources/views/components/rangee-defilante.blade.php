{{--
    Rangée de puces défilant à l'horizontale : doigt ou molette sur mobile, flèches sur ordinateur,
    fondu sur les bords quand il reste du contenu. Alpine : defilementHorizontal (resources/js/interface.js).
--}}
@props(['libelle', 'titre' => null])
<div {{ $attributes->class('flex items-center gap-2') }}>
    @if ($titre)
        <span aria-hidden="true" class="hidden shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:block sm:w-20">{{ $titre }}</span>
    @endif
    <div x-data="defilementHorizontal" class="relative min-w-0 flex-1" role="group" aria-label="{{ $libelle }}">
        <button type="button" x-cloak x-show="gauche" @click="defiler(-1)" tabindex="-1" aria-hidden="true"
                class="absolute left-0 top-1/2 z-10 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-700 shadow-md ring-1 ring-slate-200 hover:bg-slate-50 sm:flex">
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.83 10l3.94 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/></svg>
        </button>
        <ul x-ref="zone" @scroll.passive="mesurer()"
            :class="{ 'masque-gauche': gauche, 'masque-droite': droite }"
            class="sans-barre flex snap-x scroll-px-4 gap-2 overflow-x-auto overscroll-x-contain py-1">
            {{ $slot }}
        </ul>
        <button type="button" x-cloak x-show="droite" @click="defiler(1)" tabindex="-1" aria-hidden="true"
                class="absolute right-0 top-1/2 z-10 hidden size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-700 shadow-md ring-1 ring-slate-200 hover:bg-slate-50 sm:flex">
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.17 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
        </button>
    </div>
</div>
