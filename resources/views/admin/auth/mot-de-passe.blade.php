@extends($obligatoire ? 'admin.auth.layout' : 'admin.layout')

@section('titre', 'Mon mot de passe')

@section('contenu')
    <div @class(['max-w-lg rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70' => ! $obligatoire])>
        <h1 class="text-xl font-bold text-slate-900">{{ $obligatoire ? 'Choisissez votre mot de passe' : 'Mon mot de passe' }}</h1>
        @if ($obligatoire)
            <p class="mt-1 text-sm text-slate-600">Le mot de passe que vous avez reçu est provisoire : remplacez-le pour continuer.</p>
        @endif

        <form method="POST" action="{{ route('admin.mot-de-passe.update') }}" class="mt-6 space-y-5" novalidate>
            @csrf
            @method('PUT')
            <x-champ name="mot_de_passe_actuel" type="password" label="{{ $obligatoire ? 'Mot de passe provisoire' : 'Mot de passe actuel' }}" autocomplete="current-password" required autofocus />
            <x-champ name="mot_de_passe" type="password" label="Nouveau mot de passe" autocomplete="new-password" required aide="8 caractères au minimum, avec des lettres et des chiffres." />
            <x-champ name="mot_de_passe_confirmation" type="password" label="Confirmez le nouveau mot de passe" autocomplete="new-password" required />
            <x-bouton class="w-full">Enregistrer</x-bouton>
        </form>

        @if ($obligatoire)
            <form method="POST" action="{{ route('admin.deconnexion') }}" class="mt-4 text-center">
                @csrf
                <button class="text-sm text-slate-600 underline-offset-4 hover:underline">Se déconnecter</button>
            </form>
        @endif
    </div>
@endsection
