{{-- Score de fiabilité : rouge < 40, orange 40–69, vert ≥ 70, gris tant que l'IA n'a pas répondu. --}}
<span @class([
    'inline-block min-w-10 rounded-full px-2 py-0.5 text-center text-xs font-bold',
    'bg-slate-100 text-slate-600' => $score === null,
    'bg-red-100 text-red-900' => $score !== null && $score < 40,
    'bg-amber-100 text-amber-900' => $score !== null && $score >= 40 && $score < 70,
    'bg-green-100 text-green-900' => $score !== null && $score >= 70,
])>{{ $score ?? '—' }}</span>
