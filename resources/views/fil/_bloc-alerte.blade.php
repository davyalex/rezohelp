{{-- Bloc « Créer une alerte » (CONTEXTE §5), pré-rempli avec les filtres courants du fil. --}}
<div class="rounded-2xl bg-brand p-5 text-white shadow-sm">
    <p class="text-lg font-bold">Ne ratez plus une offre</p>
    <p class="mt-1 text-sm text-white/90">Créez une alerte gratuite : nous vous prévenons dès qu’une publication correspond à votre recherche.</p>
    <a href="{{ route('alertes.depuis-fil', array_merge($filtres->parametres(), ['type' => $filtres->type?->value])) }}"
       class="mt-3 inline-flex min-h-11 items-center rounded-lg bg-white px-4 font-semibold text-brand hover:bg-brand-clair">
        🔔 Créer une alerte
    </a>
</div>
