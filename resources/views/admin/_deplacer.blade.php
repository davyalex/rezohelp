{{-- Flèches haut / bas d'un élément de référentiel ($route : nom de la route « deplacer »). --}}
<span class="inline-flex gap-1">
    @foreach (['haut' => '↑', 'bas' => '↓'] as $sens => $fleche)
        <form method="POST" action="{{ route($route, $element) }}">
            @csrf
            <input type="hidden" name="sens" value="{{ $sens }}">
            <button class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-300 text-sm hover:bg-slate-100" aria-label="Monter ou descendre « {{ $element->nom }} » : {{ $sens }}">{{ $fleche }}</button>
        </form>
    @endforeach
</span>
