@props([
    'name',
    'label',
    'type' => 'text',
    'valeur' => null,
    'aide' => null,
])
@php
    $erreur = $errors->first($name);
    $id = $attributes->get('id', $name);
    $idAide = $id.'-aide';
    $conserverSaisie = ! in_array($type, ['password'], true);
@endphp
<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-800">{{ $label }}</label>
    @if ($type === 'password')
        {{-- Bouton œil : affiche ou masque la saisie (Alpine motDePasse, resources/js/interface.js). --}}
        <div class="relative" x-data="motDePasse">
            <input id="{{ $id }}" name="{{ $name }}" type="password" x-ref="champ" :type="visible ? 'text' : 'password'"
                   @if ($erreur || $aide) aria-describedby="{{ $idAide }}" @endif
                   @if ($erreur) aria-invalid="true" @endif
                   {{ $attributes->except('id')->class([
                       'min-h-11 w-full rounded-lg border bg-white pl-3 pr-12 text-base focus:border-brand focus:ring-2 focus:ring-brand/30',
                       'border-red-600' => $erreur,
                       'border-slate-300' => ! $erreur,
                   ]) }}>
            <button type="button" x-cloak @click="basculer()" :aria-pressed="visible.toString()" aria-controls="{{ $id }}"
                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-500 hover:text-slate-800">
                <svg x-show="!visible" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                    <path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                    <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
                <svg x-show="visible" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                    <path d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                </svg>
                <span class="sr-only" x-text="visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'">Afficher le mot de passe</span>
            </button>
        </div>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
               @if ($conserverSaisie) value="{{ old($name, $valeur) }}" @endif
               @if ($erreur || $aide) aria-describedby="{{ $idAide }}" @endif
               @if ($erreur) aria-invalid="true" @endif
               {{ $attributes->except('id')->class([
                   'min-h-11 w-full rounded-lg border bg-white px-3 text-base focus:border-brand focus:ring-2 focus:ring-brand/30',
                   'border-red-600' => $erreur,
                   'border-slate-300' => ! $erreur,
               ]) }}>
    @endif
    @if ($erreur)
        <p id="{{ $idAide }}" class="text-sm text-red-700">{{ $erreur }}</p>
    @elseif ($aide)
        <p id="{{ $idAide }}" class="text-sm text-slate-600">{{ $aide }}</p>
    @endif
</div>
