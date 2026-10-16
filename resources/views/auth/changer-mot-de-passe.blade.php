@extends('layouts.public')

@section('titre', 'Changer de mot de passe')

@section('contenu')
    <x-carte-formulaire titre="Changez votre mot de passe"
                        :sous-titre="auth()->user()->doit_changer_mot_de_passe ? 'Votre mot de passe a été réinitialisé : choisissez-en un nouveau pour continuer.' : null">
        <form method="POST" action="{{ route('mot-de-passe.changer') }}" class="space-y-5" novalidate>
            @csrf
            @method('PUT')
            <x-champ name="mot_de_passe_actuel" type="password" label="Mot de passe actuel" autocomplete="current-password" required autofocus />
            <x-champ name="password" type="password" label="Nouveau mot de passe" autocomplete="new-password" required
                     aide="8 caractères au minimum." />
            <x-champ name="password_confirmation" type="password" label="Confirmez le mot de passe" autocomplete="new-password" required />
            <x-bouton class="w-full">Enregistrer</x-bouton>
        </form>
    </x-carte-formulaire>
@endsection
