@extends('layouts.public')

@section('titre', 'Quota atteint')

@section('contenu')
    <x-carte-formulaire titre="Vous avez atteint votre limite"
                        :sous-titre="match ($parcours) {
                            \App\Enums\ParcoursPublication::Emploi => 'Votre formule ne permet plus de publier d’offre d’emploi ce mois-ci.',
                            \App\Enums\ParcoursPublication::Formation => 'Votre formule ne permet pas d’avoir plus de formations en ligne en même temps.',
                            default => 'Votre formule ne permet pas d’avoir plus d’annonces en ligne en même temps.',
                        }">
        @foreach ($options as $option)
            <section class="mb-4 rounded-lg border border-slate-200 p-4" aria-label="Options pour {{ $option['nom'] }}">
                @if (count($options) > 1)
                    <p class="mb-2 font-semibold text-slate-900">{{ $option['nom'] }}</p>
                @endif
                <div class="flex flex-col gap-2">
                    @if ($option['produit'])
                        <x-formulaire-achat type="produit" :code="$option['produit']->code" :entreprise="$option['entreprise_id']">
                            Publier 1 offre de plus · {{ \App\Support\Montant::fcfa($option['produit']->prix_fcfa) }}
                        </x-formulaire-achat>
                    @endif
                    @if ($option['essai'])
                        <x-formulaire-essai :plan="$option['essai']->code" :entreprise="$option['entreprise_id']" :variante="$option['produit'] ? 'secondaire' : 'primaire'">
                            Essayer « {{ $option['essai']->nom }} » {{ $option['essai']->duree_essai_jours }} jours gratuits
                        </x-formulaire-essai>
                    @endif
                </div>
            </section>
        @endforeach

        <p class="text-slate-700">
            Vous pouvez aussi clôturer une publication en cours depuis « Mes publications », ou attendre le mois prochain.
        </p>
        <div class="mt-6 flex flex-col gap-2">
            <x-bouton :href="route('espace.publications.index')" variante="secondaire">Gérer mes publications</x-bouton>
            <x-bouton :href="route('tarifs')" variante="lien">Voir les tarifs</x-bouton>
        </div>
    </x-carte-formulaire>
@endsection
