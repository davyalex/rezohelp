@extends('admin.layout')

@section('titre', $administrateur->exists ? 'Compte '.$administrateur->nom_utilisateur : 'Nouvel administrateur')

@section('contenu')
    <a href="{{ route('admin.administrateurs.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">← Administrateurs</a>
    <h1 class="text-2xl font-bold">{{ $administrateur->exists ? 'Compte « '.$administrateur->nom_utilisateur.' »' : 'Nouvel administrateur' }}</h1>

    <div class="mt-4">@include('admin.administrateurs._mot-de-passe-genere')</div>

    <form method="POST" action="{{ $administrateur->exists ? route('admin.administrateurs.update', $administrateur) : route('admin.administrateurs.store') }}"
          class="max-w-2xl space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70">
        @csrf
        @if ($administrateur->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-champ name="prenoms" label="Prénoms" :valeur="$administrateur->prenoms" maxlength="120" autocomplete="off" />
            <x-champ name="nom" label="Nom" :valeur="$administrateur->nom" maxlength="80" required autocomplete="off" />
            <x-champ name="nom_utilisateur" label="Nom d’utilisateur" :valeur="$administrateur->nom_utilisateur" maxlength="50" required autocomplete="off"
                     aide="Minuscules sans accent, chiffres, « . », « - » ou « _ ». Sert à se connecter." />
            <x-champ name="email" type="email" label="E-mail (facultatif)" :valeur="$administrateur->email" maxlength="150" autocomplete="off" />
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-slate-800">Rôle</legend>
            @error('role')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                @foreach ($roles as $nom => $role)
                    <label @class([
                        'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors has-[:checked]:border-brand has-[:checked]:bg-brand-clair',
                        'border-slate-200 hover:bg-slate-50',
                        'cursor-not-allowed opacity-50' => $role['bloque'],
                    ])>
                        <input type="radio" name="role" value="{{ $nom }}" class="mt-1 size-4 text-brand" @checked(old('role', $roleActuel) === $nom) @disabled($role['bloque'])>
                        <span>
                            <span class="block text-sm font-semibold">{{ $role['libelle'] }}</span>
                            <span class="block text-xs text-slate-600">{{ $role['resume'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($soiMeme)
                <p class="mt-2 text-xs text-slate-600">Vous ne pouvez pas changer votre propre rôle.</p>
            @endif
        </fieldset>

        @unless ($administrateur->exists)
            <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                Un mot de passe de 8 caractères sera généré et affiché une seule fois. Il devra être changé à la première connexion.
            </p>
        @endunless

        <div class="flex flex-wrap gap-3">
            <x-bouton>{{ $administrateur->exists ? 'Enregistrer' : 'Créer le compte' }}</x-bouton>
            <x-bouton :href="route('admin.administrateurs.index')" variante="secondaire">Annuler</x-bouton>
        </div>
    </form>
@endsection
