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
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
           @if ($conserverSaisie) value="{{ old($name, $valeur) }}" @endif
           @if ($erreur || $aide) aria-describedby="{{ $idAide }}" @endif
           @if ($erreur) aria-invalid="true" @endif
           {{ $attributes->except('id')->class([
               'min-h-11 w-full rounded-lg border bg-white px-3 text-base focus:border-brand focus:ring-2 focus:ring-brand/30',
               'border-red-600' => $erreur,
               'border-slate-300' => ! $erreur,
           ]) }}>
    @if ($erreur)
        <p id="{{ $idAide }}" class="text-sm text-red-700">{{ $erreur }}</p>
    @elseif ($aide)
        <p id="{{ $idAide }}" class="text-sm text-slate-600">{{ $aide }}</p>
    @endif
</div>
