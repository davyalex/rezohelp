{{--
    Emplacement publicitaire (D-073, D-074) : annonce gérée dans l'admin, ou « Annoncez ici » pour les
    emplacements verticaux vides ; rien pour une bannière vide. Toujours libellé « Publicité ».
    Le lien passe par /pub/{id} (clic compté, rel="sponsored") ; l'impression est comptée quand la
    bannière devient visible (resources/js/publicites.js, attribut data-publicite-id).
--}}
@props(['emplacement'])
@php
    $type = $emplacement instanceof \App\Enums\EmplacementPublicite ? $emplacement : \App\Enums\EmplacementPublicite::from($emplacement);
    $publicites = app(\App\Services\Publicites::class);
    $annonce = $publicites->annonce($type);
    $format = $type->formatPrincipal();
    $secondaire = $type->formatSecondaire();
    $largeur = match ($type) {
        \App\Enums\EmplacementPublicite::MargeGauche, \App\Enums\EmplacementPublicite::MargeDroite => 'w-40 min-[1800px]:w-[300px]',
        default => 'w-full',
    };
    $proportions = match ($type) {
        \App\Enums\EmplacementPublicite::MargeGauche, \App\Enums\EmplacementPublicite::MargeDroite => 'aspect-[160/600] min-[1800px]:aspect-[300/600]',
        \App\Enums\EmplacementPublicite::ColonneFil => 'aspect-square',
        default => 'aspect-[4/1]',
    };
@endphp
@if ($annonce !== null || ($type->afficheAnnoncezIci() && $publicites->actives()))
    <aside aria-label="Publicité" data-publicite="{{ $type->value }}" @if ($annonce) data-publicite-id="{{ $annonce['id'] }}" @endif
           {{ $attributes->class($largeur) }}>
        <p class="mb-1.5 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-500">Publicité</p>

        @if ($annonce)
            <a href="{{ route('publicites.clic', $annonce['id']) }}" target="_blank" rel="sponsored noopener"
               class="block overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200/70 transition-shadow hover:shadow-md">
                <picture>
                    @if ($secondaire && $annonce['image_secondaire'])
                        <source media="{{ $secondaire['media'] }}" srcset="{{ $annonce['image_secondaire'] }}" width="{{ $secondaire['largeur'] }}" height="{{ $secondaire['hauteur'] }}">
                    @endif
                    <img src="{{ $annonce['image'] }}" alt="{{ $annonce['texte'] }}" width="{{ $format['largeur'] }}" height="{{ $format['hauteur'] }}"
                         loading="lazy" decoding="async" class="h-auto w-full">
                </picture>
            </a>
            <p class="mt-1 truncate text-center text-[11px] text-slate-500">{{ $annonce['annonceur'] }}</p>
        @else
            <a href="{{ $publicites->lienContact() }}"
               class="group flex {{ $proportions }} flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-300 bg-white/70 p-4 text-center backdrop-blur-sm transition-colors hover:border-brand hover:bg-white">
                <span aria-hidden="true" class="inline-flex size-12 items-center justify-center rounded-full bg-brand-clair text-brand transition-transform group-hover:scale-105 motion-reduce:transition-none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-6">
                        <path d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46"/>
                    </svg>
                </span>
                <span class="text-sm font-bold text-slate-800">Votre marque ici</span>
                <span class="text-xs leading-snug text-slate-600">Touchez chaque jour les chercheurs d’emploi, artisans et clients d’Abidjan.</span>
                <span class="mt-1 inline-flex min-h-9 items-center rounded-full bg-brand px-3 text-xs font-semibold text-white group-hover:bg-brand-fonce">Annoncez ici</span>
                <span class="text-[10px] text-slate-400">{{ $format['largeur'] }} × {{ $format['hauteur'] }}</span>
            </a>
        @endif
    </aside>
@endif
