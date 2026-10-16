@php
    $erreur = $errors->first($name) ?: $errors->first($nomPays);
    $idAide = $name.'-aide';
@endphp
<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-800">{{ $label }}</label>
    <div class="flex gap-2">
        <label for="{{ $nomPays }}" class="sr-only">Pays</label>
        <select id="{{ $nomPays }}" name="{{ $nomPays }}"
                class="min-h-11 rounded-lg border border-slate-300 bg-white px-2 text-base focus:border-brand focus:ring-2 focus:ring-brand/30">
            @foreach ($pays as $option)
                <option value="{{ $option['code'] }}" @selected(old($nomPays, $paysSelectionne) === $option['code'])>
                    {{ $option['drapeau'] }} {{ $option['indicatif'] }} — {{ $option['nom'] }}
                </option>
            @endforeach
        </select>
        <input id="{{ $name }}" name="{{ $name }}" type="tel" inputmode="tel" autocomplete="tel-national"
               value="{{ old($name, $valeur) }}" placeholder="07 08 09 10 11" required
               aria-describedby="{{ $idAide }}" @if ($erreur) aria-invalid="true" @endif
               {{ $attributes->class([
                   'min-h-11 w-full min-w-0 flex-1 rounded-lg border bg-white px-3 text-base focus:border-brand focus:ring-2 focus:ring-brand/30',
                   'border-red-600' => $erreur,
                   'border-slate-300' => ! $erreur,
               ]) }}>
    </div>
    @if ($erreur)
        <p id="{{ $idAide }}" class="text-sm text-red-700">{{ $erreur }}</p>
    @elseif ($aide)
        <p id="{{ $idAide }}" class="text-sm text-slate-600">{{ $aide }}</p>
    @endif
</div>
