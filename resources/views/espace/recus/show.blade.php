@extends('layouts.public')

@section('titre', 'Reçu '.$commande->numero_recu)

@push('entete')
    <meta name="robots" content="noindex">
@endpush

@section('contenu')
    <div class="mx-auto max-w-2xl">
        <div class="mb-4 flex flex-wrap gap-2 print:hidden">
            <x-bouton :href="route('espace.recu.pdf', $commande)">Télécharger en PDF</x-bouton>
            <x-bouton :href="route('espace.abonnement')" variante="secondaire">Retour à mon abonnement</x-bouton>
        </div>
        <article class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70 sm:p-8 print:shadow-none print:ring-0">
            @include('espace.recus._contenu')
        </article>
    </div>
@endsection
