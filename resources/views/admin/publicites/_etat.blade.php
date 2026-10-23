@php
    [$libelle, $classes] = match ($etat) {
        'en_cours' => ['En cours', 'bg-green-100 text-green-800'],
        'programmee' => ['Programmée', 'bg-sky-100 text-sky-800'],
        'terminee' => ['Terminée', 'bg-slate-100 text-slate-600'],
        default => ['Inactive', 'bg-amber-100 text-amber-900'],
    };
@endphp
<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $classes }}">{{ $libelle }}</span>
