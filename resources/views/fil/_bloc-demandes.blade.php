{{-- Demandes de prestation mises en avant (D-069) : des particuliers cherchent un artisan ou un prestataire. --}}
@php($referentiels = app(\App\Services\Referentiels::class))
<section aria-labelledby="titre-demandes-{{ $contexte }}" class="rounded-2xl bg-service-clair p-4 ring-1 ring-service/30">
    <h2 id="titre-demandes-{{ $contexte }}" class="flex items-center gap-2 font-bold text-slate-900">
        <span aria-hidden="true">🙋</span>Ils cherchent un prestataire
        <span class="ml-auto rounded-full bg-service px-2 text-xs font-bold leading-5 text-white">{{ $demandesRecentes['nombre'] }}<span class="sr-only"> demande(s) en cours</span></span>
    </h2>
    <ul class="mt-3 space-y-2">
        @foreach ($demandesRecentes['demandes'] as $demande)
            <li>
                <a href="{{ route('publications.show', $demande['slug']) }}#repondre" class="block rounded-xl bg-white px-3 py-2 ring-1 ring-service/20 hover:ring-service">
                    <span class="line-clamp-2 break-words font-semibold text-slate-900">{{ $demande['titre'] }}</span>
                    <span class="mt-0.5 block text-sm text-slate-700">
                        {{ $referentiels->nomCommune($demande['commune_id']) ?? 'Abidjan' }}@if ($demande['publiee_le']) · {{ \Illuminate\Support\Carbon::parse($demande['publiee_le'])->diffForHumans() }}@endif
                    </span>
                    <span class="mt-1 inline-block text-sm font-semibold text-service">Je suis disponible →</span>
                </a>
            </li>
        @endforeach
    </ul>
    <div class="mt-3 flex flex-wrap gap-x-4">
        <a href="{{ route('fil.demandes') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-service hover:underline">Voir toutes les demandes →</a>
        <a href="{{ route('publier.formulaire', 'service-demande') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-slate-800 hover:underline">Publier mon besoin</a>
    </div>
</section>
