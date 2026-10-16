@props(['titre', 'sousTitre' => null])
<section {{ $attributes->class('mx-auto w-full max-w-md rounded-2xl bg-white p-5 shadow-sm sm:p-8') }} aria-labelledby="titre-formulaire">
    <h1 id="titre-formulaire" class="text-2xl font-bold text-slate-900">{{ $titre }}</h1>
    @if ($sousTitre)
        <p class="mt-1 text-slate-600">{{ $sousTitre }}</p>
    @endif
    <div class="mt-6">
        {{ $slot }}
    </div>
</section>
