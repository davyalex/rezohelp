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
    <a href="{{ route('fil.demandes') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-service hover:underline">Voir toutes les demandes →</a>

    {{-- Appel à publier : mis en valeur pour les particuliers qui cherchent un artisan. --}}
    <div class="mt-3 rounded-xl bg-white p-3 ring-1 ring-service/20">
        <p class="text-sm font-semibold text-slate-900">Vous cherchez un artisan ?</p>
        <p class="mt-0.5 text-sm text-slate-700">Décrivez votre besoin en 2 minutes : les prestataires disponibles vous contactent.</p>
        <a href="{{ route('publier.formulaire', 'service-demande') }}"
           class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-service px-4 font-semibold text-white shadow-sm transition hover:brightness-110 motion-reduce:transition-none">
            <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
            Publier mon besoin
        </a>
    </div>
</section>
