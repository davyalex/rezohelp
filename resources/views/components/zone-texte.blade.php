@props([
    'name',
    'label',
    'valeur' => null,
    'aide' => null,
    'min' => null,
    'max' => null,
    'lignes' => 5,
])
@php
    $erreur = $errors->first($name);
    $id = $attributes->get('id', $name);
    $idAide = $id.'-aide';
@endphp
<div class="space-y-1.5" x-data="{ longueur: 0 }" x-init="longueur = $refs.champ.value.length">
    <div class="flex items-baseline justify-between gap-2">
        <label for="{{ $id }}" class="block text-sm font-medium text-slate-800">{{ $label }}</label>
        @if ($max)
            <span class="text-xs text-slate-600" aria-live="polite"><span x-text="longueur">0</span> / {{ $max }}</span>
        @endif
    </div>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $lignes }}" x-ref="champ" @input="longueur = $event.target.value.length"
              @if ($min) minlength="{{ $min }}" @endif @if ($max) maxlength="{{ $max }}" @endif
              aria-describedby="{{ $idAide }}" @if ($erreur) aria-invalid="true" @endif
              {{ $attributes->except('id')->class([
                  'w-full rounded-lg border bg-white px-3 py-2 text-base focus:border-brand focus:ring-2 focus:ring-brand/30',
                  'border-red-600' => $erreur,
                  'border-slate-300' => ! $erreur,
              ]) }}>{{ old($name, $valeur) }}</textarea>
    @if ($erreur)
        <p id="{{ $idAide }}" class="text-sm text-red-700">{{ $erreur }}</p>
    @else
        <p id="{{ $idAide }}" class="text-sm text-slate-600">{{ $aide ?? ($min ? 'Au moins '.$min.' caractères.' : '') }}</p>
    @endif
</div>
