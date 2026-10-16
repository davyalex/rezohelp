@extends('layouts.public')

@section('titre', 'Créer un compte')

@section('contenu')
    <x-carte-formulaire titre="Créer un compte" sous-titre="Gratuit. Pour créer des alertes, réagir, postuler et publier.">
        <form method="POST" action="{{ route('register.store') }}" class="space-y-5" novalidate>
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <x-champ name="prenoms" label="Prénoms" autocomplete="given-name" required autofocus />
                <x-champ name="nom" label="Nom" autocomplete="family-name" required />
            </div>

            <x-champ-telephone />

            <x-champ name="password" type="password" label="Mot de passe" autocomplete="new-password" required
                     aide="8 caractères au minimum." />
            <x-champ name="password_confirmation" type="password" label="Confirmez le mot de passe" autocomplete="new-password" required />

            <x-bouton class="w-full">Créer mon compte</x-bouton>

            <p class="text-xs text-slate-600">Un code de vérification vous sera envoyé par SMS.</p>
        </form>

        <p class="mt-6 text-center text-sm text-slate-700">
            Déjà inscrit ?
            <a href="{{ route('login') }}" class="font-semibold text-brand underline-offset-4 hover:underline">Se connecter</a>
        </p>
    </x-carte-formulaire>
@endsection
