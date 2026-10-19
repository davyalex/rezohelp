@props([
    'name',
    'label',
    'coche' => false,
    'valeur' => '1',
])
@php
    $id = $attributes->get('id', $name.'-'.$valeur);
    $estCoche = old() !== [] ? (bool) old($name) : (bool) $coche;
@endphp
<label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-center gap-3 text-sm text-slate-800">
    <input type="hidden" name="{{ $name }}" value="0">
    <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $valeur }}" @checked($estCoche)
           {{ $attributes->except('id')->class('size-5 rounded border-slate-300 text-brand focus:ring-brand') }}>
    <span>{{ $label }}</span>
</label>
