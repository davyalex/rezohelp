@extends('admin.auth.layout')

@section('titre', 'Connexion')

@section('contenu')
    <h1 class="text-xl font-bold text-slate-900">Connexion à l’administration</h1>
    <p class="mt-1 text-sm text-slate-600">Avec votre nom d’utilisateur et votre mot de passe d’équipe.</p>

    <form method="POST" action="{{ route('admin.connexion.store') }}" class="mt-6 space-y-5" novalidate>
        @csrf
        <x-champ name="nom_utilisateur" label="Nom d’utilisateur" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
        <x-champ name="mot_de_passe" type="password" label="Mot de passe" autocomplete="current-password" required />
        <label class="inline-flex min-h-11 items-center gap-2 text-sm text-slate-800">
            <input type="checkbox" name="se_souvenir" value="1" class="size-5 rounded border-slate-300 text-brand" @checked(old('se_souvenir'))>
            Rester connecté
        </label>
        <x-bouton class="w-full">Se connecter</x-bouton>
    </form>
    <p class="mt-6 text-xs text-slate-500">Mot de passe oublié : demandez à un super administrateur d’en générer un nouveau.</p>
@endsection
