@extends('layouts.public')

@section('titre', 'Quota atteint')

@section('contenu')
    <x-carte-formulaire titre="Vous avez atteint votre limite"
                        :sous-titre="match ($parcours) {
                            \App\Enums\ParcoursPublication::Emploi => 'Votre formule ne permet plus de publier d’offre d’emploi ce mois-ci.',
                            \App\Enums\ParcoursPublication::Formation => 'Votre formule ne permet pas d’avoir plus de formations en ligne en même temps.',
                            default => 'Votre formule ne permet pas d’avoir plus d’annonces en ligne en même temps.',
                        }">
        <p class="text-slate-700">
            Vous pouvez clôturer une publication en cours depuis « Mes publications », ou attendre le mois prochain.
        </p>
        <div class="mt-6 flex flex-col gap-2">
            <x-bouton :href="route('espace.publications.index')">Gérer mes publications</x-bouton>
            <x-bouton :href="route('fil.index')" variante="secondaire">Retour au fil</x-bouton>
        </div>
    </x-carte-formulaire>
@endsection
