@props(['cle', 'titre'])
{{-- Incitation contextuelle (CONTEXTE §14.11) : toujours fermable, jamais bloquante. --}}
<aside x-data="incitation(@js($cle))" x-show="visible"
       {{ $attributes->class('relative rounded-2xl border border-brand/30 bg-brand-clair p-4 pr-14 text-slate-800') }}
       aria-label="{{ $titre }}">
    <p class="font-semibold text-brand-fonce">{{ $titre }}</p>
    <div class="mt-1 text-sm">{{ $slot }}</div>
    <button type="button" @click="fermer()" x-cloak
            class="absolute right-1 top-1 flex size-11 items-center justify-center rounded-full text-xl text-slate-600 hover:bg-white/60">
        <span aria-hidden="true">×</span><span class="sr-only">Fermer</span>
    </button>
</aside>
