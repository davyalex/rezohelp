{{-- Entrée d'un menu d'actions (x-admin.menu-actions) : lien, ou bouton d'envoi s'il est dans un formulaire. --}}
@props(['href' => null, 'ton' => 'normal', 'externe' => false])
@php
    $classes = [
        'flex min-h-10 w-full items-center gap-2 rounded-lg px-3 text-left text-sm font-medium transition-colors focus-visible:outline-2',
        'text-slate-700 hover:bg-slate-100' => $ton === 'normal',
        'text-amber-800 hover:bg-amber-50' => $ton === 'attention',
        'text-red-700 hover:bg-red-50' => $ton === 'danger',
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" role="menuitem" @if ($externe) target="_blank" rel="noopener" @endif {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="submit" role="menuitem" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
