@props([
    'name',
    'label',
    'options' => [],
    'groupes' => null,
    'valeur' => null,
    'vide' => null,
    'aide' => null,
])
@php
    $erreur = $errors->first($name);
    $id = $attributes->get('id', $name);
    $idAide = $id.'-aide';
    $choisie = (string) old($name, $valeur);
@endphp
<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-800">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}"
            @if ($erreur || $aide) aria-describedby="{{ $idAide }}" @endif
            @if ($erreur) aria-invalid="true" @endif
            {{ $attributes->except('id')->class([
                'min-h-11 w-full rounded-lg border bg-white px-3 text-base focus:border-brand focus:ring-2 focus:ring-brand/30',
                'border-red-600' => $erreur,
                'border-slate-300' => ! $erreur,
            ]) }}>
        @if ($vide !== null)
            <option value="">{{ $vide }}</option>
        @endif
        @if ($groupes !== null)
            @foreach ($groupes as $groupe => $optionsDuGroupe)
                <optgroup label="{{ $groupe }}">
                    @foreach ($optionsDuGroupe as $valeurOption => $libelle)
                        <option value="{{ $valeurOption }}" @selected($choisie === (string) $valeurOption)>{{ $libelle }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        @else
            @foreach ($options as $valeurOption => $libelle)
                <option value="{{ $valeurOption }}" @selected($choisie === (string) $valeurOption)>{{ $libelle }}</option>
            @endforeach
        @endif
    </select>
    @if ($erreur)
        <p id="{{ $idAide }}" class="text-sm text-red-700">{{ $erreur }}</p>
    @elseif ($aide)
        <p id="{{ $idAide }}" class="text-sm text-slate-600">{{ $aide }}</p>
    @endif
</div>
