@extends('layouts.public')

@section('titre', 'Mot de passe oublié')

@section('contenu')
    <x-carte-formulaire titre="Mot de passe oublié" sous-titre="Indiquez votre numéro : nous vous enverrons un code par SMS.">
        <form method="POST" action="{{ route('mot-de-passe.oublie.envoyer') }}" class="space-y-5" novalidate>
            @csrf
            <x-champ-telephone :aide="null" autofocus />
            <x-bouton class="w-full">Recevoir un code</x-bouton>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="font-semibold text-brand underline-offset-4 hover:underline">Retour à la connexion</a>
        </p>
    </x-carte-formulaire>
@endsection
