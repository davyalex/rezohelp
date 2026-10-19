@extends('layouts.public')

@section('titre', 'Paiement')

@push('entete')
    <meta name="robots" content="noindex">
    @if ($actualiser)
        {{-- En attendant la notification de la passerelle : actualisation toutes les 3 s pendant 60 s. --}}
        <meta http-equiv="refresh" content="3;url={{ route('paiements.retour', ['commande' => $commande->id, 'n' => $actualisationSuivante]) }}">
    @endif
@endpush

@section('contenu')
    @php($statut = $commande->statut)
    <x-carte-formulaire :titre="match ($statut) {
                            \App\Enums\StatutCommande::Payee => 'Paiement confirmé',
                            \App\Enums\StatutCommande::Echouee, \App\Enums\StatutCommande::Annulee => 'Paiement non abouti',
                            \App\Enums\StatutCommande::Remboursee => 'Commande remboursée',
                            default => 'Paiement en cours de confirmation',
                        }"
                        :sous-titre="($article->nom ?? 'Commande').' · '.\App\Support\Montant::fcfa($commande->montant_ttc_fcfa)">
        <div aria-live="polite">
            @if ($statut === \App\Enums\StatutCommande::Payee)
                <p class="text-slate-700">Merci ! Votre achat est actif. Votre reçu n° <strong>{{ $commande->numero_recu }}</strong> est disponible.</p>
                <div class="mt-6 flex flex-col gap-2">
                    <x-bouton :href="route('espace.abonnement')">Voir mon abonnement</x-bouton>
                    <x-bouton :href="route('espace.recu', $commande)" variante="secondaire">Voir le reçu</x-bouton>
                </div>
            @elseif ($statut === \App\Enums\StatutCommande::EnAttente)
                <p class="text-slate-700">
                    Nous attendons la confirmation de l’opérateur. Cela prend en général quelques secondes.
                    Ne payez pas une deuxième fois.
                </p>
                @unless ($actualiser)
                    <p class="mt-3 text-slate-700">
                        La confirmation tarde. Vous recevrez une notification dès qu’elle arrivera ;
                        vous pouvez aussi <a href="{{ route('paiements.retour', $commande) }}" class="font-semibold text-brand underline">actualiser cette page</a>.
                    </p>
                @endunless

                @if ($guichetSimule)
                    <div class="mt-6 rounded-lg border border-dashed border-amber-400 bg-amber-50 p-4">
                        <p class="text-sm font-semibold text-amber-900">Guichet de paiement simulé (environnement de développement)</p>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <form method="POST" action="{{ route('paiements.simuler', $commande) }}">
                                @csrf
                                <input type="hidden" name="issue" value="accepte">
                                <x-bouton>Simuler un paiement réussi</x-bouton>
                            </form>
                            <form method="POST" action="{{ route('paiements.simuler', $commande) }}">
                                @csrf
                                <input type="hidden" name="issue" value="refuse">
                                <x-bouton variante="secondaire">Simuler un refus</x-bouton>
                            </form>
                        </div>
                    </div>
                @endif
            @else
                <p class="text-slate-700">Le paiement n’a pas été accepté. Si un montant a tout de même été débité, contactez-nous en indiquant le numéro de commande ci-dessous.</p>
                <div class="mt-6 flex flex-col gap-2">
                    <x-bouton :href="route('espace.abonnement')">Réessayer</x-bouton>
                </div>
            @endif
        </div>
        <p class="mt-6 text-sm text-slate-500">Commande {{ $commande->numero }}</p>
    </x-carte-formulaire>
@endsection
