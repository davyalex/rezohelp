@extends('layouts.public')

@php
    $enLigne = $publication->statut === \App\Enums\StatutPublication::Publiee;
    $nom = match ($publication->type) {
        \App\Enums\TypePublication::Emploi => 'offre',
        \App\Enums\TypePublication::Formation => 'formation',
        \App\Enums\TypePublication::Service => 'annonce',
    };
@endphp

@section('titre', $enLigne ? 'Publication en ligne' : 'Publication envoyée')

@section('contenu')
    <x-carte-formulaire :titre="$enLigne ? 'Votre '.$nom.' est en ligne 🎉' : 'Merci, votre '.$nom.' est envoyée'">
        @if ($enLigne)
            <p class="text-slate-700">Elle apparaît dès maintenant dans le fil. Partagez-la pour qu’elle soit vue plus vite.</p>
            <div class="mt-6 flex flex-col gap-2">
                <x-bouton :href="route('publications.partager', $publication->slug)" target="_blank" rel="noopener">Partager sur WhatsApp</x-bouton>
                <x-bouton :href="route('publications.show', $publication->slug)" variante="secondaire">Voir ma publication</x-bouton>
            </div>
        @else
            <p class="text-slate-700">Votre {{ $nom }} sera visible après vérification, généralement en moins de 2 h. Vous serez notifié.</p>
            <div class="mt-6 flex flex-col gap-2">
                <x-bouton :href="route('espace.publications.index')">Voir mes publications</x-bouton>
                <x-bouton :href="route('fil.index')" variante="secondaire">Retour au fil</x-bouton>
            </div>
        @endif
    </x-carte-formulaire>
@endsection
