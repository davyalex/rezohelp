@extends('layouts.public')

@section('titre', 'Nouveau mot de passe')

@section('contenu')
    <x-carte-formulaire titre="Choisissez un nouveau mot de passe"
                        :sous-titre="'Code envoyé au '.app(\App\Services\TelephoneService::class)->formatInternational($telephone).'.'">
        <form method="POST" action="{{ route('mot-de-passe.reinitialiser') }}" class="space-y-5" novalidate>
            @csrf
            <x-champ name="code" label="Code reçu par SMS" inputmode="numeric" autocomplete="one-time-code"
                     maxlength="6" pattern="[0-9]{6}" required autofocus aide="Le code est valable 10 minutes." />
            <x-champ name="password" type="password" label="Nouveau mot de passe" autocomplete="new-password" required
                     aide="8 caractères au minimum." />
            <x-champ name="password_confirmation" type="password" label="Confirmez le mot de passe" autocomplete="new-password" required />
            <x-bouton class="w-full">Enregistrer</x-bouton>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('mot-de-passe.oublie') }}" class="font-semibold text-brand underline-offset-4 hover:underline">Recevoir un nouveau code</a>
        </p>
    </x-carte-formulaire>
@endsection
