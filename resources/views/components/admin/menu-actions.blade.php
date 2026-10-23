{{--
    Actions d'une ligne de tableau dans un menu déroulant « ⋯ » (API popover : au-dessus de tout,
    jamais coupé par le défilement du tableau ; Échap et clic extérieur le ferment). Positionné par admin.js.
--}}
@props(['id', 'libelle' => 'Actions'])
<div class="flex justify-end">
    <button type="button" popovertarget="actions-{{ $id }}" data-menu-actions-bouton
            class="inline-flex size-9 items-center justify-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 aria-expanded:bg-slate-100">
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M3 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm5.5 0a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM15.5 8.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>
        <span class="sr-only">{{ $libelle }}</span>
    </button>
    <div id="actions-{{ $id }}" popover role="menu" aria-label="{{ $libelle }}" class="menu-actions">
        {{ $slot }}
    </div>
</div>
