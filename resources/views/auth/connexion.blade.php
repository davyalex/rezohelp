@extends('layouts.public')

@section('titre', 'Se connecter')

@section('contenu')
    <x-carte-formulaire titre="Se connecter" sous-titre="Avec votre numéro de téléphone et votre mot de passe.">
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
            @csrf

            <x-champ-telephone :aide="null" autofocus />

            <x-champ name="password" type="password" label="Mot de passe" autocomplete="current-password" required />

            <div class="flex items-center justify-between gap-3">
                <label class="inline-flex min-h-11 items-center gap-2 text-sm text-slate-800">
                    <input type="checkbox" name="remember" value="1" class="size-5 rounded border-slate-300 text-brand" @checked(old('remember'))>
                    Rester connecté
                </label>
                <a href="{{ route('mot-de-passe.oublie') }}" class="text-sm font-medium text-brand underline-offset-4 hover:underline">Mot de passe oublié ?</a>
            </div>

            <x-bouton class="w-full">Se connecter</x-bouton>
        </form>

        <p class="mt-6 text-center text-sm text-slate-700">
            Pas encore de compte ?
            <a href="{{ route('register') }}" class="font-semibold text-brand underline-offset-4 hover:underline">Créer un compte</a>
        </p>
    </x-carte-formulaire>
@endsection
