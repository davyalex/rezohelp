@extends('layouts.public')

@section('titre', 'Changer de numéro')

@section('contenu')
    <x-carte-formulaire titre="Changer de numéro" sous-titre="Votre numéro sert à vous connecter. Nous vérifions le nouveau par un code SMS.">
        <form method="POST" action="{{ route('espace.numero.envoyer') }}" class="space-y-5" novalidate>
            @csrf
            <x-champ-telephone label="Nouveau numéro" />
            <x-bouton class="w-full">Recevoir un code</x-bouton>
        </form>

        @if ($enAttente)
            <form method="POST" action="{{ route('espace.numero.confirmer') }}" class="mt-6 space-y-5 border-t border-slate-100 pt-6" novalidate>
                @csrf
                <p class="text-sm text-slate-700">Code envoyé au {{ app(\App\Services\TelephoneService::class)->formatInternational($enAttente) }}.</p>
                <x-champ name="code" label="Code reçu par SMS" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required />
                <x-bouton class="w-full">Confirmer le nouveau numéro</x-bouton>
            </form>
        @endif

        <p class="mt-6 text-center"><a href="{{ route('espace.profil') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">Retour au profil</a></p>
    </x-carte-formulaire>
@endsection
